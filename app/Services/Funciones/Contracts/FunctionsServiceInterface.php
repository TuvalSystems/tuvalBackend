<?php

namespace App\Services\Funciones\Contracts;

interface FunctionsServiceInterface
{
    public function getItemProviders($data, $item, $pendientes = false, $existentes = false);
}
