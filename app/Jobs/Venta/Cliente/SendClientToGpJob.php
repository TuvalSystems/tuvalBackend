<?php

namespace App\Jobs\Venta\Cliente;

use App\Enums\Admin\Sync\StatusSync;
use App\Models\Admin\Sync\SyncLog;
use App\Models\Ventas\Clientes\GpCliente;
use App\Services\Ventas\Clientes\Contracts\ClientGpSenderServiceInterface;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendClientToGpJob
{
    use Dispatchable, SerializesModels;


    public function __construct(public int $clientId)
    {}


    public function handle(ClientGpSenderServiceInterface $sender): void
    {
        try {
            $sender->syncClient($this->clientId);
            $this->completed();
        } catch (Throwable $exception) {
            $this->failed($exception);

            throw $exception;
        }
    }

    private function completed(): void
    {
        $client = GpCliente::query()
            ->select([
                'id',
                'CLI_RUC',
                'CLI_RAZON_SOCIAL',
                'CLI_ESTADO',
                'user_creator_id',
                'enviado_gp',
            ])
            ->findOrFail($this->clientId);

        $branches = $client->sucursales()
            ->get(['id', 'enviado_gp']);

        $payloadLog = [
            'client_id'        => $client->id,
            'ruc'              => $client->CLI_RUC,
            'razon_social'     => $client->CLI_RAZON_SOCIAL,
            'estado'           => $client->CLI_ESTADO,
            'user_id'          => $client->user_creator_id,
            'cliente_enviado'  => $client->enviado_gp,
            'sucursales_total' => $branches->count(),
            'sucursales_enviadas' => $branches->where('enviado_gp', true)->count(),
            'sucursales_pendientes' => $branches->where('enviado_gp', false)->count(),
            'execution_mode'   => 'sync',
        ];

        SyncLog::logSync(
            'SendClientToGpJob',
            'enviar_cliente_y_sucursales_gp',
            'syncClient',
            'Cliente y sucursales enviados correctamente a GP.',
            $client->CLI_RUC,
            $client->id,
            $payloadLog,
            1,
            StatusSync::SUCCESS
        );
    }

    public function failed(Throwable $exception): void
    {
        $client = GpCliente::query()
            ->select([
                'id',
                'CLI_RUC',
                'CLI_RAZON_SOCIAL',
                'CLI_ESTADO',
                'user_creator_id',
            ])
            ->find($this->clientId);

        $payloadLog = [
            'client_id'      => $this->clientId,
            'ruc'            => $client?->CLI_RUC,
            'razon_social'   => $client?->CLI_RAZON_SOCIAL,
            'estado'         => $client?->CLI_ESTADO,
            'user_id'        => $client?->user_creator_id,
            'execution_mode' => 'sync',
        ];

        SyncLog::logSync(
            'SendClientToGpJob',
            'enviar_cliente_gp',
            'syncClient',
            $exception->getMessage(),
            $client?->CLI_RUC,
            $this->clientId,
            $payloadLog,
            1,
            StatusSync::FAILED
        );
    }
}
