<?php

namespace App\Services\Admin\Sync;

use App\Models\GpCliente;
use App\Models\GpClienteSucursal;
use App\Services\Admin\Sync\Contracts\SyncClientsServiceInterface;
use App\Services\Api\Admin\ApiSync;
use App\Services\Builders\Venta\Contracts\ClientBuilderInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class SyncClientsService implements SyncClientsServiceInterface
{
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
        Log::channel('cron_process')->debug('Dirty fields', [
            'ruc'    => $ruc,
            'dirty'  => $client->getDirty(),
            'original' => collect($client->getDirty())
                ->mapWithKeys(fn($_, $field) => [$field => $client->getOriginal($field)])
                ->toArray(),
        ]);
        if ($this->hasRealChanges($client)) {
            // Hay diferencias reales → updated_at se actualiza
            $client->timestamps = true;
            $client->save();
        } else {
            // Misma data → no tocar updated_at
            $client->timestamps = false;
            $client->save();
        }
    }
    private function hasRealChanges(Model $model): bool
    {
        foreach ($model->getDirty() as $field => $_) {
            $original = $model->getOriginal($field);
            $current  = $model->getAttribute($field); // ya casteado

            // Normalizar a string para comparación final
            $normalizedOriginal = $this->normalizeValue($original);
            $normalizedCurrent  = $this->normalizeValue($current);

            if ($normalizedOriginal !== $normalizedCurrent) {
                return true;
            }
        }

        return false;
    }

    private function normalizeValue(mixed $value): string
    {
        if ($value === null) return '';

        // Carbon/DateTime → formato estándar
        if ($value instanceof \Carbon\Carbon) {
            return $value->format('Y-m-d');
        }

        // Numéricos → normalizar decimales
        if (is_numeric($value)) {
            return rtrim(rtrim(number_format((float)$value, 4, '.', ''), '0'), '.');
        }

        return trim((string) $value);
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
            $payload = $this->builder->formPayloadClientBranchesSync(
                $cliente->id,
                $ruc,
                $descripcion,
                $dir
            );
            GpClienteSucursal::updateOrCreate(
                ['cliente_id' => $cliente->id, 'CLI_SUC_ID_DIRECCION' => $idDireccion],
                $payload
            );
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
        $sucursal->coordenadas = data_get($data, 'datosCoordenadas', '');

        if ($sucursal->isDirty('coordenadas')) {
            $sucursal->timestamps = false;
            $sucursal->save();
        }
    }
}
