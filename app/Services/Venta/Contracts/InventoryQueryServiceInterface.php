<?php

namespace App\Services\Venta\Contracts;

interface InventoryQueryServiceInterface
{
    // Define los métodos que debe implementar el servicio

    public function getConsultStock($data);
    public function getConsultStockOthers($data);

    public function getConsultItemTransito($data);

    public function fetchStockInTransit();

    /** funcion para consulta de cupo de cliente */

    public function getAvailableSpace($ruc);


    /** funcion para consulta de segmento de cliente */

    public function getConsultClientSegment($data);


    /** funcion de consulta de reservados  */

     public function listReservedInventory();
}
