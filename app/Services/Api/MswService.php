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


    public function cobranzas(string $method, array $params){

        $response = Soap::baseWsdl(env('WEBSERVICES_COBRANZAS_URL'))
        ->$method($params);
        $response->throw();
        return $response->json();
    }


    public function listaprecios(array $params){

        $response = Soap::baseWsdl(env('WEBSERVICES_URL'))
        ->MST_Lista_Precio([
            'Lista_Precio_Articulo' => $params,
        ]);
        return $response->json()['MST_Lista_PrecioResult']['CS_LISTA_PRECIO'];
    }

}
