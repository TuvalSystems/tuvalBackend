<?php

namespace App\Models\Ventas\Vendedores;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Ventas\Vendedores\VendedorAtributoOpcion
 *
 * @property int $id
 * @property int $atributo_id
 * @property string $codigo
 * @property string $nombre
 * @property int $estado
 * @property int|null $created_by_user_id
 * @property int|null $updated_by_user_id
 * @property string|null $modified_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Ventas\Vendedores\VendedorAtributo $atributo
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Ventas\Vendedores\VendedorAtributoValor> $valores
 * @property-read int|null $valores_count
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorAtributoOpcion newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorAtributoOpcion newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorAtributoOpcion query()
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorAtributoOpcion whereAtributoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorAtributoOpcion whereCodigo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorAtributoOpcion whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorAtributoOpcion whereCreatedByUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorAtributoOpcion whereEstado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorAtributoOpcion whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorAtributoOpcion whereModifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorAtributoOpcion whereNombre($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorAtributoOpcion whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorAtributoOpcion whereUpdatedByUserId($value)
 * @mixin \Eloquent
 */
class VendedorAtributoOpcion extends Model
{
    use HasFactory;

    protected $table = 'vendedor_atributo_opciones';

    protected $fillable = [
        'atributo_id',
        'opcion_id',
        'codigo',
        'nombre',
        'estado',
        'created_by_user_id',
        'updated_by_user_id',
        'modified_at',
    ];

    public function atributo()
    {
        return $this->belongsTo(VendedorAtributo::class, 'atributo_id');
    }

    public function valores()
    {
        return $this->hasMany(VendedorAtributoValor::class, 'opcion_id');
    }
}
