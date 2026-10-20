<?php

namespace App\Models\Concerns;

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
            ? app(\App\Services\Inventario\Bodegas\Contracts\DeliveryNoteQrServiceInterface::class)->code($this)
            : null;
    }

    public function registerMediaCollections(): void
    {
        // Los archivos se sirven mediante la API autenticada de la guia.
        $this->addMediaCollection('firmas')->useDisk('local')->acceptsMimeTypes(['image/png'])->singleFile();
        $this->addMediaCollection('documentos')->useDisk('local')->acceptsMimeTypes([
            'application/pdf', 'image/jpeg', 'image/png', 'image/webp',
        ]);
    }
}
