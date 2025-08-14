<?php

namespace App\Services\Bodegas\Contracts;

interface DispatchServiceInterface
{

    public function guiasDespachadas($ptoVtaId, $perpage, $search,  $sortBy, $sortOrder, $fecha_desde, $fecha_hasta, $clienteRuc);
    public function transferenciasEnviadas($ptoVtaId, $perpage, $search, $sortBy, $sortOrder, $fecha_desde, $fecha_hasta, $clienteRuc,$ptoVta);
    public function transferenciasRecibidas($ptoVtaId, $perpage, $search, $sortBy, $sortOrder, $fecha_desde, $fecha_hasta,$clienteRuc, $ptoVta);
    public function reposicionesEnviadas($ptoVtaId, $perpage, $search, $sortBy, $sortOrder, $fecha_desde, $fecha_hasta,$clienteRuc,  $ptoVta);
    public function reposicionesRecibidas($ptoVtaId, $perpage, $search, $sortBy, $sortOrder, $fecha_desde, $fecha_hasta,$clienteRuc, $ptoVta);

    public function checkTraslado($order);
    public function getAllPtoVta();

}
