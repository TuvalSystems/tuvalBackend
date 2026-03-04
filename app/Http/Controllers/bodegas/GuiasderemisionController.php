<?php

namespace App\Http\Controllers\bodegas;

use App\Models\GpPedido;
use App\Models\GpPuntoVenta;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\GpOrdenDespacho;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\GpPedidoDetalle;
use App\Traits\JsonResponseTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\despachos\GpGuiaRemision;
use App\Models\traslados\GpTrasladosVentas;
use App\Models\reservas\GpGuiaRemisionReserva;
use Illuminate\Validation\ValidationException;
use App\Models\despachos\GpGuiaRemisionDetalle;
use App\Models\reposicion\GpTrasladosReposicion;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\ResponseFactory;
use App\Models\despachos\GpGuiaRemisionTransferencia;
use Symfony\Component\HttpKernel\Exception\HttpException;
use App\Services\Funciones\Contracts\InvoiceServiceInterface;
use App\Services\Devoluciones\Contracts\EntryServiceInterface;
use App\Services\Bodegas\Contracts\DeliveryNoteServiceInterface;
use App\Services\Bodegas\Contracts\DeliveryNoteDispatchServiceInterface;
use App\Services\Bodegas\Contracts\DeliveryNoteReplenishmentTransferServiceInterface;
use App\Services\Bodegas\Contracts\DeliveryNoteSalesTransferServiceInterface;
use App\Services\Bodegas\Contracts\ReservationDeliveryNoteServiceInterface;
use App\Traits\ExecuteApiTransactions;
use Illuminate\Http\JsonResponse;

class GuiasderemisionController extends Controller
{
    use JsonResponseTrait, ExecuteApiTransactions;
    public function __construct(
        protected readonly DeliveryNoteServiceInterface $deliveryNoteService,
        protected readonly InvoiceServiceInterface $invoiceService,
        protected readonly ReservationDeliveryNoteServiceInterface $reservedDeliveryNoteService,
        protected readonly DeliveryNoteDispatchServiceInterface $dispatch,
        protected readonly EntryServiceInterface $entryService,
        protected readonly DeliveryNoteSalesTransferServiceInterface $salesTransfer,
        protected readonly DeliveryNoteReplenishmentTransferServiceInterface $replenishment
    ) {}

    public function consultarGuiaGeneradaV1(Request $request): JsonResponse
    {
        return $this->executeTransaction(function () use ($request): JsonResponse {
            $data             = $request->get('id');
            $response          = $this->deliveryNoteService->consultExistenceGeneratedGuides($data);
            if ($response !== '') {
                return $this->errorResponse($response, [], 409);
            }
            return $this->buildSuccessResponse('Validación exitosa. No existen conflictos con otras guías.', 200);
        }, 'reservas_log', 'consultarGuiaGeneradaV1 -GuiasderemisionController');
    }

    public function checkguiaremision(): JsonResponse
    {
        return $this->executeTransaction(function (): JsonResponse {
            $this->deliveryNoteService->mapAssigmentNumberGuideRemision();
            return $this->buildSuccessResponse('Guias de remision actualizadas', 200);
        }, 'reservas_log', 'checkguiaremision -GuiasderemisionController');
    }

    public function imprimirguia($id): Response
    {

        $guia = GpGuiaRemision::where('id', $id)->first();

        if ($guia->tipo == 'despacho') {
            $pedido = GpPedido::where('pedido', $guia->pedido)->first();
            $orden = GpOrdenDespacho::where('pedido_id', $pedido->id)->first();

            if (!$orden) {
                $pedido = GpPedido::where('cotizacion_id', $pedido->cotizacion_id)->first();
                $orden = GpOrdenDespacho::where('pedido_id', $pedido->id)->first();
            }

            $guia->orden = $orden->orden;
            $guia->factura = $pedido->factura;
            $guia->orden_de_compra = $pedido->orden_de_compra;
        }


        $detalle = GpGuiaRemisionDetalle::where('guia_remision_id', $id)->get();
        $peso = 0;
        foreach ($detalle as $key => $det) {
            if ($guia->tipo == 'despacho') {
                $articulo = GpPedidoDetalle::where('id', $det->detalle_id)->first();
            } elseif ($guia->tipo == 'reposicion') {
                $articulo = GpTrasladosReposicion::where('id', $det->detalle_id)->first();
            } elseif ($guia->tipo == 'transferencia') {
                $articulo = GpTrasladosVentas::where('id', $det->detalle_id)->first();
            }
            if ($articulo) {
                if ($articulo->ART_TIPO_COMPRA == 'SERV') {
                    unset($detalle[$key]);
                }
                $peso += (float) number_format($articulo->ART_PESO * $det->GUI_REM_CANTIDAD, 2, '.', '');
            }
            $detTemp = GpPedidoDetalle::where('id', $det->detalle_id)->value('CHECK_ALTERNATIVO');

            $det['CHECK_ALTERNATIVO'] = $detTemp ?? 0;
        }

        $puntodeventa = GpPuntoVenta::where('PTO_VTA_ID', $guia->GUI_REM_ESTABLECIMIENTO)->first()->toarray();

        $data = array();



        $transferencias = GpGuiaRemisionTransferencia::where('guia_remision_id', $guia->id)->get()->toArray();

        $arr_trans = array();
        foreach ($transferencias as $trans) {
            $arr_trans[$trans['pedido']] = $trans['pedido'];
        }

        $data['peso'] = number_format($peso, 2, '.', '');
        $data['guia'] = $guia;
        $data['detalle'] = $detalle;
        $data['transferencias'] = $arr_trans;
        $data['PTO_VTA_DESCRIPCION'] = $puntodeventa['PTO_VTA_DESCRIPCION'];
        $data['direccion'] = $puntodeventa['PTO_VTA_DIRECCION_1'] . ' ' . $puntodeventa['PTO_VTA_DIRECCION_2'];
        //return view('guiaRemisionPDF', $data);
        $pdf = PDF::loadView('guiaRemisionPDF', $data);
        return $pdf->stream('guia_remision.pdf');
    }

    public function imprimirordenalmacenamiento(Request $request): Response
    {
        ini_set('max_execution_time', -1);
        set_time_limit(-1);
        ini_set('memory_limit', '-1');
        //demonios endemoniados
        $data = $request->all();
        $pdf = PDF::loadView('ordenAlmacenamientoPDF', $data);
        return $pdf->stream('orden_almacenamiento_' . $data['GUI_REM_GUIA_REMISION'] . '.pdf');
    }


    public function imprimirordenalmacenamientodevolucion(Request $request): Response
    {
        $data = $request->all();
        $pdf = PDF::loadView('ordenAlmacenamientoDevolucionPDF', $data);
        return $pdf->stream('orden_almacenamiento_' . $data['devolucion']['devolucion'] . '.pdf');
    }

    public function eliminarguia($id): Response|Application|ResponseFactory
    {
        $guia = GpGuiaRemision::where('id', $id)->first();

        $detalle = GpGuiaRemisionDetalle::where('guia_remision_id', $id)->get();

        if ($guia->tipo == 'transferencia') {
            foreach ($detalle as $item) {
                $traslado = GpTrasladosVentas::where('id', $item->detalle_id)->first();
                $traslado->reservado = $traslado->reservado - $item->GUI_REM_CANTIDAD;
                $traslado->save();
            }
        }

        if ($guia->tipo == 'reposicion') {
            foreach ($detalle as $item) {
                $traslado = GpTrasladosReposicion::where('id', $item->detalle_id)->first();
                $traslado->reservado = $traslado->reservado - $item->GUI_REM_CANTIDAD;
                $traslado->save();
            }
        }

        if ($guia->tipo == 'despacho') {
            foreach ($detalle as $item) {
                $traslado = GpPedidoDetalle::where('id', $item->detalle_id)->first();
                $traslado->reservado = $traslado->reservado - $item->GUI_REM_CANTIDAD;
                $traslado->save();
            }
        }

        $guia->estado = 'eliminada';
        $guia->save();

        return response([
            'success' => true,
            'message' => 'Guía de remisión eliminada',
        ]);
    }


    public function despacharguia(Request $request, $id)
    {
        $user = $request->user();
        try {
            // return DB::transaction(function () use ($user, $id) {
            $guia = GpGuiaRemision::whereKey($id)->lockForUpdate()->firstOrFail();

            // Re-chequea después del lock (otro proceso pudo enviarla)
            if ($guia->estado === 'enviada') {
                return $this->buildSuccessResponse('Guía de remisión despachada por otro usuario', 200);
            }
            $detalle = GpGuiaRemisionDetalle::where('guia_remision_id', $guia->id)->get();
            $response = null;

            switch ($guia->tipo) {
                case 'transferencia':
                    $response = $this->deliveryNoteService->dispatchGuideTransferShipment($guia, $detalle, $user);
                    break;
                case 'reposicion':
                    $response = $this->deliveryNoteService->dispatchGuideReplacementShipment($guia, $detalle, $user);
                    break;
                default:
                    throw new \Exception("Tipo de guía no reconocido: {$guia->tipo}");
            }
            return $response;
            // });
        } catch (HttpException $e) {
            return $this->buildErrorResponse(
                $e->getMessage(),
                $e->getStatusCode()
            );
        } catch (\Throwable $e) {
            Log::error('Error en despachar guia de remision (despacharguia)', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'guia_id' => $id
            ]);

            return $this->buildErrorResponse(
                $e->getMessage(),
                500
            );
        }
    }


    public function consultarDetalleGuiaPorId(Request $request): JsonResponse
    {
        return $this->executeTransaction(function () use ($request): JsonResponse {
            $data           = $request->all();
            $detalle        = $this->deliveryNoteService->getDeliveryNoteDetailByType($data['guia']);
            return $this->successResponse('Detalle de guia obtenido', $detalle, 200);
        }, 'reservas_log', 'consultarDetalleGuiaPorId -GuiasderemisionController');
    }


    public function consultarDetalleGuiaReservaPorId(Request $request): JsonResponse
    {
        return $this->executeTransaction(function () use ($request): JsonResponse {
            $id             = $request->get('id');
            $detalle        = $this->reservedDeliveryNoteService->getDetailReservationDeliveryNoteById($id);
            return $this->successResponse('Detalle de guia obtenido', $detalle, 200);
        }, 'reservas_log', 'consultarDetalleGuiaReservaPorId -GuiasderemisionController');
    }




    public function obtenerInfoGUiaRemisionAjustePedido($id): JsonResponse
    {
        try {
            $data = $this->deliveryNoteService->getDeliveryNoteAdjustmentInfoByDeliveryNoteId($id);
            return $this->successResponse('Información obtenida', $data, 200);
        } catch (\Throwable $th) {
            return $this->buildErrorResponse($th->getMessage(), 500);
        }
    }


    public function procesarPedidoDespachoPorGuia($id): JsonResponse
    {
        try {
            $pedido = $this->deliveryNoteService->processAjusteWevOrderGUideRemision($id);
            return $this->successResponse('Información obtenida', $pedido, 200);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }


    public function listaGuiaDespachoConAjuste(Request $request): JsonResponse
    {
        try {
            $fechaInicio = $request->get('fechaInicio');
            $fechaFin = $request->get('fechaFin');
            $pedido = $this->deliveryNoteService->getDeliveryNoteDispatchWithAjustment($fechaInicio, $fechaFin);
            return $this->successResponse('Información obtenida', $pedido, 200);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }


    public function listaGuiaTransferenciaReposicionConAjuste(Request $request): JsonResponse
    {
        return $this->executeTransaction(function () use ($request): JsonResponse {
            $fechaInicio    = $request->get('fechaInicio');
            $fechaFin       = $request->get('fechaFin');
            $estado         = $request->get('estado');
            $pedido         = $this->deliveryNoteService->getDeliveryNoteTransferAndRepositionAjustment($fechaInicio, $fechaFin, $estado);
            return $this->successResponse('Información obtenida', $pedido, 200);
        }, 'reservas_log', 'listaGuiaTransferenciaReposicionConAjuste -GuiasderemisionController');
    }

    /** proceso para reajuste de guia de transferencias  */
    public function procesarReajusteGuiaTransferencia(Request $request): JsonResponse
    {
        try {
            $id = $request->get('guiaId');
            $pedido = $request->get('pedido');
            $guia = $this->deliveryNoteService->processTransferGuideAdjustment($id, $pedido);
            return $this->successResponse('Reajuste procesado exitosamente para la guia de transferencia', $guia, 200);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }

    /** proceso para reajuste de guia de reposiciones */

    public function procesarReajusteGuiaReposicion(Request $request): JsonResponse
    {
        try {
            $id = $request->get('guiaId');
            $documento = $request->get('documento');
            $guia = $this->deliveryNoteService->processRepositionGuideAdjustment($id, $documento);
            return $this->successResponse('Reajuste procesado exitosamente para la guia de reposición', $guia, 200);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }



    /***
     *
     *
     *
     *
     * proceso de generacion de guia de remision de reservas
     *
     *
     *
     *
     * */


    public function crearGuiaRemisionReserva(Request $request): JsonResponse
    {
        return $this->executeTransaction(function () use ($request): JsonResponse {
            $payload    = $request->all();
            $response = $this->reservedDeliveryNoteService->createDeliveryNoteFromReservation($payload, $request->user());
            return $this->successResponse('Guia de Despacho creada exitosamente', $response, 200);
        }, 'reservas_log', 'generarGuiaReserva -GuiasderemisionController');
    }


    public function enviarGuiaRemisionReservaAGp($id): JsonResponse
    {
        return $this->executeTransaction(function () use ($id): JsonResponse {
            $guia = GpGuiaRemisionReserva::where('id', $id)->first();
            $response = $this->reservedDeliveryNoteService->sendReservationDeliveryNoteToGp($guia);
            return $this->successResponse('Guia de reserva creada exitosamente', $response, 200);
        }, 'reservas_log', 'enviarGuiaRemisionReservaAGp -GuiasderemisionController');
    }


    public function generarPdfGuiaRemisionReserva(Request $request)
    {
        try {
            $validated = $request->validate([
                'id' => 'required|integer|exists:gp_guia_remision_reservas,id',
            ]);
            $data = $this->reservedDeliveryNoteService->generatePdfReservedDeliveryNote($validated);
            return $this->buildPdfResponse($data);
        } catch (ValidationException $ve) {
            return $this->validationErrorResponse($ve->errors(), 'validation error', 422);
        } catch (\Throwable $e) {
            Log::channel('reservas_log')->error('Error en la funcion generarPdfGuiaRemisionReserva -GuiasderemisionController: ', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }


    public function despacharGuiaRemisionReserva(Request $request): JsonResponse
    {
        return $this->executeTransaction(function () use ($request): JsonResponse {
            $validated = $request->validate([
                'id' => 'required|integer|exists:gp_guia_remision_reservas,id',
            ]);
            $response   = $this->reservedDeliveryNoteService->dispatchGuideReservation($validated, $request->user());
            return $this->successResponse('Guia de reserva Despachada', $response, 200);
        }, 'reservas_log', 'despacharGuiaRemisionReserva -GuiasderemisionController');
    }

    public function recibirItemsGuiaReserva(Request $request): JsonResponse
    {
        return $this->executeTransaction(function () use ($request): JsonResponse {
            $guiaId     = $request->input('guiaId', null);
            $items      = $request->input('items', null);
            $user       = $request->user();

            $data   = $this->reservedDeliveryNoteService->receiveItemsFromTransferred($guiaId, $items, $user);
            return $this->successResponse('Proceso realizado correctamente', $data, 200);
        }, 'reservas_log', 'recibirItemsGuiaReserva -ReservasController');
    }


    /**
     *
     *  PROCESO DE CREACION DE GUIA DE DESPACHO
     */

    public function crearGuiaRemisionDespacho(Request $request): JsonResponse
    {
        return $this->executeTransaction(function () use ($request): JsonResponse {
            $payload    = $request->all();
            $response   = $this->dispatch->createDeliveryNoteFromDispatch($payload, $request->user());
            return $this->successResponse('Guia de Despacho creada exitosamente', $response, 200);
        }, 'despachos', 'crearGuiaRemisionDespacho -GuiasderemisionController');
    }

    public function despacharGuiaRemisionDespacho(Request $request): JsonResponse
    {
        return $this->executeTransaction(function () use ($request): JsonResponse {
            $id         = $request->input('id');
            $response   = $this->dispatch->dispatchGuideTransferShipment($id, $request->user());
            $tocoServ   = data_get($response, 'tocoServ', false);
            $mensaje = 'Guía de remisión despachada';
            if ($tocoServ) {
                $mensaje = 'Guía de remisión despachada, Sin items para despachar';
            }
            return $this->successResponse($mensaje, $response, 200);
        }, 'despachos', 'despacharGuiaRemisionDespacho -GuiasderemisionController');
    }





    /***
     * PROCESO DE CREACION DE GUIA DE VENTA
     */

    public function crearGuiaRemisionVenta(Request $request): JsonResponse
    {
        return $this->executeTransaction(function () use ($request): JsonResponse {
            $payload    = $request->all();
            $user       = $request->user();
            $response   = $this->salesTransfer->createDeliveryNoteSalesTransfer($payload, $user);
            return $this->successResponse('Guia de Transferencia de Venta creada exitosamente', $response, 200);
        }, 'guia_ventas', 'crearGuiaRemisionVenta -GuiasderemisionController');
    }


    public function recibirGuiaRemisionVenta(Request $request): JsonResponse
    {
        return $this->executeTransaction(function () use ($request): JsonResponse {
            $payload    = $request->all();
            $user       = $request->user();
            $response   = $this->salesTransfer->receiveDeliveryNoteSalesTransfer($payload, $user);
            return $this->buildSuccessResponse($response, 200);
        }, 'guia_ventas', 'recibirGuiaRemisionVenta -GuiasderemisionController');
    }

    /**
     * PROCESO DE CREACION DE GUIA DE REPOSICION
     */

    public function crearGuiaRemisionReposicion(Request $request): JsonResponse
    {
        return $this->executeTransaction(function () use ($request): JsonResponse {
            $payload    = $request->all();
            $user       = $request->user();
            $response   = $this->replenishment->createDeliveryNoteReplenishmentTransfer($payload, $user);
            return $this->successResponse('Guia de Reposicion creada exitosamente', $response, 200);
        }, 'guia_ventas', 'crearGuiaRemisionReposicion -GuiasderemisionController');
    }

    public function recibirGuiaRemisionReposicion(Request $request)
    {
        return $this->executeTransaction(function () use ($request): JsonResponse {
            $payload    = $request->all();
            $user       = $request->user();
            $response   = $this->replenishment->receiveDeliveryNoteReplenishmentTransfer($payload, $user);
            return $this->buildSuccessResponse($response, 200);
        }, 'guia_ventas', 'recibirGuiaRemisionReposicion');
    }
}
