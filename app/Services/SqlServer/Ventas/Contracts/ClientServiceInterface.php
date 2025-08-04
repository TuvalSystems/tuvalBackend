<?php

namespace App\Services\SqlServer\Ventas\Contracts;

interface ClientServiceInterface
{
    // Define los métodos que debe implementar el servicio

    public function validateClientforRucSegment($ruc);

    /**
     *  definicion de segmentos y subsegmentos
     * @return void
     */
    public function getListSegments();
    public function getLisSubSegmentos($segmento);
}
