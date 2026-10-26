<?php

namespace App\Services\Inventario\Bodegas;

use App\Enums\Ventas\OrderStatus;
use App\Models\Inventario\GuiasRemision\GpGuiaRemision;
use App\Models\Inventario\Despachos\GpOrdenDespacho;
use App\Models\Ventas\Pedidos\GpPedido;
use App\Models\Ventas\Pedidos\GpPedidoDetalle;
use App\Models\Ventas\Reservas\GpGuiaRemisionReserva;
use App\Services\Api\Bodegas\ApiDeliverNoteService;
use App\Services\Inventario\Bodegas\Contracts\LogisticsOperationsServiceInterface;
use Illuminate\Support\Facades\Log;

class LogisticsOperationsService implements LogisticsOperationsServiceInterface
{

    public function __construct(protected readonly ApiDeliverNoteService $apiDeliveryNote) {}


    /** funcion para asignar el numero de  GUI_REM_GUIA_REMISION a cada guia de remision */
    public function assignAccessCodesForPendingGuides()
    {
        $guias = GpGuiaRemision::whereNull('GUI_REM_CODIGO')->get();

        foreach ($guias as $guia) {
            $result = $this->apiDeliveryNote->getAccessCodeByNumberDocDelivery([
                'numDoc' => $guia->GUI_REM_GUIA_REMISION,
            ]);
            $success = data_get($result, 'success', false);
            $mensaje = data_get($result, 'message', '');
            if ($success === false) {
                throw new \RuntimeException($mensaje);
            }
            $response               = data_get($result, 'data', []);
            $guia->GUI_REM_CODIGO   = data_get($response, 'claveAcceso');
            $guia->save();
        }
    }

    public function assignAccessCodeForPendingGuide($guia)
    {
        if (!empty($guia->clave_acceso)) {
            return $guia->clave_acceso;
        }
        try {
            $res = $this->apiDeliveryNote->getAccessCodeByNumberDocDelivery([
                'numDoc' => $guia->numero_guia,
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('No se pudo consultar la clave de acceso: ' . $e->getMessage(), 0, $e);
        }
        $ok = data_get($res, 'success') === true;
        $msg = data_get($res, 'message', 'Error desconocido');
        $code = data_get($res, 'data.claveAcceso');

        if (!$ok) {
            throw new \DomainException($msg);
        }
        if (!is_string($code) || $code === '') {
            throw new \UnexpectedValueException('La API no retornó una claveAcceso válida.');
        }

        // Evita carrera: solo escribe si sigue NULL
        $updated = GpGuiaRemisionReserva::whereKey($guia->getKey())
            ->whereNull('clave_acceso')
            ->update(['clave_acceso' => $code]);

        // Si otro proceso la puso primero, devuelve la actual del modelo
        return $updated ? $code : (string) $guia->fresh()->clave_acceso;
    }

    //remplaza la funcion de checkdespacho - DespachoController
    public function updateOrderDispatchStatus(GpGuiaRemision $guia)
    {
        $pedido = GpPedido::query()
            ->where('pedido', $guia->pedido)
            ->whereNotIn('estado', [
                OrderStatus::RECHAZADO,
                OrderStatus::ANULADA,
                OrderStatus::ELIMINADO
            ])
            ->first();

        if (!$pedido) {
            Log::warning('Pedido no encontrado o no actualizable al verificar despacho', [
                'guia_id'       => $guia->id,
                'pedido'        => $guia->pedido,
            ]);
            return null;
        }

        $hasPending = GpPedidoDetalle::query()->where('pedido_id', $pedido->id)
            ->whereRaw('COALESCE(trasladado,0) + COALESCE(devuelto,0) < COALESCE(ART_CANTIDAD,0)')
            ->exists();
        if (!$hasPending) {
            $hasCanceledDispatchOrder = GpOrdenDespacho::query()
                ->where('pedido_id', $pedido->id)
                ->where('estado', 'cancelada')
                ->exists();

            if ($hasCanceledDispatchOrder) {
                Log::warning('Pedido listo no fue marcado como despachado porque tiene una orden de despacho cancelada', [
                    'guia_id' => $guia->id,
                    'pedido_id' => $pedido->id,
                    'pedido' => $pedido->pedido,
                ]);

                return $pedido->refresh();
            }

            $updatedDispatchOrders = GpOrdenDespacho::query()
                ->where('pedido_id', $pedido->id)
                ->where('estado', 'liberada')
                ->update(['estado' => 'despachada']);

            if (!$updatedDispatchOrders) {
                $alreadyDispatched = GpOrdenDespacho::query()
                    ->where('pedido_id', $pedido->id)
                    ->where('estado', 'despachada')
                    ->exists();

                if (!$alreadyDispatched) {
                    Log::warning('Pedido listo no fue marcado como despachado porque no tiene orden de despacho liberada o despachada', [
                        'guia_id' => $guia->id,
                        'pedido_id' => $pedido->id,
                        'pedido' => $pedido->pedido,
                        'estados_ordenes' => GpOrdenDespacho::query()
                            ->where('pedido_id', $pedido->id)
                            ->pluck('estado')
                            ->values()
                            ->all(),
                    ]);

                    return $pedido->refresh();
                }
            }

            $pedido->fill([
                'despachado' => 1,
                'despacho_en_proceso' => 0
            ])->save();
        }
        return $pedido->refresh();
    }
}
