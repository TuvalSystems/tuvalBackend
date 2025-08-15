<?php

namespace App\Services\Bodegas;

use App\Services\Bodegas\Contracts\DeliveryNoteServiceInterface;
use App\Services\Funciones\Contracts\InvoiceServiceInterface;

class DeliveryNoteService implements DeliveryNoteServiceInterface
{
    /**
     *  SERVICIO DEDICADO AL  GUIAREMISIONCONTROLLER
     */

    // protected $invoiceService;
    public function __construct(InvoiceServiceInterface $invoiceService)
    {
        // $this->invoiceService = $invoiceService;
    }



}
