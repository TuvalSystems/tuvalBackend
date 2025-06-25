<?php

namespace App\Http\Controllers\ventas;

use App\Models\GpPedido;
use App\Models\GpCliente;
use App\Models\GpArticulo;
use App\Models\GpTempCart;
use App\Models\GpCotizacion;
use App\Models\GpPuntoVenta;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Models\GpOrdenDespacho;
use App\Models\GpPedidoDetalle;
use App\Models\GpRegistroCopia;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\GpClienteSucursal;
use App\Traits\JsonResponseTrait;
use App\Services\Api\ApiGpService;
use App\Models\GpCotizacionDetalle;
use App\Http\Controllers\Controller;
use App\Models\GpPedidoDetalleStock;
use Illuminate\Support\Facades\Auth;
use App\Models\despachos\GpGuiaRemision;
use App\Models\GpCotizacionDetalleStock;
use Illuminate\Support\Facades\Response;
use App\Models\GpPedidoDetalleProveedores;
use App\Models\GpRelacionPuntoVentaIdVenta;
use App\Models\traslados\GpTrasladosVentas;
use App\Http\Controllers\FuncionesController;
use App\Models\ordendecompra\GpOrdenDeCompra;
use App\Http\Controllers\WebserviceController;
use App\Models\GpCotizacionDetalleProveedores;
use App\Models\devoluciones\GpDevolucionesDetalle;
use App\Http\Controllers\bodegas\DespachosController;
use App\Models\ordendecompra\GpOrdenDeCompraGenerado;
use App\Services\Pedidos\Contracts\OrderServiceInterface;
use App\Http\Controllers\bodegas\TransferenciasController;
use App\Models\ordendecompra\GpOrdenDeCompraGeneradoDetalle;
use App\Services\Bodegas\Contracts\DispatchServiceInterface;
use App\Services\Venta\Contracts\PurchaseOrderServiceInterface;
use App\Services\Venta\Contracts\QuotationServiceInterface;

class PedidosController extends Controller
{
    use JsonResponseTrait;
    protected $apiGp;
    protected $orderService;
    public function __construct(ApiGpService $apiGp, OrderServiceInterface $orderService)
    {
        $this->orderService = $orderService;
        $this->apiGp = $apiGp;
    }


    //en proceso de mejora
    public function guardarpedido2(Request $request)
    {

        try {
            $orderData = $request->all();
            return $this->orderService->storeOrder($orderData);
        } catch (\Throwable $th) {
            return $this->buildErrorResponse($th->getMessage(), 500);
        }
    }

    public function guardarpedido(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $data = $request->all();
        $user = Auth::user();
        $aprobaciones = new AprobacionesController;
        foreach ($data['cotizacion'] as $det) {
            if ($det['ART_CANTIDAD'] <= 0) {
                return response([
                    'error' => 'Artículo con cantidad menor o igual a cero, no es posible guardar pedido'
                ], 401);
            }
        }

        $requiere_aprobacion_comercial = $aprobaciones->checkAprobacionesComerciales($data, true);
        //        echo '<pre>';
        //        print_r($requiere_aprobacion_comercial);
        //        die();
        $requiere_aprobacion_crediticia = $aprobaciones->checkAprobacionesCrediticias($data);
        $data['motivo_crediticio'] = null;
        if ($requiere_aprobacion_crediticia != 'no') {
            $data['motivo_crediticio'] = $requiere_aprobacion_crediticia;
            $requiere_aprobacion_crediticia = 'yes';
        }
        $pedido = new GpPedido;

        $puntodeventa = GpPuntoVenta::where('PTO_VTA_ID', $data['PTO_VTA_ID'])->first();

        $ultimoPedido = GpPedido::orderBy('id', 'DESC')->first();

        if (!$ultimoPedido) {
            $ultimoPedido = new \stdClass();
            $ultimoPedido->id = env('SECUENCIAL_PEDIDO');
            $pedido->id = $ultimoPedido->id + 1;
        }

        $pedido->pedido = 'PED' . str_pad($ultimoPedido->id + 1, 8, "0", STR_PAD_LEFT);
        $pedido->user_id = $user->id;
        $pedido->sucursal_id = $data['sucursal_id'];
        $pedido->sucursal_facturacion_id = $data['sucursal_facturacion_id'];
        $pedido->cliente_id = $data['cliente_id'];
        $pedido->factura_posterior_despacho = $data['factura_posterior_despacho'];
        $pedido->factura_a_demanda = $data['factura_a_demanda'];
        $pedido->factura_siguiente_mes = $data['factura_siguiente_mes'];
        $pedido->impuesto = env('IVA_IMPUESTO');

        if ($data['despacho'] == 'Despacho en ruta') {
            $pedido->fecha_entrega = $data['fecha_entrega'];
        }
        if (isset($data['cotizacion_id']) && $data['cotizacion_id'] != '') {
            $pedido->cotizacion_id = $data['cotizacion_id'];
        }
        $pedido->BOD_BODEGA = $data['bodega_facturacion'];
        $this->setguardarpedido($data, $pedido, $puntodeventa['PTO_VTA_DESCRIPCION'], $user, $requiere_aprobacion_comercial, $requiere_aprobacion_crediticia);

        if ($pedido->save()) {
            $i = 0;
            foreach ($data['cotizacion'] as $det) {
                $i++;
                $detalle = $this->savePedidoDetalle($i, $pedido, $det);

                if (count($det['proveedores']) > 0) {
                    foreach ($det['proveedores'] as $pro) {
                        $proveedor = $this->savePedidoDetalleProveedores($pedido, $detalle, $pro);
                        $proveedor->MARGEN = $pro['MARGEN'];
                        $proveedor->save();
                    }
                }
                $this->savePedidoDetalleStock($det['stock'], $pedido, $detalle);
            }

            GpTempCart::where('sucursal_id', $data['sucursal_id'])
                ->where('puntodeventa_id', $data['PTO_VTA_ID'])
                ->where('cliente_id', $data['cliente_id'])
                ->where('user_id', $user->id)
                ->delete();

            if (isset($data['cotizacion_id']) && $data['cotizacion_id'] != '') {
                GpCotizacion::where('id', $data['cotizacion_id'])->update(['estado' => 'procesado', 'facturado' => 1]);
            }

            $this->checkenviarfactura($pedido);

            return response([
                'success' => true,
                'message' => 'El pedido ha sido guardado - ' . $pedido->pedido
            ]);
        } else {
            return response([
                'error' => 'Hubo un error al guardar'
            ], 401);
        }
    }



    public function facturarpedido(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $data = $request->validate([
            'id' => 'required',
        ]);

        $pedido = GpPedido::where('id', $data['id'])->get()->first();

        $funciones = new FuncionesController();
        $funciones->recalcularpedido($pedido);

        $this->sendfacturagp($pedido);

        return response([
            'success' => true,
            'message' => 'Pedido ' . $pedido->pedido . ' facturado',
        ]);
    }

    public function facturarpedidourl(): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {


        $pedido = GpPedido::where('id', 802)->get()->first();

        $this->sendfacturagp($pedido);

        return response([
            'success' => true,
            'message' => 'Pedido ' . $pedido->pedido . ' facturado',
        ]);
    }

    /*
     *
     * Chequea si envía la factura a GP o traslados
     *
     */
    public function checkenviarfactura(GpPedido $pedido, $trasladado = false, $checkDemanda = true)
    {

        if ($pedido->estado == 'rechazado' || $pedido->estado == 'anulada')
            return;

        if ($pedido->requiere_aprobacion_comercial == 'yes' && $pedido->aprobado_comercial == 'no')
            return;

        if ($pedido->requiere_aprobacion_crediticia == 'yes' && $pedido->aprobado_crediticio == 'no')
            return;


        if ($trasladado) {
            $this->checktrasladado($pedido);
        } else {
            $this->checktraslado($pedido);
        }

        $order = app(PurchaseOrderServiceInterface::class);
        $ordendecompra = new OrdendecompraController($order);

        $ordendecompra->checkordendecompra($pedido);

        $ordendecompra->enviarordenesdecompra();

        $ordendecompra->checkordendecomprarecibidos($pedido);


        if ($pedido->estado == 'orden_de_compra') {
            return;
        }

        if ($pedido->estado == 'traslado')
            return;

        if ($checkDemanda) {
            $demanda = $this->checkfacturademanda($pedido);
            if ($demanda) {
                return;
            }
        }
        $order = app(OrderServiceInterface::class);
        $dispatch = app(DispatchServiceInterface::class);
        $despachos = new DespachosController($order, $dispatch);
        // $despachos = new DespachosController();
        $despachos->generarordendespacho($pedido);

        $funciones = new FuncionesController();
        $funciones->recalcularpedido($pedido);

        $this->sendfacturagp($pedido);

        return;
    }

    public function generarordendespacho($id)
    {
        $pedido = GpPedido::where('id', $id)
            ->first();
        $order = app(OrderServiceInterface::class);
        $dispatch = app(DispatchServiceInterface::class);
        $despachos = new DespachosController($order, $dispatch);
        // $despachos = new DespachosController();
        $despachos->generarordendespacho($pedido);
    }


    public function checkfacturademanda(GpPedido $pedido): bool
    {
        $cliente = GpCliente::where('id', $pedido->cliente_id)->first();
        if ($cliente->CLI_FACTURA_POSTERIOR_AL_DESPACHO == 'SI') {
            $pedido->factura_posterior_despacho = 1;
            if ($cliente->CLI_DIA_MAXIMO_MES_FACTURACION == 1) {
                $pedido->factura_a_demanda = 1;
            } elseif (date('d') > $cliente->CLI_DIA_MAXIMO_MES_FACTURACION) {
                $pedido->factura_siguiente_mes = 1;
            }
        }
        $pedido->save();
        $order = app(OrderServiceInterface::class);
        $dispatch = app(DispatchServiceInterface::class);
        $despachos = new DespachosController($order, $dispatch);
        // $despachos = new DespachosController();

        //Facturar a demanda posterior al despacho (MINERA)
        if ($pedido->factura_posterior_despacho && $pedido->factura_a_demanda && !$pedido->factura_siguiente_mes) {

            $despachos->generarordendespacho($pedido);
            return true;
        }

        //Facturar al día menor al límite
        if ($pedido->factura_posterior_despacho && !$pedido->factura_a_demanda && !$pedido->factura_siguiente_mes) {
            return false;
        }

        //Facturar al siguiente mes
        if ($pedido->factura_posterior_despacho && !$pedido->factura_a_demanda && $pedido->factura_siguiente_mes) {

            $despachos->generarordendespacho($pedido);
            return true;
        }
        return false;
    }

    public function facturarsiguientemes(): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {

        $mes = date("m", strtotime("-1 month"));

        if ($mes == date("m")) {
            return response([
                'success' => true,
                'message' => 'Aun no llega el mes',
            ]);
        }

        $pedidos = GpPedido::where('estado', 'espera')
            ->where('factura_posterior_despacho', 1)
            ->where(function ($query) {
                $query->whereNull('factura_a_demanda');
                $query->orWhere('factura_a_demanda', 0);
            })
            ->where('factura_siguiente_mes', 1)
            ->whereMonth('created_at', '=', $mes)
            ->get();

        foreach ($pedidos as $pedido) {
            $this->checkenviarfactura($pedido, true, false);
        }

        return response([
            'success' => true,
            'message' => 'Pedidos facturados',
        ]);
    }

    public function facturarfaltante()
    {
        $pedido = GpPedido::where('id', 1370)->first();

        $this->checkenviarfactura($pedido, true, false);
    }

    public function facturarsiguientemescron(): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {

        $date = date('Y-m-d h:i:s');
        if (date('N', strtotime($date)) >= 6) {
            return response([
                'success' => true,
                'message' => 'Fin de semana',
            ]);
        }

        $parameters = array(
            'IDCompania' => env('ID_COMPANIA'),
            'Fecha' => date('Y-m-d'),
        );
        $webservices = new WebserviceController;
        $diaHabil = $webservices->cobranzas('ConsultaDiaFeriado', $parameters);

        if ($diaHabil['ConsultaDiaFeriadoResult']['Retorno']['0']['Codigo'] == 0) {
            return response([
                'success' => true,
                'message' => 'Día no habil',
            ]);
        }
        //ejemplo



        return $this->facturarsiguientemes();
    }

    public function corregirenviando(): void
    {

        $webservices = new WebserviceController();
        $enviando = GpPedido::where('estado', 'enviando')
            ->where('updated_at', '<=', Carbon::now()->subMinutes(5)->toDateTimeString())->get();

        foreach ($enviando as $pedido) {

            $this->respuestaFacturaVenta($pedido, $webservices);
            //            $pedido->estado = 'espera';
            //            $pedido->save();
        }
    }

    public function sendfacturasgp()
    {

        $webservices = new WebserviceController();

        $pedidos = GpPedido::where('estado', 'espera')
            //->where('factura_siguiente_mes', 0)
            //where('id',69)
            ->get();

        foreach ($pedidos as $pedido) {

            $this->checkenviarfactura($pedido);
        }
        $traslados = GpPedido::where('estado', 'orden_de_compra')->get();
        foreach ($traslados as $pedido) {
            $listo = 'yes';
            $detalleProveedores = GpPedidoDetalleProveedores::join('gp_orden_de_compra_generados', 'gp_orden_de_compra_generados.detalle_id', 'gp_pedido_detalle_proveedores.detalle_id')
                ->where('pedido_id', $pedido->id)
                ->select('gp_pedido_detalle_proveedores.*', 'gp_orden_de_compra_generados.ORDEN_COMPRA_RECIBO')
                ->get();

            if (!count($detalleProveedores)) {
                $listo = 'no';
            }

            foreach ($detalleProveedores as $prov) {
                if ($prov->recibido == 'no') {
                    $listo = 'no';
                }

                $parameters = array(
                    'documento_origen' => $prov->ORDEN_COMPRA_RECIBO,
                    'tipo_documento' => 1,
                );

                $result = $webservices->transaccional('MST_Estado_Recibo_Compra', $parameters);

                if (isset($result['MST_Estado_Recibo_CompraResult']['CS_ESTADO_RECIBO_COMPRA'][0]['REC_COM_ESTADO_RECEPCION']) && $result['MST_Estado_Recibo_CompraResult']['CS_ESTADO_RECIBO_COMPRA'][0]['REC_COM_ESTADO_RECEPCION'] != 'PROCESADO') {
                    $listo = 'no';
                }
            }
            $order = app(OrderServiceInterface::class);
            $dispatch = app(DispatchServiceInterface::class);
            $despachos = new DespachosController($order, $dispatch);
            // $despachos = new DespachosController();
            if ($listo == 'yes') {
                $pedido->estado = 'espera';
                $pedido->save();
                $despachos->generarordendespacho($pedido);

                $this->sendfacturagp($pedido);
            }
        }
    }

    public function checktraslado(GpPedido $pedido)
    {

        $detalles = GpPedidoDetalle::where('gp_pedido_detalles.pedido_id', $pedido->id)
            ->join('gp_pedido_detalle_stocks', 'gp_pedido_detalles.id', '=', 'gp_pedido_detalle_stocks.detalle_id')
            ->where('gp_pedido_detalle_stocks.seleccionado', '>', 0)
            ->whereNull('traslado')
            ->select(['gp_pedido_detalles.*', 'gp_pedido_detalle_stocks.STO_ART_BODEGA', 'gp_pedido_detalle_stocks.seleccionado', 'gp_pedido_detalle_stocks.id AS detalle_stocks_id'])
            ->get();
        $traslado = false;
        foreach ($detalles as $detalle) {
            if ($pedido->BOD_BODEGA != $detalle->STO_ART_BODEGA)
                $traslado = true;
        }

        if ($traslado) {
            $pedido->estado = 'traslado';
            $pedido->save();

            $transferencias = new TransferenciasController();
            $transferencias->savetransferenciasventas($pedido, $detalles);
        } else {
            $pedido->estado = 'espera';
            $pedido->save();
        }

        return;
    }

    public function checktrasladado(GpPedido $pedido)
    {

        $detalles = GpPedidoDetalle::where('gp_pedido_detalles.pedido_id', $pedido->id)
            ->join('gp_pedido_detalle_stocks', 'gp_pedido_detalles.id', '=', 'gp_pedido_detalle_stocks.detalle_id')
            ->where('gp_pedido_detalle_stocks.seleccionado', '>', 0)
            //->select(['gp_pedido_detalles.*', 'gp_pedido_detalle_stocks.STO_ART_BODEGA', 'gp_pedido_detalle_stocks.seleccionado', 'gp_pedido_detalle_stocks.id AS detalle_stocks_id'])
            ->select('gp_pedido_detalle_stocks.STO_ART_BODEGA')
            ->get();
        $traslado = false;

        foreach ($detalles as $detalle) {

            if ($pedido->BOD_BODEGA != $detalle->STO_ART_BODEGA)
                $traslado = true;
        }
        if ($traslado) {
            $pedido->estado = 'traslado';
        } else {
            $pedido->estado = 'espera';
        }

        $pedido->save();
    }

    public function verpedidos(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {

        $data = $request->validate([
            'puntodeventa_id' => 'required',
            'estado' => 'required',
            'fecha_desde' => '',
            'fecha_hasta' => '',
            'cliente' => '',
        ]);

        $pedidos = GpPedido::where('gp_pedidos.PTO_VTA_ID', $data['puntodeventa_id'])
            ->leftJoin('gp_cotizacions', 'gp_cotizacions.id', 'gp_pedidos.cotizacion_id')
            ->select('gp_pedidos.*', 'gp_cotizacions.cotizacion');

        if ($data['estado'] != 'todos') {
            $pedidos = $pedidos->where('gp_pedidos.estado', $data['estado']);
        }
        if (isset($data['fecha_desde']) && $data['fecha_desde'] != '' && isset($data['fecha_hasta']) && $data['fecha_hasta'] != '') {
            $pedidos = $pedidos->whereDate('gp_pedidos.created_at', '>=', $data['fecha_desde'] . ' 00:00:00');
            $pedidos = $pedidos->whereDate('gp_pedidos.created_at', '<=', $data['fecha_hasta'] . ' 23:59:59');
        }

        if (isset($data['cliente'])) {
            $pedidos = $pedidos->where('gp_pedidos.CLI_RUC', $data['cliente']['CLI_RUC']);
        }

        $pedidos = $pedidos->orderBy('gp_pedidos.created_at', 'desc')->limit(1500)->get();

        foreach ($pedidos as $pedido) {

            $pedido->direccion_entrega = GpClienteSucursal::where('id', $pedido->sucursal_id)->first();

            if ($pedido->requiere_aprobacion_comercial == 'yes' && $pedido->aprobado_comercial == 'no') {
                $pedido->imprimir = 'no';
            } elseif ($pedido->requiere_aprobacion_crediticia == 'yes' && $pedido->aprobado_crediticio == 'no') {
                $pedido->imprimir = 'no';
            } elseif ($pedido->estado == 'rechazado') {
                $pedido->imprimir = 'no';
            } else {
                $pedido->imprimir = 'yes';
            }
        }

        return response([
            'success' => true,
            'pedidos' => $pedidos,
        ]);
    }

    public function detallepedido(Request $request)
    {

        $data = $request->all();
        return $this->orderService->detailOrderById($data);
    }


    public function consultarexistente(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $data = $request->all();
        $facturarExistente = array();

        $data['subtotal'] = 0;
        $data['descuento'] = 0;
        $data['subtotalmenosdescuento'] = 0;
        $data['iva'] = 0;
        $detalle = GpPedidoDetalle::where('pedido_id', $data['id'])->get();
        $funciones = new FuncionesController();

        foreach ($detalle as $key => $articulo) {
            $stocks = GpPedidoDetalleStock::where('detalle_id', $articulo->id)
                ->where('STO_ART_BODEGA', $data['BOD_BODEGA'])
                ->first();

            if ($stocks && $stocks->seleccionado == 0 && $articulo->ART_TIPO_COMPRA != 'L' && $articulo->ART_TIPO_COMPRA != 'SERV') {
                unset($detalle[$key]);
                continue;
            }

            if ($stocks && $articulo->ART_TIPO_COMPRA != 'L' && $articulo->ART_TIPO_COMPRA != 'SERV') {
                $articulo->ART_CANTIDAD = $stocks->seleccionado;
            }
            $articulo->stocks = $stocks;

            $proveedores = $funciones->getitemproveedores($data, $articulo, false);
            $array_prov = array();

            if (count($proveedores['proveedores']) > 0) {
                $articulo->ART_CANTIDAD = 0;
                foreach ($proveedores['proveedores'] as $proveedor) {
                    if ($proveedor->ingresado > 0) {
                        $articulo->ART_CANTIDAD += $proveedor->ingresado - $proveedor->ejecutado;
                        $array_prov[] = $proveedor;
                    }
                }
            }

            if ($articulo->ART_CANTIDAD == 0) {
                unset($detalle[$key]);
                continue;
            }

            $articulo->proveedores = $array_prov;

            $articulo->ART_IMP_VTA = GpArticulo::where('ART_CODIGO', $articulo->ART_CODIGO)->first()->ART_IMP_VTA;
            $articulo->ART_IVA = number_format(number_format((($articulo->ART_VALOR - $articulo->ART_DESCUENTO) * $articulo->ART_CANTIDAD), 2, '.', '') * ($articulo->ART_IMP_VTA / 100), 2, '.', '');
            $articulo->subtotal = (float) number_format($articulo->ART_VALOR * $articulo->ART_CANTIDAD, 2, '.', '');
            $data['subtotal'] += (float) number_format($articulo->ART_VALOR * $articulo->ART_CANTIDAD, 2, '.', '');
            $data['descuento'] += (float) number_format($articulo->ART_DESCUENTO * $articulo->ART_CANTIDAD, 2, '.', '');
            $data['subtotalmenosdescuento'] += (float) number_format(($articulo->ART_VALOR - $articulo->ART_DESCUENTO) * $articulo->ART_CANTIDAD, 2, '.', '');
            $data['iva'] += $articulo->ART_IVA;
        }

        $data['descuento'] = number_format($data['descuento'], 2, '.', '');
        $data['subtotalmenosdescuento'] = number_format($data['subtotalmenosdescuento'], 2, '.', '');
        $data['iva'] = number_format($data['iva'], 2, '.', '');
        $data['total'] = number_format($data['subtotalmenosdescuento'] + (float) $data['iva'], 2, '.', '');
        $facturarExistente['pedido'] = $data;
        $facturarExistente['detalle'] = $detalle;

        return response([
            'success' => true,
            'facturar_existente' => $facturarExistente,
        ]);
    }

    public function facturarexistente(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $facturarExistente = $request->all();

        $data = $facturarExistente['pedido'];

        $funciones = new FuncionesController();
        $webservices = new WebserviceController();
        $detalle = GpPedidoDetalle::where('pedido_id', $data['id'])->get();

        $eliminar = array('');

        foreach ($detalle as $key => $articulo) {
            $stocks = GpPedidoDetalleStock::where('detalle_id', $articulo->id)
                ->where('STO_ART_BODEGA', $data['BOD_BODEGA'])
                ->first();

            if ($articulo->ART_TIPO_COMPRA != 'SERV') {
                if ($stocks->seleccionado == $articulo->ART_CANTIDAD) {
                    //echo 'por eliminar: '.$articulo->ART_CODIGO.'<br>';
                    $articulo->delete();
                } elseif ($stocks->seleccionado > 0 && $stocks->seleccionado < $articulo->ART_CANTIDAD) {
                    //echo 'Encera stock bodega '.$stocks->id.' y edita cantidad linea: '.$articulo->ART_CODIGO.' a '.$articulo->ART_CANTIDAD - $stocks->seleccionado.'<br>';
                    $articulo->ART_CANTIDAD = $articulo->ART_CANTIDAD - $stocks->seleccionado;
                    $articulo->save();

                    $stocks->seleccionado = 0;
                    $stocks->reservado = 0;
                    $stocks->save();
                }

                $proveedores = $funciones->getitemproveedores($data, $articulo, false);

                foreach ($proveedores['proveedores'] as $proveedor) {
                    if (($proveedor->ingresado - $proveedor->ejecutado) > 0) {
                        //echo 'Eliminar Proveedor: '.$proveedor->id.'<br>';
                        $articulo->ART_CANTIDAD -= $proveedor->ingresado - $proveedor->ejecutado;
                        if ($proveedor->ingresado == $proveedor->CANTIDAD) {
                            unset($proveedor->orden_detalle);

                            $eliminar['proveedores'][] = $proveedor;

                            //$proveedor->delete();
                        } else {
                            unset($proveedor->orden_detalle);
                            $proveedor->save();
                        }
                    }
                }

                if (count($proveedores['proveedores'])) {
                    if ($articulo->ART_CANTIDAD == 0) {
                        //echo 'Por eliminar L: '.$articulo->ART_CODIGO.'<br>';
                        $eliminar['articulos'][] = $articulo;
                        //$articulo->delete();
                    } else {
                        //echo 'Editar la cantidad del L '.$articulo->ART_CODIGO.' a: '.$articulo->ART_CANTIDAD.'<br>';
                        $articulo->save();
                    }
                }
            } else {
                $articulo->delete();
            }
        }

        $articulos = $facturarExistente['detalle'];

        $user = Auth::user();
        $pedido = new GpPedido;

        $puntodeventa = GpPuntoVenta::where('PTO_VTA_ID', $data['PTO_VTA_ID'])->first();

        $ultimoPedido = GpPedido::orderBy('id', 'DESC')->first();

        if (!$ultimoPedido) {
            $ultimoPedido = new \stdClass();
            $ultimoPedido->id = env('SECUENCIAL_PEDIDO');
            $pedido->id = $ultimoPedido->id + 1;
        }
        $pedido->pedido = 'PED' . str_pad($ultimoPedido->id + 1, 8, "0", STR_PAD_LEFT);
        $pedido->user_id = $user->id;
        $pedido->sucursal_id = $data['sucursal_id'];
        $pedido->sucursal_facturacion_id = $data['sucursal_facturacion_id'];
        $pedido->cliente_id = $data['cliente_id'];
        $pedido->factura_posterior_despacho = $data['factura_posterior_despacho'];
        $pedido->factura_a_demanda = $data['factura_a_demanda'];
        $pedido->factura_siguiente_mes = $data['factura_siguiente_mes'];
        $pedido->cotizacion_id = $data['cotizacion_id'];
        $pedido->BOD_BODEGA = $data['BOD_BODEGA'];
        $pedido->PTO_VTA_ID = $data['PTO_VTA_ID'];
        $pedido->PTO_VTA_DESCRIPCION = $puntodeventa['PTO_VTA_DESCRIPCION'];
        $pedido->USERID = $user->USERID;
        $pedido->DESC_FORMA_PAGO_CODIGO = $data['DESC_FORMA_PAGO_CODIGO'];
        $pedido->CLI_RUC = $data['CLI_RUC'];
        $pedido->CLI_RAZON_SOCIAL = $data['CLI_RAZON_SOCIAL'];
        $pedido->CLI_CORREO_ELECT_SRI = $data['CLI_CORREO_ELECT_SRI'];
        $pedido->COND_ID = $data['COND_ID'];
        $pedido->COND_ID_ORIGINAL = $data['COND_ID_ORIGINAL'];
        $pedido->VEND_ID = $data['VEND_ID'];
        $pedido->VEND_NOMBRES = $data['VEND_NOMBRES'];
        $pedido->VEND_APELLIDOS = $data['VEND_APELLIDOS'];
        $pedido->despacho = $data['despacho'];
        $pedido->porcentage_descuentooriginal = $data['porcentage_descuentooriginal'];
        $pedido->porcentage_descuento = $data['porcentage_descuento'];
        $pedido->subtotal = number_format((float) $data['subtotal'], 2, '.', '');
        $pedido->descuento = number_format((float) $data['descuento'], 2, '.', '');
        $pedido->subtotalmenosdescuento = number_format((float) $data['subtotalmenosdescuento'], 2, '.', '');
        $pedido->iva = number_format((float) $data['iva'], 2, '.', '');
        $pedido->total = number_format((float) $data['total'], 2, '.', '');
        $pedido->requiere_aprobacion_comercial = $data['requiere_aprobacion_comercial'];
        $pedido->aprobado_comercial = $data['aprobado_comercial'];
        $pedido->aprobador_comercial_id = $data['aprobador_comercial_id'];
        $pedido->requiere_aprobacion_crediticia = $data['requiere_aprobacion_crediticia'];
        $pedido->aprobado_crediticio = $data['aprobado_crediticio'];
        $pedido->aprobador_crediticio_id = $data['aprobador_crediticio_id'];
        $pedido->nota_interna = $data['nota_interna'];
        $pedido->nota_cliente = $data['nota_cliente'];
        $pedido->orden_de_compra = $data['orden_de_compra'];
        $pedido->pedido_origen = $data['pedido'];
        $pedido->impuesto = env('IVA_IMPUESTO');

        if ($pedido->save()) {
            $i = 0;
            foreach ($articulos as $det) {
                $i++;
                $detalle = $this->savePedidoDetalle($i, $pedido, $det);

                if ($det['ART_TIPO_COMPRA'] != 'SERV') {
                    if (count($det['proveedores']) > 0) {
                        foreach ($det['proveedores'] as $pro) {
                            $pro['recibido'] = 'yes';
                            $proveedor = $this->savePedidoDetalleProveedores($pedido, $detalle, $pro);
                            $proveedor->save();


                            foreach ($pro['orden_detalle'] as $orden) {
                                $ordenCompra = GpOrdenDeCompra::where('DOC_GENERADO', $orden['ORDEN_COMPRA_DOCUMENTO'])
                                    ->where('detalle_id', $det['id'])
                                    ->where('detalle_proveedores_id', $pro['id'])
                                    ->first();

                                $ejecutado = GpPedidoDetalleProveedores::where('id', $pro['id'])->first();

                                //                                if(!$proveedor->ejecutado)
                                //                                    $proveedor->ejecutado = 0;

                                if ($ejecutado) {
                                    $ejecutado->ejecutado += $det['ART_CANTIDAD'];
                                    $ejecutado->save();
                                }

                                if (!$ejecutado) {
                                    $ordenCompra->ORDEN_COMPRA_DOCU_ORIGEN = $pedido->pedido;
                                    $ordenCompra->detalle_id = $detalle->id;
                                    $ordenCompra->detalle_proveedores_id = $proveedor->id;
                                    $ordenCompra->save();
                                } else {
                                    $nueva_orden = $ordenCompra->replicate();
                                    $nueva_orden->ORDEN_COMPRA_DOCU_ORIGEN = $pedido->pedido;
                                    $nueva_orden->detalle_id = $detalle->id;
                                    $nueva_orden->detalle_proveedores_id = $proveedor->id;
                                    $nueva_orden->ORDEN_COMPRA_CANTIDAD = $det['ART_CANTIDAD'];
                                    $nueva_orden->save();
                                }

                                $ordenCompraGenerado = GpOrdenDeCompraGenerado::where('orden_id', $ordenCompra->id)
                                    //                                    ->where('detalle_id', $det['id'])
                                    //                                    ->where('detalle_proveedores_id', $pro['id'])
                                    ->first();

                                if (!$ejecutado) {
                                    $ordenCompraGenerado->detalle_id = $detalle->id;
                                    $ordenCompraGenerado->detalle_proveedores_id = $proveedor->id;
                                    $ordenCompraGenerado->save();
                                } else {
                                    if ($ordenCompraGenerado) {
                                        $nueva_ordenCompraGenerado = $ordenCompraGenerado->replicate();
                                        $nueva_ordenCompraGenerado->orden_id = $nueva_orden->id;
                                        $nueva_ordenCompraGenerado->detalle_id = $detalle->id;
                                        $nueva_ordenCompraGenerado->detalle_proveedores_id = $proveedor->id;
                                        $nueva_ordenCompraGenerado->estado = 'recibido';
                                        $nueva_ordenCompraGenerado->save();

                                        $ordenCompraGeneradoDetalle = GpOrdenDeCompraGeneradoDetalle::where('orden_generada_id', $ordenCompraGenerado->id)->get();

                                        foreach ($ordenCompraGeneradoDetalle as $ocgd) {
                                            $nueva_ocgd = $ocgd->replicate();
                                            $nueva_ocgd->orden_generada_id = $nueva_ordenCompraGenerado->id;
                                            $nueva_ocgd->detalle_proveedores_id = $proveedor->id;
                                            $nueva_ocgd->save();
                                        }
                                    }
                                }
                            }
                        }
                    }
                    $stock = new GpPedidoDetalleStock();
                    $stock->pedido_id = $pedido->id;
                    $stock->detalle_id = $detalle->id;
                    $stock->STO_ART_BODEGA = $det['stocks']['STO_ART_BODEGA'];
                    $stock->STO_ART_STOCK_DISPONIBLE = $det['stocks']['STO_ART_STOCK_DISPONIBLE'];
                    $stock->seleccionado = $det['stocks']['seleccionado'];
                    $stock->reservado = $det['stocks']['seleccionado'];
                    $stock->save();
                }
            }

            if (isset($eliminar['proveedores']) && count($eliminar['proveedores']) > 0) {
                foreach ($eliminar['proveedores'] as $proveedor) {
                    $proveedor->delete();
                }
            }
            if (isset($eliminar['articulos']) && count($eliminar['articulos']) > 0) {
                foreach ($eliminar['articulos'] as $articulo) {
                    $articulo->delete();
                }
            }

            $this->recalcularpedido($data['id']);
            $this->recalcularpedido($pedido->id);

            $pedido = GpPedido::where('id', $pedido->id)->first();

            $this->checkenviarfactura($pedido, true);
        }
        return response([
            'success' => true,
            'message' => 'El pedido ha sido dividido'
        ]);
    }

    public function consultardespachado(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $data = $request->all();
        $facturarExistente = array();

        $data['subtotal'] = 0;
        $data['descuento'] = 0;
        $data['iva'] = 0;
        $data['subtotalmenosdescuento'] = 0;
        $detalle = GpPedidoDetalle::where('pedido_id', $data['id'])->get();
        $funciones = new FuncionesController();

        foreach ($detalle as $key => $articulo) {

            if ($articulo->trasladado == 0) {
                unset($detalle[$key]);
                continue;
            }

            $proveedores = $funciones->getitemproveedores($data, $articulo, false);
            $array_prov = array();

            if (count($proveedores['proveedores']) > 0) {
                foreach ($proveedores['proveedores'] as $proveedor) {
                    $array_prov[] = $proveedor;
                }
            }

            $articulo->proveedores = $array_prov;

            $articulo->ART_CANTIDAD = $articulo->trasladado;

            $data['subtotal'] += $articulo->ART_CANTIDAD * $articulo->ART_VALOR;
            $data['descuento'] += (float) number_format($articulo->ART_DESCUENTO * $articulo->ART_CANTIDAD, 2, '.', '');
            $data['subtotalmenosdescuento'] += number_format(($articulo->ART_VALOR - $articulo->ART_DESCUENTO) * $articulo->ART_CANTIDAD, 2, '.', '');
            $data['iva'] += (float) number_format((($articulo->ART_VALOR - $articulo->ART_DESCUENTO) * $articulo->ART_CANTIDAD) * ($articulo->ART_IMP_VTA / 100), 2, '.', '');
        }

        $data['descuento'] = number_format($data['descuento'], 2, '.', '');
        $data['subtotalmenosdescuento'] = number_format($data['subtotalmenosdescuento'], 2, '.', '');
        $data['iva'] = number_format($data['iva'], 2, '.', '');
        $data['total'] = number_format($data['subtotalmenosdescuento'] + (float) $data['iva'], 2, '.', '');

        $facturarExistente['pedido'] = $data;
        $facturarExistente['detalle'] = $detalle;

        return response([
            'success' => true,
            'facturar_despachado' => $facturarExistente,
        ]);
    }


    public function facturardespachado(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $facturarDespachado = $request->all();

        $data = $facturarDespachado['pedido'];

        $funciones = new FuncionesController();
        $detalle = GpPedidoDetalle::where('pedido_id', $data['id'])->get();

        $eliminar = array('');

        foreach ($detalle as $key => $articulo) {
            $stocks = GpPedidoDetalleStock::where('detalle_id', $articulo->id)
                ->where('STO_ART_BODEGA', $data['BOD_BODEGA'])
                ->first();

            if ($articulo->ART_TIPO_COMPRA != 'SERV') {
                if ($articulo->trasladado == $articulo->ART_CANTIDAD) {
                    $articulo->delete();
                } elseif ($articulo->trasladado > 0 && $articulo->trasladado < $articulo->ART_CANTIDAD) {
                    $articulo->ART_CANTIDAD = $articulo->ART_CANTIDAD - $articulo->trasladado;
                    $articulo->trasladado = 0;
                    $articulo->save();

                    $stocks->seleccionado = $articulo->ART_CANTIDAD;
                    $stocks->reservado = $articulo->ART_CANTIDAD;
                    $stocks->save();
                }

                $proveedores = $funciones->getitemproveedores($data, $articulo, false);

                foreach ($proveedores['proveedores'] as $proveedor) {
                    if (($proveedor->ingresado - $proveedor->ejecutado) > 0) {
                        //echo 'Eliminar Proveedor: '.$proveedor->id.'<br>';
                        $articulo->ART_CANTIDAD -= $proveedor->ingresado - $proveedor->ejecutado;
                        if ($proveedor->ingresado == $proveedor->CANTIDAD) {
                            unset($proveedor->orden_detalle);

                            $eliminar['proveedores'][] = $proveedor;

                            //$proveedor->delete();
                        } else {
                            unset($proveedor->orden_detalle);
                            $proveedor->save();
                        }
                    }
                }

                if (count($proveedores['proveedores'])) {
                    if ($articulo->ART_CANTIDAD == 0) {
                        //echo 'Por eliminar L: '.$articulo->ART_CODIGO.'<br>';
                        $eliminar['articulos'][] = $articulo;
                        //$articulo->delete();
                    } else {
                        //echo 'Editar la cantidad del L '.$articulo->ART_CODIGO.' a: '.$articulo->ART_CANTIDAD.'<br>';
                        $articulo->save();
                    }
                }
            } else {
                $articulo->delete();
            }
        }

        $articulos = $facturarDespachado['detalle'];

        $user = Auth::user();
        $pedido = new GpPedido;

        $puntodeventa = GpPuntoVenta::where('PTO_VTA_ID', $data['PTO_VTA_ID'])->first();

        $ultimoPedido = GpPedido::orderBy('id', 'DESC')->first();

        if (!$ultimoPedido) {
            $ultimoPedido = new \stdClass();
            $ultimoPedido->id = env('SECUENCIAL_PEDIDO');
            $pedido->id = $ultimoPedido->id + 1;
        }
        $pedido->pedido = 'PED' . str_pad($ultimoPedido->id + 1, 8, "0", STR_PAD_LEFT);
        $pedido->user_id = $user->id;
        $pedido->sucursal_id = $data['sucursal_id'];
        $pedido->sucursal_facturacion_id = $data['sucursal_facturacion_id'];
        $pedido->cliente_id = $data['cliente_id'];
        $pedido->factura_posterior_despacho = $data['factura_posterior_despacho'];
        $pedido->factura_a_demanda = $data['factura_a_demanda'];
        $pedido->factura_siguiente_mes = $data['factura_siguiente_mes'];
        $pedido->cotizacion_id = $data['cotizacion_id'];
        $pedido->BOD_BODEGA = $data['BOD_BODEGA'];
        $pedido->PTO_VTA_ID = $data['PTO_VTA_ID'];
        $pedido->PTO_VTA_DESCRIPCION = $puntodeventa['PTO_VTA_DESCRIPCION'];
        $pedido->USERID = $user->USERID;
        $pedido->DESC_FORMA_PAGO_CODIGO = $data['DESC_FORMA_PAGO_CODIGO'];
        $pedido->CLI_RUC = $data['CLI_RUC'];
        $pedido->CLI_RAZON_SOCIAL = $data['CLI_RAZON_SOCIAL'];
        $pedido->CLI_CORREO_ELECT_SRI = $data['CLI_CORREO_ELECT_SRI'];
        $pedido->COND_ID = $data['COND_ID'];
        $pedido->COND_ID_ORIGINAL = $data['COND_ID_ORIGINAL'];
        $pedido->VEND_ID = $data['VEND_ID'];
        $pedido->VEND_NOMBRES = $data['VEND_NOMBRES'];
        $pedido->VEND_APELLIDOS = $data['VEND_APELLIDOS'];
        $pedido->despacho = $data['despacho'];
        $pedido->porcentage_descuentooriginal = $data['porcentage_descuentooriginal'];
        $pedido->porcentage_descuento = $data['porcentage_descuento'];
        $pedido->subtotal = number_format((float) $data['subtotal'], 2, '.', '');
        $pedido->descuento = number_format((float) $data['descuento'], 2, '.', '');
        $pedido->subtotalmenosdescuento = number_format((float) $data['subtotalmenosdescuento'], 2, '.', '');
        $pedido->iva = number_format((float) $data['iva'], 2, '.', '');
        $pedido->total = number_format((float) $data['total'], 2, '.', '');
        $pedido->requiere_aprobacion_comercial = $data['requiere_aprobacion_comercial'];
        $pedido->aprobado_comercial = $data['aprobado_comercial'];
        $pedido->aprobador_comercial_id = $data['aprobador_comercial_id'];
        $pedido->requiere_aprobacion_crediticia = $data['requiere_aprobacion_crediticia'];
        $pedido->aprobado_crediticio = $data['aprobado_crediticio'];
        $pedido->aprobador_crediticio_id = $data['aprobador_crediticio_id'];
        $pedido->nota_interna = $data['nota_interna'];
        $pedido->nota_cliente = $data['nota_cliente'];
        $pedido->orden_de_compra = $data['orden_de_compra'];
        $pedido->pedido_origen = $data['pedido'];
        $pedido->despachado = 1;
        $pedido->impuesto = env('IVA_IMPUESTO');

        if ($pedido->save()) {
            $i = 0;
            foreach ($articulos as $det) {
                $i++;
                $detalle = $this->savePedidoDetalle($i, $pedido, $det);

                if ($det['ART_TIPO_COMPRA'] != 'SERV') {
                    if (count($det['proveedores']) > 0) {
                        foreach ($det['proveedores'] as $pro) {
                            $pro['recibido'] = 'yes';
                            $proveedor = $this->savePedidoDetalleProveedores($pedido, $detalle, $pro);
                            $proveedor->save();

                            foreach ($pro['orden_detalle'] as $orden) {
                                $ordenCompra = GpOrdenDeCompra::where('DOC_GENERADO', $orden['ORDEN_COMPRA_DOCUMENTO'])
                                    ->where('detalle_id', $det['id'])
                                    ->where('detalle_proveedores_id', $pro['id'])
                                    ->first();

                                $ejecutado = GpPedidoDetalleProveedores::where('id', $pro['id'])->first();

                                if ($ejecutado) {
                                    $ejecutado->ejecutado += $det['ART_CANTIDAD'];
                                    $ejecutado->save();
                                }

                                if (!$ejecutado) {
                                    $ordenCompra->ORDEN_COMPRA_DOCU_ORIGEN = $pedido->pedido;
                                    $ordenCompra->detalle_id = $detalle->id;
                                    $ordenCompra->detalle_proveedores_id = $proveedor->id;
                                    $ordenCompra->save();
                                } else {
                                    $nueva_orden = $ordenCompra->replicate();
                                    $nueva_orden->ORDEN_COMPRA_DOCU_ORIGEN = $pedido->pedido;
                                    $nueva_orden->detalle_id = $detalle->id;
                                    $nueva_orden->detalle_proveedores_id = $proveedor->id;
                                    $nueva_orden->ORDEN_COMPRA_CANTIDAD = $det['ART_CANTIDAD'];
                                    $nueva_orden->save();
                                }

                                $ordenCompraGenerado = GpOrdenDeCompraGenerado::where('orden_id', $ordenCompra->id)
                                    ->where('detalle_id', $det['id'])
                                    ->where('detalle_proveedores_id', $pro['id'])
                                    ->first();

                                if (!$ejecutado) {
                                    $ordenCompraGenerado->detalle_id = $detalle->id;
                                    $ordenCompraGenerado->detalle_proveedores_id = $proveedor->id;
                                    $ordenCompraGenerado->save();
                                } else {
                                    $nueva_ordenCompraGenerado = $ordenCompraGenerado->replicate();
                                    $nueva_ordenCompraGenerado->orden_id = $nueva_orden->id;
                                    $nueva_ordenCompraGenerado->detalle_id = $detalle->id;
                                    $nueva_ordenCompraGenerado->detalle_proveedores_id = $proveedor->id;
                                    $nueva_ordenCompraGenerado->estado = 'recibido';
                                    $nueva_ordenCompraGenerado->save();

                                    $ordenCompraGeneradoDetalle = GpOrdenDeCompraGeneradoDetalle::where('orden_generada_id', $ordenCompraGenerado->id)->get();

                                    foreach ($ordenCompraGeneradoDetalle as $ocgd) {
                                        $nueva_ocgd = $ocgd->replicate();
                                        $nueva_ocgd->orden_generada_id = $nueva_ordenCompraGenerado->id;
                                        $nueva_ocgd->detalle_proveedores_id = $proveedor->id;
                                        $nueva_ocgd->save();
                                    }
                                }
                            }
                        }
                    }
                    $stock = new GpPedidoDetalleStock();
                    $stock->pedido_id = $pedido->id;
                    $stock->detalle_id = $detalle->id;
                    $stock->STO_ART_BODEGA = $pedido->BOD_BODEGA;
                    $stock->STO_ART_STOCK_DISPONIBLE = $det['ART_CANTIDAD'];
                    $stock->seleccionado = $det['ART_CANTIDAD'];
                    $stock->reservado = $det['ART_CANTIDAD'];
                    $stock->save();
                }
            }

            if (isset($eliminar['proveedores']) && count($eliminar['proveedores']) > 0) {
                foreach ($eliminar['proveedores'] as $proveedor) {
                    $proveedor->delete();
                }
            }
            if (isset($eliminar['articulos']) && count($eliminar['articulos']) > 0) {
                foreach ($eliminar['articulos'] as $articulo) {
                    $articulo->delete();
                }
            }

            $this->recalcularpedido($data['id']);
            $this->recalcularpedido($pedido->id);

            $pedido_anterior = GpPedido::where('id', $data['id'])->first();
            $pedido = GpPedido::where('id', $pedido->id)->first();

            GpGuiaRemision::where('pedido', $pedido_anterior->pedido)->update(['pedido' => $pedido->pedido]);

            $this->checkenviarfactura($pedido, true, false);
        }
        return response([
            'success' => true,
            'message' => 'El pedido ha sido dividido'
        ]);
    }
    public function facturardespachadoBK(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $facturarDespachado = $request->all();
        $data = $facturarDespachado['pedido'];

        $detalle = GpPedidoDetalle::where('pedido_id', $data['id'])->get();

        foreach ($detalle as $key => $articulo) {
            $stocks = GpPedidoDetalleStock::where('detalle_id', $articulo->id)
                ->where('STO_ART_BODEGA', $data['BOD_BODEGA'])
                ->first();

            if ($articulo->ART_TIPO_COMPRA != 'SERV') {
                if (!$articulo->trasladado) {
                    $articulo->delete();
                } elseif ($articulo->trasladado > 0) {
                    $articulo->ART_CANTIDAD = $articulo->trasladado;
                    $articulo->save();

                    $stocks->seleccionado = $articulo->trasladado;
                    $stocks->save();
                }
            }
        }
        $this->recalcularpedido($data['id']);

        $pedido = GpPedido::where('id', $data['id'])->first();

        GpOrdenDespacho::where('pedido_id', $pedido->id)->update(['estado' => 'despachada']);

        $this->checkenviarfactura($pedido, true, false);

        return response([
            'success' => true,
            'message' => 'El pedido ha sido modificado y facturado'
        ]);
    }

    public function recalcularpedido($id): void
    {
        $funciones = new FuncionesController();
        $pedido = GpPedido::where('id', $id)->first();
        $funciones->recalcularpedido($pedido);
    }

    public function corregirpedidos()
    {
        $pedidos = GpPedido::whereNotNull('pedido_origen')->get();
        foreach ($pedidos as $pedido) {
            $this->recalcularpedido($pedido->id);
        }
    }

    public function recuperarpedido(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $data = $request->all();
        $user = Auth::user();

        $funciones = new FuncionesController();
        $cliente = $funciones->getcliente($data);

        $detalle = $this->getDetallePedido($data);

        $funciones->recalculariva($detalle);

        $funciones->setTempCartFrom($detalle, $data, $user);

        $registroCopia = new GpRegistroCopia();
        $registroCopia->user_id = $user->id;
        $registroCopia->USERID = $user->USERID;
        $registroCopia->tipo = 'volver_a_pedir';
        $registroCopia->pedido_origen = $data['id'];
        $registroCopia->save();

        return response([
            'success' => true,
            'cliente' => $cliente,
            'registro_copia' => $registroCopia->id,
            'currentSucursal' => $data['sucursal_id'],
        ]);
    }

    public function copiarpedido(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $data = $request->all();
        $user = Auth::user();

        $funciones = new FuncionesController();
        $cliente = $funciones->getcliente($data);

        $data['pedido']['sucursal_id'] = $data['sucursal_id'];
        $data['pedido']['cliente_id'] = $data['cliente_id'];

        $registroCopia = new GpRegistroCopia();
        $registroCopia->user_id = $user->id;
        $registroCopia->USERID = $user->USERID;
        $registroCopia->tipo = 'copia';

        if ($data['tipo'] == 'cotizacion') {
            $quotationService = app(QuotationServiceInterface::class);
            $cotizaciones = new CotizacionesController($quotationService);
            $detalle = $cotizaciones->getdetallecotizacion($data['pedido'], true);
            $registroCopia->cotizacion_origen = $data['pedido']['id'];
        } else {
            $detalle = $this->getDetallePedido($data['pedido'], true);
            $registroCopia->pedido_origen = $data['pedido']['id'];
        }
        $registroCopia->save();
        $funciones->recalculariva($detalle);
        $funciones->setTempCartFrom($detalle, $data['pedido'], $user);

        return response([
            'success' => true,
            'cliente' => $cliente,
            'registro_copia' => $registroCopia->id,
            'currentSucursal' => $data['sucursal_id'],
            'test' => 'copiarpedido'
        ]);
    }

    public function getDetallePedido($data, $encera = false): \Illuminate\Database\Eloquent\Collection|array
    {

        $funciones = new FuncionesController();
        $cliente = $funciones->getcliente($data);
        $arr_bodegas = $funciones->gebodegas($data, 1);

        $consulta_bodega = array();
        $consulta_precio = array();
        $stock_bodega_arr = array();
        $arrArticulos = array();

        $detalle = GpPedidoDetalle::where('pedido_id', $data['id'])->get();


        foreach ($detalle as $articulo) {
            $articulo->proveedores = array();

            $stockbodega = GpPedidoDetalleStock::where('pedido_id', $data['id'])
                ->where('detalle_id', $articulo->id)
                ->get()->toArray();
            $stock_bodega_arr[$articulo->ART_CODIGO] = $stockbodega;

            $arrArticulos[] = array(
                'articulo' => $articulo->ART_CODIGO,
            );

            $consulta_precio[] = array(
                'ART_CODIGO' => $articulo->ART_CODIGO,
            );

            if ($encera) {
                $articulo->descuento = $cliente->CLI_DSTO_COMERCIAL;
                $articulo->descuento_original = $cliente->CLI_DSTO_COMERCIAL;
                $articulo->ART_DESCUENTO = 0;
                $articulo->modificador = 0;
                $articulo->ART_DESCRIPCION = $articulo->ART_DESCRIPCION_ORIGINAL;
            }
        }

        $webservices = new WebserviceController;
        if ($encera) {
            $precios = $webservices->listaprecios($consulta_precio);
            $lista_precios_general = array();
            foreach ($precios as $precio) {
                $lista_precios_general[$precio['LST_PRC_ARTICULO']][$precio['LST_PRC_ID']] = $precio;
            }
        }

        // $stock_general = $funciones->stockarticulobodega($consulta_bodega, $arr_bodegas);
        $stock_general = $this->orderService->restoreOrders($arrArticulos, $arr_bodegas);
        $stock_general = collect($stock_general)->groupBy('STO_ART_ARTICULO') // Agrupamos primero por artículo
            ->map(function ($items) {
                return $items->keyBy('STO_ART_BODEGA'); // Agrupamos dentro por bodega
            })
            ->toArray();
        foreach ($stock_bodega_arr as $key => $bodegas) {
            foreach ($bodegas as $bodega) {
                $stock_general[$key][$bodega['STO_ART_BODEGA']]['seleccionado'] = $bodega['seleccionado'];
            }
        }

        $articulos_array_l = array();

        foreach ($detalle as $articulo) {
            $funciones->formatStock($articulo, $stock_general);
            if ($articulo->ART_TIPO_COMPRA != 'L') {
                if ($encera) {
                    $articulo->ART_VALOR = $lista_precios_general[$articulo->ART_CODIGO][$cliente->CLI_NIVEL_PRECIO]['LST_PRC_VALOR'];
                    $articulo->ART_VALOR_ORIGINAL = $lista_precios_general[$articulo->ART_CODIGO][$cliente->CLI_NIVEL_PRECIO]['LST_PRC_VALOR'];
                }
            }
            if (!$articulo->disponible) {
                $clase = GpArticulo::where('ART_CODIGO', $articulo->ART_CODIGO)->first();
                $articulo->ART_CODIGO = $funciones->buscareledisponible($articulos_array_l, $clase->ART_CLASE_ARTICULO);
                $articulos_array_l[] = $articulo->ART_CODIGO;
            }
        }

        return $detalle;
    }



    public function sendfacturagp($pedido): void
    {

        $order = app(PurchaseOrderServiceInterface::class);
        $ordendecompra = new OrdendecompraController($order);

        $flag = $ordendecompra->checkordendecomprarecibidos($pedido);
        if ($flag) {
            return;
        }

        $this->checktrasladado($pedido);
        if ($pedido->estado == 'orden_de_compra') {
            return;
        }

        if ($pedido->estado == 'espera') {
            $pedido->estado = 'enviando';
            $pedido->save();

            $sucursalEnvio = GpClienteSucursal::where('id', $pedido->sucursal_id)->first();

            $sucursalFacturacion = GpClienteSucursal::where('id', $pedido->sucursal_facturacion_id)->first();

            $doc_id = GpRelacionPuntoVentaIdVenta::where('PTO_VTA_ID_PUNTO_VENTA', $pedido->PTO_VTA_ID)
                ->where('PTO_VTA_ID_FACTURA_NC', 'LIKE', 'FAC%')->first();



            if (!$doc_id) {
                return;
            }

            $FAC_VTA_ID_DIRECCION_ENVIO = $sucursalEnvio->CLI_SUC_ID_DIRECCION;
            if ($pedido->despacho == 'Retiro en bodega') {
                $FAC_VTA_ID_DIRECCION_ENVIO = $sucursalFacturacion->CLI_SUC_ID_DIRECCION;
            }

            if (!$pedido->factura_origen)
                $pedido->factura_origen = '';

            $cabecera = array(
                array(
                    'FAC_VTA_DOCU_ORIGEN' => $pedido->pedido,
                    'FAC_VTA_DOC_COMPRA' => $pedido->orden_de_compra,
                    'FAC_VTA_ID_TIPO' => 3,
                    'FAC_VTA_PUNTO_VENTA' => $pedido->PTO_VTA_ID,
                    'FAC_VTA_DOC_ID' => $doc_id->PTO_VTA_ID_FACTURA_NC,
                    'FAC_VTA_ID_CLIENTE' => $pedido->CLI_RUC,
                    'FAC_VTA_ID_VENDEDOR' => $pedido->VEND_ID,
                    'FAC_VTA_ID_DIRECCION' => $sucursalFacturacion->CLI_SUC_ID_DIRECCION,
                    'FAC_VTA_ID_DIRECCION_ENVIO' => $FAC_VTA_ID_DIRECCION_ENVIO,
                    'FAC_VTA_COND_PAGO' => $pedido->COND_ID,
                    'FAC_VTA_NOTA_DOCU' => $pedido->factura_origen,
                    'FAC_VTA_DATE_DOCU' => date('Y-m-d H:i:s'),
                    'FAC_VTA_FORMA_PAGO' => $pedido->DESC_FORMA_PAGO_CODIGO,
                    'FAC_VTA_BODEGA' => $pedido->BOD_BODEGA,
                    'FAC_VTA_SUBTOTAL' => number_format($pedido->subtotal - $pedido->descuento, 2, '.', ''),
                    'FAC_VTA_DESCUENTO_COMERCIAL' => 0,
                    'FAC_VTA_IMPUESTO' => $pedido->iva,
                    'FAC_VTA_TOTAL' => number_format($pedido->subtotal - $pedido->descuento + $pedido->iva, 2, '.', ''), //$pedido->total,
                    'FAC_VTA_TIPO_DESPACHO' => $pedido->despacho,
                )
            );

            $detalles = GpPedidoDetalle::where('pedido_id', $pedido->id)->get();



            if (!count($detalles)) {
                return;
            }

            $DetallePedido = array();
            $linea = 0;
            foreach ($detalles as $key => $detalle) {
                $linea++;
                $DetallePedido[] = array(
                    'FAC_VTA_DET_LINEA' => $linea,
                    'FAC_VTA_DET_DOCU_ORIGEN' => $pedido->pedido,
                    'FAC_VTA_DET_ID_TIPO' => 3,
                    'FAC_VTA_DET_ARTICULO' => $detalle->ART_CODIGO,
                    'FAC_VTA_DET_DESC_ARTICULO' => $detalle->ART_DESCRIPCION,
                    'FAC_VTA_DET_UNIDAD_MEDIDA' => $detalle->ART_PLAN_UMEDIDA,
                    'FAC_VTA_DET_BODEGA' => $pedido->BOD_BODEGA,
                    'FAC_VTA_DET_CANTIDAD' => $detalle->ART_CANTIDAD,
                    'FAC_VTA_DET_COSTO_UNITARIO' => $detalle->ART_VALOR,
                    'FAC_VTA_DET_PRECIO_UNITARIO' => $detalle->ART_VALOR,
                    'FAC_VTA_DET_DESCUENTO' => $detalle->ART_DESCUENTO,
                    'FAC_VTA_DET_IMPUESTO_IVA' => $detalle->ART_IVA,
                    'FAC_VTA_DET_TOTAL' => number_format(($detalle->ART_VALOR - $detalle->ART_DESCUENTO) * $detalle->ART_CANTIDAD, 2, '.', ''),
                );
            }
            $parameters = array(
                'Cabecera' => $cabecera,
                'Detalle' => $DetallePedido
            );

            if ($pedido->rechazado) {
                $pedido->estado = 'rechazado';
                $pedido->save();
                return;
            }

            if ($pedido->requiere_aprobacion_crediticia == 'yes' && $pedido->aprobado_crediticio == 'no') {
                return;
            }

            //            $cronLog = new GpCronLog();
            //            $cronLog->metodo = 'Enviar pedido';
            //            $cronLog->fecha = date('Y-m-d H:i:s');
            //            $cronLog->data = json_encode($parameters);
            //            $cronLog->save();

            $webservices = new WebserviceController();
            $result = $webservices->transaccional('MST_FAC_VTA', $parameters);



            if (isset($result['MST_FAC_VTAResult'])) {
                $pedido->MST_FAC_VTAResult = $result['MST_FAC_VTAResult'];
                $pedido->save();
                $this->respuestaFacturaVenta($pedido, $webservices);
            }
        }
    }

    public function imprimirpedido($id): \Illuminate\Http\Response|string //\Illuminate\Contracts\View\View|\Illuminate\Contracts\View\Factory|string|\Illuminate\Contracts\Foundation\Application
    {
        try {
            $pedido = $this->orderService->getOrderInfo($id);
            if (is_string($pedido)) {
                abort(403, $pedido); // Ej: 'No permitido'
            }
            $pdf = PDF::loadView('pedidoPDF', $pedido);
            return $pdf->stream('pedido-' . $pedido['pedido'] . '.pdf');
        } catch (\Exception $e) {
            report($e);
            abort(500, 'Error al generar el PDF del pedido');
        }
    }
    public function imprimirpedidoimagenes($id): \Illuminate\Http\Response|string //\Illuminate\Contracts\View\View|\Illuminate\Contracts\View\Factory|string|\Illuminate\Contracts\Foundation\Application
    {
        try {
            $pedido = $this->orderService->getOrderInfo($id);
            if (is_string($pedido)) {
                abort(403, $pedido); // Ej: 'No permitido'
            }
            $pdf = PDF::loadView('pedidoimagenesPDF', $pedido);
            return $pdf->stream('pedido-' . $pedido['pedido'] . '.pdf');
        } catch (\Exception $e) {
            report($e);
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }

    public function imprimirpedidoride($id)
    {
        try {

            $pedido = $this->orderService->generateInvoiceRide($id);

            if (is_string($pedido)) {
                abort(403, $pedido); // Ej: 'No permitido'
            }
            $pdf = PDF::loadView('pedidoridePDF', $pedido);
            return $pdf->stream('ride-' . $pedido['pedido'] . '.pdf');
        } catch (\Exception $e) {
            report($e);
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }

    public function imprimirpedidoxml($id)
    {
        try {
            $xmlContent = $this->orderService->generateInvoiceXml($id);
            $nombreArchivo = 'factura_' . $xmlContent['factura'] . '.xml';
            return Response::make($xmlContent['xml'], 200, [
                'Content-Type' => 'application/xml',
                'Content-Disposition' => 'attachment; filename="' . $nombreArchivo . '"',
            ]);
        } catch (\Throwable $th) {
            report($th);
            return $this->buildErrorResponse($th->getMessage(), 500);
        }
    }
    public function despachoenproceso($id)
    {
        $pedido = gpPedido::where('id', $id)->first();
        return response([
            'success' => true,
            'despacho_en_proceso' => $pedido->despacho_en_proceso,
            'transferencia_en_proceso' => $pedido->transferencia_en_proceso,
        ]);
    }



    public function guardarordendecompra(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $data = $request->all();

        $pedido = GpPedido::where('id', $data['id'])->get()->first();
        $pedido->orden_de_compra = $data['orden_de_compra'];
        $pedido->nota_interna = $data['nota_interna'];
        $pedido->save();

        return response([
            'success' => true,
            'message' => 'Orden de compra guardada'
        ]);
    }

    public function guardarmensaje(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $data = $request->all();

        $pedido = GpPedido::where('id', $data['id'])->get()->first();
        $pedido->orden_de_compra = $data['orden_de_compra'];
        $pedido->aprobado_comercial_mensaje = $data['aprobado_comercial_mensaje'];
        $pedido->aprobado_crediticio_mensaje = $data['aprobado_crediticio_mensaje'];
        $pedido->save();

        return response([
            'success' => true,
            'message' => 'Todo guardado'
        ]);
    }

    public function facturarcotizacion(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $data = $request->all();
        $user = Auth::user();

        $detalle = GpCotizacionDetalle::where('cotizacion_id', $data['id'])->get()->toArray();
        foreach ($detalle as $key => $det) {
            $proveedores = GpCotizacionDetalleProveedores::where('detalle_id', $det['id'])->get()->toArray();
            $detalle[$key]['proveedores'] = $proveedores;

            $stock = GpCotizacionDetalleStock::where('detalle_id', $det['id'])->get()->toArray();
            $detalle[$key]['stock'] = $stock;
        }

        $data['cotizacion'] = $detalle;

        $aprobaciones = new AprobacionesController;

        $requiere_aprobacion_comercial = 'no';

        $requiere_aprobacion_crediticia = $aprobaciones->checkAprobacionesCrediticias($data);

        $pedido = new GpPedido;

        $puntodeventa = GpPuntoVenta::where('PTO_VTA_ID', $data['PTO_VTA_ID'])->first();

        $ultimoPedido = GpPedido::orderBy('id', 'DESC')->first();

        if (!$ultimoPedido) {
            $ultimoPedido = new \stdClass();
            $ultimoPedido->id = 0;
        }
        $pedido->pedido = 'PED' . str_pad($ultimoPedido->id + 1, 8, "0", STR_PAD_LEFT);
        $pedido->user_id = $user->id;
        $pedido->sucursal_id = $data['sucursal_id'];
        $pedido->sucursal_facturacion_id = $data['sucursal_facturacion_id'];
        $pedido->cliente_id = $data['cliente_id'];
        $pedido->factura_posterior_despacho = $data['factura_posterior_despacho'];
        $pedido->factura_a_demanda = $data['factura_a_demanda'];
        $pedido->factura_siguiente_mes = $data['factura_siguiente_mes'];
        $pedido->cotizacion_id = $data['id'];
        $pedido->BOD_BODEGA = $data['BOD_BODEGA'];
        $pedido->PTO_VTA_ID = $data['PTO_VTA_ID'];
        $pedido->PTO_VTA_DESCRIPCION = $puntodeventa['PTO_VTA_DESCRIPCION'];
        $pedido->USERID = $user->USERID;
        $pedido->DESC_FORMA_PAGO_CODIGO = $data['DESC_FORMA_PAGO_CODIGO'];
        $pedido->CLI_RUC = $data['CLI_RUC'];
        $pedido->CLI_RAZON_SOCIAL = $data['CLI_RAZON_SOCIAL'];
        $pedido->CLI_CORREO_ELECT_SRI = $data['CLI_CORREO_ELECT_SRI'];
        $pedido->COND_ID = $data['COND_ID'];
        $pedido->COND_ID_ORIGINAL = $data['COND_ID_ORIGINAL'];
        $pedido->VEND_ID = $data['VEND_ID'];
        $pedido->VEND_NOMBRES = $data['VEND_NOMBRES'];
        $pedido->VEND_APELLIDOS = $data['VEND_APELLIDOS'];
        $pedido->despacho = $data['despacho'];
        $pedido->porcentage_descuentooriginal = $data['porcentage_descuentooriginal'];
        $pedido->porcentage_descuento = $data['porcentage_descuento'];
        $pedido->subtotal = (float) $data['subtotal'];
        $pedido->descuento = (float) $data['descuento'];
        $pedido->subtotalmenosdescuento = (float) $data['subtotal'] - (float) $data['descuento'];
        $pedido->iva = (float) $data['iva'];
        $pedido->total = (float) $data['total'];
        $pedido->requiere_aprobacion_comercial = $requiere_aprobacion_comercial;
        $pedido->aprobado_comercial = 'no';
        $pedido->aprobador_comercial_id = null;
        $pedido->requiere_aprobacion_crediticia = $requiere_aprobacion_crediticia;
        $pedido->aprobado_crediticio = 'no';
        $pedido->aprobador_crediticio_id = null;
        $pedido->nota_interna = $data['nota_interna'];
        $pedido->nota_cliente = $data['nota_cliente'];
        $pedido->orden_de_compra = $data['orden_de_compra'];
        $pedido->impuesto = env('IVA_IMPUESTO');

        if ($pedido->save()) {
            $i = 0;
            foreach ($data['cotizacion'] as $det) {
                $i++;
                $detalle = $this->savePedidoDetalle($i, $pedido, $det);

                if (count($det['proveedores']) > 0) {
                    foreach ($det['proveedores'] as $pro) {
                        $proveedor = $this->savePedidoDetalleProveedores($pedido, $detalle, $pro);
                        $proveedor->save();
                    }
                }
                $this->savePedidoDetalleStock($det['stock'], $pedido, $detalle);
            }

            GpCotizacion::where('id', $data['id'])->update(['estado' => 'procesado']);

            $this->checkenviarfactura($pedido);
        }
        return response([
            'success' => true,
            'message' => 'El pedido ha sido guardado'
        ]);
    }

    /**
     * @param array $data
     * @param GpPedido $pedido
     * @param $PTO_VTA_DESCRIPCION
     * @param \App\Models\User|\Illuminate\Contracts\Auth\Authenticatable|null $user
     * @param array $requiere_aprobacion_comercial
     * @param string $requiere_aprobacion_crediticia
     * @return void
     */
    public function setguardarpedido(array $data, GpPedido $pedido, $PTO_VTA_DESCRIPCION, \App\Models\User|\Illuminate\Contracts\Auth\Authenticatable|null $user, array $requiere_aprobacion_comercial, string $requiere_aprobacion_crediticia): void
    {
        $pedido->PTO_VTA_ID = $data['PTO_VTA_ID'];
        $pedido->PTO_VTA_DESCRIPCION = $PTO_VTA_DESCRIPCION;
        $pedido->USERID = $user->USERID;
        $pedido->DESC_FORMA_PAGO_CODIGO = $data['DESC_FORMA_PAGO_CODIGO'];
        $pedido->CLI_RUC = $data['CLI_RUC'];
        $pedido->CLI_RAZON_SOCIAL = $data['CLI_RAZON_SOCIAL'];
        $pedido->CLI_CORREO_ELECT_SRI = $data['CLI_CORREO_ELECT_SRI'];
        $pedido->COND_ID = $data['COND_ID'];
        $pedido->COND_ID_ORIGINAL = $data['COND_ID_ORIGINAL'];
        $pedido->VEND_ID = $data['VEND_ID'];
        $pedido->VEND_NOMBRES = $data['VEND_NOMBRES'];
        $pedido->VEND_APELLIDOS = $data['VEND_APELLIDOS'];
        $pedido->despacho = $data['despacho'];
        $pedido->porcentage_descuentooriginal = $data['porcentage_descuentooriginal'];
        $pedido->porcentage_descuento = $data['porcentage_descuento'];
        $pedido->subtotal = number_format((float) $data['subtotal'] + (float) $data['descuento'], 2, '.', '');
        $pedido->descuento = number_format((float) $data['descuento'], 2, '.', '');
        $pedido->subtotalmenosdescuento = number_format((float) $data['subtotal'], 2, '.', '');
        $pedido->iva = number_format((float) $data['iva'], 2, '.', '');
        $pedido->total = number_format((float) $data['total'], 2, '.', '');
        $pedido->requiere_aprobacion_comercial = $requiere_aprobacion_comercial['requiere'];
        $pedido->motivo_aprobacion_comercial = $requiere_aprobacion_comercial['motivo'];
        $pedido->aprobado_comercial = 'no';
        $pedido->aprobador_comercial_id = null;
        $pedido->requiere_aprobacion_crediticia = $requiere_aprobacion_crediticia;
        $pedido->aprobado_crediticio = 'no';
        $pedido->aprobador_crediticio_id = null;
        $pedido->motivo_crediticio = $data['motivo_crediticio'];
        $pedido->nota_interna = $data['nota_interna'];
        $pedido->nota_cliente = $data['nota_cliente'];
        $pedido->orden_de_compra = $data['orden_de_compra'];
    }

    /**
     * @param int $i
     * @param GpPedido $pedido
     * @param mixed $det
     * @return GpPedidoDetalle
     */
    public function savePedidoDetalle(int $i, GpPedido $pedido, mixed $det): GpPedidoDetalle
    {

        //        $articulos_array_l = array();
        //        $articulo = (object)$det;
        //        $funciones = new FuncionesController();
        //        $articulo->disponible = $funciones->checkarticulodisponible($articulo);
        //        if(!$articulo->disponible){
        //            $clase = GpArticulo::where('ART_CODIGO', $articulo->ART_CODIGO)->first();
        //            $articulo->ART_CODIGO = $funciones->buscareledisponible($articulos_array_l, $clase->ART_CLASE_ARTICULO);
        //            $articulos_array_l[] = $articulo->ART_CODIGO;
        //        }
        //        $det['ART_CODIGO'] = $articulo->ART_CODIGO;

        $detalle = new GpPedidoDetalle;
        $detalle->pedido_id = $pedido->id;
        $detalle->orden = $i;
        $detalle->ART_CODIGO = $det['ART_CODIGO'];
        $detalle->ART_DESCRIPCION = $det['ART_DESCRIPCION'];
        $detalle->ART_DESCRIPCION_ORIGINAL = $det['ART_DESCRIPCION_ORIGINAL'];
        $detalle->CHECK_ALTERNATIVO = $det['CHECK_ALTERNATIVO'] ?? 0;
        $detalle->ART_CODIGO_TRES_LETRAS = $det['ART_CODIGO_TRES_LETRAS'];
        $detalle->ART_DECIMAL_CANTIDAD = (int) $det['ART_DECIMAL_CANTIDAD'];
        $detalle->ART_PLAN_UMEDIDA = $det['ART_PLAN_UMEDIDA'];
        $detalle->ART_MARCA = $det['ART_MARCA'];
        $detalle->ART_MATERIAL = $det['ART_MATERIAL'];
        $detalle->ART_PESO = $det['ART_PESO'];
        $detalle->ART_TIPO_COMPRA = $det['ART_TIPO_COMPRA'];
        $detalle->ART_VALOR = (float) $det['ART_VALOR'];
        $detalle->ART_VALOR_ORIGINAL = (float) $det['ART_VALOR_ORIGINAL'];
        $detalle->ART_DESCUENTO = (float) $det['ART_DESCUENTO'];
        $detalle->ART_IVA = (float) $det['ART_IVA'];
        $detalle->ART_CANTIDAD = (float) $det['ART_CANTIDAD'];
        $detalle->ART_STOCK = (float) $det['ART_STOCK'];
        $detalle->ART_IMP_VTA = (float) $det['ART_IMP_VTA'];
        $detalle->ART_COSTO_PROMEDIO = (float) $det['ART_COSTO_PROMEDIO'];
        $detalle->descuento = (float) $det['descuento'];
        $detalle->descuento_original = (float) $det['descuento_original'];
        $detalle->modificador = (float) $det['modificador'];
        $detalle->save();
        return $detalle;
    }

    /**
     * @param GpPedido $pedido
     * @param GpPedidoDetalle $detalle
     * @param mixed $pro
     * @return GpPedidoDetalleProveedores
     */
    public function savePedidoDetalleProveedores(GpPedido $pedido, GpPedidoDetalle $detalle, mixed $pro): GpPedidoDetalleProveedores
    {
        $proveedor = new GpPedidoDetalleProveedores;
        $proveedor->pedido_id = $pedido->id;
        $proveedor->detalle_id = $detalle->id;
        $proveedor->ID_PROVEEDOR = $pro['ID_PROVEEDOR'];
        $proveedor->DESCRIPCION_ARTICULO = trim($pro['DESCRIPCION_ARTICULO']);
        $proveedor->FECHA_ENTREGA = $pro['FECHA_ENTREGA'];
        $proveedor->CANTIDAD = (float) $pro['CANTIDAD'];
        $proveedor->COSTO_UNITARIO = (float) $pro['COSTO_UNITARIO'];
        $proveedor->IMPUESTO = (float) $pro['IMPUESTO'];
        $proveedor->TOTAL = (float) $pro['TOTAL'];
        $proveedor->TOTALCONIMPUESTO = (float) $pro['TOTALCONIMPUESTO'];
        $proveedor->NOTA = $pro['NOTA'];
        if (isset($pro['recibido'])) {
            $proveedor->recibido = $pro['recibido'];
        }
        if (isset($pro['MARGEN'])) {
            $proveedor->MARGEN = $pro['MARGEN'];
        }
        return $proveedor;
    }

    /**
     * @param $stock1
     * @param GpPedido $pedido
     * @param GpPedidoDetalle $detalle
     * @return void
     */
    public function savePedidoDetalleStock($stock1, GpPedido $pedido, GpPedidoDetalle $detalle): void
    {
        if (count($stock1) > 0) {
            foreach ($stock1 as $st) {
                $stock = new GpPedidoDetalleStock();
                $stock->pedido_id = $pedido->id;
                $stock->detalle_id = $detalle->id;
                $stock->STO_ART_BODEGA = $st['STO_ART_BODEGA'];
                $stock->STO_ART_STOCK_DISPONIBLE = $st['STO_ART_STOCK_DISPONIBLE'];
                $stock->seleccionado = $st['seleccionado'];
                $stock->reservado = $st['seleccionado'];
                $stock->save();
            }
        }
    }

    public function volverafacturar($devolucion)
    {
        $pedido = GpPedido::where('id', $devolucion->pedido_id)->first();
        $despachada = $this->orderService->checkOutValidateDispatchReInvoice($devolucion);
        $cliente = GpCliente::where('id', $devolucion->cliente_volver_a_facturar_id)->first();
        $detalle = GpPedidoDetalle::where('pedido_id', $pedido->id)->get();

        $nuevo_pedido = $pedido->replicate();

        $ultimoPedido = GpPedido::orderBy('id', 'DESC')->first();

        $nuevo_pedido->pedido = 'PED' . str_pad($ultimoPedido->id + 1, 8, "0", STR_PAD_LEFT);

        $nuevo_pedido->factura = null;
        $nuevo_pedido->factura_origen = $pedido->factura;
        $nuevo_pedido->fecha_factura = null;
        $nuevo_pedido->estado = 'espera';

        $nuevo_pedido->sucursal_id = $devolucion->sucursal_id;
        $nuevo_pedido->sucursal_facturacion_id = $devolucion->sucursal_facturacion_id;
        $nuevo_pedido->cliente_id = $devolucion->cliente_volver_a_facturar_id;
        $nuevo_pedido->orden_de_compra = $devolucion->orden_de_compra;

        $nuevo_pedido->CLI_RUC = $cliente->CLI_RUC;
        $nuevo_pedido->CLI_RAZON_SOCIAL = $cliente->CLI_RAZON_SOCIAL;
        $nuevo_pedido->CLI_CORREO_ELECT_SRI = $cliente->CLI_CORREO_ELECT_SRI;

        $nuevo_pedido->factura_siguiente_mes = 0;

        if ($cliente->CLI_FACTURA_POSTERIOR_AL_DESPACHO == 'SI') {
            $nuevo_pedido->factura_posterior_despacho = 1;
            if ($cliente->CLI_DIA_MAXIMO_MES_FACTURACION == 1) {
                $nuevo_pedido->factura_a_demanda = 1;
            } elseif (date('d') > $cliente->CLI_DIA_MAXIMO_MES_FACTURACION) {
                $nuevo_pedido->factura_siguiente_mes = 1;
            }
        }

        if ($despachada) {
            $nuevo_pedido->despachado = 1;
        }

        $nuevo_pedido->save();

        foreach ($detalle as $det) {
            $nuevo_det = $det->replicate();
            $nuevo_det->pedido_id = $nuevo_pedido->id;
            $nuevo_det->devuelto = null;

            if ($devolucion->tipo_devolucion == 'parcial') {
                $articulo_devolucion = GpDevolucionesDetalle::where('detalle_id', $det->id)
                    ->where('devolucion_id', $devolucion->id)
                    ->first();
                if ($articulo_devolucion) {
                    if ($despachada) {  //&& $devolucion->tipo_devolucion == 'total'
                        $nuevo_det->ART_CANTIDAD = $articulo_devolucion->disponible - $articulo_devolucion->recibido;
                    } else {
                        $nuevo_det->ART_CANTIDAD = $articulo_devolucion->disponible - $articulo_devolucion->seleccionado;
                    }
                } else {
                    $nuevo_det->ART_CANTIDAD = null;
                }
            } else {
                $articulo_devolucion = GpDevolucionesDetalle::where('detalle_id', $det->id)
                    ->where('devolucion_id', $devolucion->id)
                    ->first();
                if ($articulo_devolucion) {
                    $nuevo_det->ART_CANTIDAD = $articulo_devolucion->disponible;
                } else {
                    $nuevo_det->ART_CANTIDAD = null;
                }
            }

            if (!$nuevo_det->ART_CANTIDAD)
                continue;

            $nuevo_det->ART_IMP_VTA = GpArticulo::where('ART_CODIGO', $nuevo_det->ART_CODIGO)->first()->ART_IMP_VTA;

            $nuevo_det->save();

            $detalle_proveedores = GpPedidoDetalleProveedores::where('pedido_id', $pedido->id)
                ->where('detalle_id', $det->id)
                ->get();

            foreach ($detalle_proveedores as $proveedor) {
                $nuevo_proveedor = $proveedor->replicate();
                $nuevo_proveedor->pedido_id = $nuevo_pedido->id;
                $nuevo_proveedor->detalle_id = $nuevo_det->id;
                $nuevo_proveedor->save();

                $parameters = array(
                    // 'IDCompania' => env('ID_COMPANIA'),
                    'fecha' => date('Y/m/d'),
                    'item' => $nuevo_det->ART_CODIGO,
                    'bodega' => $nuevo_pedido->BOD_BODEGA,
                    'cantidad' => $nuevo_proveedor->CANTIDAD,
                    'BinOrigen' => $pedido->pedido,
                    'BinDestino' => $nuevo_pedido->pedido,
                );
                // $webservices->inventario('TransferenciaCompartimento', $parameters);
                $this->apiGp->transferCompartment($parameters);
            }

            $detalle_stocks = GpPedidoDetalleStock::where('pedido_id', $pedido->id)
                ->where('detalle_id', $det->id)
                ->get();

            foreach ($detalle_stocks as $stock) {
                $nuevo_stock = $stock->replicate();
                $nuevo_stock->pedido_id = $nuevo_pedido->id;
                $nuevo_stock->detalle_id = $nuevo_det->id;

                if ($devolucion->tipo_devolucion == 'parcial' && $nuevo_stock->STO_ART_BODEGA == $pedido->BOD_BODEGA) {
                    $articulo_devolucion = GpDevolucionesDetalle::where('detalle_id', $det->id)
                        ->where('devolucion_id', $devolucion->id)
                        ->first();
                    if ($articulo_devolucion) {
                        $nuevo_stock->seleccionado = $articulo_devolucion->disponible - $articulo_devolucion->seleccionado;
                        $nuevo_stock->reservado = $nuevo_stock->seleccionado;
                    }
                } elseif ($devolucion->tipo_devolucion == 'total') {
                    $nuevo_stock->reservado = $nuevo_stock->seleccionado;
                }
                $nuevo_stock->save();
            }
        }

        $funciones = new FuncionesController();
        $funciones->recalcularpedido($nuevo_pedido);


        $this->checkenviarfactura($nuevo_pedido);
    }

    function cancelarpedido(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $data = $request->all();
        $user = Auth::user();

        $pedido = GpPedido::where('id', $data['id'])->get()->first();

        if ($pedido->estado == 'facturado') {
            return response([
                'success' => true,
                'message' => 'Pedido ' . $pedido->pedido . ' ya está facturado y no se puede eliminar',
            ]);
        }

        $pedido->estado = 'eliminado';
        $pedido->USERID_cancelado = $user->USERID;
        $pedido->fecha_hora_cancelado = date('Y-m-d H:i:s');
        $pedido->save();

        GpTrasladosVentas::where('pedido_id', $pedido->id)->delete();
        GpOrdenDespacho::where('pedido_id', $pedido->id)->update(['estado' => 'cancelada']);

        return response([
            'success' => true,
            'message' => 'Pedido ' . $pedido->pedido . ' eliminado',
        ]);
    }

    public function recalcularpedidourl()
    {
        $pedido = GpPedido::where('id', 115242)->get()->first();

        $detalle = GpPedidoDetalle::where('pedido_id', $pedido->id)->get();
        $subtotal = 0;
        $descuento = 0;
        $subtotalmenosdescuento = 0;
        $iva = 0;
        foreach ($detalle as $articulo) {
            $subtotal += $articulo->ART_VALOR * $articulo->ART_CANTIDAD;
            $descuento += number_format($articulo->ART_DESCUENTO * $articulo->ART_CANTIDAD, 2, '.', '');
            $subtotalmenosdescuento += number_format(($articulo->ART_VALOR - $articulo->ART_DESCUENTO) * $articulo->ART_CANTIDAD, 2, '.', '');
            $iva += number_format((($articulo->ART_VALOR - ($articulo->ART_VALOR * ($articulo->descuento / 100))) * $articulo->ART_CANTIDAD) * ($articulo->ART_IMP_VTA / 100), 2, '.', '');
        }
        $subtotalmenosdescuento = number_format($subtotalmenosdescuento, 2, '.', '');
        $iva = number_format($iva, 2, '.', '');
        $total = $subtotalmenosdescuento + $iva;

        $pedido->subtotal = $subtotal;
        $pedido->descuento = $descuento;
        $pedido->subtotalmenosdescuento = $subtotalmenosdescuento;
        $pedido->iva = $iva;
        $pedido->total = $total;


        $pedido->save();
    }

    //    public function transferirtonglin(){
    //
    //        $webservices = new WebserviceController();
    //        $parameters = array(
    //            'IDCompania' => env('ID_COMPANIA'),
    //            'Fecha' => date('Y/m/d'),
    //            'Item' => '000-001060-L',
    //            'Bodega' => 'INMACONSA',
    //            'Cantidad' => 50,
    //            'BinOrigen' => 'PED00117106',
    //            'BinDestino' => 'PED00117030',
    //        );
    //        $webservices->inventario('TransferenciaCompartimento', $parameters);
    //
    //        $parameters = array(
    //            'IDCompania' => env('ID_COMPANIA'),
    //            'Fecha' => date('Y/m/d'),
    //            'Item' => '000-001061-L',
    //            'Bodega' => 'INMACONSA',
    //            'Cantidad' => 10,
    //            'BinOrigen' => 'PED00117106',
    //            'BinDestino' => 'PED00117030',
    //        );
    //        $webservices->inventario('TransferenciaCompartimento', $parameters);
    //    }
    /**
     * @param mixed $pedido
     * @param WebserviceController $webservices
     * @return void
     */
    public function respuestaFacturaVenta(mixed $pedido, WebserviceController $webservices): void
    {
        $parameters = array(
            'documento_origen' => $pedido->pedido,
            'tipo_documento' => 3,
        );
        $result = $webservices->transaccional('MST_Respuesta_Factura_Vta', $parameters);

        if (
            isset($result['MST_Respuesta_Factura_VtaResult']['CS_RESPUESTA_FACTURA'][0]['DOC_GENERADO'])
            && $result['MST_Respuesta_Factura_VtaResult']['CS_RESPUESTA_FACTURA'][0]['DOC_GENERADO'] != ''
        ) {
            $pedido->factura = $result['MST_Respuesta_Factura_VtaResult']['CS_RESPUESTA_FACTURA'][0]['DOC_GENERADO'];
            $pedido->FAC_VTA_MENSAJE = $result['MST_Respuesta_Factura_VtaResult']['CS_RESPUESTA_FACTURA'][0]['FAC_VTA_MENSAJE'];
            $pedido->estado = 'facturado';
            $pedido->fecha_factura = date('Y-m-d');
            $pedido->fecha_hora_factura = date('Y-m-d H:i:s');
            $pedido->save();
        } else {
            if (
                isset($result['MST_Respuesta_Factura_VtaResult']['CS_RESPUESTA_FACTURA'][0]['FAC_VTA_MENSAJE'])
                && $result['MST_Respuesta_Factura_VtaResult']['CS_RESPUESTA_FACTURA'][0]['FAC_VTA_MENSAJE'] != ''
            )
                $pedido->FAC_VTA_MENSAJE = $result['MST_Respuesta_Factura_VtaResult']['CS_RESPUESTA_FACTURA'][0]['FAC_VTA_MENSAJE'];
            else
                $pedido->FAC_VTA_MENSAJE = 'Error no definido por GP';
            $pedido->estado = 'espera';
            $pedido->save();
        }
    }

    public function getListPedidosSinCoordenadas()
    {
        return $this->orderService->getLisOrdersWithoutCoordinated();
    }
}
