<?php

namespace App\Models\Ventas\Vendedores;

use App\Models\Ventas\Vendedores\GpVendedor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Ventas\Vendedores\VendedorAtributoValor
 *
 * @property int $id
 * @property int $vendedor_id
 * @property int $atributo_id
 * @property int|null $opcion_id
 * @property string|null $valor_string
 * @property int|null $valor_integer
 * @property string $valor_decimal
 * @property bool $valor_boolean
 * @property \Illuminate\Support\Carbon|null $valor_date
 * @property int|null $created_by_user_id
 * @property int|null $updated_by_user_id
 * @property string|null $modified_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Ventas\Vendedores\VendedorAtributo $atributo
 * @property-read \App\Models\Ventas\Vendedores\VendedorAtributoOpcion|null $opcion
 * @property-read GpVendedor $vendedor
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorAtributoValor newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorAtributoValor newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorAtributoValor query()
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorAtributoValor whereAtributoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorAtributoValor whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorAtributoValor whereCreatedByUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorAtributoValor whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorAtributoValor whereModifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorAtributoValor whereOpcionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorAtributoValor whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorAtributoValor whereUpdatedByUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorAtributoValor whereValorBoolean($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorAtributoValor whereValorDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorAtributoValor whereValorDecimal($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorAtributoValor whereValorInteger($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorAtributoValor whereValorString($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorAtributoValor whereVendedorId($value)
 * @mixin \Eloquent
 */
class VendedorAtributoValor extends Model
{
    use HasFactory;

    protected $table = 'vendedor_atributo_valores';

    protected $fillable = [
        'vendedor_id',
        'atributo_id',
        'opcion_id',
        'valor_string',
        'valor_integer',
        'valor_decimal',
        'valor_boolean',
        'valor_date',
        'created_by_user_id',
        'updated_by_user_id',
        'modified_at',
    ];

    protected $casts = [
        'valor_boolean' => 'boolean',
        'valor_date'    => 'date',
        'valor_decimal' => 'decimal:4',
    ];

    public function vendedor()
    {
        return $this->belongsTo(GpVendedor::class, 'vendedor_id');
    }

    public function atributo()
    {
        return $this->belongsTo(VendedorAtributo::class, 'atributo_id');
    }

    public function opcion()
    {
        return $this->belongsTo(VendedorAtributoOpcion::class, 'opcion_id');
    }
}
