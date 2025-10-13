<?php

namespace App\Models;

use App\Models\reservas\GpReserva;
use App\Models\reservas\GpReservaTipoTraslado;
use App\Models\reservas\GpTipoReservas;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use \DateTimeInterface;

/**
 * App\Models\GpCotizacion
 *
 * @property int $id
 * @property string|null $cotizacion
 * @property int|null $user_id
 * @property int|null $sucursal_id
 * @property int|null $sucursal_facturacion_id
 * @property int|null $cliente_id
 * @property int|null $prospecto_id
 * @property string $estado
 * @property string $BOD_BODEGA
 * @property string $PTO_VTA_ID
 * @property string $PTO_VTA_DESCRIPCION
 * @property string $USERID
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
 * @property string|null $impreso
 * @property string|null $nota_interna
 * @property string|null $nota_cliente
 * @property int|null $impuesto
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacion newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacion newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacion query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacion whereAprobadoComercial($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacion whereAprobadoCrediticio($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacion whereAprobadorComercialId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacion whereAprobadorCrediticioId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacion whereBODBODEGA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacion whereCLICORREOELECTSRI($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacion whereCLIRAZONSOCIAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacion whereCLIRUC($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacion whereCONDID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacion whereCONDIDORIGINAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacion whereClienteId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacion whereCotizacion($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacion whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacion whereDESCFORMAPAGOCODIGO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacion whereDescuento($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacion whereDespacho($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacion whereEstado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacion whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacion whereIva($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacion whereNotaCliente($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacion whereNotaInterna($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacion wherePTOVTADESCRIPCION($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacion wherePTOVTAID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacion wherePorcentageDescuento($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacion wherePorcentageDescuentooriginal($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacion whereRequiereAprobacionComercial($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacion whereRequiereAprobacionCrediticia($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacion whereSubtotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacion whereSubtotalmenosdescuento($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacion whereSucursalFacturacionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacion whereSucursalId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacion whereTotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacion whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacion whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacion whereVENDAPELLIDOS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacion whereVENDID($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacion whereVENDNOMBRES($value)
 * @mixin \Eloquent
 * @property int|null $factura_posterior_despacho
 * @property int|null $factura_a_demanda
 * @property int|null $factura_siguiente_mes
 * @property string|null $orden_de_compra
 * @property string|null $fecha_impreso
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacion whereFacturaADemanda($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacion whereFacturaPosteriorDespacho($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacion whereFacturaSiguienteMes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacion whereFechaImpreso($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacion whereImpreso($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacion whereOrdenDeCompra($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacion whereProspectoId($value)
 * @property string|null $aprobado_comercial_mensaje
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacion whereAprobadoComercialMensaje($value)
 * @property string|null $fecha_entrega
 * @property boolean $facturado
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacion whereFechaEntrega($value)
 * @property string|null  $user_ing_recordatorio
 * @property int|null $co_tipTrasResInv
 * @property int|null $co_usrIngSolResInv
 * @property string|null $origen
 * @property int|null $pedido_reserva_id
 * @property string|null $pedido_generado_at
 *
 */
class GpCotizacion extends Model
{
    use HasFactory;

    protected $appends = ['reserva_disabled', 'has_l_items', 'has_service_items', 'has_reserva'];
    protected $casts = [
        'reserva_disabled' => 'boolean',
        'has_l_items' => 'boolean',
        'has_service_items' => 'boolean',
        'has_reserva' => 'boolean',
    ];
    protected $fillable = [
        'cotizacion',
        'user_id',
        'sucursal_id',
        'sucursal_facturacion_id',
        'cliente_id',
        'prospecto_id',
        'estado',
        'BOD_BODEGA',
        'PTO_VTA_ID',
        'PTO_VTA_DESCRIPCION',
        'USERID',
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
        'descuento',
        'porcentage_descuentooriginal',
        'porcentage_descuento',
        'subtotal',
        'subtotalmenosdescuento',
        'iva',
        'total',
        'requiere_aprobacion_comercial',
        'motivo_aprobacion_comercial',
        'aprobado_comercial',
        'aprobacion_comercial_id',
        'requiere_aprobacion_crediticia',
        'aprobado_crediticia',
        'impreso',
        'nota_interna',
        'nota_cliente',
        'impuesto',
        'factura_posterior_despacho',
        'factura_a_demanda',
        'factura_siguiente_mes',
        'orden_de_compra',
        'fecha_impreso',
        'aprobado_comercial_mensaje',
        'fecha_entrega',
        'facturado',
        'user_ing_recordatorio',
        'origen',
        'pedido_reserva_id',
        'pedido_generado_at',
    ];

    /**
     * Prepare a date for array / JSON serialization.
     *
     * @param  \DateTimeInterface  $date
     * @return string
     */
    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i');
    }


    public function detallecotizacion()
    {
        return $this->hasMany(GpCotizacionDetalle::class, 'cotizacion_id', 'id');
    }

    public function usuario()
    {
        return $this->belongsTo(GpUsuario::class, 'user_id');
    }

    public function motivosNoVenta()
    {
        return $this->belongsTo(GpCotizacionVentaMotivo::class, 'cotizacion_venta_motivo_id');
    }

    public function usuariorecordatorio()
    {
        return $this->belongsTo(GpUsuario::class, 'user_ing_recordatorio');
    }



    public function detallecotizacionstock()
    {
        return $this->hasMany(GpCotizacionDetalleStock::class, 'cotizacion_id', 'id');
    }





    /** functiones de atributos  */

    public function getReservaDisabledAttribute(): bool
    {
        return ! $this->detallecotizacionstock()
            ->where('seleccionado', '>', 0)
            ->exists();
    }


    public function getHasLItemsAttribute(): bool
    {
        return $this->relationLoaded('detallecotizacion')
            ? $this->detallecotizacion->contains('ART_TIPO_COMPRA', 'L')
            : $this->detallecotizacion()->where('ART_TIPO_COMPRA', 'L')->exists();
    }


    public function getHasServiceItemsAttribute(): bool
    {
        return $this->relationLoaded('detallecotizacion')
            ? $this->detallecotizacion->contains('ART_TIPO_COMPRA', 'SERV')
            : $this->detallecotizacion()->where('ART_TIPO_COMPRA', 'SERV')->exists();
    }



    public function reserva()
    {
        return $this->hasOne(GpReserva::class, 'cotizacion_id', 'id');
    }

    public function getHasReservaAttribute(): bool
    {
        return $this->relationLoaded('reservas')
            ? $this->reserva->isNotEmpty()
            : $this->reserva()->exists();
    }
}
