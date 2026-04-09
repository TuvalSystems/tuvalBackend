<?php

namespace App\Models\Ventas\Vendedores;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Enums\Ventas\Vendedores\SellerAttributeTypeEnum;

/**
 * App\Models\Ventas\Vendedores\VendedorAtributo
 *
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property SellerAttributeTypeEnum $tipo_dato string, boolean, integer, decimal, date, opcion
 * @property int $es_multiple
 * @property int $es_requerido
 * @property int $estado
 * @property string|null $descripcion
 * @property int|null $created_by_user_id
 * @property int|null $updated_by_user_id
 * @property string|null $modified_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Ventas\Vendedores\VendedorAtributoOpcion> $opciones
 * @property-read int|null $opciones_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Ventas\Vendedores\VendedorAtributoValor> $valores
 * @property-read int|null $valores_count
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorAtributo newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorAtributo newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorAtributo query()
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorAtributo whereCodigo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorAtributo whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorAtributo whereCreatedByUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorAtributo whereDescripcion($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorAtributo whereEsMultiple($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorAtributo whereEsRequerido($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorAtributo whereEstado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorAtributo whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorAtributo whereModifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorAtributo whereNombre($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorAtributo whereTipoDato($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorAtributo whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorAtributo whereUpdatedByUserId($value)
 * @mixin \Eloquent
 */
class VendedorAtributo extends Model
{
    use HasFactory;

    protected $table = 'vendedor_atributos';

    protected $casts = [
        'tipo_dato' => SellerAttributeTypeEnum::class,
    ];
    protected $fillable = [
        'codigo',
        'nombre',
        'tipo_dato',
        'es_multiple',
        'es_requerido',
        'estado',
        'descripcion',
        'created_by_user_id',
        'updated_by_user_id',
        'modified_at',
    ];


    public function opciones()
    {
        return $this->hasMany(VendedorAtributoOpcion::class, 'atributo_id');
    }

    public function valores()
    {
        return $this->hasMany(VendedorAtributoValor::class, 'atributo_id');
    }
}
