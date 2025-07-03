<?php

namespace App\Services\Funciones\Contracts;

interface FunctionsServiceInterface
{
    public function getItemProviders($data, $item, $pendientes = false, $existentes = false);

    public function consultStockDetail($data, $detalle, $pedidoId = null);

    public function recalculateOrder($order);

    public function recalculateIva($detalle);
}
