<?php

namespace App\Models\Concerns;

use App\Models\Inventario\GuiasRemision\GuiaRemisionFirma;
use App\Models\Ventas\Reservas\GpGuiaRemisionReserva;
use App\Services\Inventario\Bodegas\Contracts\DeliveryNoteQrServiceInterface;
use Spatie\MediaLibrary\InteractsWithMedia;

trait HasDeliveryNoteMedia
{
    use InteractsWithMedia;

    public function initializeHasDeliveryNoteMedia(): void
    {
        $this->append('codigo_qr');
    }

    public function getCodigoQrAttribute(): ?string
    {
        return $this->getKey()
            ? app(DeliveryNoteQrServiceInterface::class)->code($this)
            : null;
    }

    public function firma()
    {
        $foreignKey = $this instanceof GpGuiaRemisionReserva
            ? 'guia_reserva_id' : 'guia_remision_id';

        return $this->hasOne(GuiaRemisionFirma::class, $foreignKey);
    }

    public function registerMediaCollections(): void
    {
        // Los archivos se sirven mediante la API autenticada de la guia.
        $this->addMediaCollection('firmas')->useDisk('local')->acceptsMimeTypes(['image/png'])->singleFile();
        $this->addMediaCollection('documentos')->useDisk('local')->acceptsMimeTypes([
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/webp',
        ]);
    }
}
