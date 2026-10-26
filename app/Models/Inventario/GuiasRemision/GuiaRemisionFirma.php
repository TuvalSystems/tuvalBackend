<?php

namespace App\Models\Inventario\GuiasRemision;

use App\Models\Admin\Usuarios\GpUsuario;
use App\Models\Ventas\Reservas\GpGuiaRemisionReserva;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class GuiaRemisionFirma extends Model
{
    protected $table = 'guia_remision_firmas';

    protected $guarded = ['id'];

    protected $casts = [
        'guia_remision_id' => 'integer',
        'guia_reserva_id' => 'integer',
        'media_id' => 'integer',
        'usuario_id' => 'integer',
        'latitud' => 'float',
        'longitud' => 'float',
        'precision_ubicacion' => 'float',
        'fecha_firma' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $signature) {
            if (($signature->guia_remision_id === null) === ($signature->guia_reserva_id === null)) {
                throw new \InvalidArgumentException('La firma debe pertenecer a una sola guía.');
            }
        });
    }

    public function guiaRemision()
    {
        return $this->belongsTo(GpGuiaRemision::class, 'guia_remision_id');
    }

    public function guiaReserva()
    {
        return $this->belongsTo(GpGuiaRemisionReserva::class, 'guia_reserva_id');
    }

    public function media()
    {
        return $this->belongsTo(Media::class, 'media_id');
    }

    public function usuario()
    {
        return $this->belongsTo(GpUsuario::class, 'usuario_id');
    }
}
