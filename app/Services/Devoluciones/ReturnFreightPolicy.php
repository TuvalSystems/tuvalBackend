<?php

namespace App\Services\Devoluciones;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReturnFreightPolicy
{
    public function dispatchType(?string $description): ?string
    {
        $type = Str::lower(Str::ascii(trim((string) $description)));
        return in_array($type, ['paqueteria', 'flete al cobro'], true) ? $type : null;
    }

    public function includesFreight(bool $dispatched, string $responsible): bool
    {
        return !$dispatched || $responsible === 'tuval';
    }

    public function validateReturnType(string $type, bool $dispatched): void
    {
        if (!in_array($type, ['total', 'parcial'], true) || (!$dispatched && $type !== 'total')) {
            throw ValidationException::withMessages([
                'devolucion.tipo_devolucion' => 'Para paquetería y flete al cobro sin despacho solo se permiten devoluciones totales.',
            ]);
        }
    }

    /** Freeze the credit price, so jobs, retries and printed totals use the same value. */
    public function creditItem(array $item, string $type, bool $includeFreight): array
    {
        if ($type === 'flete al cobro' && !$includeFreight && (float) ($item['fle_valor'] ?? 0) > 0) {
            // precio_sin_flete is the gross unit price, before the original discount.
            if (!isset($item['precio_sin_flete']) || !is_numeric($item['precio_sin_flete'])
                || (float) $item['precio_sin_flete'] < 0
                || (float) $item['precio_sin_flete'] > (float) $item['ART_VALOR']) {
                throw ValidationException::withMessages([
                    'detallepedido' => 'El artículo ' . $item['ART_CODIGO'] . ' no tiene un precio sin flete válido.',
                ]);
            }
            $item['ART_VALOR'] = (float) $item['precio_sin_flete'];
            $item['ART_DESCUENTO'] = round($item['ART_VALOR'] * (float) ($item['descuento'] ?? 0) / 100 + 0.000001, 4);
        }
        return $item;
    }
}
