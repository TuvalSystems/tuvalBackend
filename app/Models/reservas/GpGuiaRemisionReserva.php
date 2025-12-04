<?php

namespace App\Models\reservas;

use App\Models\GpCotizacion;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
            // $this->load('cotizaciones:id,cotizacion');
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
