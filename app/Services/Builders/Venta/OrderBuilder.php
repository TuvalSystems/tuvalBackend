<?php

namespace App\Services\Builders\Venta;

use App\Enums\Ventas\OrderOrigenEnum;
use App\Enums\Ventas\TaxRateEnum;
use App\Models\GpPedido;
use App\Models\GpPedidoDetalle;
use App\Models\GpPedidoDetalleProveedores;
use App\Models\GpPedidoDetalleStock;
use App\Models\GpPuntoVenta;
use App\Models\GpUsuario;
use App\Services\Builders\Venta\Contracts\OrderBuilderInterface;

class OrderBuilder implements OrderBuilderInterface
{

    public function formPayloadOrderDemand(array $infoCreator, array $data, array  $approvalComercial, GpPuntoVenta $puntoVenta, string  $requiereAprobacionCrediticia): GpPedido
    {
        $newOrder           = new GpPedido();
        $subtotalBruto      = (float)  data_get($data, 'subtotal', 0) + (float) data_get($data, 'descuento', 0);
        $descuento          = (float) data_get($data, 'descuento', 0);
        $subMenosDescuento  = (float) data_get($data, 'subtotal', 0);

        $newOrder->fill([
            'cotizacion_id'                 => data_get($infoCreator, 'cotizacion_id'),
            'user_id'                       => data_get($infoCreator, 'user.id'),
            'sucursal_id'                   => data_get($data, 'sucursal_id'),
            'sucursal_facturacion_id'       => data_get($data, 'sucursal_facturacion_id'),
            'cliente_id'                    => data_get($data, 'cliente_id'),
            'factura_posterior_despacho'    => data_get($data, 'factura_posterior_despacho'),
            'factura_a_demanda'             => data_get($data, 'factura_a_demanda'),
            'factura_siguiente_mes'         => data_get($data, 'factura_siguiente_mes'),
            'impuesto'                      => TaxRateEnum::IVA_15->value,
            'BOD_BODEGA'                    => data_get($infoCreator, 'bodega'),
            'PTO_VTA_ID'                    => data_get($data, 'PTO_VTA_ID'),
            'PTO_VTA_DESCRIPCION'           => $puntoVenta->PTO_VTA_DESCRIPCION,
            'USERID'                        => data_get($infoCreator, 'user.USERID'),
            'DESC_FORMA_PAGO_CODIGO'        => data_get($data, 'DESC_FORMA_PAGO_CODIGO'),
            'CLI_RUC'                       => data_get($data, 'CLI_RUC'),
            'CLI_RAZON_SOCIAL'              => data_get($data, 'CLI_RAZON_SOCIAL'),
            'CLI_CORREO_ELECT_SRI'          => data_get($data, 'CLI_CORREO_ELECT_SRI'),
            'COND_ID'                       => data_get($data, 'COND_ID'),
            'COND_ID_ORIGINAL'              => data_get($data, 'COND_ID_ORIGINAL'),
            'VEND_ID'                       => data_get($data, 'VEND_ID'),
            'VEND_NOMBRES'                  => data_get($data, 'VEND_NOMBRES'),
            'VEND_APELLIDOS'                => data_get($data, 'VEND_APELLIDOS'),
            'despacho'                      => data_get($data, 'despacho'),
            'porcentage_descuentooriginal'  => data_get($data, 'porcentage_descuentooriginal'),
            'porcentage_descuento'          => data_get($data, 'porcentage_descuento'),
            'subtotal'                      => $subtotalBruto,
            'descuento'                     => $descuento,
            'subtotalmenosdescuento'        => $subMenosDescuento,
            'iva'                           => (float) data_get($data, 'iva', 0),
            'total'                         => (float) data_get($data, 'total', 0),
            'requiere_aprobacion_comercial' => data_get($approvalComercial, 'requiere'),
            'motivo_aprobacion_comercial'   => data_get($infoCreator, 'motivoComercial'),
            'aprobado_comercial'            => 'no',
            'aprobador_comercial_id'        => null,
            'requiere_aprobacion_crediticia' => $requiereAprobacionCrediticia,
            'aprobado_crediticio'           => 'no',
            'aprobador_crediticio_id'       => null,
            'motivo_crediticio'             => data_get($infoCreator, 'motivoCrediticio'),
            'nota_interna'                  => data_get($data, 'nota_interna'),
            'nota_cliente'                  => data_get($data, 'nota_cliente'),
            'nota_operaciones'              => data_get($data, 'nota_operaciones'),
            'orden_de_compra'               => data_get($data, 'orden_de_compra'),
            'fecha_entrega'                 => data_get($infoCreator, 'fecha_entrega'),
            'origen'                        => data_get($infoCreator, 'origen'),
            'fe_facturacion_reserva'        => data_get($infoCreator, 'fecha_reserva'),
            'modificador_global'            => data_get($data, 'modificador_global', 0),
        ]);

        return $newOrder;
    }



    public function formPayloadOrderDetail(int $orderId, array $item, int $i): GpPedidoDetalle
    {
        $det = new GpPedidoDetalle();
        $det->fill([
            'pedido_id'                 => $orderId,
            'orden'                     => $i + 1,
            'ART_CODIGO'                => data_get($item, 'ART_CODIGO'),
            'ART_DESCRIPCION'           => data_get($item, 'ART_DESCRIPCION'),
            'ART_DESCRIPCION_ORIGINAL'  => data_get($item, 'ART_DESCRIPCION_ORIGINAL'),
            'CHECK_ALTERNATIVO'         => data_get($item, 'CHECK_ALTERNATIVO', 0),
            'ART_CODIGO_TRES_LETRAS'    => data_get($item, 'ART_CODIGO_TRES_LETRAS'),
            'ART_DECIMAL_CANTIDAD'      => (int) data_get($item, 'ART_DECIMAL_CANTIDAD', 0),
            'ART_PLAN_UMEDIDA'          => data_get($item, 'ART_PLAN_UMEDIDA'),
            'ART_MARCA'                 => data_get($item, 'ART_MARCA'),
            'ART_MATERIAL'              => data_get($item, 'ART_MATERIAL'),
            'ART_PESO'                  => data_get($item, 'ART_PESO'),
            'ART_TIPO_COMPRA'           => data_get($item, 'ART_TIPO_COMPRA'),
            'ART_VALOR'                 => (float)data_get($item, 'ART_VALOR', 0),
            'ART_VALOR_ORIGINAL'        => data_get($item, 'ART_VALOR_ORIGINAL', 0),
            'ART_DESCUENTO'             => data_get($item, 'ART_DESCUENTO', 0),
            'ART_IVA'                   => data_get($item, 'ART_IVA', 0),
            'ART_CANTIDAD'              => data_get($item, 'ART_CANTIDAD', 0),
            'ART_STOCK'                 => data_get($item, 'ART_STOCK', 0),
            'ART_IMP_VTA'               => data_get($item, 'ART_IMP_VTA'),
            'ART_COSTO_PROMEDIO'        => data_get($item, 'ART_COSTO_PROMEDIO'),
            'descuento'                 => data_get($item, 'descuento'),
            'descuento_original'        => data_get($item, 'descuento_original'),
            'modificador'               => data_get($item, 'modificador'),
        ]);
        return $det;
    }

    public function formPayloadOrderDetailProvider(array $provider, int $orderId, int $detailOrder, string $recibido): GpPedidoDetalleProveedores
    {
        $prov = new GpPedidoDetalleProveedores();
        $prov->fill([
            'pedido_id'             => $orderId,
            'detalle_id'            => $detailOrder,
            'ID_PROVEEDOR'          => data_get($provider, 'ID_PROVEEDOR', ''),
            'DESCRIPCION_ARTICULO'  => data_get($provider, 'DESCRIPCION_ARTICULO', ''),
            'FECHA_ENTREGA'         => data_get($provider, 'FECHA_ENTREGA', ''),
            'CANTIDAD'              => data_get($provider, 'CANTIDAD', 0),
            'COSTO_UNITARIO'        => (float)data_get($provider, 'COSTO_UNITARIO', 0),
            'IMPUESTO'              => (float)data_get($provider, 'IMPUESTO', 0),
            'TOTAL'                 => (float)data_get($provider, 'TOTAL', 0),
            'TOTALCONIMPUESTO'      => (float)data_get($provider, 'TOTALCONIMPUESTO', 0),
            'NOTA'                  => data_get($provider, 'NOTA', ''),
            'recibido'              => $recibido,
            'MARGEN'                => (float)data_get($provider, 'MARGEN', 0)
        ]);

        return $prov;
    }


    public function formPayloadOrderDetailStock(mixed $item, int $orderId, int $detailOrderId, float $disponible, float $reservado): GpPedidoDetalleStock
    {
        $newStock = new GpPedidoDetalleStock();
        $newStock->fill([
            'pedido_id'                 => $orderId,
            'detalle_id'                => $detailOrderId,
            'STO_ART_BODEGA'            => data_get($item, 'STO_ART_BODEGA', ''),
            'STO_ART_STOCK_DISPONIBLE'  => (float)$disponible,
            'seleccionado'              => (float) data_get($item, 'seleccionado', 0),
            'reservado'                 => (float)$reservado,
        ]);
        return $newStock;
    }


    /**
     *  nuevo proceso de guardado de pedidos v2
     *
     *
     */


    public function formPayloadOrderV2(array $data, GpUsuario $user, GpPuntoVenta $ptoVta,  array $requiredApprovalCommercial, array $extras): GpPedido
    {
        $od = new GpPedido();
        $od->fill([
            'cotizacion_id'                     => data_get($data, 'cotizacion_id'),
            'user_id'                           => $user->id,
            'USERID'                            => $user->USERID,
            'sucursal_id'                       => data_get($data, 'direccion_entrega_id'),
            'sucursal_facturacion_id'           => data_get($data, 'direccion_facturacion_id'),
            'cliente_id'                        => data_get($data, 'cliente_id', null),
            'factura_posterior_despacho'        => data_get($data, 'factura_posterior_despacho'),
            'factura_a_demanda'                 => data_get($data, 'factura_a_demanda'),
            'factura_siguiente_mes'             => data_get($data, 'factura_siguiente_mes'),
            'impuesto'                          => TaxRateEnum::IVA_15->value,
            'BOD_BODEGA'                        => data_get($data, 'bodega_facturacion'),
            'PTO_VTA_ID'                        => data_get($data, 'punto_venta_id'),
            'PTO_VTA_DESCRIPCION'               => $ptoVta->PTO_VTA_DESCRIPCION,
            'DESC_FORMA_PAGO_CODIGO'            => data_get($data, 'forma_pago_id'),
            'CLI_RUC'                           => data_get($data, 'ruc'),
            'CLI_RAZON_SOCIAL'                  => data_get($data, 'razon_social'),
            'CLI_CORREO_ELECT_SRI'              => data_get($data, 'correo_electronico_sri'),
            'COND_ID'                           => data_get($data, 'condicion_pago_id'),
            'COND_ID_ORIGINAL'                  => data_get($data, 'condicion_pago_original_id'),
            'VEND_ID'                           => data_get($data, 'vendedor_id'),
            'VEND_NOMBRES'                      => data_get($data, 'vendedor_nombres'),
            'VEND_APELLIDOS'                    => data_get($data, 'vendedor_apellidos'),
            'despacho'                          => data_get($extras, 'tipo_despacho'),
            'orden_de_compra'                   => data_get($data, 'orden_de_compra'),
            'porcentage_descuento'              => data_get($data, 'descuento_articulo'),
            'porcentage_descuentooriginal'      => data_get($data, 'descuento_original'),
            'subtotal'                          => (float) data_get($data, 'subtotal') + (float) data_get($data, 'descuento'),
            'descuento'                         => (float) data_get($data, 'descuento'),
            'subtotalmenosdescuento'            => (float) data_get($data, 'subtotal'),
            'iva'                               => (float) data_get($data, 'iva'),
            'total'                             => (float) data_get($data, 'total'),

            'requiere_aprobacion_comercial'     => data_get($requiredApprovalCommercial, 'requiere'),
            'motivo_aprobacion_comercial'       => data_get($extras, 'motivo_comercial'),
            'aprobado_comercial'                => 'no',
            'aprobador_comercial_id'            => null,
            'requiere_aprobacion_crediticia'    => data_get($extras, 'requiere_aprobacion_crediticia'),
            'aprobado_crediticio'               => 'no',
            'motivo_crediticio'                 => data_get($extras, 'motivo_crediticio'),
            'aprobador_crediticio_id'           => null,
            'nota_interna'                      => data_get($data, 'nota_interna', null),
            'nota_cliente'                      => data_get($data, 'nota_cliente', null),
            'nota_operaciones'                  => data_get($data, 'nota_operaciones', null),
            'fecha_entrega'                     => data_get($data, 'fecha_entrega', null),
            'origen'                            => data_get($extras, 'origen'),
            'modificador_global'                => data_get($data, 'modificador_global', 0),
            'fe_facturacion_reserva'            =>  data_get($extras, 'facturacion_reserva'),
            'proyecto'                          => data_get($data, 'proyecto', null),
        ]);
        return $od;
    }



    /** builder para facturar existentes */
    public function formPayloadInvoiceExistence(array $data, GpUsuario $user, GpPuntoVenta $ptoVta): GpPedido
    {
        $od = new GpPedido();
        $od->fill([
            'cotizacion_id'                     => data_get($data, 'cotizacion_id'),
            'user_id'                           => $user->id,
            'USERID'                            => $user->USERID,
            'sucursal_id'                       => data_get($data, 'sucursal_id'),
            'sucursal_facturacion_id'           => data_get($data, 'sucursal_facturacion_id'),
            'cliente_id'                        => data_get($data, 'cliente_id', null),
            'factura_posterior_despacho'        => data_get($data, 'factura_posterior_despacho'),
            'factura_a_demanda'                 => data_get($data, 'factura_a_demanda'),
            'factura_siguiente_mes'             => data_get($data, 'factura_siguiente_mes'),
            'impuesto'                          => TaxRateEnum::IVA_15->value,
            'BOD_BODEGA'                        => data_get($data, 'BOD_BODEGA'),
            'PTO_VTA_ID'                        => data_get($data, 'PTO_VTA_ID'),
            'PTO_VTA_DESCRIPCION'               => $ptoVta->PTO_VTA_DESCRIPCION,
            'DESC_FORMA_PAGO_CODIGO'            => data_get($data, 'DESC_FORMA_PAGO_CODIGO'),
            'CLI_RUC'                           => data_get($data, 'CLI_RUC'),
            'CLI_RAZON_SOCIAL'                  => data_get($data, 'CLI_RAZON_SOCIAL'),
            'CLI_CORREO_ELECT_SRI'              => data_get($data, 'CLI_CORREO_ELECT_SRI'),
            'COND_ID'                           => data_get($data, 'COND_ID'),
            'COND_ID_ORIGINAL'                  => data_get($data, 'COND_ID_ORIGINAL'),
            'VEND_ID'                           => data_get($data, 'VEND_ID'),
            'VEND_NOMBRES'                      => data_get($data, 'VEND_NOMBRES'),
            'VEND_APELLIDOS'                    => data_get($data, 'VEND_APELLIDOS'),
            'despacho'                          => data_get($data, 'despacho'),
            'orden_de_compra'                   => data_get($data, 'orden_de_compra'),
            'porcentage_descuento'              => data_get($data, 'porcentage_descuento'),
            'porcentage_descuentooriginal'      => data_get($data, 'porcentage_descuentooriginal'),
            'subtotal'                          => (float) data_get($data, 'subtotal') + (float) data_get($data, 'descuento'),
            'descuento'                         => (float) data_get($data, 'descuento'),
            'subtotalmenosdescuento'            => (float) data_get($data, 'subtotal'),
            'iva'                               => (float) data_get($data, 'iva'),
            'total'                             => (float) data_get($data, 'total'),

            'requiere_aprobacion_comercial'     => data_get($data, 'requiere_aprobacion_comercial'),
            // 'motivo_aprobacion_comercial'       => data_get($data, 'motivo_comercial'),
            'aprobado_comercial'                => data_get($data, 'aprobado_comercial'),
            'aprobador_comercial_id'            => data_get($data, 'aprobador_comercial_id'),
            'requiere_aprobacion_crediticia'    => data_get($data, 'requiere_aprobacion_crediticia'),
            'aprobado_crediticio'               => data_get($data, 'aprobado_crediticio'),
            // 'motivo_crediticio'                 => data_get($data, 'motivo_crediticio'),
            'aprobador_crediticio_id'           => data_get($data, 'aprobador_crediticio_id'),
            'nota_interna'                      => data_get($data, 'nota_interna'),
            'nota_cliente'                      => data_get($data, 'nota_cliente'),
            'nota_operaciones'                  => data_get($data, 'nota_operaciones'),
            'fecha_entrega'                     => data_get($data, 'fecha_entrega', null),
            'origen'                            => OrderOrigenEnum::FACTURAR_EXISTENTE->value,
            'modificador_global'                => data_get($data, 'modificador_global', 0),
            'fe_facturacion_reserva'            => data_get($data, 'facturacion_reserva'),
            'pedido_origen'                     => data_get($data, 'pedido'),
        ]);

        return $od;
    }
}
