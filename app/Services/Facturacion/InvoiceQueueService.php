<?php

namespace App\Services\Facturacion;

use App\Jobs\Facturacion\InvoiceOrderJob;
use App\Models\Ventas\Pedidos\GpPedido;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class InvoiceQueueService
{
    public function enqueue(GpPedido $pedido, bool $trasladado = false, bool $checkDemanda = true, bool $directa = false): void
    {
        // La solicitud y el job se insertan en la misma BD/transacción del pedido.
        // El worker solo puede verlos después del commit; un rollback elimina ambos.
        DB::transaction(function () use ($pedido, $trasladado, $checkDemanda, $directa) {
            $identity = [
                'pedido_id' => $pedido->id,
                'directa' => $directa,
                'trasladado' => $trasladado,
                'check_demanda' => $checkDemanda
            ];
            DB::table('solicitudes_facturacion')->insertOrIgnore($identity + ['created_at' => now(), 'updated_at' => now()]);
            $query = DB::table('solicitudes_facturacion')->where($identity);
            $request = (clone $query)->lockForUpdate()->first();
            if ($request->estado === 'pendiente') {
                return;
            }
            $version = $request->version + 1;
            $query->update(['estado' => 'pendiente', 'version' => $version, 'ultimo_error' => null, 'updated_at' => now()]);
            Queue::connection('facturacion')->push(new InvoiceOrderJob($request->id, $version), '', 'facturacion');
        });
    }
}
