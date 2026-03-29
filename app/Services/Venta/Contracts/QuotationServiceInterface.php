<?php

namespace App\Services\Venta\Contracts;

use App\Models\GpUsuario;

interface QuotationServiceInterface
{

    public function getNonSalesReasons();

    public function updateNonSaleAndReminderData($data, GpUsuario $user);
    public function getQuotationsWithReminderToday();

    /**
     *  recuperar cotizacion
     */

    public function restoreQuotationV2($quoteId);

    /** prospecto*/
    public function updateQuoteClientData($quoteId, $clientId);

    /**
     *  volver a pedir
     */
    public function loadQuotationForCopy($quoteId,  GpUsuario $user): array;
    //  enviar cotizaciones sql server
    public function sendStoreReminderGp($cotizacion);


    /** lista de items alternativos para cotizacione s */

    public function getItemsAlternatives(string $item);


    /**
     *  imprimir cotizacion pdf
     */

    public function printQuotationPdf($id, $withImages = false);

    /***
     * API para obtener listado de cotizaciones con novedades
     */
    public function consultQuotationsNews($data);


    /** funcion para obtener el detalle de la cotizacion  */

    public function detailQuotation($data, GpUsuario $user);

    /** funcion para llenar la cotizacion  */

    /** funcion para obtener el stock del articulo en la cotizacion  */
    public function getStockItemsForWarehouse($article, $warehouse, $cotizacionId);


    /** funcion para obtener el stock del articuo en la cotizacion v2  */
     public function getStockItemsForWarehouseV2($articles, $warehouse, $quoteId);

    /** funcion para obtener el margen del item l en cotizaciónes */
    public function getMarginItem($artArticulo);




    /** procesos de cliente */

    public function getAllSegments();
    public function getSubSegmentBySegment(int $coAtrPadre);
    public function storeClientQuotation(array $data, GpUsuario $user);

    public function checkClientWithSegmentExist($ruc);
    public function updateClientWithSegment(string $cliente, int $segmentoId, int $subsegmentoId);
}
