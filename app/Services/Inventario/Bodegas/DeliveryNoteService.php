<?php

namespace App\Services\Inventario\Bodegas;

use App\Models\Inventario\GuiasRemision\GpGuiaRemision;
use App\Models\Inventario\Transferencias\GpTrasladosVentas;
use App\Models\Inventario\GuiasRemision\GpGuiaRemisionDetalle;
use App\Models\Inventario\Reposiciones\GpTrasladosReposicion;
use App\Models\Inventario\GuiasRemision\GpGuiaRemisionTransferencia;
use App\Models\Ventas\Pedidos\GpPedido;
use App\Models\Ventas\Pedidos\GpPedidoDetalle;
use App\Models\Ventas\Pedidos\GpPedidoDetalleStock;
use App\Services\Api\Bodegas\ApiDeliverNoteService;
use App\Services\Builders\Bodega\Contracts\DeliveryNoteParamsBuilderInterface;
use App\Services\Inventario\Bodegas\Contracts\DeliveryNoteCommonServiceInterface;
use App\Services\Inventario\Bodegas\Contracts\DeliveryNoteServiceInterface;
use App\Services\Inventario\Bodegas\Contracts\LogisticsOperationsServiceInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DeliveryNoteService implements DeliveryNoteServiceInterface
{

    public function __construct(
        protected readonly DeliveryNoteParamsBuilderInterface $deliveryNoteBuilder,
        protected readonly ApiDeliverNoteService $apiDeliveryNote,
        protected readonly LogisticsOperationsServiceInterface $logisticsOperationsService,
        protected readonly DeliveryNoteCommonServiceInterface $common,
    ) {}


    /**
     * funcion para consulta del detalle de la guia para visualizar en bodega - consultas
     */

    public function getDeliveryNoteDetailByType(int|string $guiaId): array
    {
        $guia = GpGuiaRemision::findOrFail($guiaId);
        $detalle = GpGuiaRemisionDetalle::where('guia_remision_id', $guia->id)
            ->get(['id', 'guia_remision_id', 'detalle_id', 'GUI_REM_CODI_INTERNO', 'GUI_REM_CANTIDAD', 'GUI_REM_CODI_ADICIONAL', 'GUI_REM_UNIDAD_MEDIDA', 'GUI_REM_DESCRIPCION']);
        $pesoTotal = 0.0;
        if ($detalle->isNotEmpty()) {
            $ids = $detalle->pluck('detalle_id')->toArray();
            $pedidoDetalle = $this->getDetailByTypeGuide($guia->tipo, $ids);
            $checksAlternativos = $this->getCheckAlternativeByDetailGuide($ids);
            $detalle = $detalle->map(function ($det) use ($pedidoDetalle, $checksAlternativos, &$pesoTotal) {
                $art = $pedidoDetalle->get($det->detalle_id);

                if (($art->ART_TIPO_COMPRA ?? null) == 'SERV')
                    return null;

                $cantidad = (float) $det->GUI_REM_CANTIDAD;
                $peso = ($art == null || $art->ART_PESO == null) ? 0.0 : (float) $art->ART_PESO;
                $pesoItem = $peso * $cantidad;
                $pesoTotal += $pesoItem;

                $det->setAttribute('CHECK_ALTERNATIVO', $checksAlternativos->get($det->detalle_id) ?? 0);
                $det->setAttribute('ART_PESO', $peso);
                return $det;
            })->filter()->values();
        }
        $transferencias = GpGuiaRemisionTransferencia::where('guia_remision_id', $guia->id)
            ->pluck('pedido')
            ->toArray();
        return [
            'detalle' => $detalle,
            'transferencias' => $transferencias,
            'peso_total' => round($pesoTotal, 2),
        ];
    }



    public function getDetailByTypeGuide(string $type, array $ids): Collection
    {
        if (empty($ids)) {
            return collect();
        }
        return match ($type) {
            'despacho' => GpPedidoDetalle::whereIn('id', $ids)->get()->keyBy('id'),
            'reposicion' => GpTrasladosReposicion::whereIn('id', $ids)->get()->keyBy('id'),
            'transferencia' => GpTrasladosVentas::whereIn('id', $ids)->get()->keyBy('id'),
            default => collect(),
        };
    }

    public function getCheckAlternativeByDetailGuide(array $ids): Collection
    {
        if (empty($ids)) {
            return collect();
        }
        return GpPedidoDetalle::whereIn('id', $ids)
            ->pluck('CHECK_ALTERNATIVO', 'id');
    }



    public function mapAssigmentNumberGuideRemision()
    {
        $this->logisticsOperationsService->assignAccessCodesForPendingGuides();
    }



    public function getDeliveryNoteAdjustmentInfoByDeliveryNoteId(int $id, bool $debug = true)
    {
        // 1) Cabecera guía (solo tipo despacho)
        $guia = GpGuiaRemision::select('id', 'pedido', 'estado')
            ->where('tipo', 'despacho')
            ->find($id);
        $pedidoOrigen = GpPedido::where('pedido', $guia->pedido)->value('pedido_origen');
        $pedido = $pedidoOrigen ?: $guia->pedido;
        $pedidoNuevo = $pedidoOrigen ? $guia->pedido : '';

        $ajusteWebGuia = DB::table('gp_guia_remision_despachos')->where('guia_remision_id', $guia->id)
            ->whereNotNull('codigo')
            ->where('codigo', '!=', '')
            ->select('id_movimiento', 'pedido', 'codigo')->first();
        $dataAjuste = $this->fecthAjustmentGuideDispatch($pedido, $pedidoNuevo);

        if (!$guia) {
            return ['error' => "Guía $id no encontrada o no es de tipo 'despacho'."];
        }

        // 2) Pedido (mínimo necesario)
        $order = GpPedido::select('id', 'pedido', 'despachado', 'estado')
            ->where('pedido', $guia->pedido)
            ->first();

        if (!$order) {
            return ['error' => "Pedido {$guia->pedido} no encontrado.", 'guia' => $guia];
        }

        // 3) Traer solo las columnas necesarias para el cruce
        $detalleguia = GpGuiaRemisionDetalle::select('GUI_REM_CODI_INTERNO', 'GUI_REM_CANTIDAD')
            ->where('guia_remision_id', $guia->id)
            ->get();

        $detallepedido = GpPedidoDetalle::select('ART_CODIGO', 'ART_CANTIDAD', 'reservado', 'trasladado', 'devuelto')
            ->where('pedido_id', $order->id)
            ->get();
        $stocks = GpPedidoDetalleStock::select('detalle_id', 'seleccionado', 'reservado')
            ->where('pedido_id', $order->id)
            ->get();

        // 4) Agrupar/sumar por código
        $pedidoQtyByCode = $detallepedido
            ->groupBy('ART_CODIGO')
            ->map(fn($rows) => (float) $rows->sum('ART_CANTIDAD'));

        $guiaQtyByCode = $detalleguia
            ->groupBy('GUI_REM_CODI_INTERNO')
            ->map(fn($rows) => (float) $rows->sum('GUI_REM_CANTIDAD'));

        $allCodes = $pedidoQtyByCode->keys()
            ->merge($guiaQtyByCode->keys())
            ->unique()
            ->values();

        $despachados = [];
        $pendientes = [];

        foreach ($allCodes as $codigo) {
            $cantPedido = (float) ($pedidoQtyByCode[$codigo] ?? 0.0);
            $cantGuia = (float) ($guiaQtyByCode[$codigo] ?? 0.0);

            $despachadoQty = min($cantGuia, $cantPedido);
            $pendienteQty = max($cantPedido - $cantGuia, 0.0);

            $row = [
                'codigo' => $codigo,
                'pedido_qty' => $cantPedido,
                'guia_qty' => $cantGuia,
                'despachado_qty' => $despachadoQty,
                'pendiente_qty' => $pendienteQty,
            ];

            if ($despachadoQty > 0)
                $despachados[] = $row;
            if ($pendienteQty > 0)
                $pendientes[] = $row;
        }

        $resumen = [
            'lineas_pedido' => $pedidoQtyByCode->count(),
            'lineas_guia' => $guiaQtyByCode->count(),
            'total_despachado' => array_sum(array_column($despachados, 'despachado_qty')),
            'total_pendiente' => array_sum(array_column($pendientes, 'pendiente_qty')),
        ];

        // 5) Respuesta SLIM (solo lo útil)
        $response = [
            'ajusteWebGuia' => [
                'ajusteWebGuia' => $ajusteWebGuia,
                'ajusteGp' => $dataAjuste
            ],
            'guia' => [
                'id' => $guia->id,
                'pedido' => $guia->pedido,
                'estado' => $guia->estado,
            ],
            'pedido' => [
                'id' => $order->id,
                'pedido' => $order->pedido,
                'estado' => $order->estado->value,
                'despachado' => (bool) $order->despachado,
            ],
            'stocks' => $stocks,
            'despachados_por_guia' => $despachados,
            'pendientes_de_despacho' => $pendientes,
            'resumen' => $resumen,
        ];

        // Si necesitas depurar, activa $debug para incluir materia prima
        if ($debug) {
            $response['debug'] = [
                'detalle_guia' => $detalleguia,
                'detalle_pedido' => $detallepedido,
            ];
        }

        return $response;
    }


    public function processAjusteWevOrderGUideRemision(int $id)
    {
        $guia   = GpGuiaRemision::find($id);
        return $this->logisticsOperationsService->updateOrderDispatchStatus($guia);
    }

    public function fecthAjustmentGuideDispatch(string $orderNumber, string $newOrderNumber)
    {
        $response = $this->apiDeliveryNote->checkDispatchGuideAdjustment([
            'orderNumber'       => $orderNumber,
            'newOrderNumber'    => $newOrderNumber
        ]);
        if ($response['success'] === false) {
            throw new \Exception($response['message']);
        }
        return data_get($response, 'data', []);
    }



    public function getDeliveryNoteDispatchWithAjustment(string $fechaInicio, string $fechaFin)
    {
        $query = DB::table('gp_guia_remisions as gr')
            ->join('gp_guia_remision_despachos as grd', 'gr.id', '=', 'grd.guia_remision_id')
            ->where('gr.tipo', 'despacho')->whereNotNull('grd.codigo')->where('grd.codigo', '!=', '')
            ->where('gr.estado', 'enviada');
        if ($fechaInicio && $fechaFin) {
            $query->where('gr.created_at', '>=', $fechaInicio)->where('gr.created_at', '<=', $fechaFin);
        }
        return $query->select('gr.id', 'grd.codigo', 'grd.pedido', 'gr.tipo', 'gr.estado')->get();
    }

    public function getDeliveryNoteTransferAndRepositionAjustment(string $fechaInicio, string $fechaFin, $estado = null)
    {
        $query = DB::table('gp_guia_remisions as gr')
            ->join('gp_guia_remision_transferencias as grt', 'gr.id', '=', 'grt.guia_remision_id')
            ->whereIn('gr.tipo', ['transferencia', 'reposicion']);
        if ($estado) {
            $query->where('gr.estado', $estado);
        }
        if ($fechaInicio && $fechaFin) {
            $query->where('gr.created_at', '>=', $fechaInicio)->where('gr.created_at', '<=', $fechaFin);
        }
        return $query
            ->select([
                'gr.id',
                'grt.pedido',
                'gr.tipo',
                'gr.estado',
                DB::raw("GROUP_CONCAT(DISTINCT grt.codigo ORDER BY grt.codigo SEPARATOR ', ') AS codigos"),
            ])
            ->groupBy('gr.id', 'grt.pedido', 'gr.tipo', 'gr.estado')
            ->get();
    }



    /** verificar la existencia de guias generadas */

    public function consultExistenceGeneratedGuides(array $payload): string
    {
        $mensaje    = '';
        $items      = collect(data_get($payload, 'items', []));
        $type       = data_get($payload, 'tipo', null);
        if ($type === 'reposicion') {
            return $mensaje;
        }

        $trasladosIds   = $items->pluck('id')->unique();
        $pedidosIds     = $items->pluck('pedido')->unique();
        $artCodes       = $items->pluck('ART_CODIGO')->filter()->unique();
        $existTransfers = GpTrasladosVentas::whereIn('id', $trasladosIds)->pluck('id');

        $detallesOtros  = GpPedidoDetalle::from('gp_pedido_detalles as gpd')
            ->join('gp_pedidos as gp', 'gp.id', '=', 'gpd.pedido_id')
            ->whereIn('gp.pedido_origen', $pedidosIds)
            ->whereIn('ART_CODIGO', $artCodes)
            ->selectRaw('gp.pedido_origen, gpd.ART_CODIGO, SUM(gpd.ART_CANTIDAD) as total_otros')
            ->groupBy('gp.pedido_origen', 'gpd.ART_CODIGO')
            ->get()
            ->keyBy(fn($item) => $item->pedido_origen . '_' . $item->ART_CODIGO);

        $qtyDispatchs = GpGuiaRemisionDetalle::from('gp_guia_remision_detalles as grd')
            ->join('gp_guia_remisions as gr', 'gr.id', '=', 'grd.guia_remision_id')
            ->where('gr.tipo', 'despacho')
            ->whereIN('gr.pedido', $pedidosIds)
            ->whereIn('grd.GUI_REM_CODI_INTERNO', $artCodes)
            ->selectRaw('gr.pedido, grd.GUI_REM_CODI_INTERNO, SUM(grd.GUI_REM_CANTIDAD) as total_despachado')
            ->groupBy('gr.pedido', 'grd.GUI_REM_CODI_INTERNO')
            ->get()
            ->keyBy(fn($item) => $item->pedido . '_' . $item->GUI_REM_CODI_INTERNO);


        foreach ($items as $itm) {
            $id             = data_get($itm, 'id');
            $pedido         = data_get($itm, 'pedido');
            $artCodigo      = data_get($itm, 'ART_CODIGO');
            $seleccionado   = (float) data_get($itm, 'seleccionado', 0);
            $pendiente      = (float) data_get($itm, 'pendiente', 0);

            if (!$existTransfers->contains($id)) {
                abort(409, "El pedido {$pedido} ha sido eliminado, por lo que no se necesita traslado, por favor actualizar la página.");
            }

            //clave para buscar
            $key = $pedido . '_' . $artCodigo;

            $otros = (float) data_get($detallesOtros->get($key), 'total_otros', 0);
            $qtyDispatch = (float) data_get($qtyDispatchs->get($key), 'total_despachado', 0);

            $totalPermitido = $pendiente + $otros;
            $totalIntentando = $qtyDispatch + $seleccionado;
            if ($totalIntentando > $totalPermitido) {
                $quedan = $totalPermitido - $qtyDispatch;
                $mensaje .= "El artículo {$artCodigo} del pedido {$pedido} ya fue enviado en otra guía de remisión. Quedan {$quedan} unidades disponibles.<br>";
            }
        }
        return $mensaje;
    }
}
