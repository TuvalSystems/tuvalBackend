<?php

namespace App\Services\Facturacion;

use Illuminate\Support\Facades\DB;

class InvoiceReservationConsumptionService
{
    public function consume(int $orderId, int $stockId, int $quantity): void
    {
        DB::transaction(function () use ($orderId, $stockId, $quantity) {
            $stockQuery = DB::table('gp_cotizacion_detalle_stocks')->where('id', $stockId);
            $row = (clone $stockQuery)->lockForUpdate()->first();
            if (!$row) return;
            $identity = ['pedido_id' => $orderId, 'stock_id' => $stockId];
            DB::table('consumos_reserva_facturacion')->insertOrIgnore($identity + ['cantidad' => 0]);
            $consumption = DB::table('consumos_reserva_facturacion')->where($identity);
            $previous = (int) (clone $consumption)->lockForUpdate()->value('cantidad');
            $pending = max(0, $quantity - $previous);
            $selected = (int) $row->seleccionado;
            $invoiced = (int) ($row->cantidad_facturada ?? 0);
            $limit = max(0, (int) ($row->cantidad_reservada ?? 0) - (int) ($row->cantidad_cancelada ?? 0));
            $increment = min($pending, max(0, $limit - $invoiced));
            $consumed = min($increment, $selected);
            if ($consumed <= 0) return;
            $stockQuery->update(['seleccionado' => $selected - $consumed, 'cantidad_facturada' => $invoiced + $increment]);
            $consumption->update(['cantidad' => $previous + $increment]);
        });
    }
}
