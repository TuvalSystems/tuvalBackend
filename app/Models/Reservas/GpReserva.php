<?php

namespace App\Models\Reservas;

use App\Models\GpCotizacion;
use App\Models\GpUsuario;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Reservas\GpReserva
 *
 * @property int $id
 * @property int|null $cotizacion_id
 * @property int|null $tipo_reserva_id
 * @property int|null $tipo_traslado_reserva_id
 * @property int|null $user_ingreso_reserva_id
 * @property string|null $fe_solicitud_reserva
 * @property string|null $fe_ingreso_solicitud_reserva
 * @property string|null $obs_solicitud_reserva
 * @property int|null $user_autorizacion_reserva_id
 * @property string|null $fe_autorizacion_reserva
 * @property string|null $fe_ingreso_autorizacion_reserva
 * @property string|null $obs_autorizacion_reserva
 * @property int $estado_autorizacion_reserva
 * @property string|null $estado
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read GpCotizacion|null $cotizacion
 * @property-read \App\Models\Reservas\GpTipoReservas|null $tipoReserva
 * @property-read \App\Models\Reservas\GpReservaTipoTraslado|null $tipoTraslado
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Reservas\GpTrasladosReservas> $traslados
 * @property-read int|null $traslados_count
 * @property-read GpUsuario|null $usuarioAutorizacion
 * @property-read GpUsuario|null $usuarioIngreso
 * @method static \Illuminate\Database\Eloquent\Builder|GpReserva newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpReserva newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpReserva query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpReserva whereCotizacionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpReserva whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpReserva whereEstado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpReserva whereEstadoAutorizacionReserva($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpReserva whereFeAutorizacionReserva($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpReserva whereFeIngresoAutorizacionReserva($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpReserva whereFeIngresoSolicitudReserva($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpReserva whereFeSolicitudReserva($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpReserva whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpReserva whereObsAutorizacionReserva($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpReserva whereObsSolicitudReserva($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpReserva whereTipoReservaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpReserva whereTipoTrasladoReservaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpReserva whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpReserva whereUserAutorizacionReservaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpReserva whereUserIngresoReservaId($value)
 * @mixin \Eloquent
 */
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

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

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


    public function traslados()
    {
        return $this->hasMany(GpTrasladosReservas::class, 'reserva_id');
    }
}
