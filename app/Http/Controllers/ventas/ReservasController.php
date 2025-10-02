<?php

namespace App\Http\Controllers\ventas;

use App\Http\Controllers\Controller;
use App\Models\GpUsuario;
use App\Services\Venta\Contracts\InventoryReservationServiceInterface;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
}
