<?php

namespace App\Services\Builders\Venta\Contracts;

use App\Models\ordendecompra\GpOrdenDeCompra;

interface PurchaseOrderBuilderInterface
{
    // Define los métodos que debe implementar el servicio
     public function formBuilderPruchaseOrder($order, $detalle, $prov, $dataProvider, $line): GpOrdenDeCompra;
}
