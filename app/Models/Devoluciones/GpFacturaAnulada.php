<?php

namespace App\Models\Devoluciones;

use App\Enums\devoluciones\InvoiceAnulationStatus;
use App\Models\Ventas\Pedidos\GpPedido;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Devoluciones\GpFacturaAnulada
 *
 * @property int $id
 * @property int|null $devolucion_id
 * @property int $pedido_id
 * @property string|null $pedido
 * @property string|null $factura
 * @property int|null $documento_id
 * @property string|null $claveacceso
 * @property string|null $msj_anulacion_gp
 * @property int|null $diario_response_gp
 * @property string|null $resp_document
 * @property string|null $resp_seedbilling
 * @property InvoiceAnulationStatus $estado
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpFacturaAnulada newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpFacturaAnulada newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpFacturaAnulada query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpFacturaAnulada whereClaveacceso($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpFacturaAnulada whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpFacturaAnulada whereDevolucionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpFacturaAnulada whereDiarioResponseGp($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpFacturaAnulada whereDocumentoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpFacturaAnulada whereEstado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpFacturaAnulada whereFactura($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpFacturaAnulada whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpFacturaAnulada whereMsjAnulacionGp($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpFacturaAnulada wherePedido($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpFacturaAnulada wherePedidoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpFacturaAnulada whereRespDocument($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpFacturaAnulada whereRespSeedbilling($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpFacturaAnulada whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpFacturaAnulada extends Model
{
    use HasFactory;

    protected $table = 'gp_factura_anuladas';

    protected $casts = [
        'estado' => InvoiceAnulationStatus::class
    ];
    protected $fillable = [
        'devolucion_id',
        'pedido_id',
        'factura',
        'pedido',
        'documento_id',
        'claveacceso',
        'msj_anulacion_gp',
        'diario_response_gp',
        'resp_document',
        'resp_seedbilling',
        'estado'
    ];

    public function gpPedido(){
        return $this->belongsTo(GpPedido::class, 'pedido_id', 'id');
    }

    public function gpDevolucion(){
        return $this->belongsTo(GpDevoluciones::class, 'devolucion_id', 'id');
    }
}
