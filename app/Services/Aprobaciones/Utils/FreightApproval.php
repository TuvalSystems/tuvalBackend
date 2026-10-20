<?php

namespace App\Services\Aprobaciones\Utils;

use App\Models\Ventas\TipoDespachos\TipoDespacho;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class FreightApproval
{
    public static function requiresApprovalForType(?TipoDespacho $type): bool
    {
        $description = Str::lower(Str::ascii(trim((string) $type?->descripcion)));

        return in_array($description, ['paqueteria', 'flete al cobro'], true);
    }

    public static function hasValidApproval(array $data, ?Model $source): bool
    {
        if (!$source || !$source->usuario_flete_autorizador_id || !$source->fecha_flete_autorizacion) {
            return false;
        }

        foreach (['tipo_despacho_id', 'proveedor_despacho_id', 'responsable_flete'] as $field) {
            if ((string) data_get($data, $field) !== (string) $source->getAttribute($field)) {
                return false;
            }
        }

        foreach (['monto_flete', 'peso_flete'] as $field) {
            if (round((float) data_get($data, $field, 0), 2) !== round((float) $source->getAttribute($field), 2)) {
                return false;
            }
        }

        return true;
    }

    public static function authorizationFields(array $data, ?Model $source): array
    {
        $approved = self::hasValidApproval($data, $source);

        return [
            'usuario_flete_autorizador_id' => $approved ? $source->usuario_flete_autorizador_id : null,
            'fecha_flete_autorizacion' => $approved ? $source->fecha_flete_autorizacion : null,
        ];
    }
}
