<?php

namespace App\Models\Reservas;

use App\Models\GpCotizacion;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Reservas\GpGuiaRemisionReserva
 *
 * @property int $id
 * @property string|null $clave_acceso
 * @property string|null $numero_guia
 * @property int|null $user_id
 * @property string $estado
 * @property string|null $bodega_origen
 * @property string|null $bodega_destino
 * @property string|null $guia_bodega
 * @property string|null $establecimiento
 * @property string|null $pto_emision
 * @property string|null $secuencial
 * @property string|null $direccion_partida
 * @property string|null $razon_social_transportista
 * @property string|null $identificacion_transportista
 * @property string $tipo_identificacion_transportista
 * @property string|null $placa
 * @property string|null $fecha_salida
 * @property string|null $fecha_llegada
 * @property string|null $observacion
 * @property string|null $direccion_destinatario
 * @property string|null $identificacion_destinatario
 * @property string|null $razon_social_destinatario
 * @property string|null $ruta
 * @property string|null $cod_sustento
 * @property string|null $doc_sustento
 * @property string|null $num_auto_doc_sustento
 * @property string|null $fecha_emision_doc_sustento
 * @property int|null $rebajado
 * @property string|null $idempotency_key
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, GpCotizacion> $cotizaciones
 * @property-read int|null $cotizaciones_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Reservas\GpGuiaRemisionReservaDetalle> $detalleGuiaReservas
 * @property-read int|null $detalle_guia_reservas_count
 * @property-read array $quotes
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReserva newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReserva newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReserva query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReserva whereBodegaDestino($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReserva whereBodegaOrigen($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReserva whereClaveAcceso($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReserva whereCodSustento($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReserva whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReserva whereDireccionDestinatario($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReserva whereDireccionPartida($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReserva whereDocSustento($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReserva whereEstablecimiento($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReserva whereEstado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReserva whereFechaEmisionDocSustento($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReserva whereFechaLlegada($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReserva whereFechaSalida($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReserva whereGuiaBodega($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReserva whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReserva whereIdempotencyKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReserva whereIdentificacionDestinatario($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReserva whereIdentificacionTransportista($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReserva whereNumAutoDocSustento($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReserva whereNumeroGuia($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReserva whereObservacion($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReserva wherePlaca($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReserva wherePtoEmision($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReserva whereRazonSocialDestinatario($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReserva whereRazonSocialTransportista($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReserva whereRebajado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReserva whereRuta($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReserva whereSecuencial($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReserva whereTipoIdentificacionTransportista($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReserva whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReserva whereUserId($value)
 * @mixin \Eloquent
 */
class GpGuiaRemisionReserva extends Model
{
    use HasFactory;
    protected $appends = ['quotes'];

    protected $fillable = [
        'clave_acceso',
        'numero_guia',
        'user_id',
        'estado',
        'bodega_origen',
        'bodega_destino',
        'guia_bodega',
        'establecimiento',
        'pto_emision',
        'secuencial',
        'direccion_partida',
        'razon_social_transportista',
        'identificacion_transportista',
        'tipo_identificacion_transportista',
        'placa',
        'fecha_salida',
        'fecha_llegada',
        'observacion',
        'direccion_destinatario',
        'identificacion_destinatario',
        'razon_social_destinatario',
        'ruta',
        'cod_sustento',
        'doc_sustento',
        'num_auto_doc_sustento',
        'fecha_emision_doc_sustento',
        'rebajado',
        'idempotency_key',
    ];


    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
    public function detalleGuiaReservas()
    {
        return $this->hasMany(GpGuiaRemisionReservaDetalle::class, 'guia_id', 'id');
    }

    public function getQuotesAttribute(): array
    {
        if ($this->relationLoaded('cotizaciones')) {
            return $this->cotizaciones->pluck('cotizacion')->unique()->values()->all();
        }
        return [];
    }

    /** relaciuib con GpCotizacion basada en el detalle de la guia tomada como pivot */

    public function cotizaciones()
    {
        return $this->belongsToMany(GpCotizacion::class, 'gp_guia_remision_reserva_detalles', 'guia_id', 'cotizacion_id')
            ->select(['gp_cotizacions.id', 'gp_cotizacions.cotizacion']);
    }
}
