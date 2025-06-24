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

    public function restoreQuotation($articulos, $bodegas);


    //  enviar cotizaciones sql server
    public function sendStoreReminderGp($cotizacion);


    /** lista de items alternativos para cotizacione s */

    public function getItemsAlternatives ($item);

    /**
     * actualizaciones de campos de gp_temp_cart
     */
     public function syncAndGetCheckAlternativo($articulo);
     public function updateCheckAlternativeItem($id, $checkAlternative);


     /**
      *  imprimir cotizacion pdf
      */

      public function printQuotationPdf($id);
}
