<?php

namespace App\Services\Venta\Cliente;

use App\Jobs\Venta\Cliente\SendClientToGpJob;
use App\Models\Admin\Ubicaciones\Canton;
use App\Models\Admin\Ubicaciones\Provincia;
use App\Models\Admin\Ubicaciones\Region;
use App\Models\Ventas\Clientes\GpCliente;
use App\Models\Admin\Usuarios\GpUsuario;
use App\Models\Ventas\Clientes\TipoIdentificacion;
use App\Models\Ventas\Vendedores\GpVendedor;
use App\Services\Builders\Venta\Contracts\ClientBuilderInterface;
use App\Services\Venta\Cliente\Contracts\ClientCreatorServiceInterface;
use Illuminate\Support\Facades\DB;

class ClientCreatorService implements ClientCreatorServiceInterface
{
    public function __construct(
        protected readonly ClientBuilderInterface $builder
    ) {}

    public function createClient(array $data, GpUsuario $user)
    {
        $lvlPrice       = $this->getnivelPriceSeller($user);
        $address        = $this->getDirectionWithLocationNames(data_get($data, 'direcciones'));
        $directions     = $this->getDirections($address);
        $principalDir   = data_get($address, '0');

        $clientWeb = DB::transaction(function () use ($data, $lvlPrice, $directions, $principalDir, $user, $address) {
            $identityType = TipoIdentificacion::where('id', data_get($data, 'tipo_identificacion_id'))
                ->value('codigo');
            $data = array_merge($data, [
                'direccion1'            => data_get($directions, '0'),
                'direccion2'            => data_get($directions, '1'),
                'lvlPrice'              => $lvlPrice,
                'tipo_identificacion'   => $identityType,
                'direccionPrincipal'    => $principalDir
            ]);

            $clientWeb = $this->builder->formPayloadStoreClient($data, $user);
            $clientWeb->save();

            $this->createAndSyncClientAddresses($clientWeb, $user, $address);
            return $clientWeb;
        });

        SendClientToGpJob::dispatchSync($clientWeb->id);

        return $clientWeb->refresh()->load('sucursales');
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
    protected function getDirections(array $directions): array
    {
        return collect($directions)->pluck('direccion')->values()->all();
    }


    protected  function getDirectionWithLocationNames(array $address)
    {
        $newAdd = [];
        foreach ($address as $add) {
            $region = Region::where('id', data_get($add, 'region_id'))
                ->value('nombre');

            $province = Provincia::where('id', data_get($add, 'provincia_id'))
                ->value('nombre');

            $canton = Canton::where('id', data_get($add, 'canton_id'))
                ->value('nombre');
            $newAdd[] = array_merge($add, [
                'region'    => $region,
                'provincia' => $province,
                'canton'    => $canton,
            ]);
        }
        return $newAdd;
    }


    /** creacion de las sucursales del cliente */

    protected function createAndSyncClientAddresses(GpCliente $client, GpUsuario $user, array $address)
    {
        foreach ($address as $index => $add) {
            $identifier = $index === 0 ? 'PRINCIPAL' : null;
            $newAddress = $this->builder->formPayloadStoreAddressByClient($client, $user, $add, $identifier);
            $newAddress->save();
        }
    }
}
