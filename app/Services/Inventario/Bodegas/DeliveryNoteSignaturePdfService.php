<?php

namespace App\Services\Inventario\Bodegas;

use App\Services\Inventario\Bodegas\Contracts\DeliveryNoteSignaturePdfServiceInterface;

use App\Models\despachos\GpGuiaRemision;
use App\Models\Reservas\GpGuiaRemisionReserva;
use Illuminate\Support\Facades\Storage;

class DeliveryNoteSignaturePdfService implements DeliveryNoteSignaturePdfServiceInterface
{
    public function forGuide(GpGuiaRemision|GpGuiaRemisionReserva $guide): ?array
    {
        $signature = $guide->getFirstMedia('firmas');
        if (!$signature) {
            return null;
        }
        $disk = Storage::disk($signature->disk);
        $path = $signature->getPathRelativeToRoot();
        if (!$disk->exists($path)) {
            return null;
        }
        return [
            'imagen' => 'data:image/png;base64,' . base64_encode($disk->get($path)),
            'firmante' => $signature->getCustomProperty('nombre_firmante'),
            'identificacion_firmante' => $signature->getCustomProperty('identificacion_firmante'),
        ];
    }
}
