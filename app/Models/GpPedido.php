<?php

namespace App\Models;

use App\Enums\Ventas\OrderStatus;
use App\Models\traslados\GpTrasladosVentas;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use \DateTimeInterface;

/**
 * App\Models\GpPedido
 *
 * @property int $id
 * @property string|null $pedido
 * @property string|null $factura
 * @property string|null $factura_origen
 * @property string $estado
 * @property int|null $user_id
 * @property int|null $sucursal_id
 * @property int|null $sucursal_facturacion_id
 * @property int|null $cliente_id
 * @property int|null $cotizacion_id
 * @property string $BOD_BODEGA
 * @property string $PTO_VTA_ID
 * @property string $PTO_VTA_DESCRIPCION
 * @property string $USERID
 * @property string $USERID_cancelado
 * @property string|null $DESC_FORMA_PAGO_CODIGO
 * @property string $CLI_RUC
 * @property string|null $CLI_RAZON_SOCIAL
 * @property string|null $CLI_CORREO_ELECT_SRI
 * @property string|null $COND_ID
 * @property string|null $COND_ID_ORIGINAL
 * @property string|null $VEND_ID
 * @property string|null $VEND_NOMBRES
 * @property string|null $VEND_APELLIDOS
 * @property string|null $despacho
 * @property string $porcentage_descuentooriginal
 * @property string $porcentage_descuento
 * @property string $subtotal
 * @property string|null $descuento
 * @property string $subtotalmenosdescuento
 * @property string $iva
 * @property string $total
 * @property string $requiere_aprobacion_comercial
 * @property string $motivo_aprobacion_comercial
 * @property string $aprobado_comercial
 * @property int|null $aprobador_comercial_id
 * @property string $requiere_aprobacion_crediticia
 * @property string $aprobado_crediticio
 * @property int|null $aprobador_crediticio_id
 * @property string|null $motivo_crediticio
 * @property string|null $nota_interna
 * @property string|null $nota_cliente
 * @property string|null $MST_FAC_VTAResult
 * @property string|null $pedido_origen
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereAprobadoComercial($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereAprobadoCrediticio($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereAprobadorComercialId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereAprobadorCrediticioId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereBODBODEGA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereCLICORREOELECTSRI($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereCLIRAZONSOCIAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereCLIRUC($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereCONDID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereCONDIDORIGINAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereClienteId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereCotizacionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereDESCFORMAPAGOCODIGO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereDescuento($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereDespacho($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereEstado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereFactura($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereIva($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereMSTFACVTAResult($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereNotaCliente($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereNotaInterna($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido wherePTOVTADESCRIPCION($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido wherePTOVTAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido wherePedido($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido wherePorcentageDescuento($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido wherePorcentageDescuentooriginal($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereRequiereAprobacionComercial($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereRequiereAprobacionCrediticia($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereSubtotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereSubtotalmenosdescuento($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereSucursalFacturacionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereSucursalId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereTotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereVENDAPELLIDOS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereVENDID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereVENDNOMBRES($value)
 * @property string|null $fecha_factura
 * @property string|null $fecha_hora_factura
 * @property string|null $fecha_hora_rechazado
 * @property string|null $fecha_entrega
 * @property string|null $fecha_hora_cancelado
 * @property string|null $FAC_VTA_MENSAJE
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereFACVTAMENSAJE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereFechaFactura($value)
 * @property int|null $factura_posterior_despacho
 * @property int|null $factura_a_demanda
 * @property int|null $factura_siguiente_mes
 * @property int|null $despachado
 * @property int|null $despacho_en_proceso
 * @property int|null $transferencia_en_proceso
 * @property int|null $rechazado
 * @property int|null $impuesto
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereDespachado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereFacturaADemanda($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereFacturaPosteriorDespacho($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereFacturaSiguienteMes($value)
 * @property string|null $orden_de_compra
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereOrdenDeCompra($value)
 * @property string|null $aprobado_comercial_mensaje
 * @property string|null $aprobado_crediticio_mensaje
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereAprobadoComercialMensaje($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereAprobadoCrediticioMensaje($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereFechaEntrega($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereMotivoCrediticio($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido wherePedidoOrigen($value)
 * @property string|null $origen
 * @property string|null $fe_facturacion_reserva
 * @property boolean $pendiente_salida
 * @property int|null $user_rechazo_id Usuario que rechazo el pedido en estado de espera
 * @property int|null $user_block_id
 * @property string|null $fecha_bloqueo
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GpPedidoDetalle> $detallepedido
 * @property-read int|null $detallepedido_count
 * @property-read \App\Models\GpClienteSucursal|null $direccion_entrega
 * @property-read bool $check_traslado_venta
 * @property-read mixed $msg_registro_copia
 * @property-read \App\Models\GpCliente|null $gpcliente
 * @property-read \App\Models\GpCotizacion|null $gpcotizacion
 * @property-read \Illuminate\Database\Eloquent\Collection<int, GpTrasladosVentas> $gptrasladoventa
 * @property-read int|null $gptrasladoventa_count
 * @property-read \App\Models\GpVendedor|null $gpvendedor
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GpPedidoDetalleStock> $pedidostock
 * @property-read int|null $pedidostock_count
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereDespachoEnProceso($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereFacturaOrigen($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereFeFacturacionReserva($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereFechaBloqueo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereFechaHoraCancelado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereFechaHoraFactura($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereFechaHoraRechazado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereImpuesto($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereMotivoAprobacionComercial($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereOrigen($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido wherePendienteSalida($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereRechazado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereTransferenciaEnProceso($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereUSERIDCancelado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereUserBlockId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedido whereUserRechazoId($value)
 * @mixin \Eloquent
 */
class GpPedido extends Model
{
    use HasFactory;

    protected $appends = ['msg_registro_copia', 'check_traslado_venta'];
    protected $casts = [
        'estado' => OrderStatus::class
    ];
    protected $fillable = [
        'pedido',
        'factura',
        'factura_origen',
        'estado',
        'user_id',
        'sucursal_id',
        'sucursal_facturacion_id',
        'cliente_id',
        'cotizacion_id',
        'BOD_BODEGA',
        'PTO_VTA_ID',
        'PTO_VTA_DESCRIPCION',
        'USERID',
        'USERID_cancelado',
        'DESC_FORMA_PAGO_CODIGO',
        'CLI_RUC',
        'CLI_RAZON_SOCIAL',
        'CLI_CORREO_ELECT_SRI',
        'COND_ID',
        'COND_ID_ORIGINAL',
        'VEND_ID',
        'VEND_NOMBRES',
        'VEND_APELLIDOS',
        'despacho',
        'porcentage_descuentooriginal',
        'porcentage_descuento',
        'subtotal',
        'descuento',
        'subtotalmenosdescuento',
        'iva',
        'total',
        'requiere_aprobacion_comercial',
        'motivo_aprobacion_comercial',
        'aprobado_comercial',
        'aprobador_comercial_id',
        'requiere_aprobacion_crediticia',
        'aprobado_crediticio',
        'aprobador_crediticio_id',
        'motivo_crediticio',
        'nota_interna',
        'nota_cliente',
        'MST_FAC_VTAResult',
        'pedido_origen',
        'fecha_factura',
        'fecha_hora_factura',
        'fecha_hora_rechazado',
        'fecha_entrega',
        'fecha_hora_cancelado',
        'FAC_VTA_MENSAJE',
        'factura_posterior_despacho',
        'factura_a_demanda',
        'factura_siguiente_mes',
        'despachado',
        'despacho_en_proceso',
        'transferencia_en_proceso',
        'rechazado',
        'impuesto',
        'orden_de_compra',
        'aprobado_comercial_mensaje',
        'aprobado_crediticio_mensaje',
        'origen',
        'fe_facturacion_reserva',
        'pendiente_salida',

        'nota_operaciones',
        'modificador_global'
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

    public function direccion_entrega()
    {
        return $this->belongsTo(GpClienteSucursal::class, 'sucursal_id');
    }


    public function detallepedido()
    {
        return $this->hasMany(GpPedidoDetalle::class, 'pedido_id');
    }

    public function pedidostock()
    {
        return $this->hasMany(GpPedidoDetalleStock::class, 'pedido_id');
    }

    public function gpcliente()
    {
        return $this->belongsTo(GpCliente::class, 'cliente_id');
    }

    public function gpvendedor()
    {

        return $this->belongsTo(GpVendedor::class, 'VEND_ID', 'VEND_ID');
    }

    public function gpcotizacion()
    {
        return $this->belongsTo(GpCotizacion::class, 'cotizacion_id');
    }



    public function imagenes (){
        return $this->hasMany(GpPedidoImagen::class, 'pedido_id');
    }




    public function getMsgRegistroCopiaAttribute()
    {
        if (!$this->relationLoaded('gpcotizacion')) {
            return null;
        }
        // 1. Validaciones iniciales de seguridad
        $cotizacion = $this->gpcotizacion;
        if (!$cotizacion) {
            return null;
        }

        $mensajes = [];

        // --- PARTE A: Validación Directa de la Cotización ---
        if ($cotizacion->requiere_aprobacion_comercial === 'yes' && $cotizacion->aprobado_comercial != 'yes') {
            $mensajes[] = "Requiere aprobación comercial. La cotización no fue aprobada con anterioridad (ID: {$cotizacion->id}).";
        }

        // --- PARTE B: Validación "Volver a Pedir" (Registro Copia) ---
        // Buscamos si existe el registro de copia
        $rgCopy = GpRegistroCopia::where('cotizacion_destino', $cotizacion->id)
            ->where('tipo', 'volver_a_pedir')
            ->first();

        if ($rgCopy) {
            $esPedido = !empty($rgCopy->pedido_origen);
            $idOrigen = $esPedido ? $rgCopy->pedido_origen : $rgCopy->cotizacion_origen;

            if ($idOrigen) {
                $origenEntidad = $esPedido ? GpPedido::find($idOrigen) : GpCotizacion::find($idOrigen);
                $desde = $esPedido ? 'pedido' : 'cotizacion';
                $prefijo = $esPedido ? 'PED' : 'COT';
                $codigoVisual = $prefijo . str_pad($idOrigen, 8, "0", STR_PAD_LEFT);

                $fechaTxt = optional($origenEntidad)->created_at
                    ? optional($origenEntidad)->created_at->format('Y-m-d')
                    : 'S/F';

                // Lógica específica del mensaje de copia
                if ($origenEntidad && $origenEntidad->requiere_aprobacion_comercial === 'yes' && $origenEntidad->aprobado_comercial !== 'yes') {
                    $mensajes[] = "Requiere aprobación comercial. La Cotización del pedido es desde Volver a Pedir estado 'Espera'. Obligatorio aprobar. Desde $desde: $codigoVisual, fecha: $fechaTxt";
                } else {
                    // Si es copia pero el origen estaba OK, igual avisamos que es copia
                    $mensajes[] = "Requiere aprobación comercial. La Cotización desde Volver a Pedir ($desde original: $codigoVisual, fecha: $fechaTxt)";
                }
            }
        }

        // --- RESULTADO FINAL ---
        if (empty($mensajes)) {
            return null;
        }

        // Unimos los mensajes. Si hay 2 razones, se verán separadas por " | "
        return implode(" | ", $mensajes);
    }


    public function gptrasladoventa()
    {
        return $this->hasMany(GpTrasladosVentas::class, 'pedido_id');
    }


    public function getCheckTrasladoVentaAttribute(): bool
    {
        return $this->gptrasladoventa()
            ->where(function ($query) {
                $query->where('completado', 1)
                    ->orWhere('picking', 1)
                    ->orwhere('trasladado', '>', 0);
            })
            ->exists();
    }



}
