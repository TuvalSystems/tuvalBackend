<?php

namespace App\Services\Api\Transacciones;


use App\Services\Api\ApiGpService;

class ApiGpProcessInviceVta extends ApiGpService
{

    public function storeDocumentInvoiceVta(array $params)
    {
        return $this->httpClient->makeRequest($this->endpoints["guarda_documento_factura_vta"], 'POST', $params, 'json');
    }


    public function responseDocumentInvoiceVta(array $params)
    {
        return $this->httpClient->makeRequest($this->endpoints["respuesta_documento_factura_vta"], 'POST', $params, 'json');
    }

        public function deleteDocumentInvoiceVta(array $params)
    {
        return $this->httpClient->makeRequest($this->endpoints["eliminar_documento_factura_vta"], 'POST', $params, 'json');
    }
}
