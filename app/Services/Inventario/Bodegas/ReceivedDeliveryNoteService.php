<?php

namespace App\Services\Inventario\Bodegas;

use App\Enums\bodegas\guiaremision\DeliveryNoteStatus;
use App\Enums\bodegas\guiaremision\DeliveryNoteType;
use App\Models\GpBodega;
use App\Services\Inventario\Bodegas\Contracts\ReceivedDeliveryNoteServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ReceivedDeliveryNoteService implements ReceivedDeliveryNoteServiceInterface
{
    public function types(): array
    {
        return [
            ['id' => DeliveryNoteType::DESPACHO->value, 'nombre' => 'Despacho'],
            ['id' => DeliveryNoteType::REPOSICION->value, 'nombre' => 'Reposición'],
            ['id' => DeliveryNoteType::TRANSFERENCIA->value, 'nombre' => 'Transferencia'],
            ['id' => 'reserva', 'nombre' => 'Reserva'],
        ];
    }

    public function getWarehouses(): Collection
    {
        return GpBodega::where('PERMITE_DESPACHO', 1)->get(['id', 'BOD_BODEGA as bodega']);
    }

    public function listReceived(array $filters, int $userId): LengthAwarePaginator
    {
        $type = $filters['tipo'];
        if (!in_array($type, array_column($this->types(), 'id'), true)) {
            throw ValidationException::withMessages(['tipo' => 'El tipo de guía no es válido.']);
        }

        // Misma asignacion de puntos de venta usada por /api/movil/point-sales.
        $allowed = DB::table('gp_relacion_usuario_punto_ventas')
            ->where('user_id', $userId)
            ->where('pv_id', $filters['punto_venta_id'])
            ->exists();
        if (!$allowed) {
            throw new AccessDeniedHttpException('No tiene acceso a este punto de venta.');
        }

        $warehouses = DB::table('gp_bodegas as b')
            ->join('gp_relacion_punto_venta_bodegas as r', 'r.PTO_VTA_BOD_BODEGA', '=', 'b.BOD_BODEGA')
            ->join('gp_punto_ventas as pv', 'pv.PTO_VTA_ID', '=', 'r.PTO_VTA_BOD_PUNTO_VENTA')
            ->where('pv.id', $filters['punto_venta_id'])
            ->select('b.id');

        $reservation = $type === 'reserva';
        $status = $type === DeliveryNoteType::DESPACHO->value
            ? DeliveryNoteStatus::ENVIADA->value
            : DeliveryNoteStatus::RECIBIDA->value;
        $query = DB::table($reservation ? 'gp_guia_remision_reservas as g' : 'gp_guia_remisions as g')
            ->select('g.id')
            ->selectRaw($reservation ? 'g.numero_guia as numero_guia' : 'g.GUI_REM_GUIA_REMISION as numero_guia')
            ->selectRaw($reservation ? 'g.fecha_salida as fecha_salida' : 'g.GUI_REM_FECHA_INICIO as fecha_salida')
            ->selectRaw('? as tipo', [$type])
            ->where('g.estado', $status)
            ->whereIn($type === DeliveryNoteType::DESPACHO->value ? 'g.bodega_origen_id' : 'g.bodega_destino_id', $warehouses);

        if (!$reservation) {
            $query->where('g.tipo', $type);
        }

        if ($type !== DeliveryNoteType::DESPACHO->value && !empty($filters['bodega_origen_id'])) {
            $query->where('g.bodega_origen_id', $filters['bodega_origen_id']);
        }

        if ($type === DeliveryNoteType::DESPACHO->value && !empty($filters['cliente_id'])) {
            // EXISTS evita duplicar guias al relacionarlas con sus pedidos.
            $query->whereExists(function ($orders) use ($filters) {
                $orders->selectRaw('1')->from('gp_pedidos as p')
                    ->whereColumn('p.pedido', 'g.pedido')
                    ->where('p.cliente_id', $filters['cliente_id']);
            });
        }

        return $query->orderByDesc('fecha_salida')->orderByDesc('g.id')
            ->paginate(min(100, max(1, (int) ($filters['per_page'] ?? 20))), ['*'], 'page', max(1, (int) ($filters['page'] ?? 1)))
            ->withQueryString();
    }
}
