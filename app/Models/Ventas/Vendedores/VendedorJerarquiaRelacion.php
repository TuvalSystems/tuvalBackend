<?php

namespace App\Models\Ventas\Vendedores;

use App\Models\Ventas\Vendedores\TipoVendedor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Ventas\Vendedores\VendedorJerarquiaRelacion
 *
 * @property int $id
 * @property int $vendedor_jerarquia_id
 * @property int $tipo_vendedor_padre_id
 * @property int $tipo_vendedor_hijo_id
 * @property bool $estado
 * @property int|null $created_by_user_id
 * @property int|null $updated_by_user_id
 * @property \Illuminate\Support\Carbon|null $modified_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Ventas\Vendedores\VendedorJerarquia $jerarquia
 * @property-read TipoVendedor $tipoVendedorHijo
 * @property-read TipoVendedor $tipoVendedorPadre
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorJerarquiaRelacion newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorJerarquiaRelacion newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorJerarquiaRelacion query()
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorJerarquiaRelacion whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorJerarquiaRelacion whereCreatedByUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorJerarquiaRelacion whereEstado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorJerarquiaRelacion whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorJerarquiaRelacion whereModifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorJerarquiaRelacion whereTipoVendedorHijoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorJerarquiaRelacion whereTipoVendedorPadreId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorJerarquiaRelacion whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorJerarquiaRelacion whereUpdatedByUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|VendedorJerarquiaRelacion whereVendedorJerarquiaId($value)
 * @mixin \Eloquent
 */
class VendedorJerarquiaRelacion extends Model
{
    use HasFactory;

    protected $table = 'vendedor_jerarquia_relaciones';

    protected $casts = [
        'estado'        => 'boolean',
        'nivel'         => 'integer',
        'modified_at'   => 'datetime',
    ];
    protected $fillable = [
        'vendedor_jerarquia_id',
        'tipo_vendedor_padre_id',
        'tipo_vendedor_hijo_id',
        'estado',
        'created_by_user_id',
        'updated_by_user_id',
        'modified_at'
    ];


    public function jerarquia()
    {
        return $this->belongsTo(VendedorJerarquia::class, 'vendedor_jerarquia_id');
    }

    public function tipoVendedorPadre()
    {
        return $this->belongsTo(TipoVendedor::class, 'tipo_vendedor_padre_id');
    }

    public function tipoVendedorHijo()
    {
        return $this->belongsTo(TipoVendedor::class, 'tipo_vendedor_hijo_id');
    }
}
