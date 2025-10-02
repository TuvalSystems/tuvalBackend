<?php

namespace App\Services\Venta;

use App\Jobs\Venta\Reservas\FanoutReservationRequest;
use App\Models\GpCotizacion;
use App\Models\GpUsuario;
use App\Services\Venta\Contracts\InventoryReservationServiceInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InventoryReservationService implements InventoryReservationServiceInterface
{

    public function __construct()
    {
        // Inyecta dependencias si es necesario
    }


    public function hasAnyReserveState(int $quotationId)
    {
        return GpCotizacion::whereKey($quotationId)->whereNotNull('st_solResInv')->where('st_solResInv', '!=', '')
            ->exists();
    }


      public function saveQuotationHeaderReserved(array $reserva,  GpUsuario $user)
    {
        GpCotizacion::whereKey($reserva['id'])->update([
            'st_solResInv'          => 'pendiente', // cuando se crean las reservas se cambia el estado a pendiente
            'co_tipSolResInv'       => $reserva['co_tipSolResInv'],
            'co_tipTrasResInv'      => $reserva['co_tipTrasResInv'],  // si va a tener traslado o no
            'fe_solResInv'          => $reserva['fe_solResInv'],
            'fe_autSolResInv'       => $reserva['fe_solResInv'],
            'tx_obsSolResInv'       => $reserva['tx_obsSolResInv'],
            'co_usrIngSolResInv'    => $user->id,
            'fe_ingSolResInv'       => Carbon::now()->format('Y-m-d H:i:s'),
        ]);
        DB::afterCommit(function () use ($reserva, $user) {
            FanoutReservationRequest::dispatch(
                $reserva['id'],
                $user->id
            )->onQueue('notifications');
        });
    }





}
