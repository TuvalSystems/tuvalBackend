<?php

namespace App\Services\Venta\Cliente;

use App\Enums\Admin\Sync\StatusSync;
use App\Models\Admin\Sync\SyncLog;
use App\Models\Ventas\Clientes\GpCliente;
use App\Models\Ventas\Clientes\GpClienteSucursal;
use App\Models\Admin\Usuarios\GpUsuario;
use App\Services\Api\ApiGpService;
use App\Services\Builders\Venta\Contracts\ClientBuilderInterface;
use App\Services\Venta\Cliente\Contracts\ClientGpSenderServiceInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ClientGpSenderService implements ClientGpSenderServiceInterface
{
    public function __construct(
        protected readonly ApiGpService $api,
        protected readonly ClientBuilderInterface $builder
    ) {}

    public function syncClient(int $clientId): void
    {
        $client = GpCliente::findOrFail($clientId);

        if (!$client->enviado_gp) {
            $payloadHeader = $this->builder->formPayloadSenderClientGp($client);
            $this->sendClientApiGp($payloadHeader);
            $this->sendSegmentationToGp($client->CLI_RUC, $client->CLI_SUBSEGMENTO_ID);

            $client->enviado_gp = true;
            $client->save();
        }

        GpClienteSucursal::where('cliente_id', $client->id)
            ->where('enviado_gp', false)
            ->pluck('id')
            ->each(fn(int $branchId) => $this->syncBranch($branchId));
    }

    public function syncBranch(int $branchId): void
    {
        $branch = GpClienteSucursal::findOrFail($branchId);

        if ($branch->enviado_gp) {
            return;
        }

        $currentId = strtoupper(trim((string) $branch->CLI_SUC_ID_DIRECCION));
        $params = $this->builder->formPayloadSenderClientBranchToGp($branch);
        $responseGp = $this->sendClientBranchApiGp($params);
        $responseId = strtoupper(trim((string) data_get($responseGp, 'identificador', '')));
        $idAddress = $responseId !== '' ? $responseId : $currentId;

        if ($idAddress === '') {
            throw new RuntimeException("GP no devolvió el identificador para la sucursal {$branch->id}.");
        }

        if ($currentId !== '' && $responseId !== '' && $currentId !== $responseId) {
            throw new RuntimeException(
                "GP devolvió el identificador {$responseId} para la sucursal {$branch->id}, pero se envió {$currentId}."
            );
        }

        $branch->CLI_SUC_ID_DIRECCION = $idAddress;
        $branch->save();

        $user = GpUsuario::find($branch->user_creator_id);
        if (!$user) {
            throw new RuntimeException("No se encontró el usuario creador de la sucursal {$branch->id}.");
        }

        $this->createRegisterBranchClient(
            $branch,
            1,
            $user->USERID,
            Carbon::parse($branch->fecha_hora_creacion)->format('Y-m-d H:i:s'),
            null,
            null,
            false
        );
    }



    protected function sendSegmentationToGp(string $ruc, ?int $subSegment): void
    {
        if ($subSegment === null) {
            return;
        }

        $response = $this->api->storeSubsegmentAssociation([
            'ruc'           => $ruc,
            'subsegmentoId' => $subSegment,
        ]);

        $this->assertSuccessfulResponse($response, 'asociar la segmentación del cliente');
    }

    protected function sendClientApiGp(array $params): array
    {
        $response = $this->api->storeClientGp($params);

        return $this->assertSuccessfulResponse($response, 'crear o actualizar el cliente');
    }

    public function sendClientBranchApiGp(array $params): array
    {
        $response = $this->api->storeNewAddressClient($params);
        $response = $this->assertSuccessfulResponse($response, 'crear la dirección del cliente');
        $data = data_get($response, 'data');

        if (!is_array($data)) {
            throw new RuntimeException('GP no devolvió datos válidos al crear la dirección del cliente.');
        }

        return $data;
    }



    public function createRegisterBranchClient(GpClienteSucursal $data, int $mode, ?string $userIngreso = null, ?string $fechaCreacion = null, ?string $userModificacion = null, ?string $fechaModificacion = null, bool $autoRetry = true): void
    {
        $data->refresh();
        if ($data->enviado_gp) {
            $data->enviado_gp = false;
            $data->save();
        }

        $params = [
            'ruc'                   => $data->CLI_SUC_RUC,
            'idDireccion'           => $data->CLI_SUC_ID_DIRECCION,
            'direccion'             => $data->CLI_SUC_DIRECCION,
            'estado'                => $data->estado === 1 ? "A" : "I",
            'usuarioIngreso'        => $userIngreso,
            'fechaIngreso'          => $fechaCreacion ?? "",
            'usuarioModificacion'   => $userModificacion ?? "",
            'fechaModificacion'     => $fechaModificacion ?? "",
            'modo'                  => $mode
        ];

        $resp = $this->api->updateOrCreateRegisterBranchClient($params);
        $status = data_get($resp, 'success', false);
        $message = data_get($resp, 'message', '');
        if (!$status && $mode === 2 && $autoRetry && $this->isNotFoundError($message)) {
            Log::info('Registro no existe en GP (Error 900), intentando crear', [
                'sucursal_id'       => $data->id,
                'ruc'               => $data->CLI_SUC_RUC,
                'id_direccion'      => $data->CLI_SUC_ID_DIRECCION,
                'error_original'    => $message
            ]);
            $params['modo'] = 1;
            $params['usuarioIngreso'] =  GpUsuario::where('id', $data->user_creator_id)->value('USERID')  ?? $userModificacion;
            $params['fechaIngreso'] =   $data->fecha_hora_creacion ?? Carbon::parse($data->created_at)->format('Y-m-d H:i:s')  ?? Carbon::now()->format('Y-m-d H:i:s');

            $retryResp = $this->api->updateOrCreateRegisterBranchClient($params);
            $retryStatus = data_get($retryResp, 'success', false);

            if ($retryStatus) {
                Log::info('✅ Registro creado exitosamente después de fallar actualización', [
                    'sucursal_id' => $data->id,
                    'ruc' => $data->CLI_SUC_RUC,
                    'modo_usado' => 1
                ]);
                $this->markBranchAsSent($data);
                return;
            }

            $retryMessage = data_get($retryResp, 'message', 'Error desconocido en retry');
            $message = "Falló actualización (Error 900) y creación. Error creación: {$retryMessage}";
            $this->logSyncError($data, $params, $message);
            throw new RuntimeException($message);
        }

        if (!$status) {
            $this->logSyncError($data, $params, $message);
            throw new RuntimeException($message ?: 'GP no pudo registrar la sucursal del cliente.');
        }

        $this->markBranchAsSent($data);
    }

    private function markBranchAsSent(GpClienteSucursal $branch): void
    {
        $branch->enviado_gp = true;
        $branch->save();
    }

    private function assertSuccessfulResponse(mixed $response, string $operation): array
    {
        if (!is_array($response) || !data_get($response, 'success', false)) {
            $message = is_array($response)
                ? data_get($response, 'message', "No se pudo {$operation} en GP.")
                : "GP devolvió una respuesta inválida al intentar {$operation}.";

            throw new RuntimeException((string) $message);
        }

        return $response;
    }
    private function isNotFoundError(string $message): bool
    {
        // Patrón específico para tu API
        if (str_contains($message, '[900]') && str_contains($message, 'No existe este registro')) {
            return true;
        }

        // Otros patrones por si cambian el mensaje
        $notFoundPatterns = [
            'no existe este registro',
            'error del sp [900]',
            'not found',
            'no encontrado',
        ];

        $messageLower = strtolower($message);

        foreach ($notFoundPatterns as $pattern) {
            if (str_contains($messageLower, strtolower($pattern))) {
                return true;
            }
        }

        return false;
    }

    private function logSyncError(GpClienteSucursal $data, array $params, string $message): void
    {
        SyncLog::logErrorSync(
            'SucursalesGP',
            'actualizar-o-crear-registro-sucursal-cliente',
            'updateOrCreateRegisterBranchClient',
            $message,
            $data->CLI_SUC_RUC,
            $data->id,
            $params,
            0,
            StatusSync::FAILED
        );
    }
}
