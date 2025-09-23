<?php

namespace App\Services\Funciones\Contracts;

interface FunctionsServiceInterface
{
    public function getItemProviders($pedidoId, $item, $pendientes = false, $existentes = false);

    public function recalculateOrder($orderId);

    public function recalculateIva($detalle);


    public function getClient($data);
    public function getClientCheckAvailableQuota(String $ruc);
    public function getClientDocumentExpired($ruc);
    public function getClientDocumentWithoutSupport($ruc);
      public function getListClientDocumentExpired($ruc);
    public function getListClientDocumentWithoutSupport($ruc);


    public function getBodegas($data, $consulta = null);
    public function findAvailableArticleCodeL($artArrm, $class = 'VTA_BNS_L', $limit = 300);
    public function setCartFormQuotation($quotationDetails, $data, $user);

    public function storeCartStock($cartId, $stock);
}
