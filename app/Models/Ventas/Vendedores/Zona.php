<?php

namespace App\Models\Ventas\Vendedores;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Ventas\Vendedores\Zona
 *
 * @property int $id
 * @property string|null $descripcion
 * @property int $estado
 * @property int|null $created_by_user_id
 * @property int|null $updated_by_user_id
 * @property string|null $modified_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Ventas\Vendedores\GpVendedor> $vendedores
 * @property-read int|null $vendedores_count
 * @method static \Illuminate\Database\Eloquent\Builder|Zona newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Zona newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Zona query()
 * @method static \Illuminate\Database\Eloquent\Builder|Zona whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Zona whereCreatedByUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Zona whereDescripcion($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Zona whereEstado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Zona whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Zona whereModifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Zona whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Zona whereUpdatedByUserId($value)
 * @mixin \Eloquent
 */
class Zona extends Model
{
    use HasFactory;

    protected $table = 'zonas';
    protected $fillable =  [
        'descripcion',
        'estado',
        'created_by_user_id',
        'updated_by_user_id',
        'modified_at',
    ];

    public function vendedores()
    {
        return $this->belongsToMany(GpVendedor::class, 'vendedor_zona', 'zona_id', 'vendedor_id');
    }
}
