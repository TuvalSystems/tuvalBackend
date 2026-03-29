<?php

namespace App\Http\Controllers\ventas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Venta\Reservas\ValidateCancelReservationRequest;
use App\Services\Aprobaciones\Contracts\ApprovalReservationServiceInterface;
use App\Services\Venta\Contracts\InventoryReservationServiceInterface;
use App\Traits\ExecuteApiTransactions;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ReservasController extends Controller
{
    use JsonResponseTrait, ExecuteApiTransactions;
    public function __construct(
        protected readonly InventoryReservationServiceInterface $reserveService,
        protected readonly ApprovalReservationServiceInterface $aprovalReservation
    ) {}


    public function validarGeneracionReservaCotizacion($id): JsonResponse
    {
        return $this->executeTransaction(function () use ($id): JsonResponse {
            $existeReserva = $this->reserveService->hasAnyReserveState($id);
            if ($existeReserva) {
                return $this->buildExistResponse($existeReserva, 'No es posible recuperar, copiar o volver a pedir la cotización porque cuenta con una reserva', 200);
            }
            return $this->buildExistResponse($existeReserva, 'La cotización no tiene reservas registradas. Puede continuar con su actividad', 202);
        }, 'reservas_log',  'validarGeneracionReservaCotizacion');
    }


    public function cancelarReservaCotizacion(ValidateCancelReservationRequest $request): JsonResponse
    {

        return $this->executeTransaction(function () use ($request): JsonResponse {
            $data      = $request->validated();
            $reservasId = data_get($data, 'id', null);
            $reserva = $this->reserveService->cancelReservedQuotation($reservasId);
            return $this->successResponse('Reserva cancelada correctamente', $reserva, 200);
        }, 'reservas_log',  'cancelarReservaCotizacion');
    }

    public function verificarStockCotizacionActual(Request $request): JsonResponse
    {
        return $this->executeTransaction(function () use ($request): JsonResponse {
            $validated = $request->validate([
                'id' => 'required|integer',
            ]);
            $id = data_get($validated, 'id');
            $response = $this->reserveService->validateAndProcessQuotationStock($id);
            if ($response) {
                return $this->buildExistResponse(false, $response, 400);
            }
            return $this->buildExistResponse(true, 'Cuentas con stock disponible', 200);
        }, 'reservas_log',  'verificarStockCotizacionActual');
    }


    public function guardarReservaCotización(Request $request): JsonResponse
    {
        return $this->executeTransaction(function () use ($request): JsonResponse {
            $gpUser     = $request->user();
            $reserva    = $request->get('reserva', null);
            $this->reserveService->saveQuotationHeaderReserved($reserva, $gpUser);
            return $this->buildSuccessResponse('Solicitud de reserva guardada correctamente', 200);
        }, 'reservas_log',  'guardarReservaCotización');
    }

    public function obtenerListaTipoReservas(): JsonResponse
    {
        return $this->executeTransaction(function (): JsonResponse {
            $data = $this->reserveService->fetchListTipeReserved();
            return $this->successResponse('Tipos de reservas obtenidos correctamente', $data, 200);
        }, 'reservas_log',  'obtenerListaTipoReservas');
    }



    public function obtenerListaReservasPendientes(Request $request): JsonResponse
    {
        try {
            $perPage        = $request->input('perPage', 10);
            $search         = $request->input('search', null);
            $sortBy         = $request->input('sortBy', null);
            $sortOrder      = $request->input('sortOrder', 'desc');
            $tipoReserva    = $request->input('tipoReserva', null);
            $ptoVtaId       = $request->input('ptovta', null);
            $clienteRuc     = $request->input('cliente', null);
            return $this->successResponse('Reservas pendientes obtenidos correctamente', $this->reserveService->fetchListReservedPending($perPage, $search, $sortBy, $sortOrder, $tipoReserva, $ptoVtaId, $clienteRuc), 200);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }


    public function obtenerCotizacionConDetalle($id): JsonResponse
    {
        return $this->executeTransaction(function () use ($id): JsonResponse {
            return $this->successResponse('Detalle obtenido correctamente', $this->reserveService->getQuotationWithDetails($id), 200);
        }, 'reservas_log',  'obtenerCotizacionConDetalle');
    }

    public function obtenerCotizacionDetalleConStock($id): JsonResponse
    {
        return $this->executeTransaction(function () use ($id): JsonResponse {
            return $this->successResponse('Cotizacion obtenida correctamente', $this->reserveService->getQuotationWithDetailsAndStock($id), 200);
        }, 'reservas_log',  'obtenerCotizacionDetalleConStock');
    }

    public function obtenerCotizacionConDetalleYStockActivo($id): JsonResponse
    {
        try {
            return $this->successResponse('Cotizacion obtenida correctamente', $this->reserveService->getQuotationWithDetailsAndActiveStockById($id), 200);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }

    public function consultarReservaAprobarRechazar(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'id' => 'required|integer|exists:gp_reservas,id',
            ]);

            $alert = $this->aprovalReservation->checkValidateApprovalReservation($request->id);
            if ($alert) {
                return $this->buildExistResponse(false, $alert, 200);
            }
            return $this->buildExistResponse(true, 'La reserva cuenta con Stock Disponible', 200);
        } catch (\Throwable $th) {
            return $this->buildErrorResponse($th->getMessage(), 500);
        }
    }


    public function rechazarSolicitudReservaCotizacion(Request $request): JsonResponse
    {
        return $this->executeTransaction(function () use ($request): JsonResponse {
            $gpUser     = $request->user();
            $data       = $request->all();
            DB::transaction(function () use ($data, $gpUser) {
                return $this->reserveService->denyApplicationReservedQuotation($data['data'], $gpUser);
            });
            return $this->buildSuccessResponse("Reserva rechazada correctamente", 200);
        }, 'reservas_log', 'rechazarSolicitudReservaCotizacion');
    }

    public function aprobarSolicitudReservaCotizacion(Request $request)
    {
        return $this->executeTransaction(function () use ($request): JsonResponse {
            $gpUser     = $request->user();
            $data       = $request->all();
            $reserva    = DB::transaction(function () use ($data, $gpUser) {
                return $this->reserveService->approvalApplicationReservedQuotation($data['data'], $gpUser);
            });
            $msg = "Reserva autorizada correctamente";
            return $this->successResponse($msg, $reserva, 200);
        }, 'reservas_log', 'aprobarSolicitudReservaCotizacion');
    }


    public function obtenerListaCotizacionesAprobadas(Request $request)
    {
        try {
            $perPage        = $request->input('perPage', 10);
            $search         = $request->input('search', null);
            $sortBy         = $request->input('sortBy', null);
            $sortOrder      = $request->input('sortOrder', 'desc');
            $fecha_desde    = $request->input('fecha_desde', null);
            $fecha_hasta    = $request->input('fecha_hasta', null);
            $estado         = $request->input('status', null);
            $ptoVtaId       = $request->input('ptovta', null);
            $clienteRuc     = $request->input('cliente', null);
            $user           = $request->user();
            return $this->successResponse('Cotizaciones obtenidas correctamente', $this->reserveService->fetchListApprovedQuotation($perPage, $search, $sortBy, $sortOrder, $fecha_desde, $fecha_hasta, $estado, $ptoVtaId, $clienteRuc, $user), 200);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }



    public function procesarReservaCotizacion(Request $request)
    {
        return $this->executeTransaction(function () use ($request): JsonResponse {
            $data       = $request->all();
            $gpUser     = $request->user();
            $response   = $this->reserveService->processReservedQuotation($data['data'], $gpUser);
            $cot        = data_get($response, 'cotizacion', null);
            $msg        = $cot === null ? "Reserva procesada correctamente" : "Reserva procesada correctamente, cotización generada:  {$cot}";

            return $this->successResponse($msg, $cot, 200);
        }, 'reservas_log', 'procesarReservaCotizacion');
    }


    public function procesarReservaFacturaDemandaCotizacion(Request $request)
    {
        $data = $request->all();
        $gpUser = $request->user();
        try {
            $response = DB::transaction(function () use ($data, $gpUser) {
                return $this->reserveService->processReservedQuotationInvoiceDemand($data['reserva'], $gpUser);
            });
            $msg = "Pedido {$response['pedido']} generado.";
            return $this->successResponse($msg, $response, 200);
        } catch (HttpException $e) {
            return $this->buildErrorResponse(
                $e->getMessage(),
                $e->getStatusCode()
            );
        } catch (\DomainException $e) {
            return $this->buildErrorResponse($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Log::channel('reservas_log')->error('Error en la funcion procesarReservaFacturaDemandaCotizacion -ReservasController: ', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'data' => $data
            ]);
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }


    public function recalcularCotizacionReserva($id)
    {
        try {
            return $this->successResponse('Cotizacion recalcular correctamente', $this->reserveService->recalculateQuotation($id), 200);
        } catch (\Throwable $th) {
            return $this->buildErrorResponse($th->getMessage(), 500);
        }
    }


    /**
     *  Funcion para obtener la lista de transferencias por reserva pero son el contador por bodega y peso por cotización
     *
     */
    public function TransferenciasPorReservaDashboard($ptovtaId)
    {
        try {
            $data = $this->reserveService->fetchReservedTransferSummaries($ptovtaId);
            return $this->successResponse('Cotizaciones obtenidas correctamente', $data, 200);
        } catch (\Throwable $e) {
            Log::channel('reservas_log')->error('Error en la funcion TransferenciasPorReservaDashboard -ReservasController: ', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }

    public function obtenerDetalleCabeceraTransferenciasReservaPorBodega(Request $request)
    {
        try {
            $ptoVtaOrigen = $request->input('origen', null);
            $ptoVtaDestino = $request->input('destino', null);
            $data = $this->reserveService->fectchReservedTransferDetailsHeader($ptoVtaOrigen, $ptoVtaDestino);
            return $this->successResponse('Cabeceras transferencias obtenidas correctamente', $data, 200);
        } catch (\Throwable $e) {
            Log::channel('reservas_log')->error('Error en la funcion transferenciasReservaPorBodega -ReservasController: ', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }

    public function ListaTransferenciasReservaPorBodega(Request $request)
    {
        try {
            $ptoVtaOrigen = $request->input('origen', null);
            $ptoVtaDestino = $request->input('destino', null);
            $quotation = $request->input('cotizacion', null);
            $data = $this->reserveService->fetchReservedTransfersByDestinationWarehouses($ptoVtaOrigen, $ptoVtaDestino, $quotation);
            return $this->successResponse('Lista transferencias obtenidas correctamente', $data, 200);
        } catch (\Throwable $e) {
            Log::channel('reservas_log')->error('Error en la funcion transferenciasReservaPorBodega -ReservasController: ', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }




    public function actualizacionPickingItemsTraslados(Request $request)
    {
        try {

            $items = $request->input('items', null);
            $affected = $this->reserveService->updatePickingItemsSelected($items);
            if ($affected === 0) {
                // No hubo cambios (ya estaban seleccionados, por ejemplo)
                return $this->successResponse('No hubo cambios en el picking.', 0, 200);
            }
            return $this->successResponse('Picking actualizado correctamente', $affected, 200);
        } catch (\Throwable $e) {
            Log::channel('reservas_log')->error('Error en la funcion actualizacionPickingItemsTraslados -ReservasController: ', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }

    public function cancelacionPickingItemTraslados(Request $request)
    {
        try {
            $item = $request->input('row', null);
            $affected = $this->reserveService->cancelPickingOneItemSelected($item);
            if ($affected === 0) {
                // No hubo cambios (ya estaban seleccionados, por ejemplo)
                return $this->buildSuccessResponse('No hubo cambios en el picking.', 200);
            }
            return $this->buildSuccessResponse('Picking actualizado correctamente', 200);
        } catch (\Throwable $e) {
            Log::channel('reservas_log')->error('Error en la funcion cancelacionPickingItemTraslados -ReservasController: ', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }

    public function generarPdfPickingReserva(Request $request)
    {
        try {
            $validated = $request->validate([
                'origen' => ['required', 'string', 'min:1'],
                'destino' => ['required', 'string', 'min:1'],
                'pesototal' => ['nullable', 'string'], // o 'string' si lo envías formateado
                'items' => ['required', 'array', 'min:1'],
                'items.*.id' => ['required', 'integer', 'distinct', 'exists:gp_traslados_reservas,id'],
                'items.*.seleccionado' => ['required', 'numeric', 'min:0.001'],
            ]);
            $data = $this->reserveService->generatePickingPdf($validated);
            return $this->buildPdfResponse($data);
        } catch (\Illuminate\Validation\ValidationException $ve) {
            return $this->errorResponse('validation error', $ve->errors(), 422);
        } catch (\Throwable $e) {
            Log::channel('reservas_log')->error('Error en la funcion generarPdfPickingReserva -ReservasController: ', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }


    public function listaVehiculosTransporte(): JsonResponse
    {
        try {
            $data = $this->reserveService->fetchVehicles();
            return $this->successResponse('Datos obtenidos correctamente', $data, 200);
        } catch (\Throwable $e) {
            log::channel('reservas_log')->error('Error en la funcion listaTransportesYVehiculos -ReservasController: ', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }





    /** listado de guias de remision pendientes para realizar el Ej:TRA000 */
    public function listaGuiaRemisionReservasPendientes(Request $request): JsonResponse
    {
        $ptoVtaOrigen = $request->input('origen', null);
        $ptoVtaDestino = $request->input('destino', null);
        try {
            $response = $this->reserveService->fetchPendingReservationdeliveryNotes($ptoVtaOrigen, $ptoVtaDestino);
            return $this->successResponse('Datos obtenidos correctamente', $response, 200);
        } catch (RuntimeException $r) {
            return $this->buildErrorResponse($r->getMessage(), 409);
        } catch (\Throwable $e) {
            Log::channel('reservas_log')->error('Error en la funcion listaGuiaRemisionReservasPendientes: ', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'linea' => $e->getLine(),
                'file' => $e->getFile()
            ]);
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }




    /*** proceso de recibir traslados de reservas */

    public function listaTransferenciasReservasPorRecibir(Request $request)
    {
        $ptoVta = $request->input('ptovta', null);
        $perpage = $request->input('perpage', 10);
        $search = $request->input('search', "");
        $sortBy = $request->filled('sortBy') ? $request->input('sortBy') : 'fecha_salida';
        $sortOrder = $request->filled('sortOrder') ? $request->input('sortOrder') : 'desc';
        try {
            $data = $this->reserveService->paginateTransfersToReceive($ptoVta, $perpage, $search, $sortBy, $sortOrder);
            return $this->successResponse('Información Obtenida Correctamente', $data, 200);
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage(), [], 500);
        }
    }


    public function detalleTransferenciaReservasPorRecibir(Request $request)
    {
        $id = $request->input('id', null);
        try {
            $data = $this->reserveService->getTransferToReceiveDetails($id);
            return $this->successResponse('Información Obtenida Correctamente', $data, 200);
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage(), [], 500);
        }
    }

    /** proceso de detalle Imprimir Orden de almacenamiento de recibir una gua de reserva */

    public function imprimirOrdAlmacenamiento(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|integer|exists:gp_guia_remision_reservas,id',
        ]);
        try {
            $data = $this->reserveService->printOrderStorageReceivedTranfered($validated);
            return $this->buildPdfResponse($data);
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage(), [], 500);
        }
    }
}
