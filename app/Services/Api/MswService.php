<?php

namespace App\Services\Api;

use CodeDredd\Soap\Facades\Soap;

class MswService
{
    /** Aqui van todos los apis de master web que no se han migrado todavia a apigp */


    public function transaccional(string $method, array $params){

        $response = Soap::baseWsdl(env('WEBSERVICES_TRANSACCIONES_URL'))
        ->$method($params);
        $response->throw();
        return $response->json();
    }

}
