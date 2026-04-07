<?php

namespace App\Models\Ventas\Vendedores;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Ventas\Vendedores\VendedorJerarquia
 *
 * @property int $id
 * @property int $vendedor_id
 * @property string|null $nombre
 * @property string|null $descripcion
 * @property int $nivel
 * @property bool $estado
 * @property int|null $created_by_user_id
 * @property int|null $updated_by_user_id
 * @property \Illuminate\Support\Carbon|null $modified_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Ventas\Vendedores\VendedorJerarquiaRelacion> $relaciones
 * @property-read int|null $relaciones_count
 * @property-read \App\Models\Ventas\Vendedores\GpVendedor $vendedor
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorJerarquia newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorJerarquia newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorJerarquia query()
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorJerarquia whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorJerarquia whereCreatedByUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorJerarquia whereDescripcion($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorJerarquia whereEstado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorJerarquia whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorJerarquia whereModifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorJerarquia whereNivel($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorJerarquia whereNombre($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorJerarquia whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorJerarquia whereUpdatedByUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorJerarquia whereVendedorId($value)
 * @mixin \Eloquent
 */
class VendedorJerarquia extends Model
{
    use HasFactory;

    protected $table = 'vendedor_jerarquias';


    protected $casts = [
        'estado'        => 'boolean',
        'nivel'         => 'integer',
        'modified_at'   => 'datetime',
    ];
    protected $fillable = [
        'vendedor_id',
        'nombre',
        'descripcion',
        'nivel',
        'estado',
        'created_by_user_id',
        'updated_by_user_id',
        'modified_at',
    ];


    public function vendedor()
    {
        return $this->belongsTo(GpVendedor::class, 'vendedor_id');
    }

    public function relaciones()
    {
        return $this->hasMany(VendedorJerarquiaRelacion::class, 'vendedor_jerarquia_id');
    }
}
