<?php

namespace App\Services\Bodegas;

use App\Models\despachos\GpGuiaRemision;
use App\Models\devoluciones\GpDevolucionesDetalle;
use App\Models\GpOrdenDespacho;
use App\Models\GpPedido;
use App\Models\GpPedidoDetalle;
use App\Models\GpRelacionPuntoVentaBodega;
use App\Models\reservas\GpGuiaRemisionReserva;
use App\Services\Bodegas\Contracts\DispatchServiceInterface;
use App\Services\Funciones\Contracts\InvoiceServiceInterface;
use App\Services\Reposiciones\Contracts\StockWarehouseServiceInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DispatchService implements DispatchServiceInterface
{

    public function __construct(
        protected readonly InvoiceServiceInterface $invoiceService,
        protected readonly StockWarehouseServiceInterface $stockService
    ) {

    }

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
            ->join('gp_usuarios as gpu', 'gpu.id', 'gr.user_id')
            ->where('gr.estado', 'enviada')
            ->where('gr.bodega_origen', $bodOrigin)

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
        if ($fecha_desde && $fecha_hasta) {
            // Asumiendo que recibes 'Y-m-d', agregamos horas para cubrir el día completo
            $query->whereBetween('gr.created_at', [
                $fecha_desde . ' 00:00:00',
                $fecha_hasta . ' 23:59:59'
            ]);
        } elseif ($fecha_desde) {
            $query->where('gr.created_at', '>=', $fecha_desde . ' 00:00:00');
        } elseif ($fecha_hasta) {
            $query->where('gr.created_at', '<=', $fecha_hasta . ' 23:59:59');
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
            ->where('gr.estado', 'enviada')
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
        if ($fecha_desde && $fecha_hasta) {
            // Asumiendo que recibes 'Y-m-d', agregamos horas para cubrir el día completo
            $query->whereBetween('gr.created_at', [
                $fecha_desde . ' 00:00:00',
                $fecha_hasta . ' 23:59:59'
            ]);
        } elseif ($fecha_desde) {
            $query->where('gr.created_at', '>=', $fecha_desde . ' 00:00:00');
        } elseif ($fecha_hasta) {
            $query->where('gr.created_at', '<=', $fecha_hasta . ' 23:59:59');
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

    public function cancelDispatchIfEmptyPicking($orderId)
    {
        $pedido = GpPedido::find($orderId);
        if (!$pedido) {
            return false;
        }
        $orderDispatch = GpOrdenDespacho::where('pedido_id', $orderId)->first();
        $report = $this->getPickingItems($pedido);
        if ($report->isEmpty() && $orderDispatch) {
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
}
