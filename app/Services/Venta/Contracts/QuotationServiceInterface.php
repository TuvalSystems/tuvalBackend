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

}
