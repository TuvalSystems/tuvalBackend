<?php

namespace App\Models\traslados;

use App\Enums\bodegas\guiaremision\DeliveryNoteStatus;
use App\Enums\bodegas\guiaremision\DeliveryNoteType;
use App\Models\despachos\GpGuiaRemisionDetalle;
use App\Models\GpArticulo;
use App\Models\GpBodega;
use App\Models\GpPedido;
use App\Models\GpPedidoDetalle;
use App\Models\GpRelacionPuntoVentaBodega;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\traslados\GpTrasladosVentas
 *
 * @property int $id
 * @property int $pedido_id
 * @property int $detalle_id
 * @property int $detalle_stocks_id
 * @property string|null $ART_CODIGO
 * @property string|null $ART_DESCRIPCION
 * @property string|null $ART_CODIGO_TRES_LETRAS
 * @property string|null $ART_PLAN_UMEDIDA
 * @property string|null $ART_PESO
 * @property string|null $cantidad
 * @property string $bodega_origen
 * @property string $bodega_destino
 * @property string $reservado
 * @property string $trasladado
 * @property int $completado
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $picking
 * @property-read GpPedido $pedido
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosVentas newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosVentas newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosVentas query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosVentas whereARTCODIGO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosVentas whereARTCODIGOTRESLETRAS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosVentas whereARTDESCRIPCION($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosVentas whereARTPESO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosVentas whereARTPLANUMEDIDA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosVentas whereBodegaDestino($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosVentas whereBodegaOrigen($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosVentas whereCantidad($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosVentas whereCompletado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosVentas whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosVentas whereDetalleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosVentas whereDetalleStocksId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosVentas whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosVentas wherePedidoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosVentas wherePicking($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosVentas whereReservado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosVentas whereTrasladado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosVentas whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpTrasladosVentas extends Model
{
    use HasFactory;
    protected $appends = ['pending_transfer', 'has_guide_active'];

    protected $casts = [
        'pending_transfer' => 'float',
    ];
    protected $fillable = [
        'pedido_id',
        'detalle_id',
        'detalle_stocks_id',
        'ART_CODIGO',
        'ART_DESCRIPCION',
        'ART_CODIGO_TRES_LETRAS',
        'ART_PLAN_UMEDIDA',
        'ART_PESO',
        'cantidad',
        'bodega_origen',
        'bodega_destino',
        'picking',
        'reservado',
        'trasladado',
        'completado',
    ];

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


    public function pedido(): BelongsTo
    {
        return $this->belongsTo(GpPedido::class, 'pedido_id');
    }

    public function pedidoDetalle(): BelongsTo
    {
        return $this->belongsTo(GpPedidoDetalle::class, 'detalle_id');
    }


    public function bodDestino()
    {
        return $this->belongsTo(GpBodega::class, 'bodega_destino', 'BOD_BODEGA');
    }


    public function relacionPuntoVenta()
    {
        return $this->belongsTo(GpRelacionPuntoVentaBodega::class, 'bodega_destino', 'PTO_VTA_BOD_BODEGA');
    }

    public function guiasDetalle()
    {
        return $this->hasMany(GpGuiaRemisionDetalle::class, 'detalle_id', 'id')
            ->whereHas('guia', function ($q) {
                $q->where('tipo', DeliveryNoteType::TRANSFERENCIA->value)
                    ->where('estado', '!=', DeliveryNoteStatus::ELIMINADA->value);
            });
    }

    public function articulo()
    {
        return $this->belongsTo(GpArticulo::class, 'ART_CODIGO', 'ART_CODIGO');
    }


    //atributos

    public function scopePendientes($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('completado')->orWhere('completado', 0);
        });
    }


    public function getHasGuideActiveAttribute(): bool
    {
        return $this->guiasDetalle()->exists();
    }







    public function getPendingTransferAttribute(): float|int
    {
        $cant = $this->cantidad ?? 0;
        $res = $this->reservado ?? 0;
        $tras = $this->trasladado ?? 0;
        return max(0, $cant - $res - $tras);
    }
}
