<?php

namespace App\Jobs\Inventario\Bodegas;

use App\Enums\Admin\Sync\StatusSync;
use App\Models\Admin\Sync\SyncLog;
use App\Models\Inventario\GuiasRemision\DeliveryNotePurchaseOrder;
use App\Services\Api\Bodegas\ApiDeliveryNotePurchaseOrderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SyncDeliveryNotePurchaseOrderJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // La API no documenta idempotencia: revisar el resultado antes de reintentar.
    public int $tries = 1;
    public int $timeout = 120;
    public bool $failOnTimeout = true;
    public int $uniqueFor = 900;

    public function __construct(public int $purchaseOrderId)
    {
        $this->afterCommit = true;
        $this->onConnection('redis');
        $this->onQueue('sync-high');
    }

    public function uniqueId(): string
    {
        return 'sync-delivery-note-purchase-order:' . $this->purchaseOrderId;
    }

    public function handle(ApiDeliveryNotePurchaseOrderService $api): void
    {
        $record = DeliveryNotePurchaseOrder::findOrFail($this->purchaseOrderId);
        if ($record->estado !== 'generada' || trim((string) $record->orden_gp) === '') {
            throw new \DomainException('Solo se puede enviar una relación con OC generada.');
        }

        $payload = [
            'id'                => $record->id,
            'id_guia_remision'  => $record->guia_remision_id,
            'id_guia_reserva'   => $record->guia_reserva_id,
            'id_pedido_id'      => $record->pedido_id,
            'tipo_guia'         => $record->tipo_guia,
            'id_oc'             => trim($record->orden_gp),
            'fecha_ingreso'     => $record->fecha_generacion->format('Y-m-d H:i:s'),
        ];
        $response = $api->storeGuidePurchaseOrder($payload);

        SyncLog::logSync('DeliveryNotePurchaseOrder', 'sync_purchase_order', 'storeGuidePurchaseOrder',
            'Relación guía/OC guardada en SQL Server.', $record->orden_gp, $record->id,
            ['request' => $payload, 'response' => $response], 1, StatusSync::SUCCESS);
    }

    public function failed(Throwable $exception): void
    {
        SyncLog::logSync('DeliveryNotePurchaseOrder', 'sync_purchase_order', 'storeGuidePurchaseOrder',
            $exception->getMessage(), null, $this->purchaseOrderId, [], 1, StatusSync::FAILED);
    }
}
