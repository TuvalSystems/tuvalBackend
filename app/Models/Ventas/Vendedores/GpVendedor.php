<?php

namespace App\Models\Ventas\Vendedores;

use App\Models\GpCliente;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Ventas\Vendedores\GpVendedor
 *
 * @property int $id
 * @property string $VEND_ID
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
 * @method static \Illuminate\Database\Eloquent\Builder|GpVendedor newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpVendedor newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpVendedor query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpVendedor whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpVendedor whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpVendedor whereUpdatedAt($value)
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
}
