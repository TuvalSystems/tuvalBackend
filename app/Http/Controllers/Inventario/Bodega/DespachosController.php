<?php

namespace App\Http\Controllers\Inventario\Bodega;

use App\Enums\Ventas\OrderStatus;
use App\Events\Bodegas\Dashboard\DispatchPendings;
use App\Http\Controllers\Controller;
use App\Http\Controllers\WebserviceController;
use App\Models\Cobranzas\GpCobro;
use App\Models\Cobranzas\GpCobrosCheques;
use App\Models\Cobranzas\GpCobrosChequesFacturas;
use App\Models\Cobranzas\GpCobrosChequesMonto;
use App\Models\Cobranzas\GpCobrosDeposito;
use App\Models\Cobranzas\GpCobrosDepositosFacturas;
use App\Models\Cobranzas\GpCobrosEfectivoFactura;
use App\Models\Cobranzas\GpCobrosEfectivoMonto;
use App\Models\Cobranzas\GpCobrosRetenciones;
use App\Models\despachos\GpGuiaRemision;
use App\Models\devoluciones\GpDevoluciones;
use App\Models\devoluciones\GpDevolucionesDetalle;
use App\Models\GpCliente;
use App\Models\GpClienteSucursal;
use App\Models\GpCobrosAplicacionFactura;
use App\Models\GpCobrosTarjetaFactura;
use App\Models\GpCobrosTarjetaMonto;
use App\Models\GpCotizacionImagen;
use App\Models\GpCronLog;
use App\Models\GpDescuentoFormaPago;
use App\Models\GpOrdenDespacho;
use App\Models\GpPedido;
use App\Models\GpPedidoDetalle;
use App\Models\GpPedidoDetalleStock;
use App\Models\GpPedidoImagen;
use App\Models\GpRelacionPuntoVentaBodega;
use App\Services\Inventario\Bodegas\Contracts\DispatchServiceInterface;
use App\Services\Inventario\Bodegas\Contracts\LogisticsOperationsServiceInterface;
use App\Services\Venta\Contracts\OrderServiceInterface;
use App\Traits\JsonResponseTrait;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Barryvdh\DomPDF\Facade\Pdf;
use DateTime;
use Illuminate\Support\Facades\Log;
use stdClass;

class DespachosController extends Controller
{
    use JsonResponseTrait;
    public function __construct(
        protected readonly OrderServiceInterface $orderService,
        protected readonly DispatchServiceInterface $dispatchService,
        protected readonly LogisticsOperationsServiceInterface $logisticsOperationsService
    ) {}

    public function checkfacturasdespachos($cobro_id)
    {

        $facturas = array();


        $fac_aplicacion = GpCobrosAplicacionFactura::where('cobro_id', $cobro_id)->get()->toArray();
        foreach ($fac_aplicacion as $fac) {
            $facturas[$fac['id_factura']] = $fac['id_factura'];
        }

        $fac_cheques = GpCobrosChequesFacturas::where('cobro_id', $cobro_id)->get()->toArray();
        foreach ($fac_cheques as $fac) {
            $facturas[$fac['id_factura']] = $fac['id_factura'];
        }

        $fac_depositos = GpCobrosDepositosFacturas::join('gp_cobros_depositos', 'gp_cobros_depositos.id', 'gp_cobros_depositos_facturas.deposito_id')
            ->where('gp_cobros_depositos.estado', 'enviado')
            ->where('gp_cobros_depositos.cobro_id', $cobro_id)
            ->get()->toArray();
        foreach ($fac_depositos as $fac) {
            $facturas[$fac['id_factura']] = $fac['id_factura'];
        }

        $fac_efectivo = GpCobrosEfectivoFactura::where('cobro_id', $cobro_id)->get()->toArray();
        foreach ($fac_efectivo as $fac) {
            $facturas[$fac['id_factura']] = $fac['id_factura'];
        }

        $fac_retenciones = GpCobrosRetenciones::where('cobro_id', $cobro_id)->get()->toArray();
        foreach ($fac_retenciones as $fac) {
            $facturas[$fac['ret_factura']] = $fac['ret_factura'];
        }

        $fac_tarjetas = GpCobrosTarjetaFactura::where('cobro_id', $cobro_id)->get()->toArray();
        foreach ($fac_tarjetas as $fac) {
            $facturas[$fac['id_factura']] = $fac['id_factura'];
        }

        foreach ($facturas as $fac) {
            $fac = substr($fac, 0, 17);

            $pedido = GpPedido::where('factura', $fac)->get()->first();
            if ($pedido)
                $pedido = $pedido->toArray();
            else {
                continue;
            }

            $pedido['total_anterior'] = $pedido['total'];

            $retencion = $pedido['total'] * env('COLCHON_RETENCION');

            foreach ($fac_aplicacion as $apl) {
                $pedido['total'] -= $apl['monto'];
            }

            foreach ($fac_cheques as $che) {
                $cheque = GpCobrosCheques::where('cobro_id', $che['cobro_id'])->get()->first()->toArray();
                if ($cheque['fecha_vencimiento'] <= $che['ECD_DET_FECHA_VENCIMIENTO']) {
                    $pedido['total'] -= $che['monto'];
                }
            }

            foreach ($fac_depositos as $dep) {
                $pedido['total'] -= $dep['monto'];
            }

            foreach ($fac_efectivo as $efe) {
                $pedido['total'] -= $efe['monto'];
            }

            foreach ($fac_tarjetas as $tar) {
                $pedido['total'] -= $tar['monto'];
            }

            foreach ($fac_retenciones as $ret) {
                $pedido['total'] -= $ret['ret_iva'] + $ret['ret_renta'] + $ret['ret_iva_srv'] + $ret['ret_renta_srv'];
            }

            if ($pedido['total'] <= $retencion) {
                $this->liberardespacho($pedido['id']);
            } else {
                $this->checksumacobros($pedido);
            }
        }

        if (!count($facturas)) {
            $this->checkpagopedido($cobro_id);
        }
    }

    public function checkpagopedido($cobro_id)
    {

        $cobro = GpCobro::where('id', $cobro_id)->first();
        $pedido = GpPedido::where('pedido', $cobro->pedido)->first();

        if ($pedido) {
            $pedido = $pedido->toArray();
            $retencion = $pedido['total'] * env('COLCHON_RETENCION');

            $retenciones = GpCobrosRetenciones::where('cobro_id', $cobro_id)->get()->toArray();
            foreach ($retenciones as $ret) {
                $pedido['total'] -= $ret['ret_iva'] + $ret['ret_renta'] + $ret['ret_iva_srv'] + $ret['ret_renta_srv'];
            }

            $efectivo = GpCobrosEfectivoMonto::where('cobro_id', $cobro_id)->get()->toArray();
            foreach ($efectivo as $efe) {
                $pedido['total'] -= $efe['monto'];
            }
            $cheque = GpCobrosChequesMonto::where('cobro_id', $cobro_id)->get()->toArray();
            foreach ($cheque as $che) {
                $pedido['total'] -= $che['monto'];
            }
            $deposito = GpCobrosDeposito::where('cobro_id', $cobro_id)->get()->toArray();
            foreach ($deposito as $dep) {
                $pedido['total'] -= $dep['monto'];
            }
            $tarjeta = GpCobrosTarjetaMonto::where('cobro_id', $cobro_id)->get()->toArray();
            foreach ($tarjeta as $tar) {
                $pedido['total'] -= $tar['monto'];
            }
            if ($pedido['total'] <= $retencion) {
                $this->liberardespacho($pedido['id']);
            }
        }
    }

    public function checksumacobros($pedido)
    {

        $pedido['total'] = $pedido['total_anterior'];
        $retencion = $pedido['total'] * env('COLCHON_RETENCION');

        $fac_retenciones = GpCobrosRetenciones::where('ret_factura', $pedido['factura'])->get()->toArray();
        foreach ($fac_retenciones as $ret) {
            $pedido['total'] -= $ret['ret_iva'] + $ret['ret_renta'] + $ret['ret_iva_srv'] + $ret['ret_renta_srv'];
        }

        $fac_aplicacion = GpCobrosAplicacionFactura::where('id_factura', 'LIKE', $pedido['factura'] . '%')->get()->toArray();
        foreach ($fac_aplicacion as $fac) {
            $pedido['total'] -= $fac['monto'];
        }

        $fac_cheques = GpCobrosChequesFacturas::where('id_factura', 'LIKE', $pedido['factura'] . '%')->get()->toArray();
        foreach ($fac_cheques as $fac) {
            $pedido['total'] -= $fac['monto'];
        }

        $fac_depositos = GpCobrosDepositosFacturas::join('gp_cobros_depositos', 'gp_cobros_depositos.id', 'gp_cobros_depositos_facturas.deposito_id')
            ->where('gp_cobros_depositos.estado', 'enviado')
            ->where('id_factura', 'LIKE', $pedido['factura'] . '%')
            ->get()->toArray();
        foreach ($fac_depositos as $fac) {
            $pedido['total'] -= $fac['monto'];
        }

        $fac_efectivo = GpCobrosEfectivoFactura::where('id_factura', 'LIKE', $pedido['factura'] . '%')->get()->toArray();
        foreach ($fac_efectivo as $fac) {
            $pedido['total'] -= $fac['monto'];
        }

        $fac_tarjetas = GpCobrosTarjetaFactura::where('id_factura', 'LIKE', $pedido['factura'] . '%')->get()->toArray();
        foreach ($fac_tarjetas as $fac) {
            $pedido['total'] -= $fac['monto'];
        }

        $pedido['total'] = number_format($pedido['total'], 2, '.', '');

        if ($pedido['total'] <= $retencion) {
            $this->liberardespacho($pedido['id']);
        }
    }

    public function liberardespacho($pedido_id)
    {



        $ordenDespacho = GpOrdenDespacho::where('pedido_id', $pedido_id)
            ->where('estado', 'pendiente')
            ->get()->first();

        if ($ordenDespacho) {
            if (!$ordenDespacho->fecha_hora_liberacion) {
                $ordenDespacho->fecha_hora_liberacion = date('Y-m-d H:i:s');
            }
            $ordenDespacho->estado = 'liberada';
            $ordenDespacho->save();
        }
    }

    public function rechazarordendespacho($id): Response|Application|ResponseFactory
    {

        $ordenDespacho = GpOrdenDespacho::where('id', $id)->first();
        $pedido = GpPedido::where('id', $ordenDespacho->pedido_id)->first();

        return response([
            'success' => true,
            'ordenDespacho' => $ordenDespacho,
            'pedido' => $pedido,
        ]);
    }

    public function ordenesliberadas($puntodeventa_id, $estado = 'liberada'): Response|Application|ResponseFactory
    {

        $bodega = GpRelacionPuntoVentaBodega::where('PTO_VTA_BOD_PUNTO_VENTA', $puntodeventa_id)->first();
        $bodega = $bodega->PTO_VTA_BOD_BODEGA;

        $ordenes = GpOrdenDespacho::where('BOD_BODEGA', $bodega)
            ->where('estado', $estado);
        if ($estado == 'despachada') {
            $ordenes = $ordenes->orWhere('estado', 'cancelada');
            $ordenes = $ordenes->orderBy('id', 'DESC');
        } else {
            $ordenes = $ordenes->orderBy('created_at', 'ASC');
        }

        $ordenes = $ordenes->get();

        foreach ($ordenes as $key => $orden) {
            $pedido = GpPedido::where('id', $orden->pedido_id)->first();

            if ($pedido->estado === OrderStatus::RECHAZADO) {
                $orden->estado = 'cancelada';
                $orden->fecha_hora_liberacion = null;
                $orden->save();
                unset($ordenes[$key]);
                continue;
            }

            $envio = GpClienteSucursal::where('id', $pedido->sucursal_id)->first()->toArray();
            $orden->envio = $envio;
            $orden->direccion = $envio['CLI_SUC_DIRECCION'];
            $orden->ciudad = $envio['CLI_SUC_CIUDAD'];
            $orden->fecha_entrega = $pedido->fecha_entrega;

            $peso_total = 0;
            if ($estado == 'liberada') {
                $reporte = $this->pesopicking($pedido);
                foreach ($reporte as &$art) {
                    $peso_total += number_format($art['ART_PESO'] * $art['pendiente'], 2, '.', '');
                }
            }
            $orden->peso = $peso_total;
            $pedido->peso = $peso_total;

            $orden->pedido = $pedido;
            $orden->numpedido = $orden->pedido->pedido;
            $orden->factura = $orden->pedido->factura;
            $orden->CLI_RAZON_SOCIAL = $orden->pedido->CLI_RAZON_SOCIAL;
            $orden->CLI_RUC = $orden->pedido->CLI_RUC;
            $orden->BOD_BODEGA = $orden->pedido->BOD_BODEGA;
            $orden->fecha = substr($orden->created_at, 0, 16);
            $orden->despacho = $orden->pedido->despacho;
            $orden->total = $orden->pedido->total;

            $origin = new DateTime($orden->fecha);
            $target = new DateTime('now');
            $orden->dias = $origin->diff($target)->days;

            $estadoGuia = 'enviada';
            if ($estado == 'liberada')
                $estadoGuia = 'pendiente';

            $orden->guias = [];

            $guias = GpGuiaRemision::where('pedido', $pedido->pedido)
                ->where('estado', $estadoGuia)
                ->where('tipo', 'despacho')
                ->orderBy('created_at')
                ->get()->toArray();
            $orden->guias = $guias;



            $guias = GpGuiaRemision::where('pedido', $pedido->pedido)
                ->where('estado', '!=', 'pendiente')
                ->where('tipo', 'despacho')
                ->orderBy('created_at')
                ->get()->count();
            $orden->enviadas = $guias;
        }

        $ultimaguia = GpGuiaRemision::join('gp_bodegas', 'gp_guia_remisions.bodega_origen', 'gp_bodegas.BOD_BODEGA')
            ->where('bodega_origen', $bodega)
            ->where('estado', 'pendiente')
            ->where('tipo', 'despacho')
            ->select('gp_guia_remisions.*', 'gp_bodegas.BOD_DESCRIPCION', 'gp_bodegas.BOD_BODEGA')
            ->orderBy('created_at', 'DESC')
            ->first();

        $this->logisticsOperationsService->assignAccessCodesForPendingGuides();
        $ordenes = array_values($ordenes->toArray());
        return response([
            'success' => true,
            'ordenes' => $ordenes,
            'ultimaguia' => $ultimaguia,
        ]);
    }

    public function ordenesliberadassindetalle($puntodeventa_id, $estado = 'liberada'): Response|Application|ResponseFactory
    {

        $bodega = GpRelacionPuntoVentaBodega::where('PTO_VTA_BOD_PUNTO_VENTA', $puntodeventa_id)->first();
        $bodega = $bodega->PTO_VTA_BOD_BODEGA;

        $ordenes = GpOrdenDespacho::where('BOD_BODEGA', $bodega)
            ->where('estado', $estado);
        if ($estado == 'despachada') {
            $ordenes = $ordenes->orWhere('estado', 'cancelada');
            $ordenes = $ordenes->orderBy('id', 'DESC');
        } else {
            $ordenes = $ordenes->orderBy('fecha_hora_liberacion', 'ASC');
        }

        $ordenes = $ordenes->get();

        foreach ($ordenes as $key => $orden) {
            $pedido = GpPedido::where('id', $orden->pedido_id)->first();

            if ($pedido->estado === OrderStatus::RECHAZADO) {
                $orden->estado = 'cancelada';
                $orden->fecha_hora_liberacion = null;
                $orden->save();
                unset($ordenes[$key]);
                continue;
            }

            $envio = GpClienteSucursal::where('id', $pedido->sucursal_id)->first()->toArray();
            $orden->envio = $envio;
            $orden->direccion = $envio['CLI_SUC_DIRECCION'];
            $orden->ciudad = $envio['CLI_SUC_CIUDAD'];
            $orden->fecha_entrega = $pedido->fecha_entrega;
            $orden->pedido_imagenes = $this->getAllImagensOperations($pedido->id, 'pedido');
            $orden->documentosPdf = $this->getAllDocumentsPdf($pedido);
            $peso_total = 0;
            $orden->peso = $peso_total;
            $pedido->peso = $peso_total;

            $orden->pedido = $pedido;
            $orden->numpedido = $orden->pedido->pedido;
            $orden->factura = $orden->pedido->factura;
            $orden->CLI_RAZON_SOCIAL = $orden->pedido->CLI_RAZON_SOCIAL;
            $orden->CLI_RUC = $orden->pedido->CLI_RUC;
            $orden->BOD_BODEGA = $orden->pedido->BOD_BODEGA;
            $orden->fecha = substr($orden->fecha_hora_liberacion, 0, 16);
            $orden->despacho = $orden->pedido->despacho;
            $orden->total = $orden->pedido->total;

            $origin = new DateTime($orden->fecha);
            $target = new DateTime('now');
            $orden->dias = $origin->diff($target)->days;

            $orden->guias = [];

            $orden->sindetalle = true;
        }

        $ultimaguia = GpGuiaRemision::join('gp_bodegas', 'gp_guia_remisions.bodega_origen', 'gp_bodegas.BOD_BODEGA')
            ->where('bodega_origen', $bodega)
            ->where('estado', 'pendiente')
            ->where('tipo', 'despacho')
            ->select('gp_guia_remisions.*', 'gp_bodegas.BOD_DESCRIPCION', 'gp_bodegas.BOD_BODEGA')
            ->orderBy('created_at', 'DESC')
            ->first();

        $this->logisticsOperationsService->assignAccessCodesForPendingGuides();

        $ordenes = array_values($ordenes->toArray());
        return response([
            'success' => true,
            'ordenes' => $ordenes,
            'ultimaguia' => $ultimaguia,
        ]);
    }

    public function getAllImagensOperations($quotationId, $type = 'cotizacion')
    {
        $model = ($type == 'pedido')
            ? GpPedidoImagen::where('pedido_id', $quotationId)
            : GpCotizacionImagen::where('cotizacion_id', $quotationId);
        return  $model->get()->map(function ($img) {
            return [
                'id' => $img->id,
                'url' => asset('storage/' . $img->ruta),
                'nombre' => $img->nombre_original
            ];
        });
    }
    public  function getAllDocumentsPdf(GpPedido $order)
    {
        return $order->getMedia('documentos_pdf')
            ->map(function ($media) {
                return [
                    'id'            => $media->id,
                    'url'           => $media->getFullUrl(),
                    'nombre'        => $media->getCustomProperty('original_name') ?: $media->file_name,
                    'mime_type'     => $media->mime_type,
                    'size'          => $media->size,
                    'file_name'     => $media->file_name
                ];
            })->values()->toArray();
    }

    public function getultimaguia($puntodeventa_id): Response|Application|ResponseFactory
    {

        $bodega = GpRelacionPuntoVentaBodega::where('PTO_VTA_BOD_PUNTO_VENTA', $puntodeventa_id)->first();
        $bodega = $bodega->PTO_VTA_BOD_BODEGA;
        $ultimaguia = GpGuiaRemision::join('gp_bodegas', 'gp_guia_remisions.bodega_origen', 'gp_bodegas.BOD_BODEGA')
            ->where('bodega_origen', $bodega)
            ->where('estado', 'pendiente')
            ->where('tipo', 'despacho')
            ->select('gp_guia_remisions.*', 'gp_bodegas.BOD_DESCRIPCION', 'gp_bodegas.BOD_BODEGA')
            ->orderBy('created_at', 'DESC')
            ->first();

        return response([
            'success' => true,
            'ultimaguia' => $ultimaguia,
        ]);
    }

    public function detalleordenliberada($id): Response|Application|ResponseFactory
    {
        $orden = GpOrdenDespacho::where('id', $id)->first();

        $pedido = GpPedido::where('id', $orden->pedido_id)->first();

        $peso_total = 0;
        $reporte = $this->pesopicking($pedido);
        foreach ($reporte as &$art) {
            $peso_total += number_format($art['ART_PESO'] * $art['pendiente'], 2, '.', '');
        }
        $orden->peso = $peso_total;

        $estadoGuia = 'enviada';
        if ($orden->estado == 'liberada')
            $estadoGuia = 'pendiente';

        $guias = GpGuiaRemision::where('pedido', $pedido->pedido)
            ->where('estado', $estadoGuia)
            ->where('tipo', 'despacho')
            ->orderBy('created_at')
            ->get()->toArray();
        $orden->guias = $guias;

        $guias = GpGuiaRemision::where('pedido', $pedido->pedido)
            ->where('estado', '!=', 'pendiente')
            ->where('tipo', 'despacho')
            ->orderBy('created_at')
            ->get()->count();
        $orden->enviadas = $guias;
        return response([
            'success' => true,
            'orden' => $orden,
        ]);
    }

    public function detalleorden($id): Response|Application|ResponseFactory
    {

        $devolucion = GpDevoluciones::where('pedido_id', '=', $id)
            ->where(function ($query) {
                $query->where('estado', 'espera');
                $query->orWhere('estado', 'solicitado');
            })
            ->first();
        if ($devolucion) {
            return response([
                'success' => false,
                'message' => 'Este pedido tiene una devolución en proceso : ' . $devolucion->devolucion,
                'detalle' => array(),
            ]);
        }

        $ordenCancelada = GpOrdenDespacho::where('pedido_id', $id)
            ->where('estado', 'cancelada')
            ->first();

        if ($ordenCancelada) {
            return response([
                'success' => false,
                'message' => 'Esta orden de despacho ha sido cancelada',
                'detalle' => array(),
            ]);
        }

        $ordenDespachada = GpOrdenDespacho::where('pedido_id', $id)
            ->where('estado', 'despachada')
            ->first();

        if ($ordenDespachada) {
            return response([
                'success' => false,
                'message' => 'Esta orden de despacho ha sido despachada por completo, el pedido fue marcado con "Facturar lo despachado"',
                'detalle' => array(),
            ]);
        }

        $pedido = GpPedido::where('id', $id)->first();

        $pedido->despacho_en_proceso = 1;
        $pedido->save();

        $detalles = GpPedidoDetalle::where('pedido_id', $id)->get();

        $resultados = $detalles->map(function ($det) {
            $devueltoNoDesp = GpDevolucionesDetalle::where('detalle_id', $det->id)
                ->where('despachado_parcial', 0)
                ->sum('devuelto');
            $detTNoDevuelto = ($det->ART_CANTIDAD - $det->trasladado) - $det->reservado - $devueltoNoDesp;

            $det->pendiente = number_format($detTNoDevuelto, 2, '.', '');
            $det->seleccionado  = 0;
            $det->ART_CANTIDAD  = round($det->ART_CANTIDAD, $det->ART_DECIMAL_CANTIDAD);
            $det->pendiente     = round($det->pendiente, $det->ART_DECIMAL_CANTIDAD);
            $det->reservado     = round($det->reservado, $det->ART_DECIMAL_CANTIDAD);
            $det->trasladado    = round($det->trasladado, $det->ART_DECIMAL_CANTIDAD);

            return $det;
        })->filter(function ($det) {
            return $det->pendiente > 0;
        })->values();

        return response([
            'success' => true,
            'detalle' => $resultados,
        ]);
    }



    public function ordenespendientes($puntodeventa_id): Response|Application|ResponseFactory
    {
        $ordenes = GpOrdenDespacho::join('gp_pedidos', 'gp_pedidos.id', 'gp_orden_despachos.pedido_id')
            ->where('gp_orden_despachos.PTO_VTA_ID', $puntodeventa_id)
            ->where('gp_orden_despachos.estado', 'pendiente')
            ->select('gp_orden_despachos.*', 'gp_pedidos.fecha_entrega')
            ->get();

        foreach ($ordenes as $orden) {
            $orden->pedido = GpPedido::where('id', $orden->pedido_id)->first();
            $orden->pedido->direccion_entrega = GpClienteSucursal::where('id', $orden->pedido->sucursal_id)->first();
        }

        return response([
            'success' => true,
            'ordenes' => $ordenes,
        ]);
    }

    public function liberarordendespacho(Request $request): Response|Application|ResponseFactory
    {
        $data = $request->all();
        $user = $request->user();

        $orden = GpOrdenDespacho::where('id', $data['id'])->first();
        if (!$orden || $orden->estado !== 'pendiente') {
            return response([
                'success' => false,
                'message' => 'La orden de despacho no está pendiente y no puede ser liberada.',
                'orden' => $orden,
            ], 409);
        }
        $orden->autorizacion_fecha = date('Y-m-d H:i:s');
        if (!$orden->fecha_hora_liberacion) {
            $orden->fecha_hora_liberacion = date('Y-m-d H:i:s');
        }
        $orden->estado = 'liberada';
        $orden->autorizacion_user_id = $user->id;
        $orden->autorizacion_USERID = $user->USERID;
        $orden->autorizacion_comentario = trim($data['motivo']);
        $orden->save();
        event(new DispatchPendings('refresh'));
        return response([
            'success' => true,
            'message' => 'Órden de despacho ' . $orden->orden . ' liberada',
            'orden' => $orden,
        ]);
    }

    public function cancelarprocesodespacho($pedido_id): Response|Application|ResponseFactory
    {
        GpPedido::where('id', $pedido_id)->update(['despacho_en_proceso' => null]);

        return response([
            'success' => true,
        ]);
    }
    public function picking($pedido_id): Response|Application|ResponseFactory
    {
        $pedido = GpPedido::where('id', $pedido_id)->first();

        $pedido->despacho_en_proceso = 1;
        $pedido->save();

        $reporte = $this->reportepicking($pedido);

        $peso_total = 0;
        foreach ($reporte as &$art) {
            $peso_total += number_format($art['ART_PESO'] * $art['pendiente'], 2, '.', '');
        }

        $picking = array();
        $picking['reporte'] = $reporte;
        $picking['peso_total'] = $peso_total;
        return response([
            'success' => true,
            'picking' => $picking,
        ]);
    }

    public function imprimirpicking($pedido_id): Response
    {
        $pedido = GpPedido::where('id', $pedido_id)->first();

        $reporte = $this->reportepicking($pedido);

        $peso_total = 0;
        foreach ($reporte as $art) {
            $peso_total += number_format($art['ART_PESO'] * $art['pendiente'], 2, '.', '');
        }

        $data = array();
        $data['reporte'] = $reporte;
        $data['pedido'] = $pedido->toArray();
        $data['peso_total'] = $peso_total;

        //return view('pickingPDF', $data);
        $pdf = PDF::loadView('pickingPDF', $data);
        return $pdf->stream('picking-' . $pedido['pedido'] . '.pdf');
    }


    public function reportepicking(GpPedido $pedido): Collection|array
    {
        $webservice = new WebserviceController();
        $detalle = GpPedidoDetalle::where('pedido_id', $pedido->id)->get()->toArray();
        $bodega = $pedido->BOD_BODEGA;

        foreach ($detalle as $key => &$art) {

            //            if($art['ART_TIPO_COMPRA'] == 'S')
            //               continue;

            if ($art['ART_TIPO_COMPRA'] == 'L') {
                $art['ART_DESCRIPCION_ORIGINAL'] = $art['ART_DESCRIPCION'];
            }

            if (!$art['reservado'])
                $art['reservado'] = 0;

            if (!$art['trasladado'])
                $art['trasladado'] = 0;

            if (!$art['devuelto'])
                $art['devuelto'] = 0;

            $detTDevuelto = 0;
            $detTNoDevuelto = 0;

            $devueltoDesp = GpDevolucionesDetalle::join('gp_devoluciones', 'gp_devoluciones.id', 'gp_devoluciones_detalles.devolucion_id')
                ->where('gp_devoluciones_detalles.despachado_parcial', 1)
                ->where('gp_devoluciones_detalles.detalle_id', $art['id'])
                ->sum('gp_devoluciones_detalles.devuelto');
            $devueltoNoDesp = GpDevolucionesDetalle::join('gp_devoluciones', 'gp_devoluciones.id', 'gp_devoluciones_detalles.devolucion_id')
                ->where('gp_devoluciones_detalles.despachado_parcial', 0)
                ->where('gp_devoluciones_detalles.detalle_id', $art['id'])
                ->sum('gp_devoluciones_detalles.devuelto');


            $detTDevuelto = $art['trasladado'] - $art['reservado'] - $devueltoDesp;
            $detTNoDevuelto = ($art['ART_CANTIDAD'] - $art['trasladado']) - $art['reservado'] - $devueltoNoDesp;

            $art['pendiente'] = number_format($detTNoDevuelto, 2, '.', '');

            if ($art['pendiente'] <= 0) {
                unset($detalle[$key]);
                continue;
            }

            $parameters = array(
                'id_articulo' => $art['ART_CODIGO'],
                'id_bodega' => $bodega,
                'id_compartimiento' => '',
            );


            $compartimento = $webservice->stockarticulobodegacompartimiento($parameters);

            if (isset($compartimento['MST_Stock_Articulo_Bodega_CompartimientoResult']['CS_STOCK_ARTICULO_BODEGA_COMPARTIMIENTO'])) {
                $compartimento = $compartimento['MST_Stock_Articulo_Bodega_CompartimientoResult']['CS_STOCK_ARTICULO_BODEGA_COMPARTIMIENTO'];
                $stock = array_column($compartimento, 'STO_ART_COMPART_STOCK_DISPONIBLE');

                array_multisort($stock, SORT_DESC, $compartimento);

                $cantidad = $art['ART_CANTIDAD'];
                $arr_comp = array();
                foreach ($compartimento as $comp) {
                    $arr_comp[] = $comp;
                    $cantidad -= $comp['STO_ART_COMPART_STOCK_DISPONIBLE'];
                    if ($cantidad <= 0)
                        break;
                }

                $art['mejorStock'] = $arr_comp[0]['STO_ART_COMPART_COMPARTIMIENTO'];

                $art['compartimento'] = $arr_comp;
            } else {
                $art['mejorStock'] = 0;

                $art['compartimento'] = [];
            }
        }

        $detalle = array_values($detalle);



        $orderby = array_column($detalle, 'mejorStock');
        array_multisort($orderby, SORT_ASC, $detalle);

        return $detalle;
    }

    public function pesopicking(GpPedido $pedido): Collection|array
    {
        $detalle = GpPedidoDetalle::where('pedido_id', $pedido->id)->get()->toArray();
        foreach ($detalle as $key => &$art) {

            if (!$art['reservado'])
                $art['reservado'] = 0;

            if (!$art['trasladado'])
                $art['trasladado'] = 0;

            if (!$art['devuelto'])
                $art['devuelto'] = 0;

            $detTDevuelto = 0;
            $detTNoDevuelto = 0;

            $devueltoDesp = GpDevolucionesDetalle::join('gp_devoluciones', 'gp_devoluciones.id', 'gp_devoluciones_detalles.devolucion_id')
                ->where('gp_devoluciones_detalles.despachado_parcial', 1)
                ->where('gp_devoluciones_detalles.detalle_id', $art['id'])
                ->sum('gp_devoluciones_detalles.devuelto');
            $devueltoNoDesp = GpDevolucionesDetalle::join('gp_devoluciones', 'gp_devoluciones.id', 'gp_devoluciones_detalles.devolucion_id')
                ->where('gp_devoluciones_detalles.despachado_parcial', 0)
                ->where('gp_devoluciones_detalles.detalle_id', $art['id'])
                ->sum('gp_devoluciones_detalles.devuelto');


            $detTDevuelto = $art['trasladado'] - $art['reservado'] - $devueltoDesp;
            $detTNoDevuelto = ($art['ART_CANTIDAD'] - $art['trasladado']) - $art['reservado'] - $devueltoNoDesp;

            $art['pendiente'] = number_format($detTNoDevuelto, 2, '.', '');


            if ($art['pendiente'] <= 0) {
                unset($detalle[$key]);
            }
        }

        return array_values($detalle);
    }

    public function checkdespacho(GpGuiaRemision $guia)
    {
        $pedido = GpPedido::where('pedido', $guia->pedido)->first();
        $listo = true;

        $detallepedido = GpPedidoDetalle::where('pedido_id', $pedido->id)->get();

        foreach ($detallepedido as $item) {
            // if ($item->ART_TIPO_COMPRA != 'SERV') {
            if (($item->trasladado + $item->devuelto) < $item->ART_CANTIDAD) {
                $listo = false;
            }
            // }
            // if ($item->ART_TIPO_COMPRA = 'SERV') {
            //     if ($item->trasladado < $item->ART_CANTIDAD) {
            //         $listo = false;
            //     }
            // }

        }

        if ($listo) {
            $pedido->despachado = 1;
            $pedido->despacho_en_proceso = 0;
            $pedido->save();

            $orden = GpOrdenDespacho::where('pedido_id', $pedido->id)->where('estado', '!=', 'cancelada')->first();
            $orden->estado = 'despachada';
            $orden->save();
        }
        return $pedido;
    }

    public function checkdespachadoscompletos()
    {
        $ordenes = GpOrdenDespacho::with([
            'pedido' => function ($q) {
                $q->where('estado', OrderStatus::FACTURADO)->with('detallepedido');
            },

        ])->where('estado', 'liberada')->get();
        foreach ($ordenes as $orden) {
            $pedido = $orden->pedido;
            if (!$pedido) {
                continue;
            }
            $listo = true;

            foreach ($pedido->detallepedido as $item) {
                if (($item->trasladado + $item->devuelto) < $item->ART_CANTIDAD) {
                    $listo = false;
                    break;
                }
            }

            if ($listo) {
                $pedido->despachado = 1;
                $pedido->save();
                $orden->estado = 'despachada';
                $orden->save();
            }
        }

        $pedidos = GpPedido::leftJoin('gp_pedido_detalles', 'gp_pedido_detalles.pedido_id', 'gp_pedidos.id')
            ->leftJoin('gp_guia_remisions', 'gp_guia_remisions.pedido', 'gp_pedidos.pedido')
            ->leftJoin('gp_orden_despachos', 'gp_orden_despachos.pedido_id', 'gp_pedidos.id')
            ->where(function ($query) {
                $query->whereNull('gp_pedidos.despachado');
                $query->orWhere('gp_pedidos.despachado', 0);
            })
            //->where('gp_pedido_detalles.ART_TIPO_COMPRA', '!=', 'SERV')
            ->where('gp_pedido_detalles.reservado', '>', 0)
            ->where('gp_guia_remisions.tipo', 'despacho')
            ->where('gp_guia_remisions.estado', 'enviada')
            ->where('gp_orden_despachos.estado', 'despachada')
            ->select('gp_pedidos.*')
            ->distinct()
            ->get();

        foreach ($pedidos as $pedido) {
            $detalle = GpPedidoDetalle::where('pedido_id', $pedido->id)->get();
            foreach ($detalle as $item) {
                $item->reservado = 0;
                $item->trasladado = $item->ART_CANTIDAD - $item->devuelto;
                $item->save();

                $stock = GpPedidoDetalleStock::where('detalle_id', $item->id)
                    ->where('STO_ART_BODEGA', $pedido->BOD_BODEGA)
                    ->first();

                if ($stock) {
                    if (!$stock->reservado)
                        $stock->reservado = 0;

                    $stock->reservado = 0;
                    $stock->save();
                }
            }
            $pedido->despachado = 1;
            $pedido->save();
        }

        $cronLog = new GpCronLog();
        $cronLog->metodo = 'checkdespachadoscompletos';
        $cronLog->fecha = date('Y-m-d H:i:s');
        $cronLog->save();
    }

    public function checkdespachosweb($id): Response|Application|ResponseFactory
    {
        $guia = GpGuiaRemision::where('id', $id)->first();
        $pedido = $this->checkdespacho($guia);
        return response([
            $pedido
        ]);
    }



    public function imprimirordendespacho($id): Response
    {
        $pedido = $this->detalleordendespacho($id);
        $pdf = Pdf::loadView('ordendespachoPDF', $pedido);
        return $pdf->stream('orden-despacho-' . $pedido['pedido'] . '.pdf');
    }

    public function verordendespacho($id)
    {
        $pedido = $this->detalleordendespacho($id);
        return response([
            'success' => true,
            'orden' => $pedido,
        ]);
    }


    public function detalleordendespacho($id)
    {
        $orden_despacho = GpOrdenDespacho::where('id', $id)->first();

        $pedido = GpPedido::leftJoin('gp_cotizacions', 'gp_cotizacions.id', 'gp_pedidos.cotizacion_id')
            ->join('gp_usuarios', 'gp_usuarios.USERID', 'gp_pedidos.USERID')
            ->select('gp_pedidos.*', 'gp_cotizacions.cotizacion', 'gp_usuarios.USERNAME')
            ->where('gp_pedidos.id', $orden_despacho->pedido_id)->first()->toarray();

        $pedido = $this->orderService->getOrderInfo($pedido['id']);
        $pedido['orden_despacho']   = $orden_despacho;
        $orderModel                 = GpPedido::where('id', $pedido['id'])->first();
        $pedido['documentosPdf']    = $this->getAllDocumentsPdf($orderModel);
        return $pedido;
    }


    public function obtenetListadoPuntoVenta()
    {
        try {
            $items = $this->dispatchService->getAllPtoVta();
            return $this->successResponse('Información obtenida', $items, 200);
        } catch (\Throwable $e) {
            Log::error('Error obtenetListadoPuntoVenta', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'linea' => $e->getLine(),
            ]);
            return $this->buildErrorResponse('Error al obtener la información', 500);
        }
    }

    public function indexPuntosVentaReservaLimits()
    {
        try {
            $items = $this->dispatchService->getAllPtoVtaReserved();
            return $this->successResponse('Información obtenida', $items, 200);
        } catch (\Throwable $e) {
            Log::error('Error indexPuntosVentaReservaLimits', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'linea' => $e->getLine(),
            ]);
            return $this->buildErrorResponse('Error al obtener la información', 500);
        }
    }



    public function getDispatchedGuides(Request $request, $puntodeventa_id)
    {

        $perPage = $request->input('perPage', 10);
        $search = $request->input('search', null);
        $sortBy = $request->input('sortBy', null);
        $sortOrder = $request->input('sortOrder', 'asc');
        $fecha_desde = $request->input('fecha_desde', null);
        $fecha_hasta = $request->input('fecha_hasta', null);
        $clienteRuc = $request->input('cliente', null);

        return response()->json([
            'success' => true,
            'data' => $this->dispatchService->guiasDespachadas($puntodeventa_id, $perPage, $search, $sortBy, $sortOrder, $fecha_desde, $fecha_hasta, $clienteRuc),
        ]);
    }

    public function getDispatchedTransfers(Request $request, $puntodeventa_id)
    {
        $perPage = $request->input('perPage', 10);
        $search = $request->input('search', null);
        $sortBy = $request->input('sortBy', null);
        $sortOrder = $request->input('sortOrder', 'asc');
        $fecha_desde = $request->input('fecha_desde', null);
        $fecha_hasta = $request->input('fecha_hasta', null);
        $clienteRuc = $request->input('cliente', null);
        $ptovtaSearch = $request->input('ptovta', null);
        return response()->json([
            'success' => true,
            'data' => $this->dispatchService->transferenciasEnviadas($puntodeventa_id, $perPage, $search, $sortBy, $sortOrder, $fecha_desde, $fecha_hasta, $clienteRuc, $ptovtaSearch),
        ]);
    }

    public function getReceivedTransfers(Request $request, $puntodeventa_id)
    {

        $perPage = $request->input('perPage', 10);
        $search = $request->input('search', null);
        $sortBy = $request->input('sortBy', null);
        $sortOrder = $request->input('sortOrder', 'asc');
        $fecha_desde = $request->input('fecha_desde', null);
        $fecha_hasta = $request->input('fecha_hasta', null);
        $clienteRuc = $request->input('cliente', null);
        $ptovtaSearch = $request->input('ptovta', null);
        return response()->json([
            'success' => true,
            'data' => $this->dispatchService->transferenciasRecibidas($puntodeventa_id, $perPage, $search, $sortBy, $sortOrder, $fecha_desde, $fecha_hasta, $clienteRuc, $ptovtaSearch),
        ]);
    }

    public function getDispatchedRepositions(Request $request, $puntodeventa_id)
    {

        $perPage = $request->input('perPage', 10);
        $search = $request->input('search', null);
        $sortBy = $request->input('sortBy', null);
        $sortOrder = $request->input('sortOrder', 'asc');
        $fecha_desde = $request->input('fecha_desde', null);
        $fecha_hasta = $request->input('fecha_hasta', null);
        $clienteRuc = $request->input('cliente', null);
        $ptovtaSearch = $request->input('ptovta', null);

        return response()->json([
            'success' => true,
            'data' => $this->dispatchService->reposicionesEnviadas($puntodeventa_id, $perPage, $search, $sortBy, $sortOrder, $fecha_desde, $fecha_hasta, $clienteRuc, $ptovtaSearch),
        ]);
    }

    public function getReceivedRepositions(Request $request, $puntodeventa_id)
    {

        $perPage = $request->input('perPage', 10);
        $search = $request->input('search', null);
        $sortBy = $request->input('sortBy', null);
        $sortOrder = $request->input('sortOrder', 'asc');
        $fecha_desde = $request->input('fecha_desde', null);
        $fecha_hasta = $request->input('fecha_hasta', null);
        $clienteRuc = $request->input('cliente', null);
        $ptovtaSearch = $request->input('ptovta', null);
        return response()->json([
            'success' => true,
            'data' => $this->dispatchService->reposicionesRecibidas($puntodeventa_id, $perPage, $search, $sortBy, $sortOrder, $fecha_desde, $fecha_hasta, $clienteRuc, $ptovtaSearch),
        ]);
    }


    public function listaGuiaReservasEnviadas(Request $request)
    {
        $perPage = $request->input('perPage', 10);
        $search = $request->input('search', null);
        $sortBy = $request->input('sortBy', null);
        $sortOrder = $request->input('sortOrder', 'desc');
        $ptoVtaOrigin = $request->input('ptoVtaOrigin', null);
        $fecha_desde = $request->input('fecha_desde', null);
        $fecha_hasta = $request->input('fecha_hasta', null);
        $ptoVtaDestino = $request->input('ptoVtaDestino', null);

        try {
            $data = $this->dispatchService->fetchListSendReservations($perPage, $search, $sortBy, $sortOrder, $ptoVtaOrigin, $fecha_desde, $fecha_hasta, $ptoVtaDestino);
            return $this->successResponse('Guias obtenidas correctamente', $data, 200);
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage(), [], 500);
        }
    }

    public function listaGuiaReservasRecibidas(Request $request)
    {
        $perPage = $request->input('perPage', 10);
        $search = $request->input('search', null);
        $sortBy = $request->input('sortBy', null);
        $sortOrder = $request->input('sortOrder', 'desc');
        $ptoVtaOrigin = $request->input('ptoVtaOrigin', null);
        $fecha_desde = $request->input('fecha_desde', null);
        $fecha_hasta = $request->input('fecha_hasta', null);
        $ptoVtaDestino = $request->input('ptoVtaDestino', null);

        try {
            $data = $this->dispatchService->fecthListReceivedReservations($perPage, $search, $sortBy, $sortOrder, $ptoVtaOrigin, $fecha_desde, $fecha_hasta, $ptoVtaDestino);
            return $this->successResponse('Guias obtenidas correctamente', $data, 200);
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage(), [], 500);
        }
    }


    public function cancelarOrdenDespacho($id)
    {

        return $this->dispatchService->cancelDispatchIfEmptyPicking($id);
    }
}
