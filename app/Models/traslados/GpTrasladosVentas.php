<?php

namespace App\Models\traslados;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\traslados\GpTrasladosVentas
 *
 * @property int $id
 * @property int $pedido_id
 * @property int $detalle_id
 * @property int $detalle_stocks_id
 * @property string|null $ART_CODIGO
 * @property string|null $ART_DESCRIPCION
 * @property string|null $cantidad
 * @property string $bodega_origen
 * @property string $bodega_destino
 * @property int|null $picking
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosVentas newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosVentas newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosVentas query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosVentas whereARTCODIGO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosVentas whereARTDESCRIPCION($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosVentas whereBodegaDestino($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosVentas whereBodegaOrigen($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosVentas whereCantidad($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosVentas whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosVentas whereDetalleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosVentas whereDetalleStocksId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosVentas whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosVentas wherePedidoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosVentas whereUpdatedAt($value)
 * @mixin \Eloquent
 * @property string|null $ART_CODIGO_TRES_LETRAS
 * @property string|null $ART_PLAN_UMEDIDA
 * @property string|null $ART_PESO
 * @property string|null $reservado
 * @property string|null $trasladado
 * @property int|null $completado
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosVentas whereARTCODIGOTRESLETRAS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosVentas whereARTPESO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosVentas whereARTPLANUMEDIDA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosVentas whereCompletado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosVentas whereReservado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosVentas whereTrasladado($value)
 */
class GpTrasladosVentas extends Model
{
    use HasFactory;

    /**
     * Prepare a date for array / JSON serialization.
     *
     * @param  \DateTimeInterface  $date
     * @return string
     */
    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i');
    }
}
