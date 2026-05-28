<?php

namespace App\Models;

use App\Models\Ventas\Vendedores\GpVendedor;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\GpClienteSucursal
 *
 * @property int $id
 * @property int $cliente_id
 * @property string $CLI_SUC_RUC
 * @property string|null $CLI_SUC_DESCRIPCION
 * @property string|null $CLI_SUC_ID_DIRECCION
 * @property string|null $CLI_SUC_DIRECCION
 * @property string|null $CLI_SUC_CONTACTO
 * @property string|null $CLI_SUC_REFERENCIA
 * @property string|null $CLI_SUC_VENDEDOR
 * @property string|null $CLI_SUC_TERRITORIO
 * @property string|null $CLI_SUC_CIUDAD
 * @property string|null $CLI_SUC_PROVINCIA
 * @property string|null $CLI_SUC_PAIS
 * @property string|null $CLI_SUC_TELEFONO
 * @property string|null $CLI_SUC_CORREO_ELECTRONICO_SRI
 * @property string|null $CLI_SUC_CORREO_ELECTRONICO_NOTIFICACION_INTERNA
 * @property int|null $user_creator_id
 * @property string|null $fecha_hora_creacion
 * @property int $estado
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $coordenadas
 * @property-read GpVendedor|null $vendedor
 * @method static \Illuminate\Database\Eloquent\Builder|GpClienteSucursal newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpClienteSucursal newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpClienteSucursal query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpClienteSucursal whereCLISUCCIUDAD($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpClienteSucursal whereCLISUCCONTACTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpClienteSucursal whereCLISUCCORREOELECTRONICONOTIFICACIONINTERNA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpClienteSucursal whereCLISUCCORREOELECTRONICOSRI($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpClienteSucursal whereCLISUCDESCRIPCION($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpClienteSucursal whereCLISUCDIRECCION($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpClienteSucursal whereCLISUCIDDIRECCION($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpClienteSucursal whereCLISUCPAIS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpClienteSucursal whereCLISUCPROVINCIA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpClienteSucursal whereCLISUCREFERENCIA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpClienteSucursal whereCLISUCRUC($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpClienteSucursal whereCLISUCTELEFONO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpClienteSucursal whereCLISUCTERRITORIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpClienteSucursal whereCLISUCVENDEDOR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpClienteSucursal whereCoordenadas($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpClienteSucursal whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpClienteSucursal whereEstado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpClienteSucursal whereFechaHoraCreacion($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpClienteSucursal whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpClienteSucursal whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpClienteSucursal whereUserCreatorId($value)
 * @mixin \Eloquent
 */
class GpClienteSucursal extends Model
{
    use HasFactory;

    protected $table = 'gp_cliente_sucursals';

    protected $casts = [
        'cliente_id'            => 'integer',
        'user_creator_id'       => 'integer',
        'estado'                => 'integer',
        'fecha_hora_creacion'   => 'datetime',
    ];
    protected $fillable = [
        'cliente_id',
        'CLI_SUC_RUC',
        'CLI_SUC_DESCRIPCION',
        'CLI_SUC_ID_DIRECCION',
        'CLI_SUC_DIRECCION',
        'CLI_SUC_CONTACTO',
        'CLI_SUC_REFERENCIA',
        'CLI_SUC_VENDEDOR',
        'CLI_SUC_TERRITORIO',
        'CLI_SUC_CIUDAD',
        'CLI_SUC_PROVINCIA',
        'CLI_SUC_PAIS',
        'CLI_SUC_TELEFONO',
        'CLI_SUC_CORREO_ELECTRONICO_SRI',
        'CLI_SUC_CORREO_ELECTRONICO_NOTIFICACION_INTERNA',
        'coordenadas',
        'user_creator_id',
        'fecha_hora_creacion',
        'estado'
    ];

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
    public function vendedor()
    {
        return $this->belongsTo(GpVendedor::class, 'CLI_SUC_VENDEDOR', 'VEND_ID');
    }

    public function cliente()
    {
        return $this->belongsTo(GpCliente::class, 'cliente_id', 'id');
    }
}
