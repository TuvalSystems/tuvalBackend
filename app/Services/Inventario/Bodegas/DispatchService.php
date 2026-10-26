<?php

namespace App\Services\Inventario\Bodegas;

use App\Enums\Ventas\OrderStatus;
use App\Models\Inventario\GuiasRemision\GpGuiaRemision;
use App\Models\Devoluciones\GpDevolucionesDetalle;
use App\Models\Integraciones\Sincronizacion\GpCronLog;
use App\Models\Inventario\Despachos\GpOrdenDespacho;
use App\Models\Ventas\Pedidos\GpPedido;
use App\Models\Ventas\Pedidos\GpPedidoDetalle;
use App\Models\Ventas\Pedidos\GpPedidoDetalleStock;
use App\Models\Admin\PuntosVenta\GpPuntoVenta;
use App\Models\Admin\PuntosVenta\GpRelacionPuntoVentaBodega;
use App\Models\Ventas\Reservas\GpGuiaRemisionReserva;
use App\Services\Api\ApiGpService;
use App\Services\Funciones\Contracts\InvoiceServiceInterface;
use App\Services\Inventario\Bodegas\Contracts\DispatchServiceInterface;
use App\Services\Inventario\Stock\Contracts\StockWarehouseServiceInterface;
use Carbon\Carbon;
use DomainException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DispatchService implements DispatchServiceInterface
{

    public function __construct(
        protected readonly InvoiceServiceInterface $invoiceService,
        protected readonly StockWarehouseServiceInterface $stockService,
        protected readonly ApiGpService $api,

    ) {}

    public function guiasDespachadas($ptoVtaId, $perpage, $search, $sortBy = null, $sortOrder = 'desc', $fecha_desde, $fecha_hasta, $clienteRuc)
    {
        $bodega = $this->getNamePtoVta($ptoVtaId);
        $query = GpGuiaRemision::select([
            'gp_guia_remisions.id',
            'gp_guia_remisions.GUI_REM_GUIA_REMISION',
            'gp_guia_remisions.pedido',
            'gp_pedidos.despacho as pedido_despacho',
            'gp_guia_remisions.GUI_REM_NUME_DOCU_SUSTENTO',
            'gp_orden_despachos.orden',
            'gp_orden_despachos.id AS orden_id',
            'gp_guia_remisions.GUI_REM_RAZON_SOCIAL_DESTINATARIO',
            'gp_guia_remisions.GUI_REM_DIRECCION_DESTINATARIO',
            'gp_guia_remisions.GUI_REM_FECHA_INICIO',
            'gp_guia_remisions.GUI_REM_RAZON_SOCIAL_TRANSPORTISTA',
            'gp_guia_remisions.GUI_REM_PLACA',
            'gp_guia_remisions.created_at',
            'gp_guia_remisions.GUI_REM_IDENTIFICACION_DESTINATARIO',
            'gp_usuarios.USERID'
        ])
            ->join('gp_pedidos', 'gp_pedidos.pedido', 'gp_guia_remisions.pedido')
            ->join('gp_orden_despachos', 'gp_orden_despachos.pedido_id', 'gp_pedidos.id')
            ->join('gp_usuarios', 'gp_usuarios.id', 'gp_guia_remisions.user_id')
            ->where('gp_guia_remisions.bodega_origen', $bodega)
            ->where('gp_guia_remisions.estado', '!=', 'pendiente')
            ->where('gp_guia_remisions.tipo', 'despacho');


        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('gp_guia_remisions.pedido', 'like', '%' . $search . '%')
                    ->orWhere('gp_guia_remisions.GUI_REM_GUIA_REMISION', 'like', '%' . $search . '%')
                    ->orWhere('gp_orden_despachos.orden', 'like', '%' . $search . '%')
                    ->orWhere('gp_guia_remisions.GUI_REM_RAZON_SOCIAL_DESTINATARIO', 'like', '%' . $search . '%')
                    ->orWhere('gp_guia_remisions.GUI_REM_RAZON_SOCIAL_TRANSPORTISTA', 'like', '%' . $search . '%')
                    ->orWhere('gp_guia_remisions.GUI_REM_PLACA', 'like', '%' . $search . '%');
            });
        }
        if ($fecha_desde && $fecha_hasta) {
            $query->where('gp_guia_remisions.created_at', '>=', $fecha_desde)
                ->where('gp_guia_remisions.created_at', '<=', $fecha_hasta);
        }

        if ($clienteRuc) {
            $query->where('gp_guia_remisions.GUI_REM_IDENTIFICACION_DESTINATARIO', $clienteRuc);
        }

        if ($sortBy) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderByDesc('gp_guia_remisions.GUI_REM_FECHA_INICIO');
        }
        return $query->paginate($perpage);
    }

    public function transferenciasEnviadas($ptoVtaId, $perpage, $search, $sortBy = null, $sortOrder = 'desc', $fecha_desde, $fecha_hasta, $clienteRuc, $ptoVta)
    {
        $bodega = $this->getNamePtoVta($ptoVtaId);
        $ptovtaSearch = $this->getNamePtoVta($ptoVta);
        $query = GpGuiaRemision::fromSub(function ($sub) use ($bodega) {
            $sub->select([
                'gp_guia_remisions.id',
                'gp_guia_remisions.pedido',
                'gp_guia_remisions.GUI_REM_GUIA_REMISION',
                'gp_guia_remisions.bodega_origen',
                'gp_guia_remisions.bodega_destino',
                'gp_guia_remisions.GUI_REM_RAZON_SOCIAL_DESTINATARIO',
                'gp_guia_remisions.GUI_REM_DIRECCION_DESTINATARIO',
                'gp_guia_remisions.GUI_REM_FECHA_INICIO',
                'gp_guia_remisions.GUI_REM_RAZON_SOCIAL_TRANSPORTISTA',
                'gp_guia_remisions.GUI_REM_PLACA',
                'gp_guia_remisions.created_at',
                'gp_guia_remisions.GUI_REM_IDENTIFICACION_DESTINATARIO',
                'gp_usuarios.USERID'
            ])
                ->from('gp_guia_remisions')
                ->leftJoin('gp_usuarios', 'gp_usuarios.id', 'gp_guia_remisions.user_id')
                ->where('gp_guia_remisions.bodega_origen', $bodega)
                ->where('gp_guia_remisions.estado', '!=', 'pendiente')
                ->Where('gp_guia_remisions.tipo', 'transferencia');
        }, 'subquery');

        if ($fecha_desde && $fecha_hasta) {
            $query->where('subquery.created_at', '>=', $fecha_desde)
                ->where('subquery.created_at', '<=', $fecha_hasta);
        }

        if ($clienteRuc) {
            $query->where('subquery.GUI_REM_IDENTIFICACION_DESTINATARIO', $clienteRuc);
        }
        if ($ptovtaSearch) {
            $query->where('subquery.bodega_destino', $ptovtaSearch);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('subquery.pedido', 'like', '%' . $search . '%')
                    ->orWhere('subquery.GUI_REM_GUIA_REMISION', 'like', '%' . $search . '%')
                    ->orWhere('subquery.GUI_REM_RAZON_SOCIAL_DESTINATARIO', 'like', '%' . $search . '%')
                    ->orWhere('subquery.GUI_REM_RAZON_SOCIAL_TRANSPORTISTA', 'like', '%' . $search . '%')
                    ->orWhere('subquery.GUI_REM_PLACA', 'like', '%' . $search . '%');
            });
        }
        if ($sortBy) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderByDesc('subquery.GUI_REM_FECHA_INICIO');
        }

        return $query->paginate($perpage);
    }

    public function transferenciasRecibidas($ptoVtaId, $perpage, $search, $sortBy = null, $sortOrder = 'desc', $fecha_desde, $fecha_hasta, $clienteRuc, $ptoVta)
    {
        $bodega = $this->getNamePtoVta($ptoVtaId);
        $ptovtaSearch = $this->getNamePtoVta($ptoVta);
        $query = GpGuiaRemision::select([
            'gp_guia_remisions.id',
            'gp_guia_remisions.GUI_REM_GUIA_REMISION',
            'gp_guia_remisions.bodega_origen',
            'gp_guia_remisions.bodega_destino',
            'gp_guia_remisions.GUI_REM_RAZON_SOCIAL_DESTINATARIO',
            'gp_guia_remisions.GUI_REM_DIRECCION_DESTINATARIO',
            'gp_guia_remisions.GUI_REM_FECHA_INICIO',
            'gp_guia_remisions.GUI_REM_RAZON_SOCIAL_TRANSPORTISTA',
            'gp_guia_remisions.GUI_REM_PLACA',
            'gp_guia_remisions.created_at',
            'gp_guia_remisions.GUI_REM_IDENTIFICACION_DESTINATARIO',
            'gp_usuarios.USERID'
        ])
            ->join('gp_usuarios', 'gp_usuarios.id', 'gp_guia_remisions.user_id')
            ->where('gp_guia_remisions.bodega_destino', $bodega)
            ->where('gp_guia_remisions.estado', '!=', 'pendiente')
            ->Where('gp_guia_remisions.tipo', 'transferencia');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('gp_guia_remisions.GUI_REM_GUIA_REMISION', 'like', '%' . $search . '%')
                    ->orWhere('gp_guia_remisions.GUI_REM_RAZON_SOCIAL_DESTINATARIO', 'like', '%' . $search . '%')
                    ->orWhere('gp_guia_remisions.GUI_REM_RAZON_SOCIAL_TRANSPORTISTA', 'like', '%' . $search . '%')
                    ->orWhere('gp_guia_remisions.GUI_REM_PLACA', 'like', '%' . $search . '%');
            });
        }

        if ($fecha_desde && $fecha_hasta) {
            $query->where('gp_guia_remisions.created_at', '>=', $fecha_desde)
                ->where('gp_guia_remisions.created_at', '<=', $fecha_hasta);
        }

        if ($clienteRuc) {
            $query->where('gp_guia_remisions.GUI_REM_IDENTIFICACION_DESTINATARIO', $clienteRuc);
        }
        if ($ptovtaSearch) {
            $query->where('gp_guia_remisions.bodega_origen', $ptovtaSearch);
        }

        if ($sortBy) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderByDesc('gp_guia_remisions.GUI_REM_FECHA_INICIO');
        }
        return $query->paginate($perpage);
    }

    public function reposicionesEnviadas($ptoVtaId, $perpage, $search, $sortBy = null, $sortOrder = 'desc', $fecha_desde, $fecha_hasta, $clienteRuc, $ptoVta)
    {
        $bodega = $this->getNamePtoVta($ptoVtaId);
        $ptovtaSearch = $this->getNamePtoVta($ptoVta);
        $query = GpGuiaRemision::select([
            'gp_guia_remisions.id',
            'gp_guia_remisions.GUI_REM_GUIA_REMISION',
            'gp_guia_remisions.bodega_destino',
            'gp_guia_remisions.pedido',
            'gp_guia_remisions.GUI_REM_RAZON_SOCIAL_DESTINATARIO',
            'gp_guia_remisions.GUI_REM_DIRECCION_DESTINATARIO',
            'gp_guia_remisions.GUI_REM_FECHA_INICIO',
            'gp_guia_remisions.GUI_REM_RAZON_SOCIAL_TRANSPORTISTA',
            'gp_guia_remisions.GUI_REM_PLACA',
            'gp_guia_remisions.created_at',
            'gp_guia_remisions.GUI_REM_IDENTIFICACION_DESTINATARIO',
            'gp_usuarios.USERID'
        ])
            ->join('gp_usuarios', 'gp_usuarios.id', 'gp_guia_remisions.user_id')
            ->where('gp_guia_remisions.bodega_origen', $bodega)
            ->where('gp_guia_remisions.estado', '!=', 'pendiente')
            ->Where('gp_guia_remisions.tipo', 'reposicion');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('gp_guia_remisions.pedido', 'like', '%' . $search . '%')
                    ->orWhere('gp_guia_remisions.GUI_REM_GUIA_REMISION', 'like', '%' . $search . '%')
                    ->orWhere('gp_guia_remisions.GUI_REM_RAZON_SOCIAL_DESTINATARIO', 'like', '%' . $search . '%')
                    ->orWhere('gp_guia_remisions.GUI_REM_RAZON_SOCIAL_TRANSPORTISTA', 'like', '%' . $search . '%')
                    ->orWhere('gp_guia_remisions.GUI_REM_PLACA', 'like', '%' . $search . '%');
            });
        }

        if ($fecha_desde && $fecha_hasta) {
            $query->where('gp_guia_remisions.created_at', '>=', $fecha_desde)
                ->where('gp_guia_remisions.created_at', '<=', $fecha_hasta);
        }

        if ($clienteRuc) {
            $query->where('gp_guia_remisions.GUI_REM_IDENTIFICACION_DESTINATARIO', $clienteRuc);
        }

        if ($ptovtaSearch) {
            $query->where('gp_guia_remisions.bodega_destino', $ptovtaSearch);
        }


        if ($sortBy) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderByDesc('gp_guia_remisions.GUI_REM_FECHA_INICIO');
        }
        return $query->paginate($perpage);
    }

    public function reposicionesRecibidas($ptoVtaId, $perpage, $search, $sortBy = null, $sortOrder = 'desc', $fecha_desde, $fecha_hasta, $clienteRuc, $ptoVta)
    {
        $bodega = $this->getNamePtoVta($ptoVtaId);
        $ptovtaSearch = $this->getNamePtoVta($ptoVta);
        $query = GpGuiaRemision::select([
            'gp_guia_remisions.id',
            'gp_guia_remisions.GUI_REM_GUIA_REMISION',
            'gp_guia_remisions.bodega_origen',
            'gp_guia_remisions.pedido',
            'gp_guia_remisions.GUI_REM_RAZON_SOCIAL_DESTINATARIO',
            'gp_guia_remisions.GUI_REM_DIRECCION_DESTINATARIO',
            'gp_guia_remisions.GUI_REM_FECHA_INICIO',
            'gp_guia_remisions.GUI_REM_RAZON_SOCIAL_TRANSPORTISTA',
            'gp_guia_remisions.GUI_REM_PLACA',
            'gp_guia_remisions.created_at',
            'gp_guia_remisions.GUI_REM_IDENTIFICACION_DESTINATARIO',
            'gp_usuarios.USERID'
        ])
            ->join('gp_usuarios', 'gp_usuarios.id', 'gp_guia_remisions.user_id')
            ->where('gp_guia_remisions.bodega_destino', $bodega)
            ->where('gp_guia_remisions.estado', '!=', 'pendiente')
            ->Where('gp_guia_remisions.tipo', '=', 'reposicion');
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('gp_guia_remisions.pedido', 'like', '%' . $search . '%')
                    ->orWhere('gp_guia_remisions.GUI_REM_GUIA_REMISION', 'like', '%' . $search . '%')
                    ->orWhere('gp_guia_remisions.GUI_REM_RAZON_SOCIAL_DESTINATARIO', 'like', '%' . $search . '%')
                    ->orWhere('gp_guia_remisions.GUI_REM_RAZON_SOCIAL_TRANSPORTISTA', 'like', '%' . $search . '%')
                    ->orWhere('gp_guia_remisions.GUI_REM_PLACA', 'like', '%' . $search . '%');
            });
        }
        if ($fecha_desde && $fecha_hasta) {
            $query->where('gp_guia_remisions.created_at', '>=', $fecha_desde)
                ->where('gp_guia_remisions.created_at', '<=', $fecha_hasta);
        }

        if ($clienteRuc) {
            $query->where('gp_guia_remisions.GUI_REM_IDENTIFICACION_DESTINATARIO', $clienteRuc);
        }
        if ($ptovtaSearch) {
            $query->where('gp_guia_remisions.bodega_origen', $ptovtaSearch);
        }
        if ($sortBy) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderByDesc('gp_guia_remisions.GUI_REM_FECHA_INICIO');
        }
        return $query->paginate($perpage);
    }


    public function getNamePtoVta($ptoVtaId)
    {
        return GpRelacionPuntoVentaBodega::where('PTO_VTA_BOD_PUNTO_VENTA', $ptoVtaId)->value('PTO_VTA_BOD_BODEGA');
    }

    public function checkTraslado($order)
    {
        $this->invoiceService->checkTrasladado($order);
    }

    public function getAllPtoVta()
    {
        return Cache::remember('pto_vta_all', 300, function () {
            return GpRelacionPuntoVentaBodega::query()
                ->join('gp_punto_ventas as pv', 'gp_relacion_punto_venta_bodegas.PTO_VTA_BOD_PUNTO_VENTA', '=', 'pv.PTO_VTA_ID')
                ->orderBy('PTO_VTA_BOD_BODEGA')               // ajusta campo
                ->get(['gp_relacion_punto_venta_bodegas.id', 'PTO_VTA_BOD_BODEGA', 'PTO_VTA_BOD_PUNTO_VENTA', 'pv.id as punto_venta_id', 'pv.PTO_VTA_DESCRIPCION']); // solo lo necesario
        });
    }


    public function getAllPtoVtaReserved()
    {

        return DB::table('gp_relacion_punto_venta_bodegas as rpv')
            ->join('gp_punto_ventas as pv', 'rpv.PTO_VTA_BOD_PUNTO_VENTA', '=', 'pv.PTO_VTA_ID')
            ->select(
                [
                    'pv.PTO_VTA_ID as PTO_VTA_ID',
                    'rpv.PTO_VTA_BOD_BODEGA',
                    'rpv.PTO_VTA_BOD_PUNTO_VENTA',
                    'pv.pto_vta_max_dias_reserva',
                    'pv.pto_vta_max_dias_autorizacion_reserva'
                ]
            )
            ->orderBy('rpv.PTO_VTA_BOD_BODEGA')
            ->get();
    }


    public function fetchListSendReservations(int $perPage, ?string $search, ?string $sortBy, string $sortOrder = 'desc', ?string $ptoVtaOrigin, ?string $fecha_desde, ?string $fecha_hasta, ?string $ptoVtaDestino = null)
    {
        $bodOrigin = $this->getNamePtoVta($ptoVtaOrigin);
        $bodDestino = $ptoVtaDestino !== null ? $this->getNamePtoVta($ptoVtaDestino) : null;
        $allowedSorts = $this->allowedSortsReservations();
        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'gr.created_at';
        }
        $sortOrder = strtolower($sortOrder) === 'asc' ? 'asc' : 'desc';


        $query = GpGuiaRemisionReserva::query()
            ->from('gp_guia_remision_reservas as gr')
            ->leftJoin('gp_usuarios as gpu', 'gpu.id', 'gr.user_id')
            ->where('gr.estado', '!=',  'pendiente')
            ->where('gr.bodega_origen', trim($bodOrigin))

            ->select([
                'gr.id',
                'gr.numero_guia',
                'gr.bodega_origen',
                'gr.bodega_destino',
                'gr.razon_social_destinatario',
                'gr.direccion_destinatario',
                'gr.identificacion_destinatario',
                'gr.fecha_salida',
                'gr.razon_social_transportista',
                'gr.direccion_partida',
                'gr.placa',
                'gr.created_at',
                'gpu.USERID'
            ]);

        if ($bodDestino) {
            $query->where('gr.bodega_destino', $bodDestino);
        }

        // Filtro por Rango de Fechas (LÓGICA AGREGADA)
        if ($fecha_desde) {
            $query->whereDate('gr.created_at', '>=', $fecha_desde);
        }
        if ($fecha_hasta) {
            $query->whereDate('gr.created_at', '<=', $fecha_hasta);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $columns = [
                    'gr.numero_guia',
                    'gr.bodega_destino',
                    'gr.razon_social_transportista',
                    'gr.placa',
                    'gpu.USERID',
                ];
                foreach ($columns as $column) {
                    $q->orWhere($column, 'like', "%{$search}%");
                }
            });
        }

        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }


    public function fecthListReceivedReservations(int $perPage, ?string $search, ?string $sortBy, string $sortOrder = 'desc', ?string $ptoVtaOrigin, ?string $fecha_desde, ?string $fecha_hasta, ?string $ptoVtaDestino = null)
    {
        $bodOrigin = $this->getNamePtoVta($ptoVtaOrigin);
        $bodDestino = $ptoVtaDestino !== null ? $this->getNamePtoVta($ptoVtaDestino) : null;
        $allowedSorts = $this->allowedSortsReservations();
        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'gr.created_at';
        }
        $sortOrder = strtolower($sortOrder) === 'asc' ? 'asc' : 'desc';


        $query = GpGuiaRemisionReserva::query()
            ->from('gp_guia_remision_reservas as gr')
            ->join('gp_usuarios as gpu', 'gpu.id', 'gr.user_id')
            ->where('gr.estado', '!=',  'pendiente')
            ->where('gr.bodega_destino', $bodOrigin)

            ->select([
                'gr.id',
                'gr.numero_guia',
                'gr.bodega_origen',
                'gr.bodega_destino',
                'gr.razon_social_destinatario',
                'gr.direccion_destinatario',
                'gr.identificacion_destinatario',
                'gr.fecha_salida',
                'gr.razon_social_transportista',
                'gr.direccion_partida',
                'gr.placa',
                'gr.created_at',
                'gpu.USERID'
            ]);

        if ($bodDestino) {
            $query->where('gr.bodega_origen', $bodDestino);
        }

        // Filtro por Rango de Fechas (LÓGICA AGREGADA)
        if ($fecha_desde) {
            $query->whereDate('gr.created_at', '>=', $fecha_desde);
        }
        if ($fecha_hasta) {
            $query->whereDate('gr.created_at', '<=', $fecha_hasta);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $columns = [
                    'gr.numero_guia',
                    'gr.bodega_origen',
                    'gr.razon_social_transportista',
                    'gr.placa',
                    'gpu.USERID',
                ];
                foreach ($columns as $column) {
                    $q->orWhere($column, 'like', "%{$search}%");
                }
            });
        }

        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }


    public function allowedSortsReservations()
    {
        return [
            'gr.id',
            'gr.numero_guia',
            'gr.bodega_origen',
            'gr.bodega_destino',
            'gr.razon_social_transportista',
            'gr.placa',
            'gr.fecha_salida',
            'gpu.USERID',
            'gr.created_at'
        ];
    }



    /** funcion migrada de checkeliminar despacho */

    public function cancelDispatchIfEmptyPicking(int $orderId)
    {
        $pedido = GpPedido::find($orderId);
        if (!$pedido) {
            return false;
        }
        $orderDispatch = GpOrdenDespacho::where('pedido_id', $orderId)->first();
        if (!$orderDispatch || !in_array($orderDispatch->estado, ['pendiente', 'liberada'], true)) {
            return;
        }

        // Una reserva o la falta de stock no eliminan la obligación de despachar.
        $details = GpPedidoDetalle::where('pedido_id', $orderId)
            ->where(function ($query) {
                $query->where('ART_TIPO_COMPRA', '!=', 'L')->orWhereNull('ART_TIPO_COMPRA');
            })
            ->get();

        if ($details->isEmpty()) {
            return;
        }

        $returns = GpDevolucionesDetalle::join('gp_devoluciones', 'gp_devoluciones.id', '=', 'gp_devoluciones_detalles.devolucion_id')
            ->whereIn('gp_devoluciones_detalles.detalle_id', $details->pluck('id'))
            ->where('gp_devoluciones_detalles.despachado_parcial', 0)
            ->whereIn('gp_devoluciones.estado', ['procesado', 'anulada'])
            ->selectRaw('gp_devoluciones_detalles.detalle_id, SUM(gp_devoluciones_detalles.devuelto) as total_devuelto')
            ->groupBy('gp_devoluciones_detalles.detalle_id')
            ->pluck('total_devuelto', 'detalle_id');

        $hasPending = $details->contains(function ($detail) use ($returns) {
            return (float) $detail->ART_CANTIDAD
                - (float) $detail->trasladado
                - (float) $returns->get($detail->id, 0) > 0;
        });

        // No cancelar por un despacho completo: debe existir una devolución efectiva.
        if (!$hasPending && $returns->sum() > 0) {
            $orderDispatch->estado = 'cancelada';
            $orderDispatch->fecha_hora_liberacion = null;
            $orderDispatch->save();
        }
    }

    public function getPickingItems($order)
    {
        $warehouse = $order->BOD_BODEGA;
        $details = GpPedidoDetalle::where('pedido_id', $order->id)->get();
        $devoluciones = $this->getSumDetailDevolutions($details->pluck('id')->toArray());
        $report = $details->map(function ($det) use ($warehouse, $devoluciones) {
            if ($det->ART_TIPO_COMPRA == 'L') {
                $det->ART_DESCRIPCION_ORIGINAL = $det->ART_DESCRIPCION;
            }

            $reservado = $det->reservado ?? 0;
            $trasladado = $det->trasladado ?? 0;

            $devsDelItem = $devoluciones->get($det->id, collect());
            // $devueltoDesp = $devsDelItem->where('despachado_parcial', 1)->sum('total_devuelto');
            $devueltoNoDesp = $devsDelItem->where('despachado_parcial', 0)->sum('total_devuelto');

            $detTNoDevuelto = ($det->ART_CANTIDAD - $trasladado) - $reservado - $devueltoNoDesp;
            $det->pendiente = number_format($detTNoDevuelto, 2, '.', '');
            if ($detTNoDevuelto <= 0) {
                return null;
            }
            $stockData = $this->getStockCompartmentByDetail($det->ART_CODIGO, $warehouse);
            $stockDisponible = data_get($stockData, 'STOCK_DISPONIBLE', 0);
            $restValor = min($det->ART_CANTIDAD, $stockDisponible);
            if ($restValor <= 0) {
                return null;
            }
            $det->compartimento = data_get($stockData, 'COMPARTIMIENTO', null);
            $det->mejorStock = $stockDisponible;
            return $det;
        })
            ->filter()
            ->values();

        return $report;
    }


    public function getSumDetailDevolutions($detailsIds)
    {
        return GpDevolucionesDetalle::join('gp_devoluciones', 'gp_devoluciones.id', '=', 'gp_devoluciones_detalles.devolucion_id')
            ->whereIn('gp_devoluciones_detalles.detalle_id', $detailsIds)
            ->selectRaw('
            gp_devoluciones_detalles.detalle_id,
            gp_devoluciones_detalles.despachado_parcial,
            SUM(gp_devoluciones_detalles.devuelto) as total_devuelto
        ')
            ->groupBy('gp_devoluciones_detalles.detalle_id', 'gp_devoluciones_detalles.despachado_parcial')
            ->get()
            ->groupBy('detalle_id');
    }


    public function getStockCompartmentByDetail($articulo, $bodega)
    {
        $params = ['articulo' => $articulo, 'bodega' => $bodega, 'compartimento' => ''];
        return $this->stockService->fetchStockWarehouseCompartiment($params);
    }



    /** lista de consulta de ordenes de despacho  */

    public function fetchListDispatchOrdersConsult(array $filters): LengthAwarePaginator
    {

        $pointOfSaleId  = data_get($filters, 'pvCodigo');
        $sortField      = data_get($filters, 'sortField', 'fecha_hora_liberacion');
        $sortOrder      = data_get($filters, 'sortOrder', 'desc');
        $search         =  trim(data_get($filters, 'search', ''));
        $perPage        = data_get($filters, 'perpage', 10);
        $ruc            = trim(data_get($filters, 'ruc', ''));
        $status         = trim(data_get($filters, 'estado', 'liberada'));
        $sortOrder      = strtolower($sortOrder) === 'asc' ? 'asc' : 'desc';
        $startDate      = data_get($filters, 'fechaInicio');
        $endDate        = data_get($filters, 'fechaFin');

        $sortColumns = [
            'ordenCodigo'           => 'god.orden',
            'ruc'                   => 'gpd.CLI_RUC',
            'estado'                => 'god.estado',
            'razon_social'          => 'gpd.CLI_RAZON_SOCIAL',
            'pedido'                => 'gpd.pedido',
            'factura'               => 'gpd.factura',
            'bodega'                => 'gpd.BOD_BODEGA',
            'fecha_entrega'         => 'gpd.fecha_entrega',
            'fecha_creacion_pedido' => 'gpd.created_at',
            'fecha_creacion_orden'  => 'god.created_at',
            'fecha_hora_liberacion' => 'god.fecha_hora_liberacion',
        ];


        $sortBy = $sortColumns[$sortField] ?? 'god.fecha_hora_liberacion';


        $query = GpOrdenDespacho::query()
            ->from('gp_orden_despachos as god')
            ->select([
                'god.id',
                'god.orden as ordenCodigo',
                'god.estado',
                'god.created_at as fecha_creacion_orden',
                'god.fecha_hora_liberacion',

                'gpd.id as pedido_id',
                'gpd.pedido',
                'gpd.factura',
                'gpd.CLI_RUC',
                'gpd.CLI_RAZON_SOCIAL as razon_social',
                'gpd.created_at as fecha_creacion_pedido',
                'gpd.BOD_BODEGA as bodega',
                'gpd.fecha_entrega',
                'gpd.despacho',
            ])
            ->join('gp_pedidos as gpd', 'gpd.id', '=', 'god.pedido_id')
            ->where('gpd.PTO_VTA_ID', $pointOfSaleId);

        if ($status !== '') {
            $query->where('god.estado', $status);
        }

        if ($search !== '') {
            $query->where(function ($query) use ($search) {
                $query
                    ->where('gpd.factura', 'like', "%{$search}%")
                    ->orWhere('god.orden', 'like', "%{$search}%")
                    ->orWhere('gpd.CLI_RUC', 'like', "%{$search}%")
                    ->orWhere(
                        'gpd.CLI_RAZON_SOCIAL',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere('gpd.pedido', 'like', "%{$search}%")
                    ->orWhere('gpd.despacho', 'like', "%{$search}%");
            });
        }


        if ($ruc !== '') {
            $query->where('gpd.CLI_RUC', $ruc);
        }

        if (!empty($startDate) && !empty($endDate)) {
            $query->whereBetween('gpd.created_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay(),
            ]);
        } elseif (!empty($startDate)) {
            $query->where(
                'gpd.created_at',
                '>=',
                Carbon::parse($startDate)->startOfDay()
            );
        } elseif (!empty($endDate)) {
            $query->where(
                'gpd.created_at',
                '<=',
                Carbon::parse($endDate)->endOfDay()
            );
        }


        return $query
            ->orderBy($sortBy, $sortOrder)
            ->orderByDesc('god.id')
            ->paginate($perPage);
    }


    public function printOrderDispatchPdf(int $id): array
    {

        $ordDispatch = GpOrdenDespacho::with([
            'pedido.detallepedido',
            'pedido.gpcotizacion',
            'pedido.usuario',
            'pedido.direccionFacturacion',
            'pedido.gpcliente'
        ])->findOrFail($id);

        $order = $ordDispatch->pedido;

        if (!$order) {
            throw new DomainException(
                'La orden de despacho no tiene un pedido relacionado.'
            );
        }

        if ($this->isOrderBlocked($order)) {
            throw new DomainException(
                'El pedido tiene aprobaciones pendientes o se encuentra eliminado. No se puede imprimir la orden de despacho.'
            );
        }
        $pointOfSale = GpPuntoVenta::where('PTO_VTA_ID', $order->PTO_VTA_ID)->first();
        $direction   = trim(($pointOfSale->PTO_VTA_DIRECCION_1 ?? '') . ' ' . ($pointOfSale->PTO_VTA_DIRECCION_2 ?? ''));
        $warehouse   = (string)$order->BOD_BODEGA;
        $ordDispatch->pedido->detallepedido->each(function ($item) use ($warehouse) {
            $item->compartimiento = $this->getCompartment($item->ART_CODIGO, $warehouse);
            $item->pesoCalculado  = (float)$item->ART_PESO * (float)$item->ART_CANTIDAD;
        });
        $kgTotal = $ordDispatch->pedido->detallepedido->sum('pesoCalculado');
        $infoPdf = [
            'despacho'      => $ordDispatch,
            'direccion'     => $direction,

            'pesoTotal'     => $kgTotal
        ];

        $html   = view('pdf.despachos.orden-despacho-pedido', $infoPdf)->render();
        $pdf    = app('dompdf.wrapper');
        $pdf->setPaper('a4', 'portrait');
        $pdf->loadHTML($html);
        $binary = $pdf->output();

        $filename = 'ORDEN-DESPACHO-'
            . Str::upper(Str::slug($ordDispatch->orden, '-'))
            . '.pdf';

        return [
            'binary'        => $binary,
            'filename'      => $filename,
            'content_type'  => 'application/pdf',
        ];
    }

    protected function isOrderBlocked(GpPedido $order): bool
    {
        $requiresCommercialApproval     = $order->requiere_aprobacion_comercial === 'yes' && $order->aprobado_comercial === 'no';
        $requiresCreditApproval         = $order->requiere_aprobacion_crediticia === 'yes' && $order->aprobado_crediticio === 'no';
        $isDelete                       = $order->estado === OrderStatus::ELIMINADO;
        return $requiresCommercialApproval || $requiresCreditApproval || $isDelete;
    }

    protected function getCompartment(string $itemCode, string $warehouseCode): ?string
    {

        $response = $this->api->getStockByWarehouseAndCompartment([
            'articulo'      => $itemCode,
            'bodega'        => $warehouseCode,
            'compartimento' => '',
        ]);
        return data_get($response, 'data.COMPARTIMIENTO', '');
    }


    public function checkCompletedDispatch()
    {
        $ordenes = GpOrdenDespacho::with([
            'pedido' => function ($q) {
                $q->where('estado', OrderStatus::FACTURADO)->with('detallepedido');
            },

        ])->where('estado', 'liberada')->get();
        foreach ($ordenes as $orden) {
            $pedido = $orden->pedido;
            if (!$pedido) {
                continue;
            }
            $listo = true;

            foreach ($pedido->detallepedido as $item) {
                if (($item->trasladado + $item->devuelto) < $item->ART_CANTIDAD) {
                    $listo = false;
                    break;
                }
            }

            if ($listo) {
                $ordenActualizada = GpOrdenDespacho::whereKey($orden->id)
                    ->where('estado', 'liberada')
                    ->update(['estado' => 'despachada']);

                if (!$ordenActualizada) {
                    Log::warning('Orden de despacho omitida al verificar despachos completos porque ya no esta liberada', [
                        'orden_id' => $orden->id,
                        'pedido_id' => $pedido->id,
                    ]);
                    continue;
                }

                $pedido->despachado = 1;
                $pedido->despacho_en_proceso = 0;
                $pedido->save();
            }
        }

        $pedidos = GpPedido::leftJoin('gp_pedido_detalles', 'gp_pedido_detalles.pedido_id', 'gp_pedidos.id')
            ->leftJoin('gp_guia_remisions', 'gp_guia_remisions.pedido', 'gp_pedidos.pedido')
            ->leftJoin('gp_orden_despachos', 'gp_orden_despachos.pedido_id', 'gp_pedidos.id')
            ->where(function ($query) {
                $query->whereNull('gp_pedidos.despachado');
                $query->orWhere('gp_pedidos.despachado', 0);
            })
            //->where('gp_pedido_detalles.ART_TIPO_COMPRA', '!=', 'SERV')
            ->where('gp_pedido_detalles.reservado', '>', 0)
            ->where('gp_guia_remisions.tipo', 'despacho')
            ->where('gp_guia_remisions.estado', 'enviada')
            ->where('gp_orden_despachos.estado', 'despachada')
            ->whereNotExists(function ($query) {
                $query->selectRaw(1)
                    ->from('gp_orden_despachos as ordenes_canceladas')
                    ->whereColumn('ordenes_canceladas.pedido_id', 'gp_pedidos.id')
                    ->where('ordenes_canceladas.estado', 'cancelada');
            })
            ->select('gp_pedidos.*')
            ->distinct()
            ->get();

        foreach ($pedidos as $pedido) {
            $detalle = GpPedidoDetalle::where('pedido_id', $pedido->id)->get();
            foreach ($detalle as $item) {
                $item->reservado = 0;
                $item->trasladado = $item->ART_CANTIDAD - $item->devuelto;
                $item->save();

                $stock = GpPedidoDetalleStock::where('detalle_id', $item->id)
                    ->where('STO_ART_BODEGA', $pedido->BOD_BODEGA)
                    ->first();

                if ($stock) {
                    if (!$stock->reservado)
                        $stock->reservado = 0;

                    $stock->reservado = 0;
                    $stock->save();
                }
            }
            $pedido->despachado = 1;
            $pedido->save();
        }

        $cronLog = new GpCronLog();
        $cronLog->metodo = 'checkdespachadoscompletos';
        $cronLog->fecha = date('Y-m-d H:i:s');
        $cronLog->save();
    }
}
