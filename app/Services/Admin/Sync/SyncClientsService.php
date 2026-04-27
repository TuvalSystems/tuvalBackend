<?php

namespace App\Services\Admin\Sync;

use App\Models\GpCliente;
use App\Models\GpClienteSucursal;
use App\Services\Admin\Sync\Contracts\SyncClientsServiceInterface;
use App\Services\Api\Admin\ApiSync;
use App\Services\Builders\Venta\Contracts\ClientBuilderInterface;
use App\Traits\Sincronizacion\DetectsRealModelChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class SyncClientsService implements SyncClientsServiceInterface
{
    use DetectsRealModelChanges;
    public function __construct(protected readonly ApiSync $api, protected readonly ClientBuilderInterface $builder) {}


    public function getClientsGp($fecha)
    {
        $params = [
            "cliente"       => "",
            "fechaCreacion" => "",
            "fechaModif"    => $fecha
        ];
        $syncData = $this->api->getSyncClients($params);
        return data_get($syncData, 'data', []);
    }



    public function handleClientSync(array $data)
    {
        $ruc = trim(data_get($data, 'ruc', ''));
        $client = GpCliente::where('CLI_RUC', $ruc)->first();
        $isNew = !$client;

        if ($isNew) {
            $client          = new GpCliente();
            $client->CLI_RUC = $ruc;
        }
        $payload = $this->builder->formPayloadNewClientSync($data);
        $client->fill($payload);
        if ($isNew) {
            $client->save();
            return;
        }

        if ($this->hasRealChanges($client)) {
            Log::channel('cron_process')->info('Cliente actualizado', [
                'ruc'     => $ruc,
                'changes' => $client->getDirty(),
            ]);
            $client->timestamps = true;
            $client->save();
        } else {
            // Misma data → no tocar updated_at
            $client->timestamps = false;
            $client->save();
        }
    }



    public function getBranchesGp($fecha)
    {
        $params = [
            "cliente"       => "",
            "fechaCreacion" => "",
            "fechaModif"    => $fecha
        ];
        $syncData = $this->api->getSyncClientBranches($params);
        return data_get($syncData, 'data', []);
    }

    public function handleClientBranchSync(array $data)
    {
        $ruc            = trim(data_get($data, 'cliente', ''));
        $descripcion    = trim(data_get($data, 'descripcion', ''));
        $cliente        = GpCliente::where('CLI_RUC', trim($ruc))->first();
        if (!$cliente) {
            throw new \Exception("Sincronización abortada: El cliente RUC {$ruc} no existe en la base de datos local.");
        }

        $direcciones = data_get($data, 'direcciones', []);
        foreach ($direcciones as $dir) {
            $idDireccion = trim(data_get($dir, 'idDireccion', ''));
            $sucursal = GpClienteSucursal::where('cliente_id', $cliente->id)
                ->where('CLI_SUC_ID_DIRECCION', $idDireccion)
                ->first();
            $payload = $this->builder->formPayloadClientBranchesSync(
                $cliente->id,
                $ruc,
                $descripcion,
                $dir
            );
            if (!$sucursal) {
                // INSERT — nueva sucursal
                GpClienteSucursal::create($payload);
                continue;
            }

            $sucursal->fill($payload);
            if ($this->hasRealChanges($sucursal)) {
                Log::channel('cron_process')->info('Cliente sucursal actualizado', [
                    'ruc'     => $ruc,
                    'changes' => $sucursal->getDirty(),
                ]);
                $sucursal->timestamps = true;
                $sucursal->save();
            } else {
                $sucursal->timestamps = false;
                $sucursal->save();
            }
        }
    }


    public function getClientsCoordinates()
    {
        $syncData = $this->api->getSyncClientCoordinates();
        return data_get($syncData, 'data', []);
    }


    public function handleClientBranchCoordinatesSync(array $data)
    {
        $ruc = trim(data_get($data, 'idCliente', ''));
        $addressCode = trim(data_get($data, 'addressCode', ''));
        if ($addressCode === 'undefined' || empty($addressCode)) {
            return;
        }
        $sucursal = GpClienteSucursal::where('CLI_SUC_RUC', $ruc)->where('CLI_SUC_ID_DIRECCION', $addressCode)->first();
        if (!$sucursal) {
            Log::channel('cron_process')->warning("Coordenada ignorada: Sucursal [{$addressCode}] no existe para RUC [{$ruc}]. Posiblemente eliminada en GP.");
            return;
        }
        $sucursal->coordenadas = trim(data_get($data, 'datosCoordenadas', '')) ?: null;

        if ($sucursal->isDirty('coordenadas')) {
            $sucursal->timestamps = false;
            $sucursal->save();
        }
    }
}
