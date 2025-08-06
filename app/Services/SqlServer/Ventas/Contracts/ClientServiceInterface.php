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

    /** funcion para almacenar los clientes en sql Server */
    public function syncToSqlServerModifyOrCreateClient($params);

    /**
     *  para guardar el segmento y ruc en sql server
     * @param mixed $data
     * @return void
     */
 public function sendToSegmentAndRucToSqlServer($ruc, $subsegmentoId);

 /** verifica si el cliente tiene segmento asignado o no  */

  public function getValidateCLientWithSegment($ruc);

}
