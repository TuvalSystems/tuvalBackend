<?php

namespace App\Models\Inventario\GuiasRemision;

use App\Models\Ventas\Pedidos\GpPedidoDetalle;
use App\Models\Inventario\Reposiciones\GpTrasladosReposicion;
use App\Models\Inventario\Transferencias\GpTrasladosVentas;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Inventario\GuiasRemision\GpGuiaRemisionDetalle
 *
 * @property int $id
 * @property int $detalle_id
 * @property int $guia_remision_id
 * @property string $GUI_REM_CODI_INTERNO
 * @property string $GUI_REM_DESCRIPCION
 * @property string|null $GUI_REM_CODI_ADICIONAL
 * @property string $GUI_REM_CANTIDAD
 * @property string $GUI_REM_UNIDAD_MEDIDA
 * @property string $recibido
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Inventario\GuiasRemision\GpGuiaRemision $guia
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionDetalle newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionDetalle newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionDetalle query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionDetalle whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionDetalle whereDetalleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionDetalle whereGUIREMCANTIDAD($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionDetalle whereGUIREMCODIADICIONAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionDetalle whereGUIREMCODIINTERNO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionDetalle whereGUIREMDESCRIPCION($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionDetalle whereGUIREMUNIDADMEDIDA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionDetalle whereGuiaRemisionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionDetalle whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionDetalle whereRecibido($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionDetalle whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpGuiaRemisionDetalle extends Model
{
    use HasFactory;

    protected $fillable = [
        'detalle_id',
        'guia_remision_id',
        'GUI_REM_CODI_INTERNO',
        'GUI_REM_DESCRIPCION',
        'GUI_REM_CODI_ADICIONAL',
        'GUI_REM_CANTIDAD',
        'GUI_REM_UNIDAD_MEDIDA',
        'recibido',
    ];




    public function guia()
    {
        return $this->belongsTo(GpGuiaRemision::class, 'guia_remision_id');
    }

    public function trasladoVenta()
    {
        return $this->belongsTo(GpTrasladosVentas::class, 'detalle_id');
    }

    public function trasladoReposicion()
    {
        return $this->belongsTo(GpTrasladosReposicion::class, 'detalle_id');
    }

    public function detallePedido()
    {
        return $this->belongsTo(GpPedidoDetalle::class, 'detalle_id');
    }

    public function getDetalleOrigenAttribute()
    {
        $tipoGuia = $this->guia->tipo ?? null;
        return match ($tipoGuia) {
            'traslado'   => $this->trasladoVenta,
            'reposicion' => $this->trasladoReposicion,
            'despacho'   => $this->detallePedido,
            default      => null,
        };
    }
}
