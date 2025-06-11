<?php

namespace App\Services\Bodegas\Contracts;

interface DispatchServiceInterface
{

    public function guiasDespachadas($bodega, $perpage, $search,  $sortBy, $sortOrder);
    public function transferenciasEnviadas($bodega, $perpage, $search, $sortBy, $sortOrder);
    public function transferenciasRecibidas($bodega, $perpage, $search, $sortBy, $sortOrder);
    public function reposicionesEnviadas($bodega, $perpage, $search, $sortBy, $sortOrder);
    public function reposicionesRecibidas($bodega, $perpage, $search, $sortBy, $sortOrder);
}
