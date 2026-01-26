<?php

namespace App\Services\Builders\Venta;

use App\Enums\Ventas\TaxRateEnum;
use App\Models\GpPedido;
use App\Models\GpPedidoDetalle;
use App\Models\GpPedidoDetalleProveedores;
use App\Models\GpPedidoDetalleStock;
use App\Models\GpUsuario;
use App\Services\Builders\Venta\Contracts\OrderBuilderInterface;

class OrderBuilder implements OrderBuilderInterface
{



    public function fromPayloadOrder($cotizacionId, array $data, array $approvalComercial, GpUsuario $user, $pv, $requiereAprobacionCrediticia, $motivoCrediticio, $fechaEntrega, $origen, $fFacturacionReserva = null, $bodega)
    {
        $newOrder = new GpPedido();
        $subtotalBruto = (float) $data['subtotal'] + (float) $data['descuento'];
        $descuento = (float) $data['descuento'];
        $subMenosDescuento = (float) $data['subtotal'];
        $motivoLimpio = iconv('UTF-8', 'UTF-8//IGNORE', $approvalComercial['motivo']);

        // 2. Si iconv falla (a veces pasa en ciertos servidores), usa este fallback robusto:
        if (!$motivoLimpio) {
            // Detecta si viene en Latin1 (común en bases de datos viejas) y lo pasa a UTF-8
            $encoding = mb_detect_encoding($approvalComercial['motivo'], mb_detect_order(), true);
            $motivoLimpio = mb_convert_encoding($approvalComercial['motivo'], 'UTF-8', $encoding ?: 'ISO-8859-1');
        }

        $newOrder->fill([
            'cotizacion_id'                 => $cotizacionId,
            'user_id'                       => $user->id,
            'sucursal_id'                   => $data['sucursal_id'],
            'sucursal_facturacion_id'       => $data['sucursal_facturacion_id'],
            'cliente_id'                    => $data['cliente_id'],
            'factura_posterior_despacho'    => $data['factura_posterior_despacho'],
            'factura_a_demanda'             => $data['factura_a_demanda'],
            'factura_siguiente_mes'         => $data['factura_siguiente_mes'],
            'impuesto'                      => TaxRateEnum::IVA_15->value,
            'BOD_BODEGA'                    => $bodega,
            'PTO_VTA_ID'                    => $data['PTO_VTA_ID'],
            'PTO_VTA_DESCRIPCION'           => $pv->PTO_VTA_DESCRIPCION,
            'USERID'                        => $user->USERID,
            'DESC_FORMA_PAGO_CODIGO'        => $data['DESC_FORMA_PAGO_CODIGO'],
            'CLI_RUC'                       => $data['CLI_RUC'],
            'CLI_RAZON_SOCIAL'              => $data['CLI_RAZON_SOCIAL'],
            'CLI_CORREO_ELECT_SRI'          => $data['CLI_CORREO_ELECT_SRI'],
            'COND_ID'                       => $data['COND_ID'],
            'COND_ID_ORIGINAL'              => $data['COND_ID_ORIGINAL'],
            'VEND_ID'                       => $data['VEND_ID'],
            'VEND_NOMBRES'                  => $data['VEND_NOMBRES'],
            'VEND_APELLIDOS'                => $data['VEND_APELLIDOS'],
            'despacho'                      => $data['despacho'],
            'porcentage_descuentooriginal'  => $data['porcentage_descuentooriginal'],
            'porcentage_descuento'          => $data['porcentage_descuento'],
            'subtotal'                      => $subtotalBruto,
            'descuento'                     => $descuento,
            'subtotalmenosdescuento'        => $subMenosDescuento,
            'iva'                           => number_format((float) $data['iva'], 2, '.', ''),
            'total'                         => number_format((float) $data['total'], 2, '.', ''),
            'requiere_aprobacion_comercial' => $approvalComercial['requiere'],
            'motivo_aprobacion_comercial'   => $motivoLimpio,
            'aprobado_comercial'            => 'no',
            'aprobador_comercial_id'        => null,
            'requiere_aprobacion_crediticia' => $requiereAprobacionCrediticia,
            'aprobado_crediticio'           => 'no',
            'aprobador_crediticio_id'       => null,
            'motivo_crediticio'             => $motivoCrediticio,
            'nota_interna'                  => $data['nota_interna'],
            'nota_cliente'                  => $data['nota_cliente'],
            'orden_de_compra'               => $data['orden_de_compra'],
            'fecha_entrega'                 => $fechaEntrega,
            'origen'                        => $origen,
            'fe_facturacion_reserva'        => $fFacturacionReserva,
        ]);

        return $newOrder;
    }



    public function formPayloadOrderDetail($orderId, $item, $i)
    {
        $det = new GpPedidoDetalle();
        $det->fill([
            'pedido_id' => $orderId,
            'orden' => $i + 1,
            'ART_CODIGO' => $item['ART_CODIGO'],
            'ART_DESCRIPCION' => $item['ART_DESCRIPCION'],
            'ART_DESCRIPCION_ORIGINAL' => $item['ART_DESCRIPCION_ORIGINAL'],
            'CHECK_ALTERNATIVO' => $item['CHECK_ALTERNATIVO'] ?? 0,
            'ART_CODIGO_TRES_LETRAS' => $item['ART_CODIGO_TRES_LETRAS'],
            'ART_DECIMAL_CANTIDAD' => (int) $item['ART_DECIMAL_CANTIDAD'],
            'ART_PLAN_UMEDIDA' => $item['ART_PLAN_UMEDIDA'],
            'ART_MARCA' => $item['ART_MARCA'],
            'ART_MATERIAL' => $item['ART_MATERIAL'],
            'ART_PESO' => $item['ART_PESO'],
            'ART_TIPO_COMPRA' => $item['ART_TIPO_COMPRA'],
            'ART_VALOR' => $item['ART_VALOR'],
            'ART_VALOR_ORIGINAL' => $item['ART_VALOR_ORIGINAL'],
            'ART_DESCUENTO' => $item['ART_DESCUENTO'],
            'ART_IVA' => $item['ART_IVA'],
            'ART_CANTIDAD' => $item['ART_CANTIDAD'],
            'ART_STOCK' => $item['ART_STOCK'],
            'ART_IMP_VTA' => $item['ART_IMP_VTA'],
            'ART_COSTO_PROMEDIO' => $item['ART_COSTO_PROMEDIO'],
            'descuento' => $item['descuento'],
            'descuento_original' => $item['descuento_original'],
            'modificador' => $item['modificador'],
        ]);
        return $det;
    }

    public function formPayloadOrderDetailProvider($provider, $orderId, $detailOrder, $recibido)
    {
        $prov = new GpPedidoDetalleProveedores();
        $prov->fill([
            'pedido_id' => $orderId,
            'detalle_id' => $detailOrder,
            'ID_PROVEEDOR' => $provider['ID_PROVEEDOR'],
            'DESCRIPCION_ARTICULO' => $provider['DESCRIPCION_ARTICULO'],
            'FECHA_ENTREGA' => $provider['FECHA_ENTREGA'],
            'CANTIDAD' => $provider['CANTIDAD'],
            'COSTO_UNITARIO' => $provider['COSTO_UNITARIO'],
            'IMPUESTO' => $provider['IMPUESTO'],
            'TOTAL' => $provider['TOTAL'],
            'TOTALCONIMPUESTO' => $provider['TOTALCONIMPUESTO'],
            'NOTA' => $provider['NOTA'],
            'recibido' => $recibido,
            'MARGEN' => $provider['MARGEN']
        ]);

        return $prov;
    }


    public function formPayloadOrderDetailStock($item, $orderId, $detailOrderId, $disponible, $reservado)
    {
        $newStock = new GpPedidoDetalleStock();
        $newStock->fill([
            'pedido_id' => $orderId,
            'detalle_id' => $detailOrderId,
            'STO_ART_BODEGA' => $item['STO_ART_BODEGA'],
            'STO_ART_STOCK_DISPONIBLE' => $disponible,
            'seleccionado' => $item['seleccionado'],
            'reservado' => $reservado,
        ]);
        return $newStock;
    }
}
