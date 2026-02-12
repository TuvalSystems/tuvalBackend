<?php

namespace App\Services\Builders\Venta;

use App\Enums\Ventas\PurchaseOrdersL;
use App\Models\ordendecompra\GpOrdenDeCompra;
use App\Services\Builders\Venta\Contracts\PurchaseOrderBuilderInterface;

class PurchaseOrderBuilder implements PurchaseOrderBuilderInterface
{


    public function formBuilderPruchaseOrder($order, $detalle, $prov, $dataProvider, $line): GpOrdenDeCompra
    {
        $nOrdL = new GpOrdenDeCompra();
        $nOrdL->fill([
            'detalle_id'                        => $detalle->id,
            'detalle_proveedores_id'            => $prov->id,
            'estado'                            => PurchaseOrdersL::INICIADO->value,
            'ORDEN_COMPRA_DOCU_ORIGEN'          => $order->pedido,
            'ORDEN_COMPRA_LINEA'                => $line,
            'ORDEN_COMPRA_FECHA_DOCUMENTO'      => $order->created_at,
            'ORDEN_COMPRA_FECHA_ENTREGA'        => $prov->FECHA_ENTREGA . ' 12:00:00',
            'ORDEN_COMPRA_ID_CLIENTE'           => $order->CLI_RUC,
            'ORDEN_COMPRA_ID_PROVEEDOR'         => $dataProvider->PROV_RUC,
            'ORDEN_COMPRA_ID_DIRECCION'         => $dataProvider->PROV_ID_DIRECCION,
            'ORDEN_COMPRA_NOTA_DOCU'            => $prov->NOTA,
            'ORDEN_COMPRA_BODEGA'               => $order->BOD_BODEGA,
            'ORDEN_COMPRA_ARTICULO'             => $detalle->ART_CODIGO,
            'ORDEN_COMPRA_DESCRIPCION_ARTICULO' => $prov->DESCRIPCION_ARTICULO,
            'ORDEN_COMPRA_UMEDIDA'              => $detalle->ART_PLAN_UMEDIDA,
            'ORDEN_COMPRA_CANTIDAD'             => $prov->CANTIDAD,
            'ORDEN_COMPRA_COSTO_UNITARIO'       => $prov->COSTO_UNITARIO,
            'ORDEN_COMPRA_TOTAL'                => $prov->TOTAL,
            'ORDEN_COMPRA_IMPUESTO'             => $prov->IMPUESTO,
            'ORDEN_COMPRA_ID_USUARIO'           => $order->USERID,
        ]);
        return $nOrdL;
    }

    public function formBuilderHeaderOrderSendGp($order): array
    {
        return [
            'docuOrigen'            => $order->ORDEN_COMPRA_DOCU_ORIGEN,
            'linea'                 => $order->ORDEN_COMPRA_LINEA,
            'fechaDocumento'        => $order->ORDEN_COMPRA_FECHA_DOCUMENTO,
            'fechaEntrega'          => $order->ORDEN_COMPRA_FECHA_ENTREGA,
            'idCliente'             => $order->ORDEN_COMPRA_ID_CLIENTE,
            'idProveedor'           => $order->ORDEN_COMPRA_ID_PROVEEDOR,
            'idDireccion'           => $order->ORDEN_COMPRA_ID_DIRECCION,
            'notaDocu'              => $order->ORDEN_COMPRA_NOTA_DOCU,
            'bodega'                => $order->ORDEN_COMPRA_BODEGA,
            'articulo'              => $order->ORDEN_COMPRA_ARTICULO,
            'descripcionArticulo'   => $order->ORDEN_COMPRA_DESCRIPCION_ARTICULO,
            'umedida'               => $order->ORDEN_COMPRA_UMEDIDA,
            'cantidad'              => $order->ORDEN_COMPRA_CANTIDAD,
            'costoUnitario'         => $order->ORDEN_COMPRA_COSTO_UNITARIO,
            'total'                 => $order->ORDEN_COMPRA_TOTAL,
            'impuesto'              => $order->ORDEN_COMPRA_IMPUESTO,
            'idUsuario'             => $order->ORDEN_COMPRA_ID_USUARIO
        ];
    }
}
