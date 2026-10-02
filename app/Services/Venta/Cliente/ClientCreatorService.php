<?php

namespace App\Services\Venta\Cliente;

use App\Models\GpUsuario;
use App\Models\Ventas\Vendedores\GpVendedor;
use App\Services\Venta\Cliente\Contracts\ClientCreatorServiceInterface;

class ClientCreatorService implements ClientCreatorServiceInterface
{
    public function __construct() {}

    public function createClient(array $data, GpUsuario $user)
    {
        $levelPrice = $this->getnivelPriceSeller($user);
        $directions = $this->getDirections(data_get($data, 'direcciones'));
        return  [
            'nivelPrecio' => $levelPrice,
            'direcciones' => $directions,
        ];
    }

    protected function getnivelPriceSeller(GpUsuario $user)
    {
        $seller = GpVendedor::with(['atributoValores' => function ($q) {
            // Aseguramos que la opción exista y esté activa
            $q->whereHas('opcion', function ($qOpcion) {
                $qOpcion->where('estado', 1)->where('atributo_id', 1);
            })->with('opcion:id,codigo');
        }])
            ->where('user_id', $user->id)
            ->first();

        if (!$seller || $seller->atributoValores->isEmpty()) {
            return 'GENERAL';
        }

        return $seller->atributoValores->first()?->opcion?->codigo ?? 'GENERAL';
    }


    public function getDirections(array $directions): array
    {
        return collect($directions)->pluck('direccion')->values()->all();
    }
}
