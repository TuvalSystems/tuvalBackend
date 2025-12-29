<?php

namespace App\Models\devoluciones;

use App\Models\GpCliente;
use App\Models\GpClienteSucursal;
use App\Models\GpPedido;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use \DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * App\Models\devoluciones\GpDevoluciones
 *
 * @property int $id
 * @property string|null $devolucion
 * @property string $estado
 * @property int $pedido_id
 * @property int $cliente_id
 * @property string|null $pedido
 * @property string|null $factura
 * @property int|null $volver_a_facturar
 * @property string $tipo_devolucion
 * @property int|null $despachado
 * @property int|null $cliente_volver_a_facturar_id
 * @property int|null $user_id
 * @property int|null $sucursal_id
 * @property int|null $sucursal_facturacion_id
 * @property string|null $motivo
 * @property string|null $orden_de_compra
 * @property string $requiere_aprobacion
 * @property string $aprobado
 * @property int|null $aprobador_id
 * @property string|null $aprobado_mensaje
 * @property int|null $devolucion_bodega
 * @property int|null $impuesto
 * @property int|null $procesando
 * @property string $subtotal
 * @property string|null $descuento
 * @property string $subtotalmenosdescuento
 * @property string $iva
 * @property string $total
 * @property string $observacion
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevoluciones newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevoluciones newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevoluciones query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevoluciones whereAprobado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevoluciones whereAprobadoMensaje($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevoluciones whereAprobadorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevoluciones whereClienteVolverAFacturarId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevoluciones whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevoluciones whereDespachado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevoluciones whereFactura($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevoluciones whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevoluciones whereMotivo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevoluciones whereOrdenDeCompra($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevoluciones wherePedido($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevoluciones wherePedidoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevoluciones whereRequiereAprobacion($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevoluciones whereSucursalFacturacionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevoluciones whereSucursalId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevoluciones whereTipoDevolucion($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevoluciones whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevoluciones whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevoluciones whereVolverAFacturar($value)
 * @mixin \Eloquent
 * @property string|null $DOC_GENERADO
 * @property string|null $fecha_NC
 * @property string|null $USERID
 * @property string|null $NC_VTA_MENSAJE
 * @property string|null $AJ_VTA_MENSAJE
 * @property string|null $nota_cliente
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevoluciones whereClienteId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevoluciones whereDOCGENERADO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevoluciones whereDevolucion($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevoluciones whereEstado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevoluciones whereFechaNC($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevoluciones whereNCVTAMENSAJE($value)
 * @property boolean $permite_anulacion
 * @property boolean $anulacion_habilitada
 */
class GpDevoluciones extends Model
{
    use HasFactory;

    protected $fillable = [
        'devolucion',
        'estado',
        'pedido_id',
        'cliente_id',
        'pedido',
        'factura',
        'volver_a_facturar',
        'tipo_devolucion',
        'despachado',
        'cliente_volver_a_facturar_id',
        'USERID',
        'user_id',
        'sucursal_id',
        'sucursal_facturacion_id',
        'motivo',
        'orden_de_compra',
        'requiere_aprobacion',
        'aprobado',
        'aprobador_id',
        'aprobado_mensaje',
        'devolucion_bodega',
        'impuesto',
        'procesando',
        'subtotal',
        'descuento',
        'subtotalmenosdescuento',
        'iva',
        'total',
        'observacion',
        'permite_anulacion',
        'anulacion_habilitada',
        'nota_cliente',
        'motivo_no_anulada'
    ];
    /**
     * Prepare a date for array / JSON serialization.
     *
     * @param  \DateTimeInterface  $date
     * @return string
     */
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }


    public function scopeConCriterios(Builder $query, $criterio)
    {
        $query->join('gp_pedidos', 'gp_pedidos.id', '=', 'gp_devoluciones.pedido_id')
            ->select('gp_devoluciones.*', 'gp_pedidos.PTO_VTA_ID');

        if (!empty($criterio['estado'])) {
            $query->where('gp_devoluciones.estado', $criterio['estado']);
        }
        if (!empty($criterio['tipo_devolucion'])) {
            $query->where('gp_devoluciones.tipo_devolucion', $criterio['tipo_devolucion']);
        }
        if (isset($criterio['volver_a_facturar'])) {
            $query->where('gp_devoluciones.volver_a_facturar', $criterio['volver_a_facturar']);
        }


        //validacion de despachados
        if (!is_null($criterio['despachado'])) {
            $query->where(function ($q) use ($criterio) {
                if ($criterio['despachado'] == 0) {
                    $q->where('gp_devoluciones.despachado', 0)
                        ->orWhereNull('gp_devoluciones.despachado');
                } elseif ($criterio['despachado'] == 1) {
                    $q->where('gp_devoluciones.despachado', 1);
                }
            });
        }


        //validacion de estado solicitado
        if ($criterio['estado'] == 'solicitado' && $criterio['tipo_devolucion'] == 'total') {
            $query->join('gp_devoluciones_detalles as dvd', 'dvd.devolucion_id', '=', 'gp_devoluciones.id')
                ->where('dvd.ART_TIPO_COMPRA', '!=', 'L')
                // esto me valida que solo procese las devoluciones que tengan devoluciones anteriores en estado parcial
                ->where(function ($subQuery) {
                    $subQuery->whereExists(function ($q) {
                        $q->select(DB::raw(1))
                            ->from('gp_devoluciones as dp')
                            ->where('dp.pedido_id', '=', DB::raw('gp_devoluciones.pedido_id'))
                            ->where('dp.tipo_devolucion', 'parcial');
                    })
                        ->orWhere('gp_devoluciones.permite_anulacion', 0)
                        ->orWhere('gp_devoluciones.anulacion_habilitada', 0);
                });
        }

        //validacion para estado solicitado parcial
        if ($criterio['estado'] == 'solicitado' && $criterio['tipo_devolucion'] == 'parcial') {
            $query->whereNotExists(function ($sub) {
                $sub->selectRaw(1)
                    ->from('gp_devoluciones_detalles as dvd')
                    ->whereColumn('dvd.devolucion_id', 'gp_devoluciones.id')
                    ->where('dvd.despachado_parcial', 1);
            });

        }

        // validacion de estado recibido
        if ($criterio['estado'] == 'recibido') {
            $query->join('gp_devoluciones_detalles as dvd', 'dvd.devolucion_id', '=', 'gp_devoluciones.id')
                ->where(function ($q) {
                    $q->where(function ($subQ) {
                        // ✅ Caso 1: Despachado Parcial = 1
                        $subQ->where('dvd.despachado_parcial', 1)
                            ->orWhereNotNull('dvd.compartimiento')
                            ->where('dvd.recibido', '>', 0)
                            ->where('dvd.devuelto', '>', 0);
                    });
                });
        }



        //validacione en estado aprobado parcial
        if ($criterio['estado'] === 'aprobado' && $criterio['tipo_devolucion'] === 'parcial' && $criterio['volver_a_facturar'] === 1) {
            $query->where(function ($q) {
                $q->where('gp_devoluciones.requiere_aprobacion', 1)
                    ->where('gp_devoluciones.volver_a_facturar', 1)
                    ->where('gp_devoluciones.tipo_devolucion', 'parcial')
                    ->where('gp_devoluciones.estado', 'aprobado')
                    ->whereNotNull('gp_devoluciones.aprobador_id');
            })->whereNotExists(function ($sub) {
                $sub->selectRaw(1)
                    ->from('gp_devoluciones_detalles as dvd')
                    ->whereColumn('dvd.devolucion_id', 'gp_devoluciones.id')
                    ->where('dvd.despachado_parcial', 1);
            });
        }

        //validacion con estad aprobado sin volver a facturar pero no despachadas parciales
        if ($criterio['estado'] === 'aprobado' && $criterio['tipo_devolucion'] === 'parcial' && $criterio['volver_a_facturar'] === 0) {
            $query->where(function ($q) {
                $q->where('gp_devoluciones.requiere_aprobacion', 1)
                    ->where('gp_devoluciones.volver_a_facturar', 0)
                    ->where('gp_devoluciones.estado', 'aprobado')
                    ->where('gp_devoluciones.tipo_devolucion', 'parcial')
                    ->whereNotNull('gp_devoluciones.aprobador_id');
            })
                ->whereNotExists(function ($sub) {
                    $sub->selectRaw(1)
                        ->from('gp_devoluciones_detalles as dvd')
                        ->whereColumn('dvd.devolucion_id', 'gp_devoluciones.id')
                        ->where('dvd.despachado_parcial', 1);
                });
        }

        //validacione en estado aprobado total
        if ($criterio['estado'] === 'aprobado' && $criterio['tipo_devolucion'] === 'total' && $criterio['volver_a_facturar'] === 1 && $criterio['despachado'] === 1) {
            $query->where(function ($q) {
                $q->where('gp_devoluciones.requiere_aprobacion', 1)
                    ->where('gp_devoluciones.volver_a_facturar', 1)
                    ->where('gp_devoluciones.estado', 'aprobado')
                    ->where('gp_devoluciones.tipo_devolucion', 'total')
                    ->whereNotNull('gp_devoluciones.aprobador_id');
            })
                /* este where es para que no se muestren las devoluciones despachadas parciales en 1 si esta parcialmente despachado no lo procesa
                    es decir si hay un item que no esta despachado o un item tiene despacho parcial que si tiene cant = 10 y se despacho 5 y quedan 5
                    no despachados, pues no entra aqui */
                ->whereNotExists(function ($sub) {
                    $sub->selectRaw(1)
                        ->from('gp_devoluciones_detalles as dvd')
                        ->whereColumn('dvd.devolucion_id', 'gp_devoluciones.id')
                        ->where('dvd.despachado_parcial', '!=', 1);
                });
        }

        //validacion con estado aprobado sin volver a facturar pero no despachadas totales
        if ($criterio['estado'] === 'aprobado' && $criterio['tipo_devolucion'] === 'total' && $criterio['volver_a_facturar'] === 0 && ($criterio['despachado'] === 0 || $criterio['despachado'] === null)) {
            $query->where(function ($q) {
                $q->where('gp_devoluciones.requiere_aprobacion', 1)
                    ->where('gp_devoluciones.volver_a_facturar', 0)
                    ->where('gp_devoluciones.estado', 'aprobado')
                    ->where('gp_devoluciones.tipo_devolucion', 'total')
                    ->whereNotNull('gp_devoluciones.aprobador_id');
            })
                /*
                    En cambio en este procesa todos los despachados parcial = 0, siempre y cuando el pedido no haya sido despachado parcialmente o totalmente
                    ya que entraria en el where de arriba
                 */
                ->whereNotExists(function ($sub) {
                    $sub->selectRaw(1)
                        ->from('gp_devoluciones_detalles as dvd')
                        ->whereColumn('dvd.devolucion_id', 'gp_devoluciones.id')
                        ->where('dvd.despachado_parcial', 1);
                });
        }



        // Validación de devoluciones en bodega
        if (array_key_exists('devolucion_bodega', $criterio)) {
            $query->when(is_null($criterio['devolucion_bodega']), function ($q) {
                $q->whereNull('gp_devoluciones.devolucion_bodega');
            }, function ($q) use ($criterio) {
                $q->where('gp_devoluciones.devolucion_bodega', $criterio['devolucion_bodega']);
            });
        }

        return $query;

    }


    public function cliente()
    {
        return $this->belongsTo(GpCliente::class, 'cliente_id');
    }


    public function cliente_volver_a_facturar()
    {
        return $this->belongsTo(GpCliente::class, 'cliente_volver_a_facturar_id');
    }

    public function sucursal_volver_a_facturar()
    {
        return $this->belongsTo(GpClienteSucursal::class, 'sucursal_facturacion_id');
    }


    public function pedido()
    {
        return $this->belongsTo(GpPedido::class, 'pedido_id');
    }
}
