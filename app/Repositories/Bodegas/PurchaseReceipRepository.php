<?php

namespace App\Repositories\Bodegas;

use App\Models\Compras\OrdenesCompra\GpOrdenDeCompraGenerado;
use App\Models\Compras\OrdenesCompra\GpOrdenDeCompraGeneradoDetalle;
use App\Repositories\Bodegas\Contracts\PurchaseReceipRepositoryInterface;
use Illuminate\Support\Facades\DB;

class PurchaseReceipRepository implements PurchaseReceipRepositoryInterface
{
    public function getGeneratedOrderDetails(int $purchaseOrderId)
    {
        return  GpOrdenDeCompraGeneradoDetalle::query()
            ->from('gp_orden_de_compra_generado_detalles as ocgd')
            ->leftJoin('gp_pedido_detalle_proveedores as pdp', 'pdp.id', '=', 'ocgd.detalle_proveedores_id')
            ->leftJoin('gp_pedido_detalles as pd', 'pd.id', '=', 'pdp.detalle_id')
            ->where('ocgd.orden_generada_id', $purchaseOrderId)
            ->select([
                'ocgd.*',                           // columnas del detalle OCGD
                DB::raw('COALESCE(pd.ART_PESO, 0) as ART_PESO'), // 0 si no hay detalle/proveedor
            ])
            ->get();
    }


    public function getPurchaseReceiptLById(int $id)
    {
        return  GpOrdenDeCompraGenerado::leftJoin('gp_orden_de_compras', 'gp_orden_de_compras.id', '=', 'gp_orden_de_compra_generados.orden_id')
            ->leftJoin('gp_proveedors', 'gp_proveedors.PROV_RUC', '=', 'gp_orden_de_compra_generados.ORDEN_COMPRA_ID_PROVEEDOR')
            ->join('gp_pedidos', 'gp_pedidos.pedido', '=', 'gp_orden_de_compras.ORDEN_COMPRA_DOCU_ORIGEN')
            ->where('gp_orden_de_compra_generados.id', $id)
            ->select(
                'gp_orden_de_compra_generados.*',
                'gp_orden_de_compras.ORDEN_COMPRA_DOCU_ORIGEN',
                'gp_orden_de_compras.ORDEN_COMPRA_BODEGA',
                'gp_orden_de_compras.ORDEN_COMPRA_NOTA_DOCU',
                'gp_proveedors.PROV_RAZON_SOCIAL',
                'gp_proveedors.PROV_DIRECCION_1',
                'gp_proveedors.PROV_TELEFONO_1',
                'gp_proveedors.PROV_CONDICION_PAGO',
                'gp_pedidos.CLI_RAZON_SOCIAL',
                'gp_pedidos.USERID',
                'gp_orden_de_compras.ORDEN_COMPRA_ID_CLIENTE'
            )
            ->first();
    }
}
