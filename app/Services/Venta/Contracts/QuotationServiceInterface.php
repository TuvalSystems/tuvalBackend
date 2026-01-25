<?php

namespace App\Services\Venta\Contracts;

use App\Models\GpUsuario;

interface QuotationServiceInterface
{

    public function getNonSalesReasons();

    public function updateNonSaleAndReminderData($data);
    public function getQuotationsWithReminderToday();

    /**
     *  recuperar cotizacion
     */

    public function restoreQuotationV2($quoteId);
    public function getStockGeneralRestoreQuotation($articulos, $bodegas);

    /**
     *  volver a pedir
     */
    public function loadQuotationForCopy($quoteId): array;
    //  enviar cotizaciones sql server
    public function sendStoreReminderGp($cotizacion);


    /** lista de items alternativos para cotizacione s */

    public function getItemsAlternatives(string $item);

    /**
     * actualizaciones de campos de gp_temp_cart
     */
    public function syncAndGetCheckAlternativo($data, $articulo);
    public function updateCheckAlternativeItem($id, $checkAlternative);


    /**
     *  imprimir cotizacion pdf
     */

    public function printQuotationPdf($id, $withImages = false);

    /***
     * API para obtener listado de cotizaciones con novedades
     */
    public function consultQuotationsNews($data);


    /** funcion para obtener el detalle de la cotizacion  */

    public function detailQuotation($data);

    /** funcion para llenar la cotizacion  */
    public function fillQuotation($data, $user);

    /** funcion para obtener el stock del articulo en la cotizacion  */
    public function getStockItemForWarehouse($article, $warehouse, $cotizacionId);



    /** funcion para obtener el margen del item l en cotizaciónes */
    public function getMarginItem($artArticulo);




    /** procesos de cliente */

    public function getAllSegments();
    public function getSubSegmentBySegment(int $coAtrPadre);
    public function storeClientQuotation(array $data, GpUsuario $user);

    public function checkClientWithSegmentExist($ruc);
    public function updateClientWithSegment(string $cliente, int $segmentoId, int $subsegmentoId);
}
