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
use Illuminate\Support\Facades\DB;

class DevolucionRepository implements DevolucionRepositoryInterface
{

    public function isInvoiceAlreadyVoided($pedido, $devolucion): bool
    {
        return GpFacturaAnulada::where('factura', $pedido->factura)->where('devolucion_id', $devolucion->id)
            ->where('pedido_id', $pedido->id)->where('estado', InvoiceAnulationStatus::ENVIADA)->exists();
    }

    public function isOrderNotDispatchedOrProcessing($pedido)
    {
        return GpPedido::where(function ($q) {
            $q->where('despachado', 0)->orWhereNull('despachado');
        })->where(function ($q) {
            $q->where('despacho_en_proceso', 0)->orWhereNull('despacho_en_proceso');
        })
            ->where('id', $pedido['id'])->exists();
    }

    public function hasOtherActiveReturns($pedido, $devolucion): bool
    {
        return GpDevoluciones::where('pedido_id', $pedido['id'])->where('id', '!=', $devolucion['id'])
            ->whereNotIn('estado', [DevolutionStatus::RECHAZADO, DevolutionStatus::NO_PROCESADO])->exists();
    }

    public function validateItemLDetailInvoice($devolucion): bool
    {
        return GpDevolucionesDetalle::where('devolucion_id', $devolucion['id'])->where('ART_TIPO_COMPRA', 'L')->exists();
    }


    public function getOrderbyId($pedidoId)
    {

        return GpPedido::find($pedidoId);
    }

    public function getDetailDevolucion($devolucion_id, $despachadoParcial = null)
    {
        $query = GpDevolucionesDetalle::where('devolucion_id', $devolucion_id);
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


    public function hasPartiallyDispatchedItems($devolucion): bool
    {
        return GpDevolucionesDetalle::where('devolucion_id', $devolucion['id'])
            ->where('despachado_parcial', 1)
            ->exists();
    }


    public function getReturnsAnulated($pedido_id): bool
    {
        return GpDevoluciones::where('pedido_id', $pedido_id)->where('estado', DevolutionStatus::ANULADA)->exists();
    }


    // funciones para el detallepedidodevolucion - getDetailOrderReturnById
    public function getReturnsInProcess($pedido_id): bool
    {
        return GpDevoluciones::where('pedido_id', $pedido_id)
            ->whereNotIn('estado', [DevolutionStatus::PROCESADO, DevolutionStatus::RECHAZADO, DevolutionStatus::NO_PROCESADO])
            ->exists();
    }

    public function getMotivosDevolucion()
    {
        return GpMotivosDevolucion::get();
    }

    public function getClientsByClientId($cliente_id)
    {
        return GpCliente::where('id', $cliente_id)->firstOrFail();
        ;
    }
    public function getClientsByClientRuc($ruc)
    {
        return GpCliente::where('CLI_RUC', $ruc)->first();
    }

    public function getClientsSucursByClientRuc($ruc)
    {
        return GpClienteSucursal::join('gp_vendedors', 'gp_vendedors.VEND_ID', 'gp_cliente_sucursals.CLI_SUC_VENDEDOR')
            ->where('CLI_SUC_RUC', $ruc)
            ->select('gp_cliente_sucursals.*', 'gp_vendedors.VEND_NOMBRES', 'gp_vendedors.VEND_APELLIDOS')
            ->orderby('CLI_SUC_ID_DIRECCION', 'DESC')
            ->get();
    }

    public function getSumGuidesRemisionByOrderDetailId($detalle_id)
    {
        return DB::table('gp_guia_remision_detalles')
            ->join('gp_guia_remisions', 'gp_guia_remisions.id', '=', 'gp_guia_remision_detalles.guia_remision_id')
            ->where('gp_guia_remision_detalles.detalle_id', $detalle_id)
            ->where('gp_guia_remisions.estado', 'enviada')
            ->where('gp_guia_remisions.tipo', 'despacho')
            ->sum('gp_guia_remision_detalles.GUI_REM_CANTIDAD');
    }

    public function sumReturnedQtyByDetailId($detalle_id, $despacho_parcial)
    {
        $query = GpDevolucionesDetalle::join('gp_devoluciones', 'gp_devoluciones.id', 'gp_devoluciones_detalles.devolucion_id')
            ->where('gp_devoluciones_detalles.detalle_id', $detalle_id);

        if (!is_null($despacho_parcial)) {
            $query->where('gp_devoluciones_detalles.despachado_parcial', $despacho_parcial);
        }
        return $query->sum('gp_devoluciones_detalles.devuelto');

    }

    public function sumReturnedQtyByDetailIdDontDispatch($detalle_id)
    {
        return GpDevolucionesDetalle::join('gp_devoluciones', 'gp_devoluciones.id', 'gp_devoluciones_detalles.devolucion_id')
            ->where(function ($q) {
                $q->where('gp_devoluciones.despachado', 0);
                $q->orWhereNull('gp_devoluciones.despachado');
            })
            ->where('gp_devoluciones_detalles.detalle_id', $detalle_id)
            ->sum('gp_devoluciones_detalles.devuelto');
    }


    public function getReturnsHistoryByOrderId($pedido_id)
    {
        return GpDevoluciones::where('pedido_id', $pedido_id)
            ->whereNotIn('estado', ['no_procesado', 'rechazada'])
            ->exists();
    }



    /** --funciones para imprimir la devolucion
     * Retrieve a return record by its ID.
     *
     * @param int $devolucion_id The ID of the return to retrieve.
     *
     */

    public function getReturnByIdToArray($devolucion_id)
    {
        return GpDevoluciones::where('id', $devolucion_id)->first()->toArray();
    }

    public function getOrderByIdWithUsers($pedido_id)
    {
        return GpPedido::join('gp_usuarios', 'gp_usuarios.USERID', 'gp_pedidos.USERID')
            ->where('gp_pedidos.id', $pedido_id)
            ->select('gp_pedidos.*', 'gp_usuarios.USERNAME')
            ->first()->toArray();
    }

    public function getDetailDevolucionByIdAnyConditionalDispatchParcial($devolucion_id)
    {
        return GpDevolucionesDetalle::where('devolucion_id', $devolucion_id)
            ->where('seleccionado', '>', 0)
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

    public function getByPtoVtaIdReturns($punto_venta_id)
    {
        return GpPuntoVenta::where('PTO_VTA_ID', $punto_venta_id)->first();
    }
    public function getClientsByClientIdToArray($cliente_id)
    {
        return GpCliente::where('ID', $cliente_id)->first();
    }

    public function getSucursalByInvoiceToArray($invoice_id)
    {
        return GpClienteSucursal::where('id', $invoice_id)->first()->toarray();
    }

    public function getDetailDevolucionById($devolucion_id)
    {
        return GpDevolucionesDetalle::where('devolucion_id', $devolucion_id)
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


    public function getDispatchOrder($orderId)
    {
        return GpOrdenDespacho::where('pedido_id', $orderId)->where('estado', '!=', 'pendiente')->exists();
    }


    public function shouldMarkAsDispatched($devId): bool
    {
        return GpDevolucionesDetalle::where('devolucion_id', $devId)
            ->where('despachado_parcial', 1)
            ->exists();
    }



}
