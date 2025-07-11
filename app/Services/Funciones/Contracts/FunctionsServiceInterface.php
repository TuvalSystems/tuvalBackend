<?php

namespace App\Services\Funciones\Contracts;

interface FunctionsServiceInterface
{
    public function getItemProviders($data, $item, $pendientes = false, $existentes = false);

    public function consultStockDetail($data, $detalle, $pedidoId = null);

    public function recalculateOrder($order);

    public function recalculateIva($detalle);


    public function getClient($data);

    public function getBodegas($data, $consulta = null);
    public function findAvailableArticleCodeL($artArrm, $class = 'VTA_BNS_L', $limit = 300);
    public function setCartFormQuotation($quotationDetails, $data, $user);
}
