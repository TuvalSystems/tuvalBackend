<?php

namespace App\Models\Inventario\Reposiciones;

use App\Models\Inventario\Bodegas\GpBodega;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * App\Models\Inventario\Reposiciones\GpTrasladosReposicionCabecera
 *
 * @property int $id
 * @property string $Documento
 * @property string $Bodega_Origen
 * @property string $Bodega_Destino
 * @property string $Codigo_Motivo
 * @property string|null $Motivo
 * @property \Illuminate\Support\Carbon $Fecha_Documento
 * @property-read \Illuminate\Database\Eloquent\Collection<int, GpTrasladosReposicion> $detalles
 * @property-read int|null $detalles_count
 * @property-read GpBodega|null $bodegaOrigen
 * @property-read GpBodega|null $bodegaDestino
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReposicionCabecera newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReposicionCabecera newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReposicionCabecera query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReposicionCabecera whereBodegaDestino($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReposicionCabecera whereBodegaOrigen($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReposicionCabecera whereCodigoMotivo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReposicionCabecera whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReposicionCabecera whereDocumento($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReposicionCabecera whereFechaDocumento($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReposicionCabecera whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReposicionCabecera whereMotivo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTrasladosReposicionCabecera whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpTrasladosReposicionCabecera extends Model
{
    use HasFactory;

    protected $table = 'gp_traslados_reposicion_cabeceras';


     protected $casts = [
        'id' => 'integer',
        'Fecha_Documento' => 'date:Y-m-d',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];
    protected $fillable = [
        'Documento',
        'Bodega_Origen',
        'Bodega_Destino',
        'Codigo_Motivo',
        'Fecha_Documento',
        'Motivo',
    ];



    public function detalles(): HasMany
    {
        return $this->hasMany(GpTrasladosReposicion::class, 'cabecera_id', 'id');
    }

    public function bodegaOrigen(): BelongsTo
    {
        return $this->belongsTo(GpBodega::class, 'Bodega_Origen', 'BOD_BODEGA');
    }

    public function bodegaDestino(): BelongsTo
    {
        return $this->belongsTo(GpBodega::class, 'Bodega_Destino', 'BOD_BODEGA');
    }
}
