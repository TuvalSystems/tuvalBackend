<?php

namespace App\Services\Venta\Contracts;

interface QuotationServiceInterface {

    public function getNonSalesReasons();

    public function updateNonSaleAndReminderData($data);
    public function getQuotationsWithReminderToday();
}
