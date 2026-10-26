<?php

namespace App\Services\Aprobaciones\Utils;

use App\Models\Inventario\Despachos\TipoDespacho;
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
        if (!$source || !$source->fle_usuario_aut_id || !$source->fle_fecha_aut) {
            return false;
        }

        foreach (['tipo_despacho_id', 'fle_proveedor_id', 'responsable_flete'] as $field) {
            if ((string) data_get($data, $field) !== (string) $source->getAttribute($field)) {
                return false;
            }
        }

        foreach (['fle_valor', 'fle_peso'] as $field) {
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
            'fle_usuario_aut_id' => $approved ? $source->fle_usuario_aut_id : null,
            'fle_fecha_aut' => $approved ? $source->fle_fecha_aut : null,
        ];
    }
}
