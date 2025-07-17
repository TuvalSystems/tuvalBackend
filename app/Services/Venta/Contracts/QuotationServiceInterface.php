<?php

namespace App\Services\Venta\Contracts;

interface QuotationServiceInterface
{

    public function getNonSalesReasons();

    public function updateNonSaleAndReminderData($data);
    public function getQuotationsWithReminderToday();

    /**
     *  recuperar cotizacion
     */
     public function restoreQuotation($data, $user);
    public function getStockGeneralRestoreQuotation($articulos, $bodegas);
    public function restoreDescriptionItemQuotation($articulos, $bodegas);

    //  enviar cotizaciones sql server
    public function sendStoreReminderGp($cotizacion);


    /** lista de items alternativos para cotizacione s */

    public function getItemsAlternatives ($item);

    /**
     * actualizaciones de campos de gp_temp_cart
     */
     public function syncAndGetCheckAlternativo($data, $articulo);
     public function updateCheckAlternativeItem($id, $checkAlternative);


     /**
      *  imprimir cotizacion pdf
      */

      public function printQuotationPdf($id);

      /***
       * API para obtener listado de cotizaciones con novedades
       */
      public function consultQuotationsNews($data);


      /** funcion para obtener el detalle de la cotizacion  */

    public function detailQuotation($data);

    /** funcion para llenar la cotizacion  */
    public function fillQuotation($data, $user);

    /** funcion para obtener el stock del articulo en la cotizacion  */
    public function getStockItemForWarehouse($articulo, $bodega);

    /** funcion para volver a pedir cotizacion  */
    public function requestQuotationAgain($data, $user);

    /** funcion para guardado de cotización  */
     public function storeQuotation($data, $user);

}
