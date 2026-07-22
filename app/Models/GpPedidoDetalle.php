<?php

namespace App\Models;

use App\Models\despachos\GpGuiaRemisionDetalle;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\GpPedidoDetalle
 *
 * @property int $id
 * @property int $pedido_id
 * @property int $orden
 * @property string|null $ART_CODIGO
 * @property string|null $ART_DESCRIPCION
 * @property string|null $ART_DESCRIPCION_ORIGINAL
 * @property bool $CHECK_ALTERNATIVO
 * @property string|null $ART_CODIGO_TRES_LETRAS
 * @property int|null $ART_DECIMAL_CANTIDAD
 * @property string|null $ART_PLAN_UMEDIDA
 * @property string|null $ART_MARCA
 * @property string|null $ART_MATERIAL
 * @property string|null $ART_PESO
 * @property string|null $ART_TIPO_COMPRA
 * @property float|null $ART_VALOR
 * @property float|null $ART_IVA
 * @property float|null $ART_VALOR_ORIGINAL
 * @property string|null $valor_original_l
 * @property float|null $ART_DESCUENTO
 * @property float|null $ART_CANTIDAD
 * @property float|null $ART_STOCK
 * @property float|null $ART_IMP_VTA
 * @property float|null $ART_COSTO_PROMEDIO
 * @property float|null $descuento
 * @property float|null $descuento_original
 * @property float|null $modificador
 * @property float $reservado
 * @property float $trasladado
 * @property float $devuelto
 * @property float $cancelado
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $traslado
 * @property-read \Illuminate\Database\Eloquent\Collection<int, GpGuiaRemisionDetalle> $detalleGuia
 * @property-read int|null $detalle_guia_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GpPedidoDetalleProveedores> $detalleProveedores
 * @property-read int|null $detalle_proveedores_count
 * @property-read bool $approval_descripcion
 * @property-read bool $approval_descuento
 * @property-read bool $approval_modificador
 * @property-read bool $approval_valor
 * @property-read \App\Models\GpPedido $pedido
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GpPedidoDetalleStock> $pedidoDetalleStocks
 * @property-read int|null $pedido_detalle_stocks_count
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalle newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalle newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalle query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalle whereARTCANTIDAD($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalle whereARTCODIGO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalle whereARTCODIGOTRESLETRAS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalle whereARTCOSTOPROMEDIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalle whereARTDECIMALCANTIDAD($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalle whereARTDESCRIPCION($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalle whereARTDESCRIPCIONORIGINAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalle whereARTDESCUENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalle whereARTIMPVTA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalle whereARTIVA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalle whereARTMARCA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalle whereARTMATERIAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalle whereARTPESO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalle whereARTPLANUMEDIDA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalle whereARTSTOCK($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalle whereARTTIPOCOMPRA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalle whereARTVALOR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalle whereARTVALORORIGINAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalle whereCHECKALTERNATIVO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalle whereCancelado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalle whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalle whereDescuento($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalle whereDescuentoOriginal($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalle whereDevuelto($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalle whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalle whereModificador($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalle whereOrden($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalle wherePedidoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalle whereReservado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalle whereTrasladado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalle whereTraslado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalle whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalle whereValorOriginalL($value)
 * @mixin \Eloquent
 */
class GpPedidoDetalle extends Model
{
    use HasFactory;

    protected $appends = [
        'approval_descripcion',
        'approval_valor',
        'approval_descuento',
        'approval_modificador',

    ];

    protected $casts = [
        'ART_DECIMAL_CANTIDAD'      => 'integer',
        'CHECK_ALTERNATIVO'         => 'boolean',
        'ART_VALOR'                 => 'float',
        'ART_VALOR_ORIGINAL'        => 'float',
        'ART_DESCUENTO'             => 'float',
        'ART_IVA'                   => 'float',
        'ART_CANTIDAD'              => 'float',
        'ART_STOCK'                 => 'float',
        'ART_IMP_VTA'               => 'float',
        'ART_COSTO_PROMEDIO'        => 'float',
        'descuento'                 => 'float',
        'descuento_original'        => 'float',
        'modificador'               => 'float',
        'approval_descripcion'      => 'boolean',
        'approval_valor'            => 'boolean',
        'approval_descuento'        => 'boolean',
        'approval_modificador'      => 'boolean',
        'reservado'                 => 'float',
        'trasladado'                => 'float',
        'devuelto'                  => 'float',
        'cancelado'                 => 'float',
        'validador_l'               => 'boolean'
    ];


    protected $fillable = [
        'pedido_id',
        'orden',
        'ART_CODIGO',
        'ART_DESCRIPCION',
        'ART_DESCRIPCION_ORIGINAL',
        'ART_CODIGO_TRES_LETRAS',
        'ART_DECIMAL_CANTIDAD',
        'ART_PLAN_UMEDIDA',
        'ART_MARCA',
        'ART_MATERIAL',
        'ART_PESO',
        'ART_TIPO_COMPRA',
        'ART_VALOR',
        'ART_IVA',
        'ART_VALOR_ORIGINAL',
        'ART_DESCUENTO',
        'ART_CANTIDAD',
        'ART_STOCK',
        'ART_IMP_VTA',
        'ART_COSTO_PROMEDIO',
        'descuento',
        'descuento_original',
        'modificador',
        'reservado',
        'trasladado',
        'devuelto',
        'cancelado',
        'CHECK_ALTERNATIVO',
        'traslado',
        'valor_original_l',
        'validador_l',
        'import_job'

    ];



    public function pedidoDetalleStocks()
    {
        return $this->hasMany(GpPedidoDetalleStock::class, 'detalle_id', 'id');
    }



    public function pedido()
    {
        return $this->belongsTo(GpPedido::class, 'pedido_id', 'id');
    }


    protected function getApprovalDescripcionAttribute(): bool
    {
        $a = preg_replace('/\s+/u', ' ', trim((string)($this->ART_DESCRIPCION_ORIGINAL ?? '')));
        $b = preg_replace('/\s+/u', ' ', trim((string)($this->ART_DESCRIPCION ?? '')));
        return strcasecmp($a, $b) !== 0;
    }


    protected function getApprovalValorAttribute(): bool
    {
        return (float)$this->ART_VALOR !== (float)$this->ART_VALOR_ORIGINAL;
    }


    protected function getApprovalDescuentoAttribute(): bool
    {
        return (float)$this->descuento !== $this->descuento_original;
    }


    protected function getApprovalModificadorAttribute(): bool
    {
        return round((float)$this->modificador, 2) !== 0.0;
    }



    public function detalleGuia()
    {
        return $this->hasMany(GpGuiaRemisionDetalle::class, 'detalle_id', 'id');
    }

    public function detalleProveedores()
    {
        return $this->hasMany(GpPedidoDetalleProveedores::class, 'detalle_id', 'id');
    }
}
