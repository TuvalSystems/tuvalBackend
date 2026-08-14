<?php

namespace App\Services\Venta\Contracts;

use App\Models\GpCotizacion;
use App\Models\GpUsuario;

interface QuotationServiceInterface
{

    public function getNonSalesReasons();

    public function updateNonSaleAndReminderData(array $data, GpUsuario $user);
    public function getQuotationsWithReminderToday();

    /**
     *  recuperar cotizacion
     */

    public function restoreQuotationV2(int $quoteId);

    /** prospecto*/
    public function updateQuoteClientData(int $quoteId, int $clientId);

    /**
     *  volver a pedir
     */
    public function loadQuotationForCopy(int $quoteId,  GpUsuario $user): array;
    //  enviar cotizaciones sql server
    public function sendStoreReminderGp(GpCotizacion $cotizacion);


    /** lista de items alternativos para cotizacione s */

    public function getItemsAlternatives(string $item);


    /**
     *  imprimir cotizacion pdf
     */

    public function printQuotationPdf(int $id, $withImages = false);

    /***
     * API para obtener listado de cotizaciones con novedades
     */
    public function consultQuotationsNews(array $data);


    /** funcion para obtener el detalle de la cotizacion  */

    public function detailQuotation(array $data, GpUsuario $user);

    /** funcion para llenar la cotizacion  */

    /** funcion para obtener el stock del articulo en la cotizacion  */
    public function getStockItemsForWarehouse(array $article, string $warehouse, ?int $cotizacionId = null): array;


    /** funcion para obtener el stock del articuo en la cotizacion v2  */
    public function getStockItemsForWarehouseV2(array $articles, string $warehouse, ?int $quoteId = null): array;

    /** funcion para obtener el margen del item l en cotizaciónes */
    public function getMarginItem(string $artArticulo);




    /** procesos de cliente */

    public function getAllSegments();
    public function getSubSegmentBySegment(int $coAtrPadre);
    public function storeClientQuotation(array $data, GpUsuario $user);

    public function checkClientWithSegmentExist(string $ruc);
    public function updateClientWithSegment(string $cliente, int $segmentoId, int $subsegmentoId);

    public function getDefaultPriceListByPos(string $pvCode): array;

    public function getistProyectSellectToGp() : array;
}
