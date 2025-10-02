<?php

namespace App\Http\Controllers\ventas;

use App\Http\Controllers\Controller;
use App\Models\GpUsuario;
use App\Services\Venta\Contracts\InventoryReservationServiceInterface;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
            return DB::transaction(function () use ($reserva,  $gpUser) {
                $this->reserveService->saveQuotationHeaderReserved($reserva,  $gpUser);
                return $this->buildSuccessResponse('Cotizacion guardada correctamente',  200);
            });
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }

    public function obtenerListaTipoReservas()
    {
        try {
            $data =  $this->reserveService->fetchListTipeReserved();
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
            return DB::transaction(function () use ($data, $gpUser) {
                $this->reserveService->denyApplicationReservedQuotation($data['data'], $gpUser);
                return $this->buildSuccessResponse('Cotización rechazada correctamente', 200);
            });
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
            return DB::transaction(function () use ($data, $gpUser) {
                $this->reserveService->approvalApplicationReservedQuotation($data['data'], $gpUser);
                return $this->buildSuccessResponse('Cotización autorizada correctamente', 200);
            });
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->buildErrorResponse('Cotización no encontrada', 404);
        } catch (\DomainException $e) {
            return $this->buildErrorResponse($e->getMessage(), 409);
        } catch (\Throwable $e) {

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
            return $this->successResponse('Cotizaciones obtenidas correctamente', $this->reserveService->fetchListApprovedQuotation($perPage, $search, $sortBy, $sortOrder, $fecha_desde, $fecha_hasta, $estado, $ptoVtaId,  $clienteRuc, $userId), 200);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }



    public function procesarReservaCotizacion(Request $request)
    {
        $data = $request->all();
        $gpUser = GpUsuario::find(Auth::user()->id);
        try {
            $response =  $this->reserveService->processReservedQuotation($data['data'], $gpUser);
            $msg = $response['cotizacion'] === null ? "Reserva procesada correctamente" : "Reserva procesada correctamente, cotización generada:  {$response['cotizacion']}";

            return  $this->buildSuccessResponse($msg,  200);
        } catch (\Throwable $e) {
            Log::error('Error al procesar la reserva de la cotizacion: ', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'data' => $data
            ]);
            return $this->buildErrorResponse('Error al procesar la reserva de la cotizacion', 500);
        }
    }


    public function procesarReservaFacturaDemandaCotizacion(Request $request)
    {
        $data = $request->all();
        $gpUser = GpUsuario::find(Auth::user()->id);
        try {
            return DB::transaction(function () use ($data, $gpUser) {
                $response =  $this->reserveService->processReservedQuotationInvoiceDemand($data['data'], $gpUser);
                $msg = "Reserva procesada correctamente, cotización generada:  {$response['pedido']}";
                return  $this->buildSuccessResponse($msg,  200);
            });
        } catch (\Throwable $e) {
            Log::error('Error al procesar la reserva de la cotizacion: ', [
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
}
