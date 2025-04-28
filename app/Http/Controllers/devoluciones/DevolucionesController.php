<?php

namespace App\Http\Controllers\devoluciones;

use App\Http\Controllers\bodegas\DespachosController;
use App\Http\Controllers\bodegas\IngresosController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ventas\AprobacionesController;
use App\Http\Controllers\ventas\PedidosController;
use App\Http\Controllers\WebserviceController;
use App\Models\despachos\GpGuiaRemisionDetalle;
use App\Models\devoluciones\GpDevoluciones;
use App\Models\devoluciones\GpDevolucionesDetalle;
use App\Models\devoluciones\GpFacturaAnulada;
use App\Models\GpCliente;
use App\Models\GpClienteSucursal;
use App\Models\GpCronLog;
use App\Models\GpMotivosDevolucion;
use App\Models\GpOrdenDespacho;
use App\Models\GpPedido;
use App\Models\GpPedidoDetalle;
use App\Models\GpPedidoDetalleStock;
use App\Models\GpPuntoVenta;
use App\Models\GpRelacionPuntoVentaBodega;
use App\Models\GpRelacionPuntoVentaIdVenta;
use App\Models\GpVendedor;
use App\Services\Api\ApiGpService;
use App\Services\Api\ApiSalesService;
use App\Services\Api\ApiSeedBellingService;
use App\Services\Devoluciones\Contracts\DevolucionServiceInterface;
use App\Services\Devoluciones\Contracts\EntryServiceInterface;
use App\Services\Devoluciones\RegisterDocumentTypeService;
use App\Traits\Devoluciones\RegisterDocumentTypeTrait;
use App\Traits\JsonResponseTrait;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DevolucionesController extends Controller
{
    use JsonResponseTrait;
    protected $entryService;
    protected $apiSales;
    protected $apiSeedBelling;
    protected $devolucionService;
    public function __construct(ApiSalesService $apiSales, ApiSeedBellingService $apiSeedBelling, EntryServiceInterface $entryService, DevolucionServiceInterface $devolucionServiceInterface)
    {
        $this->entryService = $entryService;
        $this->apiSales = $apiSales;
        $this->apiSeedBelling = $apiSeedBelling;
        $this->devolucionService = $devolucionServiceInterface;
    }

    public function pedidoscliente($cliente_id, $puntodeventa_id): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $pedidos = GpPedido::where('gp_pedidos.PTO_VTA_ID', $puntodeventa_id)
            ->where('cliente_id', $cliente_id)
            ->where('estado', 'facturado')
            ->orderBy('gp_pedidos.created_at', 'desc')->limit(10000)->get();

        foreach ($pedidos as $pedido) {
            $pedido->direccion_entrega = GpClienteSucursal::where('id', $pedido->sucursal_id)->first();

            $pedido->orden_despacho = false;
            $orden_despacho = GpOrdenDespacho::where('pedido_id', $pedido->id)
                ->where('estado', '!=', 'pendiente')
                ->first();
            if ($orden_despacho)
                $pedido->orden_despacho = true;
        }

        $devoluciones = GpDevoluciones::where('cliente_id', $cliente_id)
            ->orderBy('gp_devoluciones.created_at', 'desc')
            ->get();

        foreach ($devoluciones as $devolucion) {

            $devolucion->orden_despacho = false;
            $orden_despacho = GpOrdenDespacho::where('pedido_id', $devolucion->pedido_id)
                ->where('estado', '!=', 'pendiente')
                ->first();
            if ($orden_despacho)
                $devolucion->orden_despacho = true;

            $devolucion->cliente = GpCliente::where('id', $devolucion->cliente_id)->first();

            if ($devolucion->volver_a_facturar) {
                $devolucion->cliente_volver_a_facturar = GpCliente::where('id', $devolucion->cliente_volver_a_facturar_id)->first();
                $devolucion->sucursal_volver_a_facturar = GpClienteSucursal::where('id', $devolucion->sucursal_facturacion_id)->first();
            }
        }
        //test
        return response([
            'success' => true,
            'pedidos' => $pedidos,
            'devoluciones' => $devoluciones,
        ]);
    }

    public function detallepedidodevolucion(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $data = $request->all();

        $devolucion_en_proceso = GpDevoluciones::where('pedido_id', $data['id'])
            ->where('estado', '!=', 'procesado')
            ->where('estado', '!=', 'rechazado')
            ->first();
        if ($devolucion_en_proceso) {
            return response([
                'error' => 'Esta factura tiene una devolución en proceso'
            ], 401);
        }

        $pedido = GpPedido::where('id', $data['id'])->first();
        if ($pedido->despacho_en_proceso && !$pedido->despachado) {
            return response([
                'error' => 'Este pedido está siendo despachado'
            ], 401);
        }

        $detalle = GpPedidoDetalle::where('pedido_id', $data['id'])->get()->toArray();
        $motivos = GpMotivosDevolucion::get();

        $clientes = GpCliente::where('ID', $data['cliente_id'])->get();

        $parameters = array(
            'id_cliente' => $clientes[0]->CLI_RUC,
        );
        $webservices = new WebserviceController();
        $grupo = $webservices->maestros('MST_Cliente_Grupo_Economico', $parameters);

        $clientes = array();

        foreach ($grupo['MST_Cliente_Grupo_EconomicoResult']['CS_CLIENTE_GRUPO_ECONOMICO'] as $grupo_economico) {
            $clientes[] = GpCliente::where('CLI_RUC', $grupo_economico['CGE_RUC'])->first();
        }

        foreach ($clientes as $cliente) {
            $sucursales = GpClienteSucursal::join('gp_vendedors', 'gp_vendedors.VEND_ID', 'gp_cliente_sucursals.CLI_SUC_VENDEDOR')
                ->where('CLI_SUC_RUC', $cliente->CLI_RUC)
                ->select('gp_cliente_sucursals.*', 'gp_vendedors.VEND_NOMBRES', 'gp_vendedors.VEND_APELLIDOS')
                ->orderby('CLI_SUC_ID_DIRECCION', 'DESC')
                ->get();
            $cliente->sucursales = $sucursales;
        }

        $arr_detalle = array();

        foreach ($detalle as $det) {

            if (!$det['reservado'])
                $det['reservado'] = 0;

            if (!$det['devuelto'])
                $det['devuelto'] = 0;


            $det['seleccionado'] = 0;

            $det['despacho'] = false;

            $det['despachado'] = GpGuiaRemisionDetalle::join('gp_guia_remisions', 'gp_guia_remisions.id', 'gp_guia_remision_detalles.guia_remision_id')
                ->where('gp_guia_remision_detalles.detalle_id', $det['id'])
                ->where('gp_guia_remisions.estado', 'enviada')
                ->where('gp_guia_remisions.tipo', 'despacho')
                ->sum('gp_guia_remision_detalles.GUI_REM_CANTIDAD');

            //            if($pedido->despachado && $det['despachado'] == 0) {
            //                $det['despachado'] = $det['ART_CANTIDAD'];
            //            }else{
            //                $arr_detalle[] = $det;
            //            }

            $arr_detalle[] = $det;

            if ($det['despachado'] > 0) {
                $det['despacho'] = true;
                $arr_detalle[] = $det;
            }
        }
        //        echo '<pre>';
        //        print_r($arr_detalle);
        //        print_r($pedido);
        $pedido_origen = null;
        if (($pedido->factura_origen && $pedido->despachado)) {
            $pedido_origen = GpPedido::where('factura', $pedido->factura_origen)->first();
        }
        //        print_r($pedido_origen);
        //        die();

        foreach ($arr_detalle as $key => &$det) {

            if ($det['despacho'] || $pedido_origen) {

                $devuelto = GpDevolucionesDetalle::join('gp_devoluciones', 'gp_devoluciones.id', 'gp_devoluciones_detalles.devolucion_id')
                    ->where('gp_devoluciones.despachado', 1)
                    ->where('gp_devoluciones_detalles.detalle_id', $det['id'])
                    ->sum('gp_devoluciones_detalles.devuelto');

                $det['devuelto'] = $devuelto;
                $det['disponible'] = $det['despachado'] - $devuelto;
                if ($pedido_origen && $pedido_origen->despachado) {
                    $det['disponible'] = $det['ART_CANTIDAD'] - $det['despachado'] - $devuelto;
                }
                if ($pedido_origen && !$pedido_origen->despachado && !$det['despacho']) {
                    $det['disponible'] = 0;
                }
                //$det['ART_CANTIDAD'] -= $det['despachado'];
            } else {
                //$devuelto = 0;
                $devuelto = GpDevolucionesDetalle::join('gp_devoluciones', 'gp_devoluciones.id', 'gp_devoluciones_detalles.devolucion_id')
                    ->where(function ($q) {
                        $q->where('gp_devoluciones.despachado', 0);
                        $q->orWhereNull('gp_devoluciones.despachado');
                    })
                    ->where('gp_devoluciones_detalles.detalle_id', $det['id']);
                //$sql = $devuelto->toSql();
                $devuelto = $devuelto->sum('gp_devoluciones_detalles.devuelto');
                $det['devuelto'] = $devuelto;
                $det['disponible'] = $det['ART_CANTIDAD'] - $det['despachado'] - $devuelto;

                //                $det['ART_CANTIDAD'] -= $det['despachado'];
            }


            if ($det['disponible'] <= 0) {
                unset($arr_detalle[$key]);
            }
        }
        //        echo '<pre>';
        //        print_r($arr_detalle);
        //        die();
        $arr_detalle = array_values($arr_detalle);

        $anterior = false;
        $devoluciones = GpDevoluciones::where('pedido_id', $data['id'])
            ->where('estado', '!=', 'rechazado')
            ->first();
        if ($devoluciones)
            $anterior = true;

        return response([
            'success' => true,
            'detalle' => $arr_detalle,
            'motivos' => $motivos,
            'clientes' => $clientes,
            'devolucion_anterior' => $anterior,
        ]);
    }

    public function enviardevolucion(Request $request)
    {
        try {
            $data = $request->all();
            DB::beginTransaction();
            $validateAnulationInvoice = $this->validateInvoiceAnulation($data['pedido']);
            if ($validateAnulationInvoice) {
                $anulacionResponse =  $this->anularDevolucion($data['pedido']);
                $responseArray = $anulacionResponse->getData(true);
                if ($responseArray['success'] == false) {
                    return $this->buildErrorResponse($responseArray['error'], 401);
                }
            }

            $pedido = GpPedido::where('id', $data['pedido']['id'])->first();
            if ($pedido->despacho_en_proceso && !$pedido->despachado) {
                return response([
                    'message' => 'Este pedido está siendo despachado'
                ], 401);
            }

            if (!isset($data['pedido']['id'])) {
                return response([
                    'message' => 'Hubo un error al guardar'
                ], 401);
            }

            $arr_despachados = array();
            $arr_sindespacho = array();

            foreach ($data['detallepedido'] as $detalle) {

                if ($data['devolucion']['volver_a_facturar']) {
                    if ($detalle['despacho']) {
                        $arr_despachados['detallepedido'][] = $detalle;
                    } else {
                        $arr_sindespacho['detallepedido'][] = $detalle;
                    }
                } else {
                    if ($detalle['seleccionado'] > 0) {
                        if ($detalle['despacho']) {
                            $arr_despachados['detallepedido'][] = $detalle;
                        } else {
                            $arr_sindespacho['detallepedido'][] = $detalle;
                        }
                    }
                }
            }

            $devolucion = '';
            if (isset($arr_despachados['detallepedido']) && count($arr_despachados['detallepedido'])) {
                $arr_despachados['devolucion'] = $data['devolucion'];
                $arr_despachados['pedido'] = $data['pedido'];
                $arr_despachados['pedido']['despachado'] = 1;
                $devolucion .= $this->ejecutardevolucion($arr_despachados);
            }
            if (isset($arr_sindespacho['detallepedido']) && count($arr_sindespacho['detallepedido'])) {
                $arr_sindespacho['devolucion'] = $data['devolucion'];
                $arr_sindespacho['pedido'] = $data['pedido'];
                $devolucion .= $this->ejecutardevolucion($arr_sindespacho);
            }

            $despachos = new DespachosController();
            $despachos->checkeliminardespacho($pedido);
            $this->updateStatusDevolutions($pedido);
            $this->updateStatusOrders($pedido);
            DB::commit();
            return $this->buildSuccessResponse('La devolución ha sido solicitada', 200);
        } catch (\Throwable $th) {
            return $this->buildErrorResponse($th->getMessage(), 401);
        }
    }

    public function checkeliminardespachourl()
    {

        //        $pedido = GpPedido::where('id', 1115)->first();
        //        $despachos = new DespachosController();
        //        $despachos->checkeliminardespacho($pedido);
    }

    public function ejecutardevolucion($data): string
    {
        $user = Auth::user();
        $aprobaciones = new AprobacionesController;

        $requiere_aprobacion = $aprobaciones->checkAprobacionesDevoluciones($data);

        $estado = 'solicitado';
        if ($requiere_aprobacion == 'yes') {
            $estado = 'espera';
        }

        $devolucion = new GpDevoluciones;
        $ultimaDevolucion = GpDevoluciones::orderBy('id', 'DESC')->first();

        if (!$ultimaDevolucion) {
            $ultimaDevolucion = new \stdClass();
            $ultimaDevolucion->id = 0;
        }

        $devolucion->devolucion = 'DEV' . str_pad($ultimaDevolucion->id + 1, 8, "0", STR_PAD_LEFT);
        $devolucion->estado = $estado;
        $devolucion->pedido_id = $data['pedido']['id'];
        $devolucion->cliente_id = $data['pedido']['cliente_id'];
        $devolucion->pedido = $data['pedido']['pedido'];
        $devolucion->factura = $data['pedido']['factura'];
        $devolucion->volver_a_facturar = $data['devolucion']['volver_a_facturar'];
        $devolucion->tipo_devolucion = $data['devolucion']['tipo_devolucion'];
        $devolucion->despachado = $data['pedido']['despachado'];
        $devolucion->cliente_volver_a_facturar_id = $data['devolucion']['cliente_volver_a_facturar_id'];
        $devolucion->user_id = $user->id;
        $devolucion->USERID = $user->USERID;
        $devolucion->sucursal_id = $data['devolucion']['sucursal_id'];
        $devolucion->sucursal_facturacion_id = $data['devolucion']['sucursal_facturacion_id'];
        $devolucion->motivo = $data['devolucion']['motivo'];
        $devolucion->orden_de_compra = $data['pedido']['orden_de_compra'];
        $devolucion->requiere_aprobacion = $requiere_aprobacion;
        $devolucion->aprobado = 'no';
        $devolucion->impuesto = $data['pedido']['impuesto'];
        $devolucion->observacion = $data['devolucion']['observacion'];
        $devolucion->save();

        foreach ($data['detallepedido'] as $det) {

            //            if($det['seleccionado'] == 0)
            //                continue;

            $detalle = new GpDevolucionesDetalle();
            $detalle->devolucion_id = $devolucion->id;
            $detalle->detalle_id = $det['id'];
            $detalle->ART_CODIGO = $det['ART_CODIGO'];
            $detalle->ART_DESCRIPCION = $det['ART_DESCRIPCION'];
            $detalle->ART_DESCRIPCION_ORIGINAL = $det['ART_DESCRIPCION_ORIGINAL'];
            $detalle->ART_CODIGO_TRES_LETRAS = $det['ART_CODIGO_TRES_LETRAS'];
            $detalle->ART_DECIMAL_CANTIDAD = (int)$det['ART_DECIMAL_CANTIDAD'];
            $detalle->ART_PLAN_UMEDIDA = $det['ART_PLAN_UMEDIDA'];
            $detalle->ART_MARCA = $det['ART_MARCA'];
            $detalle->ART_MATERIAL = $det['ART_MATERIAL'];
            $detalle->ART_PESO = $det['ART_PESO'];
            $detalle->ART_TIPO_COMPRA = $det['ART_TIPO_COMPRA'];
            $detalle->ART_VALOR = (float)$det['ART_VALOR'];
            $detalle->ART_IVA = (float)$det['ART_IVA'];
            $detalle->ART_VALOR_ORIGINAL = (float)$det['ART_VALOR_ORIGINAL'];
            $detalle->ART_DESCUENTO = (float)$det['ART_DESCUENTO'];
            $detalle->ART_CANTIDAD = (float)$det['ART_CANTIDAD'];
            $detalle->ART_STOCK = (float)$det['ART_STOCK'];
            $detalle->ART_IMP_VTA = (float)$det['ART_IMP_VTA'];
            $detalle->ART_COSTO_PROMEDIO = (float)$det['ART_COSTO_PROMEDIO'];
            $detalle->descuento = (float)$det['descuento'];
            $detalle->descuento_original = (float)$det['descuento_original'];
            $detalle->modificador = (float)$det['modificador'];
            $detalle->seleccionado = (float)$det['seleccionado'];
            $detalle->disponible = (float)$det['disponible'];
            $detalle->save();
        }

        // if ($estado == 'solicitado') {
        //     $this->procesardevoluciones();
        // }

        return 'La devolución ' . $devolucion->devolucion . ' ha sido solicitada. ';
    }

    public function detalledevolucion(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $data = $request->all();

        $webservices = new WebserviceController();

        $pedido = GpPedido::where('id', $data['pedido_id'])->first();

        $detalle = GpDevolucionesDetalle::where('devolucion_id', $data['id'])->get()->toArray();

        foreach ($detalle as $key => &$item) {
            if ($item['seleccionado'] == 0 && $item['devuelto'] == 0) {
                unset($detalle[$key]);
            } else {
                $parameters = array(
                    'IDCompania' => env('ID_COMPANIA'),
                    'Bodega' => $pedido->BOD_BODEGA,
                    'Item' => $item['ART_CODIGO'],
                );
                $respuesta = $webservices->inventario('ConsultaItemCompartimento', $parameters);
                //                echo '<pre>';
                //                print_r($parameters);
                //                print_r($respuesta['ConsultaItemCompartimentoResult']);
                //                die();
                $item['compartimientos'] = $respuesta['ConsultaItemCompartimentoResult']['Items_Compartimento'];
                $item['compartimiento'] = $respuesta['ConsultaItemCompartimentoResult']['Items_Compartimento'][0]['Compartimento'];
            }
        }

        $detalle = array_values($detalle);

        return response([
            'success' => true,
            'detalle' => $detalle,
        ]);
    }

    public function  procesardevoluciones()
    {
        //1
        //Procesar devoluciones totales sin despacho y sin refacturacion
        $devoluciones = GpDevoluciones::join('gp_pedidos', 'gp_pedidos.id', 'gp_devoluciones.pedido_id')
            ->where('gp_devoluciones.estado', 'solicitado')
            ->where(function ($q) {
                $q->where('gp_devoluciones.despachado', 0);
                $q->orWhereNull('gp_devoluciones.despachado');
            })
            ->whereNull('procesando')
            ->where('tipo_devolucion', 'total')
            ->where('volver_a_facturar', 0)
            ->select('gp_devoluciones.*', 'gp_pedidos.PTO_VTA_ID')
            ->get();



        foreach ($devoluciones as $devolucion) {
            $devolucion->procesando = 1;
            $devolucion->save();
        }

        foreach ($devoluciones as $devolucion) {
            $this->procesardevoluciontotalsinrf($devolucion);
            $continua = $this->generarnotadecredito($devolucion);
            if ($continua) {
                GpOrdenDespacho::where('pedido_id', $devolucion->pedido_id)->update(['estado' => 'cancelada']);
            }
        }
        foreach ($devoluciones as $devolucion) {
            $devolucion->procesando = null;
            $devolucion->save();
        }

        //3
        //Procesar devoluciones totales sin despacho y sin refacturacion aprobadas
        $devoluciones = GpDevoluciones::join('gp_pedidos', 'gp_pedidos.id', 'gp_devoluciones.pedido_id')
            ->where('gp_devoluciones.estado', 'aprobado')
            ->where(function ($q) {
                $q->where('gp_devoluciones.despachado', 0);
                $q->orWhereNull('gp_devoluciones.despachado');
            })
            ->whereNull('procesando')
            ->where('tipo_devolucion', 'total')
            ->where('volver_a_facturar', 0)
            ->select('gp_devoluciones.*', 'gp_pedidos.PTO_VTA_ID')
            ->get();
        foreach ($devoluciones as $devolucion) {
            $devolucion->procesando = 1;
            $devolucion->save();
        }
        foreach ($devoluciones as $devolucion) {
            $this->procesardevoluciontotalsinrf($devolucion);
            $continua = $this->generarnotadecredito($devolucion);
            if ($continua) {
                GpOrdenDespacho::where('pedido_id', $devolucion->pedido_id)->update(['estado' => 'cancelada']);
            }
        }
        foreach ($devoluciones as $devolucion) {
            $devolucion->procesando = null;
            $devolucion->save();
        }

        //2
        //Procesar devoluciones parciales sin despacho y sin refacturacion
        $devoluciones = GpDevoluciones::join('gp_pedidos', 'gp_pedidos.id', 'gp_devoluciones.pedido_id')
            ->where('gp_devoluciones.estado', 'solicitado')
            ->where(function ($q) {
                $q->where('gp_devoluciones.despachado', 0);
                $q->orWhereNull('gp_devoluciones.despachado');
            })
            ->whereNull('procesando')
            ->where('tipo_devolucion', 'parcial')
            ->where('volver_a_facturar', 0)
            ->select('gp_devoluciones.*', 'gp_pedidos.PTO_VTA_ID')
            ->get();

        foreach ($devoluciones as $devolucion) {
            $devolucion->procesando = 1;
            $devolucion->save();
        }
        foreach ($devoluciones as $devolucion) {
            $this->procesardevoluciontotalsinrf($devolucion);
            $this->generarnotadecredito($devolucion);
        }
        foreach ($devoluciones as $devolucion) {
            $devolucion->procesando = null;
            $devolucion->save();
        }

        //6
        //Procesar devoluciones parciales sin despacho y sin  aprobados
        $devoluciones = GpDevoluciones::join('gp_pedidos', 'gp_pedidos.id', 'gp_devoluciones.pedido_id')
            ->where('gp_devoluciones.estado', 'aprobado')
            ->where(function ($q) {
                $q->where('gp_devoluciones.despachado', 0);
                $q->orWhereNull('gp_devoluciones.despachado');
            })
            ->where('tipo_devolucion', 'parcial')
            ->where('volver_a_facturar', 0)
            ->select('gp_devoluciones.*', 'gp_pedidos.PTO_VTA_ID')
            ->get();
        foreach ($devoluciones as $devolucion) {
            $devolucion->procesando = 1;
            $devolucion->save();
        }
        foreach ($devoluciones as $devolucion) {
            $this->procesardevoluciontotalsinrf($devolucion);
            $this->generarnotadecredito($devolucion);
        }
        foreach ($devoluciones as $devolucion) {
            $devolucion->procesando = null;
            $devolucion->save();
        }

        //4
        //Procesar devoluciones totales, sin despacho, aprobados con refacturacion
        $devoluciones = GpDevoluciones::join('gp_pedidos', 'gp_pedidos.id', 'gp_devoluciones.pedido_id')
            ->where('gp_devoluciones.estado', 'aprobado')
            ->where(function ($q) {
                $q->where('gp_devoluciones.despachado', 0);
                $q->orWhereNull('gp_devoluciones.despachado');
            })
            ->whereNull('procesando')
            ->where('tipo_devolucion', 'total')
            ->where('volver_a_facturar', 1)
            ->select('gp_devoluciones.*', 'gp_pedidos.PTO_VTA_ID')
            ->get();
        $apiGp = app(ApiGpService::class);
        $pedidos = new PedidosController($apiGp);
        foreach ($devoluciones as $devolucion) {
            $devolucion->procesando = 1;
            $devolucion->save();
        }
        foreach ($devoluciones as $devolucion) {
            $this->procesardevoluciontotalsinrf($devolucion);
            $continua = $this->generarnotadecredito($devolucion);

            if ($continua) {
                $pedidos->volverafacturar($devolucion);
                GpOrdenDespacho::where('pedido_id', $devolucion->pedido_id)->update(['estado' => 'cancelada']);
            }
        }
        foreach ($devoluciones as $devolucion) {
            $devolucion->procesando = null;
            $devolucion->save();
        }
        //7
        //Procesar devoluciones parciales, sin despacho, aprobados con refacturacion
        $devoluciones = GpDevoluciones::join('gp_pedidos', 'gp_pedidos.id', 'gp_devoluciones.pedido_id')
            ->where('gp_devoluciones.estado', 'aprobado')
            ->where(function ($q) {
                $q->where('gp_devoluciones.despachado', 0);
                $q->orWhereNull('gp_devoluciones.despachado');
            })
            ->whereNull('procesando')
            ->where('tipo_devolucion', 'parcial')
            ->where('volver_a_facturar', 1)
            ->select('gp_devoluciones.*', 'gp_pedidos.PTO_VTA_ID')
            ->get();
        foreach ($devoluciones as $devolucion) {
            $devolucion->procesando = 1;
            $devolucion->save();
        }
        foreach ($devoluciones as $devolucion) {
            $this->procesardevolucionparcialconrf($devolucion);
            $continua = $this->generarnotadecredito($devolucion);
            if ($continua) {
                $pedidos->volverafacturar($devolucion);
                GpOrdenDespacho::where('pedido_id', $devolucion->pedido_id)->update(['estado' => 'cancelada']);
            }
        }
        foreach ($devoluciones as $devolucion) {
            $devolucion->procesando = null;
            $devolucion->save();
        }
        //9
        //Procesar devoluciones despachadas sin refacturacion
        $devoluciones = GpDevoluciones::join('gp_pedidos', 'gp_pedidos.id', 'gp_devoluciones.pedido_id')
            ->where('gp_devoluciones.estado', 'recibido')
            ->where('gp_devoluciones.despachado', 1)
            ->where('volver_a_facturar', 0)
            ->whereNull('procesando')
            ->select('gp_devoluciones.*', 'gp_pedidos.PTO_VTA_ID')
            ->get();

        //        echo '<pre>';
        //        print_r($devoluciones);
        //        die();

        foreach ($devoluciones as $devolucion) {
            $devolucion->procesando = 1;
            $devolucion->save();
        }
        foreach ($devoluciones as $devolucion) {
            $continua = $this->generarnotadecredito($devolucion);
            if ($continua) {
                $this->generadevolucionventa($devolucion);
            }
        }
        foreach ($devoluciones as $devolucion) {
            $devolucion->procesando = null;
            $devolucion->save();
        }
        //10
        //Procesar devoluciones despachadas parciales recibidas en bodega con refacturacion
        $devoluciones = GpDevoluciones::join('gp_pedidos', 'gp_pedidos.id', 'gp_devoluciones.pedido_id')
            ->where('gp_devoluciones.estado', 'recibido')
            ->where('gp_devoluciones.despachado', 1)
            ->where('volver_a_facturar', 1)
            ->where('tipo_devolucion', 'parcial')
            ->whereNull('procesando')
            ->select('gp_devoluciones.*', 'gp_pedidos.PTO_VTA_ID')
            ->get();
        foreach ($devoluciones as $devolucion) {
            $devolucion->procesando = 1;
            $devolucion->save();
        }
        foreach ($devoluciones as $devolucion) {
            $this->procesardevolucionparcialconrf($devolucion);
            $continua = $this->generarnotadecredito($devolucion);
            if ($continua) {
                $pedidos->volverafacturar($devolucion, true);
                $this->generadevolucionventa($devolucion);
            }
        }
        foreach ($devoluciones as $devolucion) {
            $devolucion->procesando = null;
            $devolucion->save();
        }
        //5
        //Procesar devoluciones despachadas totales aprobadas con refacturacion
        $devoluciones = GpDevoluciones::join('gp_pedidos', 'gp_pedidos.id', 'gp_devoluciones.pedido_id')
            ->where('gp_devoluciones.estado', 'aprobado')
            ->where('gp_devoluciones.despachado', 1)
            ->where('volver_a_facturar', 1)
            ->where('tipo_devolucion', 'total')
            ->whereNull('procesando')
            ->select('gp_devoluciones.*', 'gp_pedidos.PTO_VTA_ID')
            ->get();
        foreach ($devoluciones as $devolucion) {
            $devolucion->procesando = 1;
            $devolucion->save();
        }
        foreach ($devoluciones as $devolucion) {
            $this->procesardevolucionparcialconrf($devolucion);
            $continua = $this->generarnotadecredito($devolucion);
            if ($continua) {
                $pedidos->volverafacturar($devolucion, true);
            }
        }
        foreach ($devoluciones as $devolucion) {
            $devolucion->procesando = null;
            $devolucion->save();
        }
        //Por si algun envío no se realizó
        $this->generadevolucionventaurl();
        return;
    }

    public function procesardevolucionestest()
    {

        // $pedidos = new PedidosController();

        //Procesar devoluciones despachadas sin refacturacion
        $devoluciones = GpDevoluciones::join('gp_pedidos', 'gp_pedidos.id', 'gp_devoluciones.pedido_id')
            ->where('gp_devoluciones.estado', 'recibido')
            ->where('gp_devoluciones.despachado', 1)
            ->where('volver_a_facturar', 0)
            ->whereNull('procesando')
            ->select('gp_devoluciones.*', 'gp_pedidos.PTO_VTA_ID')
            ->get();

        echo '<pre>';
        print_r($devoluciones);
        //        die();

        foreach ($devoluciones as $devolucion) {
            $devolucion->procesando = 1;
            $devolucion->save();
        }
        foreach ($devoluciones as $devolucion) {
            $continua = $this->generarnotadecredito($devolucion);
            if ($continua) {
                $this->generadevolucionventa($devolucion);
            }
        }
        foreach ($devoluciones as $devolucion) {
            $devolucion->procesando = null;
            $devolucion->save();
        }
    }

    public function volverafacturartesturl($id)
    {
        $devolucion = GpDevoluciones::where('id', $id)->first();

        $apiGp = app(ApiGpService::class);
        $pedidos = new PedidosController($apiGp);
        $pedidos->volverafacturar($devolucion, false);
    }

    public function generadevolucionventa($devolucion)
    {
        $user = Auth::user();

        if (!isset($user->USERID)) {
            $user = new \stdClass();
            $user->USERID = 'adminWeb';
        }
        $detalles = GpDevolucionesDetalle::where('devolucion_id', $devolucion->id)->get();
        $pedido = GpPedido::where('id', $devolucion->pedido_id)->first();
        $Items_Devolucion = array();
        foreach ($detalles as $detalle) {

            if ($detalle->ART_TIPO_COMPRA == 'SERV')
                continue;

            $devuelto = $detalle->devuelto;
            if ($devolucion->despachado && $devolucion->volver_a_facturar)
                $devuelto = $detalle->recibido;

            $compartimiento = $detalle->compartimiento;
            if ($detalle->ART_TIPO_COMPRA == 'L')
                $compartimiento = $pedido->pedido;

            if ($devuelto) {
                $Items_Devolucion[] = [
                    'Item' => $detalle->ART_CODIGO,
                    'Cantidad' => $devuelto,
                    'Compartimento' => $compartimiento,
                ];
            }
        }

        if (count($Items_Devolucion)) {
            $parameters = array(
                'IDCompania' => env('ID_COMPANIA'),
                'Documento' => $devolucion->devolucion,
                'PedidoOrigen' => $pedido->pedido,
                'Bodega' => $pedido->BOD_BODEGA,
                'Observacion' => $devolucion->DOC_GENERADO,
                'Usuario' => $user->USERID,
                'Items_Devolucion' => $Items_Devolucion,
            );

            $webservices = new WebserviceController();
            $respuesta = $webservices->inventario('GeneraDevolucionVenta', $parameters);
            //            print_r($respuesta);

            if (
                isset($respuesta['GeneraDevolucionVentaResult']['Retorno'][0]['Codigo']) &&
                $respuesta['GeneraDevolucionVentaResult']['Retorno'][0]['Codigo'] == 0
            ) {
                $devolucion->devolucion_bodega = 1;
                $devolucion->save();
            } else {
                $devolucion->AJ_VTA_MENSAJE = $respuesta['GeneraDevolucionVentaResult']['Retorno'][0]['Mensaje'];
                $devolucion->save();
            }
        } else {
            $devolucion->devolucion_bodega = 1;
            $devolucion->save();
        }
    }

    public function generadevolucionventaurl()
    {
        $devoluciones = GpDevoluciones::join('gp_pedidos', 'gp_pedidos.id', 'gp_devoluciones.pedido_id')
            ->where('gp_devoluciones.estado', 'procesado')
            ->where('gp_devoluciones.despachado', 1)
            ->where(function ($q) {
                $q->whereNull('devolucion_bodega');
                $q->orWhere('devolucion_bodega', 0);
            })
            ->select('gp_devoluciones.*', 'gp_pedidos.PTO_VTA_ID')
            ->get();

        foreach ($devoluciones as $key => $devolucion) {
            $this->generadevolucionventa($devolucion);
        }
    }

    public function generadevolucionventaurlalt()
    {
        $devoluciones = GpDevoluciones::join('gp_pedidos', 'gp_pedidos.id', 'gp_devoluciones.pedido_id')
            ->where('gp_devoluciones.estado', 'procesado')
            ->where('gp_devoluciones.despachado', 1)
            ->where('gp_devoluciones.id', 6881)
            ->where(function ($q) {
                $q->whereNull('devolucion_bodega');
                $q->orWhere('devolucion_bodega', 0);
            })
            ->select('gp_devoluciones.*', 'gp_pedidos.PTO_VTA_ID')
            ->get();

        echo '<pre>';
        print_r($devoluciones);

        foreach ($devoluciones as $key => $devolucion) {
            $this->generadevolucionventaalterno($devolucion);
        }
    }

    public function generadevolucionventaalterno($devolucion)
    {
        $user = Auth::user();

        if (!isset($user->USERID)) {
            $user = new \stdClass();
            $user->USERID = 'adminWeb';
        }
        $detalles = GpDevolucionesDetalle::where('devolucion_id', $devolucion->id)->get();
        $pedido = GpPedido::where('id', $devolucion->pedido_id)->first();
        $Items_Devolucion = array();
        foreach ($detalles as $detalle) {

            if ($detalle->ART_TIPO_COMPRA == 'SERV')
                continue;

            $devuelto = $detalle->devuelto;
            if ($devolucion->despachado && $devolucion->volver_a_facturar)
                $devuelto = $detalle->recibido;

            $compartimiento = $detalle->compartimiento;
            if ($detalle->ART_TIPO_COMPRA == 'L')
                $compartimiento = $pedido->pedido;

            if ($devuelto && $devuelto > 0) {
                $Items_Devolucion[] = [
                    'Item' => $detalle->ART_CODIGO,
                    'Cantidad' => $devuelto,
                    'Compartimento' => $compartimiento,
                ];
            }
        }

        if (count($Items_Devolucion)) {
            $parameters = array(
                'IDCompania' => env('ID_COMPANIA'),
                'Documento' => $devolucion->devolucion,
                'PedidoOrigen' => $pedido->pedido,
                'Bodega' => $pedido->BOD_BODEGA,
                'Observacion' => $devolucion->DOC_GENERADO,
                'Usuario' => $user->USERID,
                'Items_Devolucion' => $Items_Devolucion,
            );

            echo '<pre>';
            print_r($parameters);
            $webservices = new WebserviceController();
            $respuesta = $webservices->inventario('GeneraDevolucionVenta', $parameters);
            print_r($respuesta);

            if (
                isset($respuesta['GeneraDevolucionVentaResult']['Retorno'][0]['Codigo']) &&
                $respuesta['GeneraDevolucionVentaResult']['Retorno'][0]['Codigo'] == 0
            ) {
                $devolucion->devolucion_bodega = 1;
                $devolucion->save();
            } else {
                $devolucion->AJ_VTA_MENSAJE = $respuesta['GeneraDevolucionVentaResult']['Retorno'][0]['Mensaje'];
                $devolucion->save();
            }
        } else {
            $devolucion->devolucion_bodega = 1;
            $devolucion->save();
        }

        die('saliendo');
    }

    public function procesardevoluciontotalsinrf($devolucion)
    {
        $detalles = GpDevolucionesDetalle::where('devolucion_id', $devolucion->id)->get();
        foreach ($detalles as $detalle) {
            $detalle->devuelto = $detalle->seleccionado;
            $detalle->save();
        }
    }

    public function procesardevolucionparcialconrf($devolucion)
    {
        $detalles = GpDevolucionesDetalle::where('devolucion_id', $devolucion->id)->get();
        foreach ($detalles as $detalle) {
            $detalle->devuelto = $detalle->disponible;
            $detalle->save();
        }
    }

    public function generarnotadecredito($devolucion): bool
    {

        $return = false;

        $detalles = GpDevolucionesDetalle::where('devolucion_id', $devolucion->id)->get();
        $pedido = GpPedido::where('id', $devolucion->pedido_id)->first();

        $doc_id = GpRelacionPuntoVentaIdVenta::where('PTO_VTA_ID_PUNTO_VENTA', $devolucion->PTO_VTA_ID)
            ->where('PTO_VTA_ID_FACTURA_NC', 'LIKE', 'NC%')->first();

        $cliente = GpCliente::where('id', $devolucion->cliente_id)->first();
        $sucursal = GpClienteSucursal::where('id', $devolucion->sucursal_facturacion_id)->first();

        $subtotal = 0;
        $iva = 0;

        $DetalleDevolucion = array();

        $linea = 0;
        foreach ($detalles as $detalle) {

            if ($detalle->devuelto == 0)
                continue;

            $linea++;
            $DetalleDevolucion[] = array(
                'NC_VTA_DET_LINEA' => $linea,
                'NC_VTA_DET_DOCU_ORIGEN' => $devolucion->devolucion,
                'NC_VTA_DET_ID_TIPO' => '4',
                'NC_VTA_DET_ARTICULO' => $detalle->ART_CODIGO,
                'NC_VTA_DET_DESC_ARTICULO' => $detalle->ART_DESCRIPCION,
                'NC_VTA_DET_UNIDAD_MEDIDA' => $detalle->ART_PLAN_UMEDIDA,
                'NC_VTA_DET_BODEGA' => $pedido->BOD_BODEGA,
                'NC_VTA_DET_CANTIDAD' => $detalle->devuelto,
                'NC_VTA_DET_COSTO_UNITARIO' => $detalle->ART_VALOR,
                'NC_VTA_DET_PRECIO_UNITARIO' => $detalle->ART_VALOR,
                'NC_VTA_DET_DESCUENTO' => $detalle->ART_DESCUENTO,
                'NC_VTA_DET_IMPUESTO_IVA' => $detalle->ART_IVA,
                'NC_VTA_DET_TOTAL' => number_format(($detalle->ART_VALOR - $detalle->ART_DESCUENTO) * $detalle->devuelto, 2, '.', ''),
            );

            $subtotal += (float)number_format(($detalle->ART_VALOR - $detalle->ART_DESCUENTO) * $detalle->devuelto, 2, '.', '');
            $iva += $detalle->ART_IVA;
        }
        $total = $subtotal + $iva;

        $cabecera = array(
            array(
                'NC_VTA_DOCU_ORIGEN' => $devolucion->devolucion,
                'NC_VTA_ID_TIPO' => '4',
                'NC_VTA_PUNTO_VENTA' => $devolucion->PTO_VTA_ID,
                'NC_VTA_DOC_ID' => $doc_id->PTO_VTA_ID_FACTURA_NC,
                'NC_VTA_ID_CLIENTE' => $cliente->CLI_RUC,
                'NC_VTA_ID_VENDEDOR' => $pedido->VEND_ID,
                'NC_VTA_ID_DIRECCION' => $sucursal->CLI_SUC_ID_DIRECCION,
                'NC_VTA_COND_PAGO' => $pedido->COND_ID,
                'NC_VTA_NOTA_DOCU' => $devolucion->factura,
                'NC_VTA_FECHA' => date('Y-m-d'),
                'NC_VTA_BODEGA' =>  $pedido->BOD_BODEGA,
                'NC_VTA_SUBTOTAL' => $subtotal,
                'NC_VTA_DESCUENTO_COMERCIAL' => 0, //$pedido->porcentage_descuento,
                'NC_VTA_IMPUESTO' => $iva,
                'NC_VTA_TOTAL' => $total,
            )
        );

        $parameters = array(
            'Cabecera' => $cabecera,
            'Detalle' => $DetalleDevolucion,
        );

        //        echo '<pre>';
        //        print_r($parameters);
        //        die();
        $webservices = new WebserviceController();
        $result = $webservices->transaccional('MST_NC_VTA', $parameters);

        //        echo '<pre>';
        //        print_r($result);

        //        $cronLog = new GpCronLog();
        //        $cronLog->metodo = 'Ver Devolucion';
        //        $cronLog->fecha = date('Y-m-d H:i:s');
        //        $cronLog->data = json_encode($result);
        //        $cronLog->save();

        if (isset($result['MST_NC_VTAResult'])) {
            $parameters = array(
                'documento_origen' => $devolucion->devolucion,
                'tipo_documento' => 4,
            );


            $result = $webservices->transaccional('MST_Respuesta_Nota_Credito_Vta', $parameters);

            if (
                isset($result['MST_Respuesta_Nota_Credito_VtaResult']['CS_RESPUESTA_NOTA_CREDITO'][0]['DOC_GENERADO'])
                && $result['MST_Respuesta_Nota_Credito_VtaResult']['CS_RESPUESTA_NOTA_CREDITO'][0]['DOC_GENERADO'] != ''
            ) {
                $devolucion->DOC_GENERADO = $result['MST_Respuesta_Nota_Credito_VtaResult']['CS_RESPUESTA_NOTA_CREDITO'][0]['DOC_GENERADO'];
                $devolucion->estado = 'procesado';
                $devolucion->fecha_NC = date('Y-m-d');

                $this->bajardevolucionapedido($devolucion, $detalles, $pedido);
                $return = true;
            } else {
                if (isset($result['MST_Respuesta_Nota_Credito_VtaResult']['CS_RESPUESTA_NOTA_CREDITO'][0]['NC_VTA_MENSAJE']))
                    $devolucion->NC_VTA_MENSAJE = $result['MST_Respuesta_Nota_Credito_VtaResult']['CS_RESPUESTA_NOTA_CREDITO'][0]['NC_VTA_MENSAJE'] . ' - ' . $result['MST_Respuesta_Nota_Credito_VtaResult']['CS_RESPUESTA_NOTA_CREDITO'][0]['NC_VTA_CODIGO'];
            }
            $devolucion->save();
        }

        $this->totaldevoluciones($devolucion, $detalles);

        return $return;
    }

    public function bajardevolucionapedido($devolucion, $detalles, $pedido)
    {

        if (!$devolucion->rebajado) {
            foreach ($detalles as $detalle) {
                $articulo = GpPedidoDetalle::where('pedido_id', $devolucion->pedido_id)
                    ->where('id', $detalle->detalle_id)
                    ->first();

                $articulo->devuelto += $detalle->devuelto;
                $articulo->save();
                if (!$devolucion->despachado) {
                    $articulo_stock = GpPedidoDetalleStock::where('detalle_id', $articulo->id)
                        ->where('STO_ART_BODEGA', $pedido->BOD_BODEGA)
                        ->first();

                    if ($articulo_stock) {
                        $articulo_stock->reservado -= $articulo->devuelto;
                        $articulo_stock->save();
                    }
                }
            }
            $devolucion->rebajado = 1;
            $devolucion->save();
        }
    }

    public function verdevolucionesporrecibir($puntodeventa): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $bodega = GpRelacionPuntoVentaBodega::where('PTO_VTA_BOD_PUNTO_VENTA', $puntodeventa)->first();
        $bodega = $bodega->PTO_VTA_BOD_BODEGA;
        $devoluciones = GpDevoluciones::join('gp_clientes', 'gp_clientes.id', 'gp_devoluciones.cliente_id')
            ->join('gp_pedidos', 'gp_pedidos.id', 'gp_devoluciones.pedido_id')
            ->join('gp_punto_ventas', 'gp_pedidos.PTO_VTA_ID', 'gp_punto_ventas.PTO_VTA_ID')
            ->where(function ($query) {
                $query->where('gp_devoluciones.estado', 'aprobado');
                $query->orWhere('gp_devoluciones.estado', 'solicitado');
            })
            ->where('gp_devoluciones.despachado', 1)
            ->where('gp_pedidos.BOD_BODEGA', $bodega)
            ->select('gp_devoluciones.*', 'gp_clientes.CLI_RAZON_SOCIAL', 'gp_clientes.CLI_RUC', 'gp_punto_ventas.PTO_VTA_DESCRIPCION')
            ->get();

        //        ->where('volver_a_facturar',1)
        //        ->where('tipo_devolucion', 'total')

        foreach ($devoluciones as $key => $devolucion) {

            if ($devolucion->volver_a_facturar == 1 && $devolucion->tipo_devolucion == 'total') {
                unset($devoluciones[$key]);
            }

            $devolucion->orden_despacho = false;
            $orden_despacho = GpOrdenDespacho::where('pedido_id', $devolucion->pedido_id)
                ->where('estado', '!=', 'pendiente')
                ->first();
            if ($orden_despacho)
                $devolucion->orden_despacho = true;

            $devolucion->cliente = GpCliente::where('id', $devolucion->cliente_id)->first();
            if ($devolucion->volver_a_facturar) {
                $devolucion->cliente_volver_a_facturar = GpCliente::where('id', $devolucion->cliente_volver_a_facturar_id)->first();
                $devolucion->sucursal_volver_a_facturar = GpClienteSucursal::where('id', $devolucion->sucursal_facturacion_id)->first();
            }
        }

        $devoluciones = $devoluciones->toArray();
        $devoluciones = array_values($devoluciones);

        return response([
            'success' => true,
            'devoluciones' => $devoluciones,
        ]);
    }

    //    public function bajaratodos(){
    //        $devoluciones = GpDevoluciones::whereNotNull('DOC_GENERADO')->get();
    //        foreach($devoluciones as $devolucion){
    //            $pedido = GpPedido::where('id', $devolucion->pedido_id)->first();
    //            $detalles = GpDevolucionesDetalle::where('devolucion_id', $devolucion->id)->get();
    //            $this->bajardevolucionapedido($devolucion, $detalles, $pedido);
    //        }
    //    }


    //proceso de revision
    public function recibirdevolucion1(Request $request)
    {
        $user = Auth::user();
        $data = $request->all();
        try {
            $devolucion = GpDevoluciones::find($data['devolucion']['id']);
            if (!$devolucion) {
                return $this->buildErrorResponse('devolucion no encontrada',  404);
            }

            if (in_array($devolucion->estado, ['recibido', 'procesado'])) {
                return $this->buildErrorResponse('Esta devolución ya fue recibida en otra instancia', 404);
            }

            DB::transaction(function () use ($data, $user, $devolucion) {
                foreach ($data['detalledevolucion'] as $detalle) {
                    GpDevolucionesDetalle::where('id', $detalle['id'])->update([
                        'devuelto' => $detalle['devuelto'],
                        'recibido' => $detalle['devuelto'],
                        'compartimiento' => $detalle['compartimiento'],
                        'USERID' => $user->USERID
                    ]);
                }
                $devolucion->update(['estado' => 'recibido']);

                $response = $this->devolucionService->processDevolucion($devolucion);
                if (!$response->isSuccessful()) {
                    $responseData = (array) $response->getData();
                    throw new \Exception("Error en el procesamiento: " . ($responseData['error'] ?? 'Proceso fallido'));
                }
                $this->entryService->registerEntity('devolucion', $data['devolucion'], $data['detalledevolucion']);
            });

            return $this->buildSuccessResponse('Devolución recibida',  200);
        } catch (\Throwable $th) {
            Log::error("Error al recibir devolución ID {$data['devolucion']['id']}: {$th->getMessage()}");
            return $this->buildErrorResponse('Error al recibir la devolución ' . $th->getMessage(), 500);
        }
    }

    public function recibirdevolucion(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $user = Auth::user();
        $data = $request->all();



        $devolucion = GpDevoluciones::where('id', $data['devolucion']['id'])->first();

        if ($devolucion->estado == 'recibido' || $devolucion->estado == 'procesado') {
            return response([
                'error' => 'Esta devolución ya fue recibida en otra instancia'
            ], 401);
        }

        foreach ($data['detalledevolucion'] as $articulo) {
            GpDevolucionesDetalle::where('id', $articulo['id'])
                ->update(['devuelto' => $articulo['devuelto'], 'recibido' => $articulo['devuelto'], 'compartimiento' => $articulo['compartimiento'], 'USERID' => $user->USERID]);
        }

        GpDevoluciones::where('id', $data['devolucion']['id'])->update(['estado' => 'recibido']);

        $this->procesardevoluciones();

        $ingresos = new IngresosController();
        $ingresos->ingreso('devolucion', $data['devolucion'], $data['detalledevolucion']);

        return response([
            'success' => true,
            'message' => 'Devolución recibida',
        ]);
    }

    public function imprimirdevolucion($id): \Illuminate\Http\Response
    {
        $devolucion = GpDevoluciones::where('id', $id)->first()->toArray();
        $pedido = GpPedido::join('gp_usuarios', 'gp_usuarios.USERID', 'gp_pedidos.USERID')
            ->where('gp_pedidos.id', $devolucion['pedido_id'])
            ->select('gp_pedidos.*', 'gp_usuarios.USERNAME')
            ->first()->toArray();
        $detalle = GpDevolucionesDetalle::where('devolucion_id', $id)->get();
        $puntodeventa = GpPuntoVenta::where('PTO_VTA_ID', $pedido['PTO_VTA_ID'])->first()->toarray();


        $data['devolucion'] = $devolucion;
        $data['pedido'] = $pedido;
        $cliente = GpCliente::where('id', $pedido['cliente_id'])->first()->toarray();

        $email = explode(';', str_replace(',', ';', $cliente['CLI_CORREO_ELECT_SRI']));
        $cliente['CLI_CORREO_ELECT_SRI'] = $email[0];

        $data['cliente'] = $cliente;

        $sucursal = GpClienteSucursal::where('id', $pedido['sucursal_facturacion_id'])->first()->toarray();
        $data['sucursal'] = $sucursal;

        $data['subtotal'] = 0;
        $data['descuento'] = 0;
        $data['subtotalmenosdescuento'] = 0;
        $data['iva'] = 0;

        foreach ($detalle as $art) {
            $data['tipoimpuesto'][$art['ART_IMP_VTA']] = 0;
            $data['sumaimpuesto'][$art['ART_IMP_VTA']] = 0;
        }

        foreach ($detalle as $key => $art) {

            if ($art->devuelto == 0) {
                unset($detalle[$key]);
                continue;
            }

            $data['tipoimpuesto'][$art->ART_IMP_VTA] += (float)number_format($art->ART_VALOR * $art->devuelto, 2, '.', '');
            $data['sumaimpuesto'][$art->ART_IMP_VTA] += (float)number_format(($art->ART_IVA / $art->ART_CANTIDAD) * $art->devuelto, 2, '.', '');
            //$data['sumaimpuesto'][$art->ART_IMP_VTA] += (float)number_format(((($art->ART_VALOR - $art->ART_DESCUENTO) * $art->devuelto) * $art->ART_IMP_VTA / 100),4,'.','');

            //$data['subtotal'] += (float)number_format($art->ART_VALOR * $art->devuelto,2,'.','');

            $data['descuento'] += (float)number_format($art->ART_DESCUENTO * $art->devuelto, 2, '.', '');
            $data['subtotalmenosdescuento'] += (float)number_format(($art->ART_VALOR - $art->ART_DESCUENTO) * $art->devuelto, 2, '.', '');
            $data['iva'] += (float)number_format(((($art->ART_VALOR - $art->ART_DESCUENTO) * $art->devuelto) * $art->ART_IMP_VTA / 100), 2, '.', '');
        }

        foreach ($data['tipoimpuesto'] as $linea) {
            $data['subtotal'] += $linea;
        }

        $data['subtotalmenosdescuento'] = (float)number_format($data['subtotalmenosdescuento'], 2, '.', '');
        $data['descuento'] = (float)number_format($data['descuento'], 2, '.', '');
        $data['iva'] = (float)number_format($data['iva'], 2, '.', '');
        $data['total'] = number_format($data['subtotalmenosdescuento'] + $data['iva'], 2, '.', '');


        $data['detalle'] = $detalle;
        $data['PTO_VTA_DESCRIPCION'] = $puntodeventa['PTO_VTA_DESCRIPCION'];
        $data['direccion'] = $puntodeventa['PTO_VTA_DIRECCION_1'] . ' ' . $puntodeventa['PTO_VTA_DIRECCION_2'];
        $data['telefono'] = $puntodeventa['PTO_VTA_FONO_1'];
        $data['vendedor'] = GpVendedor::where('VEND_ID', $sucursal['CLI_SUC_VENDEDOR'])->first()->toarray();
        //return view('devolucionPDF', $data);
        $pdf = PDF::loadView('devolucionPDF', $data);
        return $pdf->stream('nota_de_credito.pdf');
    }

    public function totaldevoluciones($devolucion, $detalle): void
    {

        $devolucion->subtotal = 0;
        $devolucion->descuento = 0;
        $devolucion->subtotalmenosdescuento = 0;
        $devolucion->iva = 0;

        $data = array();
        foreach ($detalle as $art) {
            $data['tipoimpuesto'][$art['ART_IMP_VTA']] = 0;
            $data['sumaimpuesto'][$art['ART_IMP_VTA']] = 0;
        }

        foreach ($detalle as $key => $art) {

            if ($art->devuelto == 0) {
                unset($detalle[$key]);
                continue;
            }

            $data['tipoimpuesto'][$art->ART_IMP_VTA] += (float)number_format($art->ART_VALOR * $art->devuelto, 2, '.', '');
            $data['sumaimpuesto'][$art->ART_IMP_VTA] += (float)number_format($art->ART_IVA, 2, '.', '');

            //$devolucion->subtotal += (float)number_format(($art->ART_VALOR) * $art->devuelto,2,'.','');

            $devolucion->descuento += (float)number_format($art->ART_DESCUENTO * $art->devuelto, 2, '.', '');
            $devolucion->subtotalmenosdescuento += (float)number_format(($art->ART_VALOR - $art->ART_DESCUENTO) * $art->devuelto, 2, '.', '');
            $devolucion->iva += (float)number_format(((($art->ART_VALOR - $art->ART_DESCUENTO) * $art->devuelto) * $art->ART_IMP_VTA / 100), 2, '.', '');
        }

        foreach ($data['tipoimpuesto'] as $linea) {
            $devolucion->subtotal += $linea;
        }

        $devolucion->subtotalmenosdescuento = (float)number_format($devolucion->subtotalmenosdescuento, 2, '.', '');
        $devolucion->descuento = (float)number_format($devolucion->descuento, 2, '.', '');
        //$devolucion->subtotal = $devolucion->subtotalmenosdescuento + $devolucion->descuento;
        $devolucion->iva = (float)number_format($devolucion->iva, 2, '.', '');
        $devolucion->total = number_format($devolucion->subtotalmenosdescuento + $devolucion->iva, 2, '.', '');
        $devolucion->save();
    }

    public function testtotaldevolucion(): void
    {

        $devoluciones = GpDevoluciones::get();
        //
        foreach ($devoluciones as $devolucion) {
            $detalles = GpDevolucionesDetalle::where('devolucion_id', $devolucion->id)->get();
            $this->totaldevoluciones($devolucion, $detalles);
        }
    }


    public function anularDevolucion($pedido)
    {
        $pedido = GpPedido::find($pedido['id']);
        if ($this->isInvoiceAlreadyVoided($pedido)) {
            return $this->buildErrorResponse('La factura ya fue anulada', 400);
        }
        return $this->processCancellation($pedido);
    }

    public function isInvoiceAlreadyVoided($pedido)
    {
        return GpFacturaAnulada::where('factura', $pedido->factura)
            ->where('pedido_id', $pedido->id)->where('estado', 'facturado')->first();
    }
    public function validateInvoiceAnulation($pedido)
    {
        return GpPedido::where(function ($q) {
            $q->where('despachado', 0)->orWhereNull('despachado');
        })->where(function ($q) {
            $q->where('despacho_en_proceso', 0)->orWhereNull('despacho_en_proceso');
        })
            ->where('id', $pedido['id'])->first();
    }

    public function processCancellation($order)
    {
        $cancelResponse  = $this->apiInvalidateGpVoucher($order);
        if ($cancelResponse['success'] === false) {
            $this->saveBaseCancellationInfo($order, $cancelResponse);
            return $this->buildErrorResponse($cancelResponse['message'] ?? 'Error al anular la factura', 400);
        }
        $this->saveFailedCancellation($order, $cancelResponse);
        $accessKeyResponse = $this->apiGetDocumentAccessKey($order);
        $this->saveAccessKeyInfo($order, $accessKeyResponse);

        if (!$this->isValidAccessKey($accessKeyResponse)) {
            return $this->buildErrorResponse($accessKeyResponse['message'] ?? 'Error al obtener la clave de acceso', 400);
        }
        $this->sendSeedBilling($order, $accessKeyResponse['claveAcceso']);

        return $this->buildSuccessResponse('Factura anulada correctamente', 200);
    }

    public  function saveBaseCancellationInfo($order, $cancelResponse)
    {

        GpFacturaAnulada::updateOrCreate(
            ['pedido_id' => $order->id],
            [
                'pedido' => $order->pedido,
                'factura' => $order->factura,
                'msj_anulacion_gp' => $cancelResponse['message'],
                'diario_response_gp' => $cancelResponse['diario'] ?? null,
                'estado' => 'pendiente',
            ]
        );
    }

    public function saveAccessKeyInfo($order, $accessKeyResponse)
    {

        $data = [
            'resp_document' => $accessKeyResponse['message'] ?? null,
        ];

        if (!empty($accessKeyResponse['claveAcceso'])) {
            $data['claveacceso'] = $accessKeyResponse['claveAcceso'];
            $data['documento_id'] = $accessKeyResponse['id'];
        }

        GpFacturaAnulada::updateOrCreate(
            ['pedido_id' => $order->id, 'factura' => $order->factura],
            $data
        );
    }

    private function isValidAccessKey($accessKeyResponse): bool
    {
        return !isset($accessKeyResponse['success'])
            && !empty($accessKeyResponse['claveAcceso']);
    }
    private function sendSeedBilling($order, string $accessKey): void
    {
        $response = $this->apiResponseSeedBelling($accessKey);

        GpFacturaAnulada::updateOrCreate(
            ['pedido_id' => $order->id, 'factura' => $order->factura],
            [
                'resp_seedbilling' => $response['respuesta'] ?? null,
                'estado' =>  'enviada',
            ]
        );

        $this->apiUpdateTbhFacAnuAutorize($order->factura);
        // $this->updateStatusDevolutions($order);
    }

    private function saveFailedCancellation($order, array $cancelResponse): void
    {
        GpFacturaAnulada::updateOrCreate(
            ['pedido_id' => $order->id],
            [
                'pedido' => $order->pedido,
                'factura' => $order->factura,
                'msj_anulacion_gp' => $cancelResponse['message'],
                'estado' => 'pendiente',
            ]
        );
    }

    public function updateStatusDevolutions($order)
    {
        GpDevoluciones::where('pedido_id', $order->id)
            ->update(['estado' => 'anulada']);
    }

    public function updateStatusOrders($order)
    {
        GpPedido::where('id', $order->id)
            ->update(['estado' => 'anulada']);
    }


    public function apiInvalidateGpVoucher($pedido)
    {
        return $this->apiSales->invalidateGpVoucher(array(
            'numeroDocumento' => $pedido->factura,
            'cliente' => $pedido->CLI_RUC
        ));
    }

    public function apiGetDocumentAccessKey($pedido)
    {
        return $this->apiSales->getDocumentAccesKey(array(
            'numdoc' => $pedido->factura,
        ));
    }

    public function apiResponseSeedBelling($accesCode)
    {

        return  $this->apiSeedBelling->anularComprobante(array(
            'claveAcceso' => $accesCode,
        ));
    }

    public function apiUpdateTbhFacAnuAutorize($factura)
    {
        $this->apiSales->updatedStatusDocumentGp(array(
            'numdoc' => $factura,
        ));
    }
}
