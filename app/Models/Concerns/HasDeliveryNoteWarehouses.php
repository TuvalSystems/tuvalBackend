<?php

namespace App\Models\Concerns;

use App\Models\GpBodega;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin Model
 */
trait HasDeliveryNoteWarehouses
{
    public static function bootHasDeliveryNoteWarehouses(): void
    {
        static::saving(function (Model $guia) {
            $isDispatch = $guia->getAttribute('tipo') === 'despacho';

            foreach (['origen', 'destino'] as $side) {
                $code = 'bodega_' . $side;
                $id = $code . '_id';

                if ($isDispatch && $side === 'destino') {
                    $guia->setAttribute($code, null);
                    $guia->setAttribute($id, null);
                    continue;
                }

                if (!$guia->exists || $guia->isDirty($code) || $guia->isDirty($id) || $guia->getAttribute($id) === null) {
                    $value = $guia->getAttribute($code);
                    $warehouseId = $value === null || trim($value) === ''
                        ? null
                        : GpBodega::on($guia->getConnectionName())
                            ->where('BOD_BODEGA', $value)->value('id');

                    $guia->setAttribute($id, $warehouseId);
                }
            }
        });
    }

    public function bodegaOrigen()
    {
        return $this->belongsTo(GpBodega::class, 'bodega_origen_id');
    }

    public function bodegaDestino()
    {
        return $this->belongsTo(GpBodega::class, 'bodega_destino_id');
    }
}
