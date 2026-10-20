<?php

namespace App\Services\Inventario\Bodegas;

use App\Services\Inventario\Bodegas\Contracts\DeliveryNoteSignaturePdfServiceInterface;

use App\Models\despachos\GpGuiaRemision;
use App\Models\Reservas\GpGuiaRemisionReserva;
use App\Models\GpUsuario;
use Carbon\Carbon;
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
        $uploadedAt = $signature->getCustomProperty('uploaded_at') ?? $signature->created_at;
        $userName = $signature->getCustomProperty('user_name');
        if (!$userName && $signature->getCustomProperty('user_id')) {
            $userName = GpUsuario::whereKey($signature->getCustomProperty('user_id'))->value('USERNAME');
        }
        return [
            'imagen' => 'data:image/png;base64,' . base64_encode($disk->get($path)),
            'firmante' => $signature->getCustomProperty('nombre_firmante'),
            'usuario' => $userName ?? $signature->getCustomProperty('user_login'),
            'fecha' => $uploadedAt ? Carbon::parse($uploadedAt)->timezone('America/Guayaquil')->format('d/m/Y H:i:s') : null,
        ];
    }
}
