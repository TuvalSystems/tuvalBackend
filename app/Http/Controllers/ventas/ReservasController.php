<?php

namespace App\Http\Controllers\ventas;

use App\Http\Controllers\Controller;
use App\Models\GpUsuario;
use App\Services\Venta\Contracts\InventoryReservationServiceInterface;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ReservasController extends Controller
{
    use JsonResponseTrait;
    protected $reserveService;
    public function __construct(InventoryReservationServiceInterface $reserveService)
    {
        $this->reserveService = $reserveService;
    }


    public function validarGeneracionReservaCotizacion($id)
    {
        try {
            $existeReserva = $this->reserveService->hasAnyReserveState($id);
            if ($existeReserva) {
                return $this->buildExistResponse($existeReserva, 'No es posible recuperar, copiar o volver a pedir la cotización porque cuenta con una reserva', 200);
            }
            return $this->buildExistResponse($existeReserva, 'La cotización no tiene reservas registradas. Puede continuar con su actividad', 202);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }


    public function guardarReservaCotización(Request $request)
    {
        $gpUser = GpUsuario::find(Auth::user()->id);
        $reserva = $request->get('reserva', null);
        try {
            return DB::transaction(function () use ($reserva, $gpUser) {
                $this->reserveService->saveQuotationHeaderReserved($reserva, $gpUser);
                return $this->buildSuccessResponse('Cotizacion guardada correctamente', 200);
            });
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }

    public function obtenerListaTipoReservas()
    {
        try {
            $data = $this->reserveService->fetchListTipeReserved();
            return $this->successResponse('Tipos de reservas obtenidos correctamente', $data, 200);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }



    public function obtenerListaReservasPendientes(Request $request)
    {
        try {
            $perPage = $request->input('perPage', 10);
            $search = $request->input('search', null);
            $sortBy = $request->input('sortBy', null);
            $sortOrder = $request->input('sortOrder', 'desc');
            $tipoReserva = $request->input('tipoReserva', null);
            $ptoVtaId = $request->input('ptovta', null);
            $clienteRuc = $request->input('cliente', null);
            return $this->successResponse('Reservas pendientes obtenidos correctamente', $this->reserveService->fetchListReservedPending($perPage, $search, $sortBy, $sortOrder, $tipoReserva, $ptoVtaId, $clienteRuc), 200);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }


    public function obtenerCotizacionConDetalle($id)
    {
        try {
            return $this->successResponse('Cotizacion obtenida correctamente', $this->reserveService->getQuotationWithDetails($id), 200);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }

    public function obtenerCotizacionDetalleConStock($id)
    {
        try {
            return $this->successResponse('Cotizacion obtenida correctamente', $this->reserveService->getQuotationWithDetailsAndStock($id), 200);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }

    public function obtenerCotizacionConDetalleYStockActivo($id)
    {
        try {
            return $this->successResponse('Cotizacion obtenida correctamente', $this->reserveService->getQuotationWithDetailsAndActiveStockById($id), 200);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }


    public function rechazarSolicitudReservaCotizacion(Request $request)
    {
        try {
            $gpUser = GpUsuario::find(Auth::user()->id);
            $data = $request->all();
            $reserva = DB::transaction(function () use ($data, $gpUser) {
                return $this->reserveService->denyApplicationReservedQuotation($data['data'], $gpUser);
            });
            $msg = "Reserva rechazada correctamente:  {$reserva['id']}";
            return $this->buildSuccessResponse($msg, 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->buildErrorResponse('Cotización no encontrada', 404);
        } catch (\DomainException $e) { // por si tu service valida estados
            return $this->buildErrorResponse($e->getMessage(), 409);
        } catch (\Throwable $e) {

            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }

    public function aprobarSolicitudReservaCotizacion(Request $request)
    {
        try {
            $gpUser = GpUsuario::find(Auth::user()->id);
            $data = $request->all();
            $reserva = DB::transaction(function () use ($data, $gpUser) {
                return $this->reserveService->approvalApplicationReservedQuotation($data['data'], $gpUser);
            });
            $msg = "Reserva autorizada correctamente:  {$reserva['reserva_id']}";
            return $this->buildSuccessResponse($msg, 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->buildErrorResponse('Cotización no encontrada', 404);
        } catch (\DomainException $e) {
            return $this->buildErrorResponse($e->getMessage(), 409);
        } catch (\Throwable $e) {
            Log::channel('reservas_log')->error('Error al aprobar la reserva de la cotizacion: ', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'line' => $e->getLine(),
                'data' => $request->all(),
                'user_id' => Auth::user()->id
            ]);
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }


    public function obtenerListaCotizacionesAprobadas(Request $request)
    {
        try {
            $perPage = $request->input('perPage', 10);
            $search = $request->input('search', null);
            $sortBy = $request->input('sortBy', null);
            $sortOrder = $request->input('sortOrder', 'desc');
            $fecha_desde = $request->input('fecha_desde', null);
            $fecha_hasta = $request->input('fecha_hasta', null);
            $estado = $request->input('status', null);
            $ptoVtaId = $request->input('ptovta', null);
            $clienteRuc = $request->input('cliente', null);
            $userId = Auth::user()->id; //filto para ver las cotizaciones del usuario logueado
            return $this->successResponse('Cotizaciones obtenidas correctamente', $this->reserveService->fetchListApprovedQuotation($perPage, $search, $sortBy, $sortOrder, $fecha_desde, $fecha_hasta, $estado, $ptoVtaId, $clienteRuc, $userId), 200);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }



    public function procesarReservaCotizacion(Request $request)
    {
        $data = $request->all();
        $gpUser = GpUsuario::find(Auth::user()->id);
        try {
            $response = $this->reserveService->processReservedQuotation($data['data'], $gpUser);
            $msg = $response['cotizacion'] === null ? "Reserva procesada correctamente" : "Reserva procesada correctamente, cotización generada:  {$response['cotizacion']}";

            return $this->buildSuccessResponse($msg, 200);
        } catch (\Throwable $e) {
            Log::channel('reservas_log')->error('Error al procesar la reserva de la cotizacion: ', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'data' => $data
            ]);
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }


    public function procesarReservaFacturaDemandaCotizacion(Request $request)
    {
        $data = $request->all();
        $gpUser = GpUsuario::find(Auth::user()->id);
        try {
            $response = DB::transaction(function () use ($data, $gpUser) {
                return $this->reserveService->processReservedQuotationInvoiceDemand($data['reserva'], $gpUser);
            });
            $msg = "Reserva procesada correctamente. Pedido {$response['pedido']} generado.";
            return $this->buildSuccessResponse($msg, 200);
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
                'items.*.seleccionado' => ['required', 'numeric', 'min:1'],
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


    public function listaVehiculos()
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

    public function listaTransportistas(Request $request)
    {
        $vehicleId = $request->input('vehicle_id', null);
        try {
            $data = $this->reserveService->fetchTransportistas($vehicleId);
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
}
