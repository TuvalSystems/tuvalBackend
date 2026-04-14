<?php

namespace App\Services\Admin\Sync;

use App\Models\GpCliente;
use App\Services\Admin\Sync\Contracts\SyncClientsServiceInterface;
use App\Services\Api\Admin\ApiSync;
use App\Services\Builders\Venta\Contracts\ClientBuilderInterface;
use Illuminate\Support\Facades\Log;

class SyncClientsService implements SyncClientsServiceInterface
{
    public function __construct(protected readonly ApiSync $api, protected readonly ClientBuilderInterface $builder) {}


    public function getClientsGp($fecha)
    {
        $params = [
            "cliente" => "",
            "fechaCreacion" => "",
            "fechaModif" => $fecha
        ];
        $syncData = $this->api->getSyncClients($params);
        return data_get($syncData, 'data', []);
    }



    public function handleClientSync(array $data)
    {
        $ruc = trim(data_get($data, 'ruc', ''));
        $client = GpCliente::where('CLI_RUC', $ruc)->first();

        if (!$client) {
            $client = new GpCliente();
            $client->CLI_RUC = $ruc;
        }
        $payload = $this->builder->formPayloadNewClientSync($data);
        $client->fill($payload);
        if ($client->isDirty()) {
            $client->save();
            Log::channel('cron_process')->info('Se sincronizó el cliente con cambios: ' . $ruc);
        } else {
            Log::channel('cron_process')->info('Cliente sin cambios: ' . $ruc);
        }
    }
}
