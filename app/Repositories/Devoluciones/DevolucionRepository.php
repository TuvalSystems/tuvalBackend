<?php

namespace App\Repositories\Devoluciones;

use App\Enums\devoluciones\DevolutionStatus;
use App\Enums\devoluciones\InvoiceAnulationStatus;
use App\Models\GpCliente;
use App\Models\GpClienteSucursal;
use App\Models\GpMotivosDevolucion;
use App\Models\GpPedido;
use App\Models\devoluciones\GpDevoluciones;
use App\Models\devoluciones\GpDevolucionesDetalle;
use App\Models\devoluciones\GpFacturaAnulada;
use App\Models\GpOrdenDespacho;
use App\Models\GpPuntoVenta;
use App\Repositories\Devoluciones\Contracts\DevolucionRepositoryInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

class DevolucionRepository implements DevolucionRepositoryInterface
{

    public function isInvoiceAlreadyVoided(GpPedido $pedido, GpDevoluciones $devolucion): bool
    {
        return GpFacturaAnulada::where('factura', $pedido->factura)->where('devolucion_id', $devolucion->id)
            ->where('pedido_id', $pedido->id)->where('estado', InvoiceAnulationStatus::ENVIADA)->exists();
    }

    public function isOrderNotDispatchedOrProcessing(array|GpPedido $pedido): bool
    {
        return GpPedido::where(function ($q) {
            $q->where('despachado', 0)->orWhereNull('despachado');
        })->where(function ($q) {
            $q->where('despacho_en_proceso', 0)->orWhereNull('despacho_en_proceso');
        })
            ->where('id', data_get($pedido, 'id'))->exists();
    }

    public function hasOtherActiveReturns(array|GpPedido $pedido, array|GpDevoluciones $devolucion): bool
    {
        return GpDevoluciones::where('pedido_id', data_get($pedido, 'id'))->where('id', '!=', data_get($devolucion, 'id'))
            ->whereNotIn('estado', [DevolutionStatus::RECHAZADO, DevolutionStatus::NO_PROCESADO])->exists();
    }

    public function validateItemLDetailInvoice(array|GpDevoluciones $devolucion): bool
    {
        return GpDevolucionesDetalle::where('devolucion_id', data_get($devolucion, 'id'))->where('ART_TIPO_COMPRA', 'L')->exists();
    }


    public function getOrderbyId(int|string|null $pedidoId): GpPedido
    {

        return GpPedido::findOrFail($pedidoId);
    }

    public function getDetailDevolucion(int|string $devolucionId, int|bool|null $despachadoParcial = null): array
    {
        $query = GpDevolucionesDetalle::where('devolucion_id', $devolucionId);
        if (!is_null($despachadoParcial)) {
            $query->where('despachado_parcial', $despachadoParcial);
        }
        return $query->where('seleccionado', '>', 0)->get()
            ->filter(function ($item) {
                // ✅ Verificación de datos correctos y no nulos
                return is_array($item->toArray()) && isset($item['ART_CODIGO']);
            })
            ->map(function ($item) {
                // 🚀 Convertimos cada item a array para asegurar tipado
                return $item->toArray();
            })
            ->toArray();
    }


    public function hasPartiallyDispatchedItems(array|GpDevoluciones $devolucion): bool
    {
        return GpDevolucionesDetalle::where('devolucion_id', data_get($devolucion, 'id'))
            ->where('despachado_parcial', 1)
            ->exists();
    }


    public function getReturnsAnulated(int|string|null $pedidoId): bool
    {
        return GpDevoluciones::where('pedido_id', $pedidoId)->where('estado', DevolutionStatus::ANULADA)->exists();
    }


    // funciones para el detallepedidodevolucion - getDetailOrderReturnById
    public function getReturnsInProcess(int|string|null $pedidoId): bool
    {
        return GpDevoluciones::where('pedido_id', $pedidoId)
            ->whereNotIn('estado', [DevolutionStatus::PROCESADO, DevolutionStatus::RECHAZADO, DevolutionStatus::NO_PROCESADO])
            ->exists();
    }

    public function getMotivosDevolucion(): EloquentCollection
    {
        return GpMotivosDevolucion::get();
    }

    public function getClientsByClientId(int|string|null $clienteId): GpCliente
    {
        return GpCliente::where('id', $clienteId)->firstOrFail();
    }
    public function getClientsByClientRuc(string $ruc): ?GpCliente
    {
        return GpCliente::where('CLI_RUC', $ruc)->first();
    }

    public function getClientsSucursByClientRuc(string $ruc): EloquentCollection
    {
        return GpClienteSucursal::join('gp_vendedors', 'gp_vendedors.VEND_ID', 'gp_cliente_sucursals.CLI_SUC_VENDEDOR')
            ->where('CLI_SUC_RUC', $ruc)
            ->select('gp_cliente_sucursals.*', 'gp_vendedors.VEND_NOMBRES', 'gp_vendedors.VEND_APELLIDOS')
            ->orderby('CLI_SUC_ID_DIRECCION', 'DESC')
            ->get();
    }

    public function getSumGuidesRemisionByOrderDetailId(int|string $detalleId): int|float
    {
        return DB::table('gp_guia_remision_detalles')
            ->join('gp_guia_remisions', 'gp_guia_remisions.id', '=', 'gp_guia_remision_detalles.guia_remision_id')
            ->where('gp_guia_remision_detalles.detalle_id', $detalleId)
            ->where('gp_guia_remisions.estado', 'enviada')
            ->where('gp_guia_remisions.tipo', 'despacho')
            ->sum('gp_guia_remision_detalles.GUI_REM_CANTIDAD');
    }

    public function sumReturnedQtyByDetailId(int|string $detalleId, int|bool|null $despachoParcial): int|float
    {
        $query = GpDevolucionesDetalle::join('gp_devoluciones', 'gp_devoluciones.id', 'gp_devoluciones_detalles.devolucion_id')
            ->where('gp_devoluciones_detalles.detalle_id', $detalleId);

        if (!is_null($despachoParcial)) {
            $query->where('gp_devoluciones_detalles.despachado_parcial', $despachoParcial);
        }
        return $query->sum('gp_devoluciones_detalles.devuelto');

    }

    public function sumReturnedQtyByDetailIdDontDispatch(int|string $detalleId): int|float
    {
        return GpDevolucionesDetalle::join('gp_devoluciones', 'gp_devoluciones.id', 'gp_devoluciones_detalles.devolucion_id')
            ->where(function ($q) {
                $q->where('gp_devoluciones.despachado', 0);
                $q->orWhereNull('gp_devoluciones.despachado');
            })
            ->where('gp_devoluciones_detalles.detalle_id', $detalleId)
            ->sum('gp_devoluciones_detalles.devuelto');
    }


    public function getReturnsHistoryByOrderId(int|string|null $pedidoId): bool
    {
        return GpDevoluciones::where('pedido_id', $pedidoId)
            ->whereNotIn('estado', ['no_procesado', 'rechazada'])
            ->exists();
    }



    

    public function getReturnByIdToArray(int|string $devolucionId): array
    {
        return GpDevoluciones::where('id', $devolucionId)->firstOrFail()->toArray();
    }

    public function getOrderByIdWithUsers(int|string $pedidoId): array
    {
        return GpPedido::join('gp_usuarios', 'gp_usuarios.USERID', 'gp_pedidos.USERID')
            ->where('gp_pedidos.id', $pedidoId)
            ->select('gp_pedidos.*', 'gp_usuarios.USERNAME')
            ->firstOrFail()->toArray();
    }



    public function getByPtoVtaIdReturns(string $puntoVentaId): GpPuntoVenta
    {
        return GpPuntoVenta::where('PTO_VTA_ID', $puntoVentaId)->firstOrFail();
    }

    public function getClientsByClientIdToArray(int|string|null $clienteId): array
    {
        return GpCliente::where('ID', $clienteId)->firstOrFail()->toArray();
    }

    public function getSucursalByInvoiceToArray(int|string|null $invoiceId): array
    {
        return GpClienteSucursal::where('id', $invoiceId)->firstOrFail()->toarray();
    }

    public function getDetailDevolucionById(int|string $devolucionId): array
    {
        return GpDevolucionesDetalle::where('devolucion_id', $devolucionId)
            // ->where('seleccionado', '>', 0)  //comente la condicional porque tambien se considera para el volver a facturar
            ->get()
            ->filter(function ($item) {
                // ✅ Verificación de datos correctos y no nulos
                return is_array($item->toArray()) && isset($item['ART_CODIGO']);
            })
            ->map(function ($item) {
                // 🚀 Convertimos cada item a array para asegurar tipado
                return $item->toArray();
            })
            ->toArray();
    }


    public function getDispatchOrder(int $orderId): bool
    {
        return GpOrdenDespacho::where('pedido_id', $orderId)->where('estado', '!=', 'pendiente')->exists();
    }


    public function shouldMarkAsDispatched(int $devId): bool
    {
        return GpDevolucionesDetalle::where('devolucion_id', $devId)
            ->where('despachado_parcial', 1)
            ->exists();
    }



}
