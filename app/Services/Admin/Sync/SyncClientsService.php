<?php

namespace App\Services\Admin\Sync;

use App\Models\Admin\Canton;
use App\Models\Admin\Provincia;
use App\Models\Admin\Region;
use App\Models\GpCliente;
use App\Models\GpClienteSucursal;
use App\Services\Admin\Sync\Contracts\SyncClientsServiceInterface;
use App\Services\Api\Admin\ApiSync;
use App\Services\Builders\Venta\Contracts\ClientBuilderInterface;
use App\Traits\Sincronizacion\DetectsRealModelChanges;
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
}
