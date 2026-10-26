<?php

namespace App\Services\Compras\Recepciones;

use App\Repositories\Bodegas\Contracts\PurchaseReceipRepositoryInterface;
use App\Services\Compras\Recepciones\Contracts\PurchaseReceiptServiceInterface;

class PurchaseReceiptService implements PurchaseReceiptServiceInterface
{
    public function __construct(protected readonly PurchaseReceipRepositoryInterface $purchaseReceipRepository)
    {
    }


    public function setPurcharseOrderdetails($orderPurchase)
    {
        $detailOrderPurchase = $this->purchaseReceipRepository->getGeneratedOrderDetails($orderPurchase->id);

        /**** reduce permite reducer un conjuto de datos a un solo valor mediante la acumulacion. es una forma
         mas limpia y funcional de reemplazar un foreach cuando quieras sumar, concatenar o procesar datos de una
         coleccion ****/
        $totals = $detailOrderPurchase->reduce(function ($carry, $detail) {
            $carry['subtotal'] += $detail->ORDEN_COMPRA_DETALLE_TOTAL;
            $carry['iva'] += $detail->ORDEN_COMPRA_DETALLE_IMPUESTO;
            return $carry;
        }, ['subtotal' => 0, 'iva' => 0]);

        $orderPurchase->subtotal = number_format($totals['subtotal'], 2, '.', '');
        $orderPurchase->iva = number_format($totals['iva'], 2, '.', '');
        $orderPurchase->total = number_format($totals['subtotal'] + $totals['iva'], 2, '.', '');
        $orderPurchase->detalle = $detailOrderPurchase;

        return $orderPurchase;
    }
}
