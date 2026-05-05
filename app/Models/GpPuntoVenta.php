<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\GpPuntoVenta
 *
 * @property int $id
 * @property string $PTO_VTA_ID
 * @property string|null $PTO_VTA_DESCRIPCION
 * @property string|null $email
 * @property string|null $PTO_VTA_DIRECCION_1
 * @property string|null $PTO_VTA_DIRECCION_2
 * @property string|null $PTO_VTA_ESTADO
 * @property string|null $PTO_VTA_CIUDAD
 * @property string|null $PTO_VTA_PROVINCIA
 * @property string|null $PTO_VTA_PAIS
 * @property string|null $PTO_VTA_FONO_1
 * @property string|null $PTO_VTA_FONO_2
 * @property string|null $PTO_VTA_FONO_4
 * @property int|null $PTO_VTA_DIAS_DEVOL_SIN_AUT
 * @property int|null $PTO_VTA_MAXIMO_PORC_SUBIDA_PRECIO
 * @property int|null $PTO_VTA_DIA_VALIDEZ_COTIZACION
 * @property int $PTO_VTA_DIAS_MAXIMO_PARA_ANULACION Días máximos para anulación de ventas
 * @property int $pto_vta_max_dias_reserva Días máximos para solicitud de reserva de ventas
 * @property int $pto_vta_max_dias_autorizacion_reserva Días máximos para autorización de reserva de ventas
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GpRelacionPuntoVentaBodegaRelacionada> $bodegas
 * @property-read int|null $bodegas_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GpCaja> $cajasDisponibles
 * @property-read int|null $cajas_disponibles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GpRelacionPuntoVentaCaja> $relacionPvCajas
 * @property-read int|null $relacion_pv_cajas_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GpRelacionUsuarioPuntoVenta> $relacionUsuarioPtoVta
 * @property-read int|null $relacion_usuario_pto_vta_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GpRelacionPuntoVentaBodegaRelacionada> $relacionpuntoventabodegarelacionada
 * @property-read int|null $relacionpuntoventabodegarelacionada_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GpUsuario> $usuariosConPermiso
 * @property-read int|null $usuarios_con_permiso_count
 * @method static \Illuminate\Database\Eloquent\Builder|GpPuntoVenta newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpPuntoVenta newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpPuntoVenta query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpPuntoVenta whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPuntoVenta whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPuntoVenta whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPuntoVenta wherePTOVTACIUDAD($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPuntoVenta wherePTOVTADESCRIPCION($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPuntoVenta wherePTOVTADIASDEVOLSINAUT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPuntoVenta wherePTOVTADIASMAXIMOPARAANULACION($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPuntoVenta wherePTOVTADIAVALIDEZCOTIZACION($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPuntoVenta wherePTOVTADIRECCION1($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPuntoVenta wherePTOVTADIRECCION2($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPuntoVenta wherePTOVTAESTADO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPuntoVenta wherePTOVTAFONO1($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPuntoVenta wherePTOVTAFONO2($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPuntoVenta wherePTOVTAFONO4($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPuntoVenta wherePTOVTAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPuntoVenta wherePTOVTAMAXIMOPORCSUBIDAPRECIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPuntoVenta wherePTOVTAPAIS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPuntoVenta wherePTOVTAPROVINCIA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPuntoVenta wherePtoVtaMaxDiasAutorizacionReserva($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPuntoVenta wherePtoVtaMaxDiasReserva($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPuntoVenta whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpPuntoVenta extends Model
{
    use HasFactory;

    protected $fillable = [
        'PTO_VTA_ID',
        'PTO_VTA_DESCRIPCION',
        'PTO_VTA_DIRECCION_1',
        'PTO_VTA_DIRECCION_2',
        'PTO_VTA_ESTADO',
        'PTO_VTA_CIUDAD',
        'PTO_VTA_PROVINCIA',
        'PTO_VTA_PAIS',
        'PTO_VTA_FONO_1',
        'PTO_VTA_FONO_2',
        'PTO_VTA_FONO_4',
        'PTO_VTA_DIAS_DEVOL_SIN_AUT',
        'PTO_VTA_MAXIMO_PORC_SUBIDA_PRECIO',
        'PTO_VTA_DIA_VALIDEZ_COTIZACION',
        'PTO_VTA_DIAS_MAXIMO_PARA_ANULACION', // New column added
    ];



    public function relacionUsuarioPtoVta()
    {
        return $this->hasMany(GpRelacionUsuarioPuntoVenta::class, 'USER_PTO_VTA_PUNTO_VENTA', 'PTO_VTA_ID');
    }

    public function relacionpuntoventabodegarelacionada()
    {
        return $this->hasMany(GpRelacionPuntoVentaBodegaRelacionada::class, 'PTO_VTA_BOD_REL_PUNTO_VENTA', 'PTO_VTA_ID');
    }

    public function bodegas()
    {
        return $this->hasMany(GpRelacionPuntoVentaBodegaRelacionada::class, 'PTO_VTA_BOD_REL_PUNTO_VENTA', 'PTO_VTA_ID')
            ->join('gp_bodegas', 'gp_bodegas.BOD_BODEGA', '=', 'gp_relacion_punto_venta_bodega_relacionadas.PTO_VTA_BOD_REL_BODEGA')
            ->where('PTO_VTA_BOD_REL_NIVEL_ACCESO', '!=', 'CONSULTA')
            ->select(
                'gp_relacion_punto_venta_bodega_relacionadas.*',
                'gp_bodegas.PERMITE_DESPACHO'
            );
    }



    // para accesos a cajas y puntos de venta en cobranzas



    public function relacionPvCajas()
    {
        return $this->hasMany(GpRelacionPuntoVentaCaja::class,  'pv_id');
    }


    public function usuariosConPermiso()
    {
        return $this->belongsToMany(GpUsuario::class, 'permisos_usuarios_pv_cajas', 'pv_id', 'user_id');
    }


    public function cajasDisponibles()
    {
        return $this->belongsToMany(GpCaja::class, 'gp_relacion_punto_venta_cajas', 'pv_id', 'caja_id')
            ->withPivot('estado')
            ->wherePivot('estado', 1);
    }
}
