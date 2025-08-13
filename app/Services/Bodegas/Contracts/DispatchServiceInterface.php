<?php

namespace App\Services\Bodegas\Contracts;

interface DispatchServiceInterface
{

    public function guiasDespachadas($bodega, $perpage, $search,  $sortBy, $sortOrder, $fecha_desde, $fecha_hasta, $clienteRuc);
    public function transferenciasEnviadas($bodega, $perpage, $search, $sortBy, $sortOrder, $fecha_desde, $fecha_hasta, $clienteRuc);
    public function transferenciasRecibidas($bodega, $perpage, $search, $sortBy, $sortOrder, $fecha_desde, $fecha_hasta,$clienteRuc);
    public function reposicionesEnviadas($bodega, $perpage, $search, $sortBy, $sortOrder, $fecha_desde, $fecha_hasta,$clienteRuc);
    public function reposicionesRecibidas($bodega, $perpage, $search, $sortBy, $sortOrder, $fecha_desde, $fecha_hasta,$clienteRuc);

    public function checkTraslado($order);

    public function getAllPtoVta();

}
