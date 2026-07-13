<?php

namespace App\Models\Ventas\Vendedores;

use App\Models\GpCliente;
use App\Models\GpUsuario;
use App\Models\Ventas\TipoVendedor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Ventas\Vendedores\GpVendedor
 *
 * @property int $id
 * @property string $VEND_ID
 * @property int|null $user_id
 * @property int|null $tipo_vendedor_id
 * @property string|null $VEND_NOMBRES
 * @property string|null $VEND_APELLIDOS
 * @property string|null $VEND_ID_EMPLEADO
 * @property string|null $VEND_DIRECCION
 * @property string|null $VEND_CIUDAD
 * @property string|null $VEND_PROVINCIA
 * @property string|null $VEND_TERRITORIO
 * @property string|null $VEND_ESTADO
 * @property string|null $VEND_CORREO_ELECTRONICO
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Ventas\Vendedores\VendedorAtributoValor> $atributoValores
 * @property-read int|null $atributo_valores_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, GpCliente> $clientes
 * @property-read int|null $clientes_count
 * @property-read \App\Models\Ventas\Vendedores\VendedorJerarquia|null $jerarquia
 * @property-read GpUsuario|null $usuario
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Ventas\Vendedores\Zona> $zonas
 * @property-read int|null $zonas_count
 * @method static \Illuminate\Database\Eloquent\Builder|GpVendedor newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpVendedor newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpVendedor query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpVendedor whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpVendedor whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpVendedor whereTipoVendedorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpVendedor whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpVendedor whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpVendedor whereVENDAPELLIDOS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpVendedor whereVENDCIUDAD($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpVendedor whereVENDCORREOELECTRONICO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpVendedor whereVENDDIRECCION($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpVendedor whereVENDESTADO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpVendedor whereVENDID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpVendedor whereVENDIDEMPLEADO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpVendedor whereVENDNOMBRES($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpVendedor whereVENDPROVINCIA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpVendedor whereVENDTERRITORIO($value)
 * @mixin \Eloquent
 */
class GpVendedor extends Model
{
    use HasFactory;
    protected $fillable = [
        'VEND_ID',
        'user_id',
        'tipo_vendedor_id',
        'VEND_NOMBRES',
        'VEND_APELLIDOS',
        'VEND_ID_EMPLEADO',
        'VEND_DIRECCION',
        'VEND_CIUDAD',
        'VEND_PROVINCIA',
        'VEND_TERRITORIO',
        'VEND_ESTADO',
        'VEND_CORREO_ELECTRONICO',
    ];

    protected $table = 'gp_vendedors';

    public function clientes()
    {
        return $this->hasMany(GpCliente::class, 'CLI_VENDEDOR');
    }

    public function atributoValores()
    {
        return $this->hasMany(VendedorAtributoValor::class, 'vendedor_id');
    }

    public function zonas()
    {
        return $this->belongsToMany(Zona::class, 'vendedor_zona', 'vendedor_id', 'zona_id');
    }


    public function usuario()
    {
        return $this->belongsTo(GpUsuario::class, 'user_id');
    }

    public function tipoVendedor(){
        return $this->belongsTo(TipoVendedor::class, 'tipo_vendedor_id');
    }

    public function jerarquia()
    {
        return $this->hasOne(VendedorJerarquia::class, 'vendedor_id');
    }
}
