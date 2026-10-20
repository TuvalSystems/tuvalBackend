<?php

namespace App\Services\Admin\Sync;

use App\Models\Admin\Canton;
use App\Models\Admin\Provincia;
use App\Models\Admin\Region;
use App\Models\GpCliente;
use App\Models\GpClienteSucursal;
use App\Models\GpPuntoVenta;
use App\Models\Ventas\Cliente\ClienteConfiguracion;
use App\Models\Ventas\Vendedores\GpVendedor;
use App\Services\Admin\Sync\Contracts\SyncClientsServiceInterface;
use App\Services\Api\Admin\ApiSync;
use App\Services\Builders\Venta\Contracts\ClientBuilderInterface;
use App\Traits\Sincronizacion\DetectsRealModelChanges;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

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
        $ruc         = trim(data_get($data, 'ruc', ''));

        if ($ruc === '') {
            throw new InvalidArgumentException('Sincronización abortada: GP envió un cliente sin RUC.');
        }

        $client      = GpCliente::where('CLI_RUC', $ruc)->first();
        $isNew       = !$client;
        $regionId    = Region::where('nombre', trim(data_get($data, 'territorio')))->value('id');
        $provinciaId = Provincia::where('nombre', trim(data_get($data, 'provincia')))->value('id');
        $cantonId    = Canton::where('nombre', trim(data_get($data, 'ciudad')))->value('id');


        if ($isNew) {
            $client          = new GpCliente();
            $client->CLI_RUC = $ruc;
        }
        $payload = $this->builder->formPayloadNewClientSync($data, $regionId, $provinciaId, $cantonId);
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

        if ($ruc === '') {
            throw new InvalidArgumentException('Sincronización abortada: GP envió sucursales sin RUC de cliente.');
        }

        $cliente        = GpCliente::where('CLI_RUC', trim($ruc))->first();
        if (!$cliente) {
            throw new \Exception("Sincronización abortada: El cliente RUC {$ruc} no existe en la base de datos local.");
        }

        $direcciones = data_get($data, 'direcciones', []);
        foreach ($direcciones as $dir) {
            $idDireccion = trim(data_get($dir, 'idDireccion', ''));

            if ($idDireccion === '') {
                Log::channel('cron_process')->warning('Sucursal ignorada porque GP no envió idDireccion', [
                    'ruc'       => $ruc,
                    'direccion' => trim(data_get($dir, 'direccion', '')),
                ]);
                continue;
            }

            $regionId    = Region::where('nombre', trim(data_get($dir, 'territorio')))->value('id');
            $provinciaId = Provincia::where('nombre', trim(data_get($dir, 'provincia')))->value('id');
            $cantonId    = Canton::where('nombre', trim(data_get($dir, 'ciudad')))->value('id');
            $payload = $this->builder->formPayloadClientBranchesSync(
                $cliente->id,
                $ruc,
                $descripcion,
                $dir,
                $regionId,
                $provinciaId,
                $cantonId
            );

            DB::transaction(function () use ($cliente, $idDireccion, $dir, $payload, $ruc) {
                $sucursal = GpClienteSucursal::where('cliente_id', $cliente->id)
                    ->where('CLI_SUC_ID_DIRECCION', $idDireccion)
                    ->lockForUpdate()
                    ->first();

                if (!$sucursal) {
                    $direccion = trim(data_get($dir, 'direccion', ''));

                    if ($direccion !== '') {
                        $sucursal = GpClienteSucursal::where('cliente_id', $cliente->id)
                            ->where('enviado_gp', false)
                            ->where(function ($query) {
                                $query->whereNull('CLI_SUC_ID_DIRECCION')
                                    ->orWhere('CLI_SUC_ID_DIRECCION', '');
                            })
                            ->whereRaw('TRIM(CLI_SUC_DIRECCION) = ?', [$direccion])
                            ->orderBy('id')
                            ->lockForUpdate()
                            ->first();
                    }
                }

                if (!$sucursal) {
                    GpClienteSucursal::create($payload);
                    return;
                }

                $sucursal->fill($payload);
                if ($this->hasRealChanges($sucursal)) {
                    Log::channel('cron_process')->info('Cliente sucursal actualizado', [
                        'ruc'     => $ruc,
                        'changes' => $sucursal->getDirty(),
                    ]);
                    $sucursal->timestamps = true;
                    $sucursal->save();
                    return;
                }

                $sucursal->timestamps = false;
                $sucursal->save();
            }, 3);
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





    /** funcion para sincronizar la configuracion de clientes  */

    protected function getPendingBranchConfigSyncCount(): int
    {
        $count = $this->api->getClientPendingBranchConfigSyncCount();
        $this->requireSuccessfulSyncResponse($count);
        return (int) data_get($count, 'data', 0);
    }

    protected function getPendingBranchConfigSync(int $batchSize, ?array $cursor): array
    {
        $data = $this->api->getPendingBranchConfigs($batchSize, $cursor);
        $this->requireSuccessfulSyncResponse($data);
        $items = data_get($data, 'data');
        if (!is_array($items) || !array_is_list($items) || count($items) > $batchSize) {
            throw new \RuntimeException('La API devolvio un lote de configuraciones invalido.');
        }

        return $items;
    }
    public function syncClientConfigurations(int $batchSize = 1000, ?int $maxBatches = null)
    {
        if ($batchSize < 1 || $batchSize > 1000 || ($maxBatches !== null && $maxBatches < 1)) {
            throw new InvalidArgumentException('El lote debe estar entre 1 y 1000 y el maximo de lotes debe ser positivo.');
        }

        $pendingCount = $this->getPendingBranchConfigSyncCount();

        if ($pendingCount === 0) {
            return;
        }
        $cursor = null;
        $processed = 0;
        $batches = 0;

        while ($maxBatches === null || $batches < $maxBatches) {
            $pendings = $this->getPendingBranchConfigSync($batchSize, $cursor);
            if ($pendings === []) {
                break;
            }

            foreach ($pendings as $item) {
                $key = [
                    'coEmp' => data_get($item, 'coEmp'),
                    'coLoc' => data_get($item, 'coLoc'),
                    'txCli' => trim((string) data_get($item, 'txCli')),
                ];
                if ($key['coEmp'] === null || $key['coLoc'] === null || $key['txCli'] === '') {
                    throw new InvalidArgumentException('Configuracion sin coEmp, coLoc o txCli.');
                }
                if ($cursor !== null && $key == $cursor) {
                    throw new \RuntimeException('La API repitio el cursor de configuraciones.');
                }

                $this->createOrUpdateConfig($item);
                $response = $this->api->confirmBranchConfigurationSync($key);
                $this->requireSuccessfulSyncResponse($response);
                if ((int) data_get($response, 'data') !== 1) {
                    throw new \RuntimeException('La API no confirmo una configuracion y su historico.');
                }

                // Avanzar solamente despues del guardado local y la confirmacion en GP.
                $cursor = $key;
                $processed++;
            }

            $batches++;
            Log::channel('cron_process')->info('Lote de configuraciones sincronizado', [
                'batch' => $batches,
                'processed' => $processed,
                'initial_pending' => $pendingCount,
                'cursor' => $cursor,
            ]);
        }
    }

    protected function requireSuccessfulSyncResponse(array $response): void
    {
        if (data_get($response, 'success') !== true) {
            throw new \RuntimeException((string) data_get($response, 'message', 'Fallo la API de sincronizacion.'));
        }
    }

    protected function createOrUpdateConfig(array $pending)
    {
        $codigoLocal    = data_get($pending, 'coLoc');
        $ruc            = trim((string) data_get($pending, 'txCli'));
        if ($ruc === '' || data_get($pending, 'coEmp') === null || $codigoLocal === null) {
            throw new InvalidArgumentException('Configuracion sin coEmp, coLoc o txCli.');
        }
        $codigoVendedor = data_get($pending, 'coVen');
        $pvId           = GpPuntoVenta::where('codigo_local', $codigoLocal)->value('id');
        $clienteId      = GpCliente::where('CLI_RUC', $ruc)->value('id');
        $vendedorId     = GpVendedor::where('VEND_ID', $codigoVendedor)->value('id');
        $dateModify     = data_get($pending, 'feUltmod')
            ? Carbon::parse(data_get($pending, 'feUltmod'))->format('Y-m-d H:i:s')
            : null;
        ClienteConfiguracion::updateOrCreate([
            'codigo_empresa'    => data_get($pending, 'coEmp'),
            'codigo_localidad'  => $codigoLocal,
            'ruc'               => $ruc,
        ], [
            'pv_id'                         => $pvId,
            'cliente_id'                    => $clienteId,
            'vendedor_id'                   => $vendedorId,
            'codigo_vendedor'               => $codigoVendedor,
            'fecha_ultima_modificacion'     => $dateModify,
            'estado'                        => 1,
            'sincronizado_at'               => now()
        ]);
    }
}
