<?php

namespace App\Http\Controllers\ventas;

use App\Enums\Ventas\OrderStatus;
use App\Models\GpPedido;
use App\Models\GpArticulo;
use App\Services\Funciones\Contracts\FunctionsServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Models\GpOrdenDespacho;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\GpClienteSucursal;
use App\Traits\JsonResponseTrait;
use App\Services\Api\ApiGpService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Venta\Pedidos\ValidateStoreOrderRequest;
use App\Models\traslados\GpTrasladosVentas;
use App\Models\devoluciones\GpDevoluciones;
use App\Models\GpUsuario;
use App\Services\Funciones\Contracts\InvoiceServiceInterface;
use App\Services\Devoluciones\Contracts\ReInvoiceServiceInterface;
use App\Services\Venta\Contracts\OrderCreatorServiceInterface;
use App\Services\Venta\Contracts\OrderServiceInterface;
use App\Traits\ExecuteApiTransactions;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PedidosController extends Controller
{
    use JsonResponseTrait, ExecuteApiTransactions;

    public function __construct(
        protected readonly ApiGpService $apiGp,
        protected readonly OrderServiceInterface $orderService,
        protected readonly InvoiceServiceInterface $invoiceService,
        protected readonly FunctionsServiceInterface $functionService,

        //prueba
        protected readonly ReInvoiceServiceInterface $reInvoice,
        protected readonly OrderCreatorServiceInterface $orderCreator
    ) {}



    /** proceso de guardar pedido v2 */
    public function storeOrderV2(ValidateStoreOrderRequest $request)
    {
        $imagenesExistentes = $request->input('imagenes_existentes', []);
        $imagenesNuevas     = $request->file('imagenes_nuevas', []);
        $pdfsNuevos         = $request->file('pdfs_nuevos', []);
        $pdfsExistentes     = $request->input('pdfs_existentes', []);
        $maxRetries         = 3;
        $attempt            = 0;
        $user               = $request->user();
        while (true) {
            try {
                $pedido         =  $this->orderCreator->createOrder(
                    $request->validated(),
                    $user,
                    $imagenesExistentes,
                    $imagenesNuevas,
                    $pdfsExistentes,
                    $pdfsNuevos
                );
                $order          = data_get($pedido, 'pedido');
                $invoiceError   = data_get($pedido, 'invoice_error');
                if ($invoiceError) {
                    $msg = "Pedido #{$order->pedido} generado con éxito, pero no se pudo procesar la factura. Detalle: {$invoiceError}";
                    return $this->buildSuccessResponse($msg, 201);
                }
                return $this->buildSuccessResponse("Pedido {$order->pedido} guardado", 200);
            } catch (QueryException $e) {
                $mysqlCode = $e->errorInfo[1] ?? null;
                // ¿es un error de concurrencia y aún puedo reintentar?
                if (in_array($mysqlCode, [1213, 1205]) && $attempt < $maxRetries) {
                    $attempt++;
                    usleep(200000);
                    Log::warning('Deadlock/lock wait guardarpedido, reintentando...', [
                        'attempt' => $attempt,
                        'mysqlCode' => $mysqlCode,
                        'msg' => $e->getMessage(),
                    ]);

                    // pasa al siguiente ciclo del while
                    continue;
                }

                // si no es 1213/1205 o ya superó los reintentos, lo tratamos como error real
                Log::error('Error in storeOrderV2 (guardar pedido)', [
                    'error'     => $e->getMessage(),
                    'line'      => $e->getLine(),
                    'file'      => $e->getFile(),
                    'trace'     => $e->getTraceAsString(),
                ]);
                return $this->buildErrorResponse('No se pudo guardar el pedido, intente de nuevo.', 500);
            } catch (DomainException $e) {
                // 👈 este es el que puede lanzar tu flujo de stock
                return $this->buildErrorResponse($e->getMessage(), 422);
            } catch (\Throwable $e) {
                // cualquier otra excepción que no sea de SQL
                Log::error('Error in storeOrderV2 (guardar pedido)', [
                    'error'     => $e->getMessage(),
                    'line'      => $e->getLine(),
                    'file'      => $e->getFile(),
                    'trace'     => $e->getTraceAsString(),
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
        $enviando = GpPedido::where('estado', OrderStatus::ENVIANDO)
            ->where('updated_at', '<=', Carbon::now()->subMinutes(5)->toDateTimeString())->get();

        foreach ($enviando as $pedido) {
            $this->invoiceService->respuestaFacturaVenta($pedido);
        }
    }

    public function sendfacturasgp()
    {
        try {
            $this->orderService->sendInvoicesToGp();
            return $this->buildSuccessResponse("Proceso de facturas ejecutado", 200);
        } catch (\Throwable $e) {
            Log::error('Error critico en sendInvoicesToGp', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->buildErrorResponse($e->getMessage(), 500);
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
            ->select('gp_pedidos.*', 'gp_cotizacions.cotizacion', 'gp_cotizacions.origen as cotizacion_origen');

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

        $pedidos = $pedidos->orderBy('gp_pedidos.created_at', 'desc')->limit(1000)->get();

        foreach ($pedidos as $pedido) {

            // $pedido->direccion_entrega = GpClienteSucursal::where('id', $pedido->sucursal_id)->first();

            if ($pedido->requiere_aprobacion_comercial == 'yes' && $pedido->aprobado_comercial == 'no') {
                $pedido->imprimir = 'no';
            } elseif ($pedido->requiere_aprobacion_crediticia == 'yes' && $pedido->aprobado_crediticio == 'no') {
                $pedido->imprimir = 'no';
            } elseif ($pedido->estado ===  OrderStatus::RECHAZADO) {
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
            $result = $this->orderService->detailOrderById($data, $request->user());
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
            $validated = $request->validate([
                'pedido'  => 'required|array',
                'detalle' => 'required|array',
            ]);
            $pedidoId = data_get($validated, 'pedido.id');
            $user = $request->user();
            //new sintaxis
            $pedido = DB::transaction(
                fn() =>
                $this->orderService->generateInvoiceFromExistingOrder($validated['pedido'], $validated['detalle'], $user)
            );

            return $this->buildSuccessResponse('Pedido Generado Correctamente' . ' ' . $pedido->pedido . ' ' . ' en Facturar Existente', 200);
        } catch (HttpException $e) {
            return $this->buildErrorResponse(
                $e->getMessage(),
                $e->getStatusCode()
            );
        } catch (\Throwable $e) {
            Log::error('Error in facturarexistente', [
                'error' => $e->getMessage(),
                'data' => $pedidoId,
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
    }

    public function corregirpedidos()
    {
        $pedidos = GpPedido::whereNotNull('pedido_origen')->get();
        foreach ($pedidos as $pedido) {
            $this->functionService->recalculateOrder($pedido->id);
        }
    }



    public function volverAPedirPedido(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'id' => 'required|exists:gp_pedidos,id',
            ]);
            $user = $request->user() ?? GpUsuario::find(179);
            $response = $this->orderService->loadOrderForCopy($request->get('id'),  $user);
            return $this->successResponse('Pedido copiado correctamente', $response, 200);
        } catch (\Throwable $e) {
            Log::error('Error en volverAPedirPedido', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTrace(),
                'data' => $request->all(),
                'exception' => $e,
            ]);
            return $this->buildErrorResponse('Datos inválidos para volver a pedir el pedido: ' . $e->getMessage(), 500);
        }
    }



    public function copiarCotizacionOPedido(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'rowId' => 'required|integer',
                'sucursal_id' => 'required|integer',
                'cliente_id' => 'required|integer',
                'tipo' => 'required|string',
            ]);
            $user = $request->user();
            $response = $this->orderService->copyQuoteOrOrder($request->input('rowId'), $request->input('sucursal_id'), $request->input('cliente_id'), $request->input('tipo'), $user);
            return $this->successResponse('Pedido/Cotización copiado correctamente', $response, 200);
        } catch (\Throwable $e) {
            Log::error('Error en copiarCotizacionOPedido', [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTrace(),
                'data' => $request->all(),
                'exception' => $e,
            ]);
            return $this->buildErrorResponse('Datos inválidos para copiar el pedido o cotizacion: ' . $e->getMessage(), 500);
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
        } catch (\Throwable $e) {
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
                throw new \Exception('No se pudo generar el XML.');
            }

            $nombreArchivo = 'factura_' . $xmlContent['factura'] . '.xml';

            return response($xmlContent['xml'], 200)
                ->header('Content-Type', 'application/xml')
                ->header('Content-Disposition', 'attachment; filename="' . $nombreArchivo . '"');
        } catch (\Throwable $th) {
            Log::error('Error en imprimirpedidoxml', [
                'error' => $th->getMessage(),
                'linea' => $th->getLine(),
                'pedido_id' => $id,
            ]);
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





    public function volveraFacuturarTest($devolucionId)
    {
        try {
            $devolucion = GpDevoluciones::where('id', $devolucionId)->first();
            $newOrd = DB::transaction(fn() => $this->reInvoice->execute($devolucion));
            return $this->successResponse('Información Obtenida Correctamente', $newOrd, 200);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }


    function cancelarpedido(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        $data = $request->all();
        $user = $request->user();

        $pedido = GpPedido::where('id', $data['id'])->get()->first();

        if ($pedido->estado === OrderStatus::FACTURADO) {
            return response([
                'success' => true,
                'message' => 'Pedido ' . $pedido->pedido . ' ya está facturado y no se puede eliminar',
            ]);
        }

        $pedido->estado = OrderStatus::ELIMINADO;
        $pedido->USERID_cancelado = $user->USERID;
        $pedido->fecha_hora_cancelado = date('Y-m-d H:i:s');
        $pedido->save();

        GpTrasladosVentas::where('pedido_id', $pedido->id)->update(
            [
                'cantidad' => 0
            ]
        );
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
            $user = $request->user();
            $order = $this->orderService->updateOrderAndBlockDispach($id, $pendingDispach, $user);
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


    public function crearTrasladoPedidos($pedido)
    {
        return $this->executeTransaction(function () use ($pedido) {

            $pedido = $this->orderService->createTransferVtaOrders($pedido);
            return $this->successResponse('Traslado creado correctamente', $pedido, 200);
        }, 'laravel', 'crearTrasladoPedidos');
    }
}
