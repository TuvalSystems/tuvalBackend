<?php

namespace App\Http\Controllers\Ventas\Reservas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Venta\Reservas\ValidateCancelReservationRequest;
use App\Services\Aprobaciones\Contracts\ApprovalReservationServiceInterface;
use App\Services\Venta\Contracts\InventoryReservationServiceInterface;
use App\Services\Venta\Utils\Contracts\OrderAndQuotationServiceInterface;
use App\Traits\ExecuteApiTransactions;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ReservasController extends Controller
{
    use JsonResponseTrait, ExecuteApiTransactions;
    public function __construct(
        protected readonly InventoryReservationServiceInterface $reserveService,
        protected readonly ApprovalReservationServiceInterface $aprovalReservation,
        protected readonly OrderAndQuotationServiceInterface $orderAndQuotation
    ) {}


    public function validarGeneracionReservaCotizacion(int $id): JsonResponse
    {
        return $this->handleApiAction(function () use ($id): JsonResponse {
            $existeReserva = $this->reserveService->hasAnyReserveState($id);
            if ($existeReserva) {
                return $this->buildExistResponse($existeReserva, 'No es posible recuperar, copiar o volver a pedir la cotización porque cuenta con una reserva', 200);
            }
            return $this->buildExistResponse($existeReserva, 'La cotización no tiene reservas registradas. Puede continuar con su actividad', 202);
        }, 'reservas_log',  'validarGeneracionReservaCotizacion');
    }


    public function cancelarReservaCotizacion(ValidateCancelReservationRequest $request): JsonResponse
    {

        return $this->handleApiAction(function () use ($request): JsonResponse {
            $data      = $request->validated();
            $reservasId = data_get($data, 'id', null);
            $reserva = $this->reserveService->cancelReservedQuotation($reservasId);
            return $this->successResponse('Reserva cancelada correctamente', $reserva, 200);
        }, 'reservas_log',  'cancelarReservaCotizacion');
    }

    public function verificarStockCotizacionActual(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
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
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $gpUser     = $request->user();
            $reserva    = $request->get('reserva', null);
            $quote = $this->reserveService->saveQuotationHeaderReserved($reserva, $gpUser);
            return $this->buildSuccessResponse("Solicitud de reserva guardada correctamente {$quote->cotizacion}", 200);
        }, 'reservas_log',  'guardarReservaCotización');
    }

    public function obtenerListaTipoReservas(): JsonResponse
    {
        return $this->handleApiAction(function (): JsonResponse {
            $data = $this->reserveService->fetchListTipeReserved();
            return $this->successResponse('Tipos de reservas obtenidos correctamente', $data, 200);
        }, 'reservas_log',  'obtenerListaTipoReservas');
    }



    public function obtenerListaReservasPendientes(Request $request): JsonResponse
    {

        return $this->handleApiAction(function () use ($request) {
            $perPage        = $request->input('perPage', 10);
            $search         = $request->input('search', null);
            $sortBy         = $request->input('sortBy', null);
            $sortOrder      = $request->input('sortOrder', 'desc');
            $tipoReserva    = $request->input('tipoReserva', null);
            $ptoVtaId       = $request->input('ptovta', null);
            $clienteRuc     = $request->input('cliente', null);
            $respuesta = $this->reserveService->fetchListReservedPending($perPage, $search, $sortBy, $sortOrder, $tipoReserva, $ptoVtaId, $clienteRuc);
            return $this->successResponse(
                'Reservas pendientes obtenidos correctamente',
                $respuesta,
                200
            );
        }, 'reservas_log',  'obtenerListaReservasPendientes');
    }


    public function obtenerCotizacionConDetalle(int $id): JsonResponse
    {
        return $this->handleApiAction(function () use ($id): JsonResponse {
            return $this->successResponse(
                'Detalle obtenido correctamente',
                $this->reserveService->getQuotationWithDetails($id),
                200
            );
        }, 'reservas_log',  'obtenerCotizacionConDetalle');
    }

    public function obtenerCotizacionDetalleConStock(int $id): JsonResponse
    {
        return $this->handleApiAction(function () use ($id): JsonResponse {
            return $this->successResponse(
                'Cotizacion obtenida correctamente',
                $this->reserveService->getQuotationWithDetailsAndStock($id),
                200
            );
        }, 'reservas_log',  'obtenerCotizacionDetalleConStock');
    }

    public function obtenerCotizacionConDetalleYStockActivo(int $id): JsonResponse
    {
        return $this->handleApiAction(function () use ($id): JsonResponse {
            $data = $this->reserveService->getQuotationWithDetailsAndActiveStockById($id);
            return $this->successResponse('Cotizacion obtenida correctamente', $data, 200);
        }, 'reservas_log', 'obtenerCotizacionConDetalleYStockActivo');
    }

    public function consultarReservaAprobarRechazar(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request) {
            $request->validate([
                'id' => 'required|integer|exists:gp_reservas,id',
            ]);
            $alert = $this->aprovalReservation->checkValidateApprovalReservation($request->id);
            if ($alert) {
                return $this->buildExistResponse(false, $alert, 200);
            }
            return $this->buildExistResponse(true, 'La reserva cuenta con Stock Disponible', 200);
        }, 'reservas_log', 'consultarReservaAprobarRechazar');
    }


    public function rechazarSolicitudReservaCotizacion(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
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
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $gpUser     = $request->user();
            $data       = $request->input('data', null);
            $reserva    = DB::transaction(function () use ($data, $gpUser) {
                return $this->reserveService->approvalApplicationReservedQuotation($data, $gpUser);
            });
            $alerta = data_get($reserva, 'alerta', null);
            if ($alerta) {
                return $this->buildExistResponse(false, $alerta, 200);
            }
            $msg = "Reserva autorizada correctamente";
            return $this->successResponse($msg, $reserva, 200);
        }, 'reservas_log', 'aprobarSolicitudReservaCotizacion');
    }


    public function obtenerListaCotizacionesAprobadas(Request $request)
    {
        return $this->handleApiAction(function () use ($request) {
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
            return $this->successResponse(
                'Cotizaciones obtenidas correctamente',
                $this->reserveService->fetchListApprovedQuotation($perPage, $search, $sortBy, $sortOrder, $fecha_desde, $fecha_hasta, $estado, $ptoVtaId, $clienteRuc, $user),
                200
            );
        }, 'reservas_log', 'obtenerListaCotizacionesAprobadas');
    }



    public function procesarReservaCotizacion(Request $request)
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $data       = $request->input('data', null);
            $gpUser     = $request->user();
            $response   = $this->reserveService->processReservedQuotation($data, $gpUser);
            $cot        = data_get($response, 'cotizacion', null);
            $msg        = $cot === null ? "Reserva procesada correctamente" : "Reserva procesada correctamente, cotización generada:  {$cot}";

            return $this->successResponse($msg, $cot, 200);
        }, 'reservas_log', 'procesarReservaCotizacion');
    }


    public function procesarReservaFacturaDemandaCotizacion(Request $request)
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $data = $request->input('reserva', null);
            $gpUser = $request->user();
            $response = DB::transaction(function () use ($data, $gpUser) {
                return $this->reserveService->processReservedQuotationInvoiceDemand($data, $gpUser);
            });
            $alerta = data_get($response, 'alertMessage', null);
            if ($alerta) {
                return $this->buildExistResponse(false, $alerta, 200);
            }
            $pedidoNombre = data_get($response, 'order.pedido', 'N/A');
            $msg = "Pedido {$pedidoNombre} generado correctamente.";
            return $this->successResponse($msg, $response, 200);
        }, 'reservas_log', 'procesarReservaFacturaDemandaCotizacion');
    }


    public function recalcularCotizacionReserva(int $id)
    {
        return $this->handleApiAction(function () use ($id) {
            return $this->successResponse(
                'Cotizacion recalcular correctamente',
                $this->orderAndQuotation->recalculateQuotation($id),
                200
            );
        }, 'reservas_log', 'recalcularCotizacionReserva');
    }


    /**
     *  Funcion para obtener la lista de transferencias por reserva pero son el contador por bodega y peso por cotización
     *
     */
    public function TransferenciasPorReservaDashboard(string $ptovtaId): JsonResponse
    {
        return $this->handleApiAction(function () use ($ptovtaId): JsonResponse {
            $data   = $this->reserveService->fetchReservedTransferSummaries($ptovtaId);
            return $this->successResponse('Dashboard obtenido correctamente', $data, 200);
        }, 'reservas_log',  'TransferenciasPorReservaDashboard');
    }

    public function obtenerDetalleCabeceraTransferenciasReservaPorBodega(Request $request)
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $ptoVtaOrigen   = $request->input('origen', null);
            $ptoVtaDestino  = $request->input('destino', null);
            $data           = $this->reserveService->fectchReservedTransferDetailsHeader($ptoVtaOrigen, $ptoVtaDestino);
            return $this->successResponse('Cabeceras transferencias obtenidas correctamente', $data, 200);
        }, 'reservas_log',  'obtenerDetalleCabeceraTransferenciasReservaPorBodega');
    }

    public function ListaTransferenciasReservaPorBodega(Request $request)
    {
        return $this->handleApiAction(function () use ($request) {
            $ptoVtaOrigen   = $request->input('origen', null);
            $ptoVtaDestino  = $request->input('destino', null);
            $quotation      = $request->input('cotizacion', null);
            $data           = $this->reserveService->fetchReservedTransfersByDestinationWarehouses($ptoVtaOrigen, $ptoVtaDestino, $quotation);
            return $this->successResponse('Lista transferencias obtenidas correctamente', $data, 200);
        }, 'reservas_log', 'ListaTransferenciasReservaPorBodega');
    }




    public function actualizacionPickingItemsTraslados(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $items = $request->input('items', null);
            $affected = $this->reserveService->updatePickingItemsSelected($items);
            if ($affected === 0) {
                // No hubo cambios (ya estaban seleccionados, por ejemplo)
                return $this->successResponse('No hubo cambios en el picking.', 0, 200);
            }
            return $this->successResponse('Picking actualizado correctamente', $affected, 200);
        }, 'reservas_log',  'actualizacionPickingItemsTraslados');
    }

    public function cancelacionPickingItemTraslados(Request $request)
    {
        return $this->handleApiAction(function () use ($request) {
            $item = $request->input('row', null);
            $affected = $this->reserveService->cancelPickingOneItemSelected($item);
            if ($affected === 0) {
                // No hubo cambios (ya estaban seleccionados, por ejemplo)
                return $this->buildSuccessResponse('No hubo cambios en el picking.', 200);
            }
            return $this->buildSuccessResponse('Picking actualizado correctamente', 200);
        }, 'reservas_log', 'cancelacionPickingItemTraslados');
    }

    public function generarPdfPickingReserva(Request $request): Response
    {
        return $this->handleDocumentAction(function () use ($request): Response {
            $validated = $request->validate([
                'origen' => ['required', 'string', 'min:1'],
                'destino' => ['required', 'string', 'min:1'],
                'pesototal' => ['nullable', 'string'], // o 'string' si lo envías formateado
                'items' => ['required', 'array', 'min:1'],
                // 'items.*.id' => ['required', 'integer', 'distinct', 'exists:gp_traslados_reservas,id'],
                // 'items.*.seleccionado' => ['required', 'numeric', 'min:0.001'],
            ]);
            $data = $this->reserveService->generatePickingPdf($validated);
            return $this->buildFileResponse($data);
        }, 'reservas_log', 'generarPdfPickingReserva');
    }


    public function listaVehiculosTransporte(): JsonResponse
    {
        return $this->handleApiAction(function (): JsonResponse {
            $data = $this->reserveService->fetchVehicles();
            return $this->successResponse('Datos obtenidos correctamente', $data, 200);
        }, 'reservas_log', 'listaVehiculosTransporte');
    }





    /** listado de guias de remision pendientes para realizar el Ej:TRA000 */
    public function listaGuiaRemisionReservasPendientes(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $ptoVtaOrigen   = $request->input('origen', null);
            $ptoVtaDestino  = $request->input('destino', null);
            $response       = $this->reserveService->fetchPendingReservationdeliveryNotes($ptoVtaOrigen, $ptoVtaDestino);
            return $this->successResponse('Datos obtenidos correctamente', $response, 200);
        }, 'reservas_log', 'listaGuiaRemisionReservasPendientes');
    }




    /*** proceso de recibir traslados de reservas */

    public function listaTransferenciasReservasPorRecibir(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request) {
            $ptoVta     = $request->input('ptovta', null);
            $perpage    = $request->input('perpage', 10);
            $search     = $request->input('search', "");
            $sortBy     = $request->filled('sortBy') ? $request->input('sortBy') : 'fecha_salida';
            $sortOrder  = $request->filled('sortOrder') ? $request->input('sortOrder') : 'desc';
            $data       = $this->reserveService->paginateTransfersToReceive($ptoVta, $perpage, $search, $sortBy, $sortOrder);
            return $this->successResponse('Información Obtenida Correctamente', $data, 200);
        }, 'reservas_log', 'listaTransferenciasReservasPorRecibir');
    }


    public function detalleTransferenciaReservasPorRecibir(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $id     = $request->input('id', null);
            $data   = $this->reserveService->getTransferToReceiveDetails($id);
            return $this->successResponse('Información Obtenida Correctamente', $data, 200);
        }, 'reservas_log', 'detalleTransferenciaReservasPorRecibir');
    }

    /** proceso de detalle Imprimir Orden de almacenamiento de recibir una gua de reserva */

    public function imprimirOrdAlmacenamiento(Request $request): Response
    {
        return $this->handleDocumentAction(function () use ($request): Response {
            $validated = $request->validate([
                'id' => 'required|integer|exists:gp_guia_remision_reservas,id',
            ]);
            $data = $this->reserveService->printOrderStorageReceivedTranfered($validated);
            return $this->buildFileResponse($data);
        }, 'reservas_log', 'detalleTransferenciaReservasPorRecibir');
    }
}
