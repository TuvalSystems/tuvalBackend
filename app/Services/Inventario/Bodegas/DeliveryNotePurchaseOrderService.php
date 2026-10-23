<?php

namespace App\Services\Inventario\Bodegas;

use App\Enums\Admin\Sync\StatusSync;
use App\Exceptions\UncertainPurchaseOrderException;
use App\Jobs\Inventario\Bodegas\GenerateDeliveryNotePurchaseOrderJob;
use App\Models\Admin\SyncLog;
use App\Models\despachos\DeliveryNotePurchaseOrder;
use App\Models\despachos\GpGuiaRemision;
use App\Models\GpPedido;
use App\Models\GpUsuario;
use App\Models\Reservas\GpGuiaRemisionReserva;
use App\Models\Ventas\Proveedores\GpProveedor;
use App\Models\Ventas\TipoDespachos\TipoDespacho;
use App\Services\Api\Bodegas\ApiDeliveryNotePurchaseOrderService;
use App\Services\Inventario\Bodegas\Contracts\DeliveryNotePurchaseOrderServiceInterface;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Throwable;

class DeliveryNotePurchaseOrderService implements DeliveryNotePurchaseOrderServiceInterface
{
    private const MAX_ATTEMPTS = 3;
    private const RETRY_DELAY_SECONDS = 300;

    public function __construct(private readonly ApiDeliveryNotePurchaseOrderService $api) {}

    public function enqueuePendingGuides(int $limit = 200): int
    {
        if ($limit < 1) {
            throw new DomainException('El límite debe ser mayor a cero.');
        }

        DeliveryNotePurchaseOrder::where('estado', '!=', 'generada')
            ->whereNotNull('orden_gp')
            ->whereRaw("TRIM(orden_gp) <> ''")
            ->eachById(fn($record) => $this->recover($record, trim($record->orden_gp)));

        // Recuperar una respuesta ya guardada nunca vuelve a invocar la API.
        DeliveryNotePurchaseOrder::whereIn('estado', ['procesando', 'incierta'])
            ->whereNotNull('respuesta')->eachById(function ($record) {
                $number = $this->generatedNumber($record->respuesta);
                if ($number !== null) {
                    $this->recover($record, $number);
                }
            });
        DeliveryNotePurchaseOrder::where('estado', 'procesando')
            ->where('fecha_intento', '<', now()->subMinutes(10))
            ->eachById(fn($record) => $this->recordInterruptedAttempt(
                $record->id,
                new UncertainPurchaseOrderException('El worker se interrumpió sin confirmar el resultado de GP.')
            ));

        $count = 0;
        foreach ([GpGuiaRemision::class, GpGuiaRemisionReserva::class] as $model) {
            $field = $model === GpGuiaRemision::class ? 'guia_remision_id' : 'guia_reserva_id';
            $table = (new $model())->getTable();
            $query = $this->eligibleGuides($model)
                ->whereNotExists(function ($query) use ($field, $table) {
                    $query->selectRaw('1')->from('guia_remision_orden_compra as po')
                        ->whereColumn('po.' . $field, $table . '.id')
                        ->where(function ($query) {
                            $query->whereNotIn('po.estado', ['pendiente', 'fallida'])
                                ->orWhere('po.intentos', '>=', self::MAX_ATTEMPTS)
                                ->orWhere('po.fecha_proximo_intento', '>', now());
                        });
                });
            if ($model === GpGuiaRemision::class) {
                $query->whereIn('tipo', ['despacho', 'reposicion', 'transferencia']);
            }

            $query->chunkById(100, function ($guides) use (&$count, $limit, $field) {
                foreach ($guides as $guide) {
                    if ($count >= $limit) {
                        return false;
                    }
                    $reservation = $field === 'guia_reserva_id';
                    $record = DeliveryNotePurchaseOrder::firstOrCreate([$field => $guide->id], [
                        'pedido_id' => $reservation ? null : GpPedido::where('pedido', $guide->pedido)->value('id'),
                        'tipo_guia' => $reservation ? 'reserva' : $guide->tipo,
                        'estado' => 'pendiente',
                    ]);
                    GenerateDeliveryNotePurchaseOrderJob::dispatch($record->id);
                    $count++;
                }
            });
            if ($count >= $limit) {
                break;
            }
        }

        return $count;
    }

    public function generatePurchaseOrder(int $purchaseOrderId): void
    {
        $record = DB::transaction(function () use ($purchaseOrderId) {
            $record = DeliveryNotePurchaseOrder::lockForUpdate()->findOrFail($purchaseOrderId);
            if ($record->estado !== 'generada' && trim((string) $record->orden_gp) !== '') {
                return $record;
            }
            if (!in_array($record->estado, ['pendiente', 'fallida'], true)
                || $record->intentos >= self::MAX_ATTEMPTS
                || $record->fecha_proximo_intento?->isFuture()) {
                return null;
            }
            $record->forceFill([
                'estado' => 'procesando',
                'intentos' => $record->intentos + 1,
                'fecha_intento' => now(),
                'fecha_proximo_intento' => null,
                'respuesta' => null,
                'ultimo_error' => null,
            ])->save();

            return $record;
        });
        if ($record === null) {
            return;
        }

        $requestSent = false;
        $response = null;
        try {
            if (trim((string) $record->orden_gp) !== '') {
                $this->complete($record, trim($record->orden_gp));
                return;
            }
            $guide = $this->findGuide($record);
            if (!$guide->requiere_orden_compra || !in_array($guide->estado, ['enviada', 'recibida'], true)) {
                $record->update([
                    'estado' => 'pendiente',
                    'intentos' => $record->intentos - 1,
                    'ultimo_error' => 'La guía ya no cumple las condiciones para generar OC.',
                ]);
                return;
            }
            if ($guide->oc_generada) {
                throw new UncertainPurchaseOrderException('La guía indica OC generada pero la tabla de órdenes no tiene el documento. Verificar en GP.');
            }

            $payload = $this->buildPayload($guide);
            $record->solicitud = $payload;
            $record->save();
            $requestSent = true;
            $response = $this->api->createPurchaseOrder($payload);
            // Persistir antes de actualizar la guía permite recuperar un éxito tras un fallo local.
            $record->respuesta = $response;
            $record->save();

            $number = $this->generatedNumber($response);
            if ($number !== null) {
                $this->complete($record, $number);
                return;
            }

            $message = (string) data_get($response, 'message', 'GP no confirmó la creación de la orden de compra.');
            $status = (int) data_get($response, 'status', 200);
            if (data_get($response, 'success') === false && $status >= 400 && $status < 500 && $status !== 408
                && empty(data_get($response, 'data.ordenGenerada'))) {
                $requestSent = false;
                throw new DomainException($message);
            }
            if (data_get($response, 'success') === false && $status === 200
                && isset($response['message']) && empty(data_get($response, 'data.ordenGenerada'))) {
                $requestSent = false;
                throw new DomainException($message);
            }
            throw new UncertainPurchaseOrderException($message . ' Verificar en GP antes de reenviar.');
        } catch (Throwable $exception) {
            $this->recordFailure($record, $exception, $requestSent || $exception instanceof UncertainPurchaseOrderException, $response);
            throw $exception;
        }
    }

    public function recordInterruptedAttempt(int $purchaseOrderId, Throwable $exception): void
    {
        $record = DeliveryNotePurchaseOrder::find($purchaseOrderId);
        if ($record?->estado === 'procesando') {
            $this->recordFailure($record, $exception, true, $record->respuesta);
        }
    }

    private function eligibleGuides(string $model): Builder
    {
        return $model::where('requiere_orden_compra', true)->where('oc_generada', false)
            ->whereIn('estado', ['enviada', 'recibida']);
    }

    private function findGuide(DeliveryNotePurchaseOrder $record, bool $lock = false): Model
    {
        $query = $record->guia_reserva_id !== null
            ? GpGuiaRemisionReserva::query() : GpGuiaRemision::query();
        if ($lock) {
            $query->lockForUpdate();
        }
        return $query->findOrFail($record->guia_reserva_id ?? $record->guia_remision_id);
    }

    private function buildPayload(Model $guide): array
    {
        $supplier   = trim((string) GpProveedor::find($guide->fle_proveedor_id)?->PROV_RUC);
        $user       = trim((string) GpUsuario::find($guide->user_id)?->USERID);
        $amount     = (float) $guide->valor_orden_compra;
        if ($supplier === '' || $user === '' || !is_finite($amount) || $amount <= 0
            || !TipoDespacho::whereKey($guide->tipo_despacho_id)->exists()) {
            throw new DomainException('La guía requiere proveedor con PROV_RUC, usuario con USERID, tipo de despacho válido y valor de OC mayor a cero.');
        }

        return [
            'proveedor'         => $supplier,
            'usuario'           => $user,
            'tipoDespachoId'    => (int) $guide->tipo_despacho_id,
            'valorOrdenCompra'  => round($amount, 2),
            'bodega'            => trim((string) $guide->bodega_origen),
            'numGuia'           => trim((string) $guide->GUI_REM_GUIA_REMISION),
        ];
    }

    private function generatedNumber(?array $response): ?string
    {
        $number = trim((string) data_get($response, 'data.ordenGenerada', ''));
        return data_get($response, 'success') === true
            && (string) data_get($response, 'data.resultado') === '0' && $number !== '' ? $number : null;
    }

    private function complete(DeliveryNotePurchaseOrder $record, string $number): void
    {
        DB::transaction(function () use ($record, $number) {
            $record = DeliveryNotePurchaseOrder::lockForUpdate()->findOrFail($record->id);
            if ($record->estado === 'generada') {
                return;
            }
            $guide = $this->findGuide($record, true);
            $current = trim((string) $record->orden_gp);
            if ($current !== '' && $current !== $number) {
                throw new UncertainPurchaseOrderException('La guía ya tiene una OC diferente. Requiere conciliación en GP.');
            }
            $guide->oc_generada = true;
            $guide->save();
            $record->update([
                'orden_gp' => $number,
                'estado' => 'generada',
                'fecha_generacion' => now(),
                'fecha_proximo_intento' => null,
                'ultimo_error' => null,
            ]);
            $this->logResult($record, 'Orden de compra generada y asociada a la guía.', StatusSync::SUCCESS);
        });
    }

    private function recover(DeliveryNotePurchaseOrder $record, string $number): void
    {
        try {
            $this->complete($record, $number);
        } catch (Throwable $exception) {
            // Un conflicto de una guía no debe bloquear el procesamiento de las demás.
            if ($record->fresh()?->ultimo_error !== $exception->getMessage()) {
                $this->recordFailure($record, $exception, true, $record->respuesta);
            }
        }
    }

    private function recordFailure(DeliveryNotePurchaseOrder $record, Throwable $exception, bool $uncertain, ?array $response): void
    {
        $record->update([
            'estado' => $uncertain ? 'incierta' : 'fallida',
            'ultimo_error' => $exception->getMessage(),
            'respuesta' => $response,
            'fecha_proximo_intento' => $uncertain ? null : now()->addSeconds(self::RETRY_DELAY_SECONDS),
        ]);
        $this->logResult($record, $exception->getMessage(), StatusSync::FAILED);
    }

    private function logResult(DeliveryNotePurchaseOrder $record, string $message, StatusSync $status): void
    {
        SyncLog::logSync('DeliveryNotePurchaseOrder', 'generate_purchase_order', 'generatePurchaseOrder',
            $message, $record->orden_gp, $record->id, [
                'pedido_id' => $record->pedido_id,
                'guia_remision_id' => $record->guia_remision_id,
                'guia_reserva_id' => $record->guia_reserva_id,
                'tipo_guia' => $record->tipo_guia,
                'estado' => $record->estado,
                'request' => $record->solicitud,
                'response' => $record->respuesta,
            ], $record->intentos, $status);
    }
}
