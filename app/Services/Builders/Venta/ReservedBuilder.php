<?php

namespace App\Services\Builders\Venta;

use App\Models\GpUsuario;
use App\Models\reservas\GpReserva;
use App\Services\Builders\Venta\Contracts\ReservedBuilderInterface;
use Carbon\Carbon;

class ReservedBuilder implements ReservedBuilderInterface
{

    public function __construct()
    {
        // Inyecta dependencias si es necesario
    }


    public function fromPayloadReserved(array $data,  GpUsuario $user)
    {
        $new = new GpReserva();
        $new->fill([
            'cotizacion_id' => $data['cotizacion_id'],
            'estado' => 'pendiente',
            'user_ingreso_reserva_id' => $user->id,
            'tipo_reserva_id' => $data['tipo_reserva_id'],
            'tipo_traslado_reserva_id' => $data['tipo_traslado_reserva_id'],
            'fe_solicitud_reserva' => $data['fe_solicitud_reserva'],
            'fe_ingreso_solicitud_reserva' => Carbon::now(),
            'obs_solicitud_reserva' => $data['obs_solicitud_reserva']
        ]);
        return $new;
    }
}
