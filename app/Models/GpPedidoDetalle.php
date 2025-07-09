<?php

namespace App\Models;

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
 * @property string|null $ART_CODIGO_TRES_LETRAS
 * @property int|null $ART_DECIMAL_CANTIDAD
 * @property string|null $ART_PLAN_UMEDIDA
 * @property string|null $ART_MARCA
 * @property string|null $ART_MATERIAL
 * @property string|null $ART_PESO
 * @property string|null $ART_TIPO_COMPRA
 * @property string|null $ART_VALOR
 * @property string|null $ART_IVA
 * @property string|null $ART_VALOR_ORIGINAL
 * @property string|null $ART_DESCUENTO
 * @property string|null $ART_CANTIDAD
 * @property string|null $ART_STOCK
 * @property string|null $ART_IMP_VTA
 * @property string|null $ART_COSTO_PROMEDIO
 * @property string|null $descuento
 * @property string|null $descuento_original
 * @property string|null $modificador
 * @property string|null $reservado
 * @property string|null $trasladado
 * @property string|null $devuelto
 * @property string|null $cancelado
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
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
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalle whereARTPLANUMEDIDA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalle whereARTSTOCK($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalle whereARTTIPOCOMPRA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalle whereARTVALOR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalle whereARTVALORORIGINAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalle whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalle whereDescuento($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalle whereDescuentoOriginal($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalle whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalle whereModificador($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalle whereOrden($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalle wherePedidoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalle whereUpdatedAt($value)
 * @mixin \Eloquent
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalle whereARTPESO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalle whereCancelado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalle whereDevuelto($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalle whereReservado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalle whereTrasladado($value)
 * @property boolean  $CHECK_ALTERNATIVO
 * @property int $traslado
 *
 */
class GpPedidoDetalle extends Model
{
    use HasFactory;
}
