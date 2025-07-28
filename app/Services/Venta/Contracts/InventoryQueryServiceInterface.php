<?php

namespace App\Services\Venta\Contracts;

interface InventoryQueryServiceInterface
{
    // Define los métodos que debe implementar el servicio

    public function getConsultStock($data);
    public function getConsultStockOthers($data);


    /** consultar stock del pedido antes de guardar */
   

}
