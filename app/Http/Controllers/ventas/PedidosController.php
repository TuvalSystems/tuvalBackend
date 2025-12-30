<?php

namespace App\Http\Controllers\ventas;

use App\Models\GpPedido;
use App\Models\GpCliente;
use App\Models\GpArticulo;
use App\Services\Funciones\Contracts\FunctionsServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Models\GpOrdenDespacho;
use App\Models\GpPedidoDetalle;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\GpClienteSucursal;
use App\Traits\JsonResponseTrait;
use App\Services\Api\ApiGpService;
use App\Http\Controllers\Controller;
use App\Models\GpPedidoDetalleStock;
use Illuminate\Support\Facades\Auth;
use App\Models\GpPedidoDetalleProveedores;
use App\Models\traslados\GpTrasladosVentas;
use App\Http\Controllers\FuncionesController;
use App\Http\Controllers\WebserviceController;
use App\Models\devoluciones\GpDevolucionesDetalle;
use App\Models\devoluciones\GpDevoluciones;
use App\Models\GpUsuario;
use App\Services\Funciones\Contracts\InvoiceServiceInterface;
use App\Services\Aprobaciones\Contracts\ApprovalServiceInterface;
use App\Services\Devoluciones\Contracts\ReInvoiceServiceInterface;
use App\Services\Venta\Contracts\OrderServiceInterface;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PedidosController extends Controller
{
    use JsonResponseTrait;

    public function __construct(
        protected readonly ApiGpService $apiGp,
        protected readonly OrderServiceInterface $orderService,
        protected readonly ApprovalServiceInterface $approvalService,
        protected readonly InvoiceServiceInterface $invoiceService,
        protected readonly FunctionsServiceInterface $functionService,

        //prueba
        protected readonly ReInvoiceServiceInterface $reInvoice
    ) {
    }


    //en proceso de mejora
    public function guardarpedido(Request $request)
    {
        $orderData = $request->all();
        $user = Auth::user();
        $maxRetries = 3;
        $attempt = 0;
        while (true) {
            try {
                $pedido = DB::transaction(function () use ($orderData, $user) {
                    return $this->orderService->storeOrder($orderData, $user);
                }, 5);
                return $this->buildSuccessResponse("Pedido {$pedido->pedido} guardado", 200);
            } catch (QueryException $e) {
                $mysqlCode = $e->errorInfo[1] ?? null;
                // ¿es un error de concurrencia y aún puedo reintentar?
                if (in_array($mysqlCode, [1213, 1205]) && $attempt < $maxRetries) {
                    $attempt++;

                    Log::warning('Deadlock/lock wait guardarpedido, reintentando...', [
                        'attempt' => $attempt,
                        'mysqlCode' => $mysqlCode,
                        'msg' => $e->getMessage(),
                    ]);

                    // pasa al siguiente ciclo del while
                    continue;
                }

                // si no es 1213/1205 o ya superó los reintentos, lo tratamos como error real
                Log::error('Error in storeOrder (guardar pedido)', [
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'file' => $e->getFile(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return $this->buildErrorResponse('No se pudo guardar el pedido, intente de nuevo.', 500);

            } catch (DomainException $e) {
                // 👈 este es el que puede lanzar tu flujo de stock
                return $this->buildErrorResponse($e->getMessage(), 422);
            } catch (\Throwable $e) {
                // cualquier otra excepción que no sea de SQL
                Log::error('Error in storeOrder (guardar pedido)', [
                    'error' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'file' => $e->getFile(),
                    'trace' => $e->getTraceAsString(),
                ]);

                return $this->buildErrorResponse($e->getMessage(), 500);
            }
        }
    }

    public function facturarpedido(Request $request)
    {
        $data = $request->validate([
            'id' => 'required',
        ]);
        try {
            $order = DB::transaction(function () use ($data) {
                return $this->orderService->generateInvoiceOrder($data['id']);
            });
            Log::info("Pedido {$order->pedido} facturado desde Facturar Pedido - generateInvoiceOrder - facturarpedido");
            return $this->buildSuccessResponse("Pedido {$order->pedido} facturado", 200);
        } catch (HttpException $e) {
            return $this->buildErrorResponse(
                $e->getMessage(),
                $e->getStatusCode()
            );
        } catch (\Throwable $th) {
            return $this->buildErrorResponse($th->getMessage(), 500);
        }
    }

    public function facturarpedidoTest($codPedido)
    {
        $pedido = GpPedido::where('pedido', $codPedido)->first();

        return $this->orderService->generateInvoiceOrder($pedido->id);
    }



    public function generarordendespacho($id)
    {
        $pedido = GpPedido::where('id', $id)
            ->first();
        $this->invoiceService->generateDisptachOrder($pedido);
    }





    public function corregirenviando(): void
    {

        $webservices = new WebserviceController();
        $enviando = GpPedido::where('estado', 'enviando')
            ->where('updated_at', '<=', Carbon::now()->subMinutes(5)->toDateTimeString())->get();

        foreach ($enviando as $pedido) {
            $this->invoiceService->respuestaFacturaVenta($pedido);
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

            // $this->checkenviarfactura($pedido);
            $this->invoiceService->checkSendInvoice($pedido);
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
            if ($listo == 'yes') {
                $pedido->estado = 'espera';
                $pedido->save();
                $this->invoiceService->generateDisptachOrder($pedido);
                $this->invoiceService->sendInvoiceGp($pedido);
            }
        }
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

        $pedidos = $pedidos->orderBy('gp_pedidos.created_at', 'desc')->limit(500)->get();

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
        try {
            $data = $request->all();
            $result = $this->orderService->detailOrderById($data);
            return response()->json([
                'success' => true,
                ...$result
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener detalle del pedido: ' . $e->getMessage()
            ], 500);
        }
    }


    public function consultarexistente(Request $request)
    {
        try {
            $data = $request->all();
            $pedido = $this->orderService->checkExistence($data);
            return $this->successResponse('Existencia Obtenida Correctamente', $pedido, 200);
        } catch (HttpException $e) {
            return $this->buildErrorResponse(
                $e->getMessage(),
                $e->getStatusCode()
            );
        } catch (\Throwable $e) {
            Log::error('Error en checkExistence', ['exception' => $e]);

            return $this->buildErrorResponse($e->getMessage(), 500);
        }

    }

    public function facturarexistente(Request $request)
    {
        try {
            $facturarExistente = $request->all();
            $data = $facturarExistente['pedido'];
            $detalle = $facturarExistente['detalle'];
            $user = Auth::user();
            //new sintaxis
            $pedido = DB::transaction(
                fn() =>
                $this->orderService->generateInvoiceFromExistingOrder($data, $detalle, $user)
            );

            //old sintaxis
            return $this->buildSuccessResponse('Pedido Generado Correctamente' . ' ' . $pedido->pedido . ' ' . ' en Facturar Existente', 200);
        } catch (HttpException $e) {
            return $this->buildErrorResponse(
                $e->getMessage(),
                $e->getStatusCode()
            );
        } catch (\Throwable $e) {
            Log::error('Error in facturarexistente', [
                'error' => $e->getMessage(),
                'data' => $data['id'],
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTrace()
            ]);
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }






    public function recalcularpedido($id): void
    {
        $this->functionService->recalculateOrder($id);
        // $funciones = new FuncionesController();
        // $pedido = GpPedido::where('id', $id)->first();
        // $funciones->recalcularpedido($pedido);
    }

    public function corregirpedidos()
    {
        $pedidos = GpPedido::whereNotNull('pedido_origen')->get();
        foreach ($pedidos as $pedido) {
            // $this->recalcularpedido($pedido->id);
            $this->functionService->recalculateOrder($pedido->id);
        }
    }

    public function recuperarpedido(Request $request)
    {
        $data = $request->all();
        $user = Auth::user();
        return $this->orderService->restoreOrder($data, $user);
    }

    public function copiarpedido(Request $request)
    {
        $data = $request->all();
        $user = Auth::user();
        return $this->orderService->copyOrder($data, $user);
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
        } catch (\Throwable $e) {
            // report($e);
            return $this->buildErrorResponse($e->getMessage(), 404);
        }
    }

    public function imprimirpedidoridesinimagen($id)
    {
        try {
            $pedido = $this->orderService->generateInvoiceRide($id);

            if (is_string($pedido)) {
                abort(403, $pedido); // Ej: 'No permitido'
            }
            $pdf = PDF::loadView('pedidoridesinImagenPDF', $pedido);
            return $pdf->stream('ride-' . $pedido['pedido'] . '.pdf');
        } catch (\Throwable $e) {
            Log::error('Error en imprimirpedidoridesinimagen', [
                $e->getMessage()
            ]);
            return $this->buildErrorResponse($e->getMessage(), 404);
        }
    }

    public function imprimirpedidoxml($id)
    {
        try {
            $xmlContent = $this->orderService->generateInvoiceXml($id);

            if (empty($xmlContent['xml']) || empty($xmlContent['factura'])) {
                return $this->buildErrorResponse('No se pudo generar el XML.', 422);
            }

            $nombreArchivo = 'factura_' . $xmlContent['factura'] . '.xml';

            return response($xmlContent['xml'], 200)
                ->header('Content-Type', 'application/xml')
                ->header('Content-Disposition', 'attachment; filename="' . $nombreArchivo . '"');
        } catch (\Throwable $th) {
            return $this->buildErrorResponse($th->getMessage(), 500);
        }
    }
    public function despachoenproceso($id)
    {
        try {
            $order = $this->orderService->verifyOrderDispatchOrItemLRecived($id);
            return $this->successResponse('Información Obtenida Correctamente', $order, 200);
        } catch (\Throwable $th) {
            Log::error('Error al obtener el despacho en proceso: ', [
                'error' => $th->getMessage(),
                'pedido_id' => $id,
                'linea' => $th->getLine()
            ]);
            return $this->buildErrorResponse('Opps! Error al obtener el despacho en proceso', 500);
        }
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




    /** migrar mas adelante */

    // public function volveraFacuturarTest($devolucionId)
    // {
    //     try {
    //         DB::beginTransaction();
    //         $devolucion = GpDevoluciones::where('id', $devolucionId)->first();
    //         $this->volverafacturar($devolucion);
    //         DB::commit();
    //         return response()->json(['status' => 'success']);
    //     } catch (\Throwable $e) {
    //         DB::rollBack();
    //         return response()->json(['status' => 'error', 'message' => $e->getMessage()]);
    //     }
    // }


    public function volverAFacturarPrueba($devolucionId){
        try {
             $devolucion = GpDevoluciones::where('id', $devolucionId)->first();
             $newOrd = DB::transaction(fn ()=> $this->reInvoice->execute($devolucion));
             return $this->successResponse('Información Obtenida Correctamente', $newOrd, 200);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }



    // public function volverafacturar($devolucion)
    // {
    //     $pedido = GpPedido::where('id', $devolucion->pedido_id)->first();
    //     $despachada = $this->orderService->checkOutValidateDispatchReInvoice($devolucion);
    //     $cliente = GpCliente::where('id', $devolucion->cliente_volver_a_facturar_id)->first();
    //     $detalle = GpPedidoDetalle::where('pedido_id', $pedido->id)->get();

    //     $nuevo_pedido = $pedido->replicate();

    //     $ultimoPedido = GpPedido::orderBy('id', 'DESC')->first();

    //     $nuevo_pedido->pedido = 'PED' . str_pad($ultimoPedido->id + 1, 8, "0", STR_PAD_LEFT);

    //     $nuevo_pedido->factura = null;
    //     $nuevo_pedido->factura_origen = $pedido->factura;
    //     $nuevo_pedido->fecha_factura = null;
    //     $nuevo_pedido->estado = 'espera';
    //     $nuevo_pedido->sucursal_id = $devolucion->sucursal_id;
    //     $nuevo_pedido->sucursal_facturacion_id = $devolucion->sucursal_facturacion_id;
    //     $nuevo_pedido->cliente_id = $devolucion->cliente_volver_a_facturar_id;
    //     $nuevo_pedido->orden_de_compra = $devolucion->orden_de_compra;
    //     $nuevo_pedido->nota_cliente = $devolucion->nota_cliente;

    //     $nuevo_pedido->CLI_RUC = $cliente->CLI_RUC;
    //     $nuevo_pedido->CLI_RAZON_SOCIAL = $cliente->CLI_RAZON_SOCIAL;
    //     $nuevo_pedido->CLI_CORREO_ELECT_SRI = $cliente->CLI_CORREO_ELECT_SRI;

    //     $nuevo_pedido->factura_siguiente_mes = 0;

    //     if ($cliente->CLI_FACTURA_POSTERIOR_AL_DESPACHO == 'SI') {
    //         $nuevo_pedido->factura_posterior_despacho = 1;
    //         if ($cliente->CLI_DIA_MAXIMO_MES_FACTURACION == 1) {
    //             $nuevo_pedido->factura_a_demanda = 1;
    //         } elseif (date('d') > $cliente->CLI_DIA_MAXIMO_MES_FACTURACION) {
    //             $nuevo_pedido->factura_siguiente_mes = 1;
    //         }
    //     }

    //     if ($despachada) {
    //         $nuevo_pedido->despachado = 1;
    //     }

    //     $nuevo_pedido->save();

    //     foreach ($detalle as $det) {
    //         $nuevo_det = $det->replicate();
    //         $nuevo_det->pedido_id = $nuevo_pedido->id;
    //         $nuevo_det->devuelto = null;

    //         if ($devolucion->tipo_devolucion == 'parcial') {
    //             $articulo_devolucion = GpDevolucionesDetalle::where('detalle_id', $det->id)
    //                 ->where('devolucion_id', $devolucion->id)
    //                 ->first();
    //             if ($articulo_devolucion) {
    //                 if ($despachada) {  //&& $devolucion->tipo_devolucion == 'total'
    //                     $nuevo_det->ART_CANTIDAD = $articulo_devolucion->disponible - $articulo_devolucion->recibido;
    //                 } else {
    //                     $nuevo_det->ART_CANTIDAD = $articulo_devolucion->disponible - $articulo_devolucion->seleccionado;
    //                 }
    //             } else {
    //                 $nuevo_det->ART_CANTIDAD = null;
    //             }
    //         } else {
    //             $articulo_devolucion = GpDevolucionesDetalle::where('detalle_id', $det->id)
    //                 ->where('devolucion_id', $devolucion->id)
    //                 ->first();
    //             if ($articulo_devolucion) {
    //                 $nuevo_det->ART_CANTIDAD = $articulo_devolucion->disponible;
    //             } else {
    //                 $nuevo_det->ART_CANTIDAD = null;
    //             }
    //         }

    //         if (!$nuevo_det->ART_CANTIDAD)
    //             continue;

    //         $nuevo_det->ART_IMP_VTA = GpArticulo::where('ART_CODIGO', $nuevo_det->ART_CODIGO)->first()->ART_IMP_VTA;

    //         $nuevo_det->save();

    //         $detalle_proveedores = GpPedidoDetalleProveedores::where('pedido_id', $pedido->id)
    //             ->where('detalle_id', $det->id)
    //             ->get();

    //         foreach ($detalle_proveedores as $proveedor) {
    //             $nuevo_proveedor = $proveedor->replicate();
    //             $nuevo_proveedor->pedido_id = $nuevo_pedido->id;
    //             $nuevo_proveedor->detalle_id = $nuevo_det->id;
    //             $nuevo_proveedor->save();

    //             $parameters = array(
    //                 'fecha' => date('Y/m/d'),
    //                 'item' => $nuevo_det->ART_CODIGO,
    //                 'bodega' => $nuevo_pedido->BOD_BODEGA,
    //                 'cantidad' => $nuevo_proveedor->CANTIDAD,
    //                 'origen' => $pedido->pedido,
    //                 'destino' => $nuevo_pedido->pedido,
    //             );
    //             // $webservices->inventario('TransferenciaCompartimento', $parameters);
    //             $resp = $this->apiGp->transferCompartment($parameters);
    //             Log::channel('devoluciones')->info('TransferenciaCompartimento', $resp);
    //         }

    //         $detalle_stocks = GpPedidoDetalleStock::where('pedido_id', $pedido->id)
    //             ->where('detalle_id', $det->id)
    //             ->get();

    //         foreach ($detalle_stocks as $stock) {
    //             $nuevo_stock = $stock->replicate();
    //             $nuevo_stock->pedido_id = $nuevo_pedido->id;
    //             $nuevo_stock->detalle_id = $nuevo_det->id;

    //             if ($devolucion->tipo_devolucion == 'parcial' && $nuevo_stock->STO_ART_BODEGA == $pedido->BOD_BODEGA) {
    //                 $articulo_devolucion = GpDevolucionesDetalle::where('detalle_id', $det->id)
    //                     ->where('devolucion_id', $devolucion->id)
    //                     ->first();
    //                 if ($articulo_devolucion) {
    //                     $nuevo_stock->seleccionado = $articulo_devolucion->disponible - $articulo_devolucion->seleccionado;
    //                     $nuevo_stock->reservado = $nuevo_stock->seleccionado;
    //                 }
    //             } elseif ($devolucion->tipo_devolucion == 'total') {
    //                 $nuevo_stock->reservado = $nuevo_stock->seleccionado;
    //             }
    //             $nuevo_stock->save();
    //         }
    //     }

    //     // $funciones = new FuncionesController();
    //     // $funciones->recalcularpedido($nuevo_pedido);
    //     $this->functionService->recalculateOrder($nuevo_pedido->id);


    //     // $this->checkenviarfactura($nuevo_pedido);
    //     $this->invoiceService->checkSendInvoice($nuevo_pedido);
    // }

    function cancelarpedido(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $data = $request->all();
        $user = GpUsuario::where('id', Auth::user()->id)->first();

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




    public function getListPedidosSinCoordenadas()
    {
        return $this->orderService->getLisOrdersWithoutCoordinated();
    }


    public function buscarIteml($artCode)
    {
        $item = GpArticulo::where('ART_CODIGO', $artCode)->first()->toArray();
        return $this->orderService->buscarItemlTest($item);
    }

    public function generarOrdenDeDespacho($id)
    {
        return $this->orderService->generateOrderDispatch($id);
    }


    public function recalcularPedidoTest($id)
    {

        $pedido = GpPedido::where('id', $id)->get()->first();
        $this->functionService->recalculateOrder($pedido->id);
    }


    public function facturarsiguientemes()
    {
        try {
            $resultado = $this->orderService->generateNextMonthInvoice();
            if ($resultado['abortado']) {
                return $this->buildSuccessResponse($resultado['razon'], 200);
            }
            // ✅ Manejar errores parciales
            if ($resultado['fallidos'] > 0) {
                return $this->buildSuccessResponse(
                    "Proceso completado con errores: {$resultado['exitosos']} exitosos, {$resultado['fallidos']} fallidos",
                    200,
                );
            }
            return $this->buildSuccessResponse(
                "Todos los pedidos facturados: {$resultado['exitosos']} procesados",
                200,
            );
        } catch (\Throwable $e) {
            Log::channel('cron_process')->error("❌ Error crítico en proceso de facturación masiva", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->buildErrorResponse(
                "Error crítico en el proceso: {$e->getMessage()}",
                500
            );
        }
    }


    /** funcion para actualizar un pedido  */

    public function actualizarPedidoYRecalcular($id)
    {
        return $this->orderService->updateOrderAndRecalculate($id);
    }

    /** funcion para el cambio de orden de compra y nota cliente por pediod */
    public function actualizarOrdenCompraYNotaCliente(Request $request)
    {
        $id = $request->input('id');
        $newOrd = $request->input('orden_de_compra' ?? '');
        $newNote = $request->input('nota_cliente' ?? '');
        try {
            $data = $this->orderService->syncPurchaseOrderAndNoteByOrder($id, $newOrd, $newNote);
            return $this->successResponse('Datos actualizados Correctamente', $data, 200);
        } catch (\Throwable $e) {
            Log::error('Error al actualizar los datos del pedido: ', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->buildErrorResponse('Opps! Error al actualizar los datos del pedido', 500);
        }
    }


    public function actualizarFechaReservaPedido(Request $request)
    {

        $validate = $request->validate([
            'id' => 'required|integer',
            'fe_facturacion_reserva' => 'required|date_format:Y-m-d',
        ]);

        $id = data_get($validate, 'id');
        $newDate = data_get($validate, 'fe_facturacion_reserva');
        try {
            $data = $this->orderService->updateOrderReservationDate($id, $newDate);
            return $this->successResponse('Fecha de reserva actualizada Correctamente', $data, 200);
        } catch (\Throwable $e) {
            Log::error('Error al actualizar la fecha de reserva del pedido: ', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->buildErrorResponse('Opps! Error al actualizar la fecha de reserva del pedido', 500);
        }
    }



    /** funcion de lisras de pedido reservas  */


    public function listarPedidosReservas(Request $request): JsonResponse
    {

        $perPage = $request->input('perPage', 10);
        $search = $request->input('search', null);
        $sortBy = $request->input('sortBy', null);
        $sortOrder = $request->input('sortOrder', 'desc');
        $ptoVtaId = $request->input('ptoVtaId', null);

        try {
            $data = $this->orderService->fetchOrderFromReservations($perPage, $search, $sortBy, $sortOrder, $ptoVtaId);
            return $this->successResponse('Lista de pedidos de reservas obtenida correctamente', $data, 200);
        } catch (\Throwable $th) {
            return $this->buildErrorResponse($th->getMessage(), 500);
        }
    }


    public function actualizarBloqueoPendienteDespacho(Request $request)
    {

        try {
            $id = $request->input('id');
            $pendingDispach = $request->input('pendiente_salida');
            $order = $this->orderService->updateOrderAndBlockDispach($id, $pendingDispach);
            return $this->successResponse('Bloqueo actualizado correctamente', $order, 200);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);

        }
    }

    public function validacionExistenciaGuiasEnPedidos(Request $request)
    {
        try {
            $id = $request->input('id', null);
            $hasDispatchs = $this->orderService->hasDeliveryNoteAjusted($id);
            if ($hasDispatchs) {
                return $this->buildExistResponse($hasDispatchs, 'Ya cuenta con guias de despacho', 200);
            }
            return $this->buildExistResponse($hasDispatchs, 'No cuenta con guias de despacho', 200);
        } catch (HttpException $e) {
            return $this->buildErrorResponse(
                $e->getMessage(),
                $e->getStatusCode()
            );
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }
}
