<?php

namespace App\Services\Funciones;

use App\Repositories\Pedidos\Contracts\OrderRepositoryInterface;
use App\Services\Funciones\Contracts\FunctionsServiceInterface;

class FunctionsService implements FunctionsServiceInterface
{

    protected OrderRepositoryInterface $orderRepository;

    public function __construct(OrderRepositoryInterface $orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }

    public function getItemProviders($data, $item, $pendientes = false, $existentes = false)
    {
        $providers  = $this->orderRepository->getSuppliersByOrderAndItem($data['id'], $item->id);
        if ($item->ART_DECIMAL_CANTIDAD == 0) {
            $item->ART_STOCK = (int)$item->ART_STOCK;
            $item->ART_CANTIDAD = (int)$item->ART_CANTIDAD;
        }

         // Procesar cada proveedor
        $providers->each(function ($provider) use (&$pendientes, &$existentes, $item) {
            $provider->ingresado ? $existentes = true : $pendientes = true;

            // Obtener las órdenes de compra relacionadas
           $orderDetails = $this->orderRepository->getPurchaseOrdersBySupplier($provider->id);

            // Calcular el margen
            $margin = ($provider->COSTO_UNITARIO / (1 - ($provider->MARGEN / 100))) / (1 - $item->descuento / 100);
            $provider->margen = number_format($margin, 4, '.', '');

            // Conversión a enteros si no tiene decimales
            if ($item->ART_DECIMAL_CANTIDAD == 0) {
                $provider->CANTIDAD = (int)$provider->CANTIDAD;
                $provider->ingresado = (int)$provider->ingresado;
            }

            // Asignar el detalle de órdenes de compra
            $provider->orden_detalle = $orderDetails;
        });
        return [
            'proveedores' => $providers,
            'pendientes' => $pendientes,
            'existentes' => $existentes,
        ];
    }
}
