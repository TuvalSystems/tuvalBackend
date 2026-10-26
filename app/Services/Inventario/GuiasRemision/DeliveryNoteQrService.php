<?php

namespace App\Services\Inventario\GuiasRemision;

use App\Models\Inventario\GuiasRemision\GpGuiaRemision;
use App\Models\Ventas\Reservas\GpGuiaRemisionReserva;
use App\Services\Inventario\GuiasRemision\Contracts\DeliveryNoteQrServiceInterface;
use Illuminate\Validation\ValidationException;
use Milon\Barcode\DNS2D;

class DeliveryNoteQrService implements DeliveryNoteQrServiceInterface
{
    public function code(GpGuiaRemision|GpGuiaRemisionReserva $guide): string
    {
        $type = $guide instanceof GpGuiaRemisionReserva ? 'reserva' : $guide->tipo;
        $payload = 'tuval:guia:v1:' . $type . ':' . $guide->getKey();
        return $payload . ':' . hash_hmac('sha256', $payload, $this->key());
    }

    public function decode(string $code): array
    {
        if (!preg_match('/\Atuval:guia:v1:(despacho|reserva|reposicion|transferencia):([1-9][0-9]{0,17}):([a-f0-9]{64})\z/', $code, $parts)) {
            throw ValidationException::withMessages(['codigo' => 'Código QR de guía inválido.']);
        }
        $payload = substr($code, 0, strrpos($code, ':'));
        if (!hash_equals(hash_hmac('sha256', $payload, $this->key()), $parts[3])) {
            throw ValidationException::withMessages(['codigo' => 'Código QR de guía inválido.']);
        }
        return ['tipo' => $parts[1], 'guia_id' => (int) $parts[2]];
    }

    public function image(GpGuiaRemision|GpGuiaRemisionReserva $guide): string
    {
        return 'data:image/png;base64,' . (new DNS2D())->getBarcodePNG($this->code($guide), 'QRCODE,M', 5, 5);
    }

    private function key(): string
    {
        $key = (string) config('app.key');
        if ($key === '') {
            throw new \LogicException('APP_KEY es requerida para generar el QR de la guía.');
        }
        return $key;
    }
}
