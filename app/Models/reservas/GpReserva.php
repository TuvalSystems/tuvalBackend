<?php

namespace App\Models\reservas;

use App\Models\GpCotizacion;
use App\Models\GpUsuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GpReserva extends Model
{
    use HasFactory;

    protected $fillable = [
        'cotizacion_id',
        'estado',
        'tipo_reserva_id',
        'tipo_traslado_reserva_id',

        'user_ingreso_reserva_id',
        'fe_solicitud_reserva',
        'fe_ingreso_solicitud_reserva',
        'obs_solicitud_reserva',

        'user_autorizacion_reserva_id',
        'fe_autorizacion_reserva',
        'fe_ingreso_autorizacion_reserva',
        'obs_autorizacion_reserva',

        'estado_autorizacion_reserva',
    ];


    public function cotizacion()
    {
        // FK está en ESTA tabla: gp_reservas.cotizacion_id -> gp_cotizacions.id
        return $this->belongsTo(GpCotizacion::class, 'cotizacion_id', 'id');
    }

    public function usuarioIngreso()
    {
        return $this->belongsTo(GpUsuario::class, 'user_ingreso_reserva_id');
    }

    public function usuarioAutorizacion()
    {
        return $this->belongsTo(GpUsuario::class, 'user_autorizacion_reserva_id');
    }

    public function tipoReserva()
    {
        return $this->belongsTo(GpTipoReservas::class, 'tipo_reserva_id');
    }

    public function tipoTraslado()
    {
        return $this->belongsTo(GpReservaTipoTraslado::class, 'tipo_traslado_reserva_id');
    }
}
