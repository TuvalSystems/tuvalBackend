<?php

namespace App\Services\Venta\Utils\Contracts;

use App\Models\GpPedido;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\HasMedia;

interface OrderAndQuotationServiceInterface
{
    public function getDetailQuotationOrOrder($data, $cliente, $type, $encera = false, $copy = false);


    public function createRegisterCopy($dataId, $user, $typeCopy, $typeRegisterCopy);

    public function getSegmentoCliente($ruc);

    public function getAndValidateOrderDetailStock($detailOrder, $stock, $orderId, $quotationId = null);



    public function calculateDiscountFraction(float $originalPrice, float $currentPrice);
    public function calculateDiscountChange(float $originalDiscount, float $currentDiscount);



    public function getAllImagensOperations($quotationId, $type = 'cotizacion');
    public function getImageArticle($codArt): array;


    public function getApiSegmentsAbcItem($articles): Collection;
    public function getFormattedPdfMedia(HasMedia $model, string $collection = 'documentos_pdf'): array;


    /** copia de imagenees en pedidos */
    public function copyImagesFromUrls(GpPedido $pedido, array $urls);
    public function copyPdfsFromUrls(GpPedido $order, array $urls);
}
