<?php

namespace App\Services\Inventario\Bodegas;

use App\Services\Inventario\Bodegas\Contracts\DeliveryNoteSignaturePdfServiceInterface;

use App\Models\Inventario\GuiasRemision\GpGuiaRemision;
use App\Models\Ventas\Reservas\GpGuiaRemisionReserva;
use Illuminate\Support\Facades\Storage;

class DeliveryNoteSignaturePdfService implements DeliveryNoteSignaturePdfServiceInterface
{
    public function forGuide(GpGuiaRemision|GpGuiaRemisionReserva $guide): ?array
    {
        $record = $guide->firma()->with('media')->first();
        $signature = $record?->media;
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
            'firmante' => $record->nombre_firmante,
            'identificacion_firmante' => $record->identificacion_firmante,
        ];
    }
}
