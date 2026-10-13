<?php

namespace App\Models\borradores;

use App\Models\GpPuntoVenta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

/**
 * App\Models\borradores\CotizacionBorrador
 *
 * @property int $id
 * @property int $user_id
 * @property string $draft_id
 * @property string|null $punto_venta_id
 * @property array $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static Builder|CotizacionBorrador newModelQuery()
 * @method static Builder|CotizacionBorrador newQuery()
 * @method static Builder|CotizacionBorrador query()
 * @method static Builder|CotizacionBorrador whereCreatedAt($value)
 * @method static Builder|CotizacionBorrador whereData($value)
 * @method static Builder|CotizacionBorrador whereDraftId($value)
 * @method static Builder|CotizacionBorrador whereId($value)
 * @method static Builder|CotizacionBorrador wherePuntoVentaId($value)
 * @method static Builder|CotizacionBorrador whereUpdatedAt($value)
 * @method static Builder|CotizacionBorrador whereUserId($value)
 * @mixin \Eloquent
 */
class CotizacionBorrador extends Model
{
    use HasFactory, Prunable;
    protected $table = 'cotizacion_borradores';

    protected $fillable = [
        'user_id',
        'draft_id',
        'pv_id',
        'punto_venta_id',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];


    /**
     * esto lo usamos como comando model:prune que define el tiempo de vida de los borradores
     * se elimina pasando los 60 dias de inactividad,
     * definimos el tiempo en prueneable y declaramos el comando en el kernel
     */
    public function prunable(): Builder
    {
       return static::query()->where('updated_at', '<', now()->subDays(60));
    }

    public function puntoVenta(){
        return $this->belongsTo(GpPuntoVenta::class, 'pv_id', 'id');
    }
}
