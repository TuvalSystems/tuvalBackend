<?php

namespace App\Models\Ventas\Cotizaciones;

use App\Models\GpCliente;
use App\Models\GpClienteSucursal;
use App\Models\GpCotizacion;
use App\Models\GpCotizacionVentaMotivo;
use App\Models\GpPedido;
use App\Models\GpProspecto;
use App\Models\GpUsuario;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HiCotizacion extends Model
{
    use HasFactory;


    protected $table = 'hi_cotizacion';

    protected $fillable = [
        'cot_id',
        'co_his',
        'cotizacion',
        'user_id',
        'sucursal_id',
        'sucursal_facturacion_id',
        'cliente_id',
        'prospecto_id',
        'estado',
        'facturado',
        'factura_posterior_despacho',
        'factura_a_demanda',
        'factura_siguiente_mes',
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
        'porcentage_descuentooriginal',
        'porcentage_descuento',
        'descuento',
        'subtotalmenosdescuento',
        'iva',
        'total',
        'requiere_aprobacion_comercial',
        'motivo_aprobacion_comercial',
        'aprobado_comercial',
        'aprobador_comercial_id',
        'aprobado_comercial_mensaje',
        'fecha_hora_aprobacion_comercial',
        'requiere_aprobacion_crediticia',
        'aprobado_crediticio',
        'aprobador_crediticio_id',
        'nota_interna',
        'nota_cliente',
        'nota_operaciones',
        'orden_de_compra',
        'impreso',
        'fecha_impreso',
        'fecha_entrega',
        'impuesto',
        'cotizacion_venta_motivo_id',
        'recordar',
        'fecha_recordatorio',
        'observacion',
        'user_ing_recordatorio',
        'origen',
        'fe_facturacion_reserva',
        'pedido_reserva_id',
        'pedido_generado_at',
        'user_rechazo_id',
        'fe_rechazo',
        'proyecto'
    ];

    protected $casts = [
        'cot_id'                            => 'integer',
        'co_his'                            => 'integer',
        'user_id'                           => 'integer',
        'sucursal_id'                       => 'integer',
        'sucursal_facturacion_id'           => 'integer',
        'cliente_id'                        => 'integer',
        'prospecto_id'                      => 'integer',
        'aprobador_comercial_id'            => 'integer',
        'aprobador_crediticio_id'           => 'integer',
        'cotizacion_venta_motivo_id'        => 'integer',
        'pedido_reserva_id'                 => 'integer',
        'user_rechazo_id'                   => 'integer',

        'facturado'                         => 'boolean',
        'factura_posterior_despacho'        => 'boolean',
        'factura_a_demanda'                 => 'boolean',
        'factura_siguiente_mes'             => 'boolean',
        'recordar'                          => 'boolean',

        'porcentage_descuentooriginal'      => 'decimal:2',
        'porcentage_descuento'              => 'decimal:2',
        'descuento'                         => 'decimal:2',
        'subtotalmenosdescuento'            => 'decimal:2',
        'iva'                               => 'decimal:2',
        'total'                             => 'decimal:2',

        'impuesto'                          => 'integer',

        'fecha_hora_aprobacion_comercial'   => 'datetime',
        'fecha_impreso'                     => 'datetime',
        'fecha_entrega'                     => 'datetime',
        'fecha_recordatorio'                => 'date',
        'fe_facturacion_reserva'            => 'datetime',
        'pedido_generado_at'                => 'datetime',
        'fe_rechazo'                        => 'datetime',
    ];

    public function cotizacionOriginal()
    {
        return $this->belongsTo(GpCotizacion::class, 'cot_id');
    }
    public function usuario()
    {
        return $this->belongsTo(GpUsuario::class, 'user_id');
    }

    public function sucursal()
    {
        return $this->belongsTo(GpClienteSucursal::class, 'sucursal_id');
    }

    public function sucursalFacturacion()
    {
        return $this->belongsTo(GpClienteSucursal::class, 'sucursal_facturacion_id');
    }

    public function cliente()
    {
        return $this->belongsTo(GpCliente::class, 'cliente_id');
    }

    public function prospecto()
    {
        return $this->belongsTo(GpProspecto::class, 'prospecto_id');
    }

    public function aprobadorComercial()
    {
        return $this->belongsTo(GpUsuario::class, 'aprobador_comercial_id');
    }

    public function aprobadorCrediticio()
    {
        return $this->belongsTo(GpUsuario::class, 'aprobador_crediticio_id');
    }

    public function motivoVenta()
    {
        return $this->belongsTo(GpCotizacionVentaMotivo::class, 'cotizacion_venta_motivo_id');
    }

    public function pedidoReserva()
    {
        return $this->belongsTo(GpPedido::class, 'pedido_reserva_id');
    }

    public function usuarioRechazo()
    {
        return $this->belongsTo(GpUsuario::class, 'user_rechazo_id');
    }

    public function detalles()
    {
        return $this->hasMany(HiCotizacionDetalle::class, 'hi_cot_id');
    }

    public function detalleStocks()
    {
        return $this->hasMany(HiCotizacionDetalleStock::class, 'hi_cot_id');
    }

    public function detalleProveedores()
    {
        return $this->hasMany(HiCotizacionDetalleProveedores::class, 'hi_cot_id');
    }
}
