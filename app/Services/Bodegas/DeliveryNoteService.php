<?php

namespace App\Services\Bodegas;


use App\Models\despachos\GpGuiaRemision;
use App\Models\despachos\GpGuiaRemisionDespacho;
use App\Models\traslados\GpTrasladosVentas;
use App\Models\despachos\GpGuiaRemisionDetalle;
use App\Models\reposicion\GpTrasladosReposicion;
use App\Models\despachos\GpGuiaRemisionTransferencia;
use App\Models\GpPedido;
use App\Models\GpPedidoDetalle;
use App\Models\GpPedidoDetalleStock;
use App\Models\GpUsuario;
use App\Services\Api\Bodegas\ApiDeliverNoteService;
use App\Services\Bodegas\Contracts\DeliveryNoteCommonServiceInterface;
use App\Services\Bodegas\Contracts\DeliveryNoteServiceInterface;
use App\Services\Bodegas\Contracts\LogisticsOperationsServiceInterface;
use App\Services\Builders\Bodega\Contracts\DeliveryNoteParamsBuilderInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;


class DeliveryNoteService implements DeliveryNoteServiceInterface
{
    /**
     *  SERVICIO DEDICADO AL  GUIAREMISIONCONTROLLER
     */

    protected $apiDeliveryNote;
    protected $deliveryNoteBuilder;
    protected $logisticsOperationsService;
    protected $common;


    // protected $invoiceService;
    public function __construct(DeliveryNoteParamsBuilderInterface $deliveryNoteBuilder, ApiDeliverNoteService $apiDeliveryNote, LogisticsOperationsServiceInterface $logisticsOperationsService, DeliveryNoteCommonServiceInterface $common,)
    {
        // $this->invoiceService = $invoiceService;
        $this->deliveryNoteBuilder = $deliveryNoteBuilder;
        $this->apiDeliveryNote = $apiDeliveryNote;
        $this->logisticsOperationsService = $logisticsOperationsService;
        $this->common = $common;

    }


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





    /***
     *
     *
     * PROCESO DE DEPACHO DE GUIA POR TIPO - DESPACHO - REPOSICION - TRANSFERENCIA
     *
     *
     */


    /**
     *
     *
     *   TRANSFERENCIA AJUSTE DE GUIA
     */
    public function dispatchGuideTransferShipment(GpGuiaRemision $guia, Collection $detalle, $user)
    {
        $itemsIds = collect($detalle)->pluck('detalle_id')->toArray();
        if (empty($itemsIds)) {
            return;
        }
        $traslados = $this->getTransferSales($itemsIds);
        if (!$traslados || $traslados->isEmpty()) {
            throw new \RuntimeException(
                'No hay traslados de ventas pendientes para procesar el ajuste de la guía de transferencia'
            );
        }
        $grupos = $this->groupItemsByOrderTransferVta($detalle, $traslados);

        $almenos_uno = false;
        foreach ($grupos as $grupo) {
            $params = $this->deliveryNoteBuilder->buildParamsHeaderTransfer($guia, $grupo['pedido'], $grupo['ws_items'], $user);
            $almenos_uno = $this->validateExistenceTransferGuideAdjustment($guia, $params, $grupo['items'], $grupo['pedido']);
        }
        if ($almenos_uno) {
            $guia->update(['estado' => 'enviada']);
        } else {
            throw new \Exception('No se pudo generar el despacho');
        }
        return response([
            'success' => true,
            'message' => 'Guía de remisión despachada',
        ]);
    }


    public function decrementOriginWarehouseReservedStock($trl, $qty)
    {
        $dtP = GpPedidoDetalle::find($trl->detalle_id);
        if ($dtP->ART_TIPO_COMPRA != 'L' && $dtP->ART_TIPO_COMPRA != 'SERV') {
            $stock = GpPedidoDetalleStock::where('detalle_id', $trl->detalle_id)
                ->where('STO_ART_BODEGA', $trl->bodega_origen)
                ->lockForUpdate()
                ->first();
            $nuevo = max(0, ($stock->reservado ?? 0) - $qty);
            $stock->reservado = $nuevo;
            $stock->save();
        }
    }
    public function decrementOriginWarehouseReservedStockDispatch($detalleId, $warehouse, $qty)
    {
        $det = GpPedidoDetalle::find($detalleId);
        if ($det->ART_TIPO_COMPRA != 'L') {
            $stock = GpPedidoDetalleStock::where('detalle_id', $detalleId)
                ->where('STO_ART_BODEGA', $warehouse)
                ->lockForUpdate()
                ->first();
            $nuevo = max(0, ($stock->reservado ?? 0) - $qty);
            $stock->reservado = $nuevo;
            $stock->save();
        }
    }

    public function createDocumentGuideTrfv(GpGuiaRemision $guia, $responseApi, $documento)
    {
        $newDocuTrfv = new GpGuiaRemisionTransferencia();
        $newDocuTrfv->guia_remision_id = $guia->id;
        $newDocuTrfv->pedido = $documento;
        $newDocuTrfv->codigo = $responseApi['docGp'];
        $newDocuTrfv->save();
    }


    public function groupItemsByOrderTransferVta(Collection $detalle, Collection $traslados): array
    {
        $grupos = [];
        foreach ($detalle as $det) {
            $traslado = $traslados->get($det['detalle_id']);
            if (!$traslado) {
                // puedes loggear y continuar
                continue;
            }
            $det->traslado = $traslado;
            $pedido = $traslado->pedido;
            $grupos[$pedido]['pedido'] = $pedido;
            $grupos[$pedido]['items'][] = $det;

            //items para webservice
            $grupos[$pedido]['ws_items'][] = [
                'item' => $det->GUI_REM_CODI_INTERNO,
                'compartimiento' => '',
                'cantidad' => $det->GUI_REM_CANTIDAD,
            ];
        }
        return $grupos;
    }


    /**
     *
     *
     *   DESPACHO AJUSTE DE GUIA
     */
    public function dispatchGuideDispatchShipment(GpGuiaRemision $guia, Collection $detalle, $user)
    {
        if ($detalle->isEmpty()) {
            return $this->markGuideSentAndUpdateOrder($guia, 'Sin ítems para despachar');
        }

        $ids = $detalle->pluck('detalle_id')->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return $this->markGuideSentAndUpdateOrder($guia, 'Sin ítems válidos');
        }

        $articulos = $this->getAllDetailordersByGuideDetailsIds($ids);

        $tocoServ = false;
        foreach ($detalle->groupBy('detalle_id') as $detalleId => $row) {
            $art = $articulos->get($detalleId);
            if (!$art) {
                continue;
            }
            if ($art->ART_TIPO_COMPRA == 'SERV') {
                $qty = (float) $row->sum('GUI_REM_CANTIDAD');
                $qty = max(0.0, $qty);
                $nuevoReservado = max(0.0, (float) ($art->reservado ?? 0) - $qty);
                $nuevoTrasladado = (float) ($art->trasladado ?? 0) + $qty;
                $cap = (float) ($art->ART_CANTIDAD ?? 0);
                if ($cap > 0)
                    $nuevoTrasladado = min($nuevoTrasladado, $cap);
                $art->fill([
                    'reservado' => $nuevoReservado,
                    'trasladado' => $nuevoTrasladado
                ])->save();
                $tocoServ = true;
            }
        }

        if ($tocoServ && $guia->estado != 'enviada') {
            $guia->update(['estado' => 'enviada']);
        }

        $groups = $this->groupDispatchItemsBySourceOrder($guia, $detalle);
        if (count($groups) === 0) {
            return $this->markGuideSentAndUpdateOrder($guia, 'Sin ítems para despachar');
        }
        $processed = false;
        foreach ($groups as $grupo) {
            $params = $this->deliveryNoteBuilder->buildParameterHeaderAndDetailDispatchShipment($guia, $grupo['pedido'], $grupo['pedido_nuevo'], $grupo['ws_items'], $user);
            $processed = $this->validateExistsDispatchGuideAdjustment($guia, $grupo['pedido'], $params, $grupo['items']);
        }

        if ($processed && $guia->estado != 'enviada') {
            $guia->update(['estado' => 'enviada']);
        }
        $pedido = $this->logisticsOperationsService->updateOrderDispatchStatus($guia);
        return [
            'success' => true,
            'message' => 'Guía de remisión despachada',
            'pedido' => $pedido,
        ];
    }


    public function markGuideSentAndUpdateOrder(GpGuiaRemision $guia, $why)
    {
        if ($guia->estado != 'enviada') {
            $guia->update(['estado' => 'enviada']);
            $pedido = $this->logisticsOperationsService->updateOrderDispatchStatus($guia);
            return [
                'success' => true,
                'message' => 'Guía de remisión despachada (' . $why . ')',
                'pedido' => $pedido,
            ];
        }
    }


    public function groupDispatchItemsBySourceOrder(GpGuiaRemision $guia, Collection $detalle): array
    {

        $pedidoOrigen = GpPedido::where('pedido', $guia->pedido)->value('pedido_origen');
        $documento = $pedidoOrigen ?: $guia->pedido;
        $pedidonuevo = $pedidoOrigen ? $guia->pedido : '';
        $groups = [];
        foreach ($detalle as $item) {
            $detallePedido = GpPedidoDetalle::where('id', $item->detalle_id)->first();
            if ($detallePedido->ART_TIPO_COMPRA == 'SERV') {
                continue;
            }
            $doc = $documento;
            $groups[$doc]['pedido'] = $documento;
            $groups[$doc]['pedido_nuevo'] = $pedidonuevo;
            $groups[$doc]['items'][] = $item;
            $groups[$doc]['ws_items'][] = [
                'item' => $item->GUI_REM_CODI_INTERNO,
                'compartimiento' => '',
                'cantidad' => $item->GUI_REM_CANTIDAD,
            ];
        }

        return $groups;
    }

    public function applyDispatchToOrderDetail($item, $qty)
    {
        $dt = GpPedidoDetalle::where('id', $item->detalle_id)->lockForUpdate()->first();

        $rsvdac = (float) ($dt->reservado ?? 0);
        $trlsac = (float) ($dt->trasladado ?? 0);
        $cantac = (float) ($dt->ART_CANTIDAD ?? 0);

        $newRsvd = max(0.0, $rsvdac - $qty);
        $newTrls = $trlsac + $qty;

        if ($cantac != null) {
            $newTrls = min($newTrls, $cantac);
        }

        $dt->reservado = $newRsvd;
        $dt->trasladado = $newTrls;
        $dt->save();

        return $dt->refresh();
    }

    public function createDocumentDispatchTrfv(GpGuiaRemision $guia, $responseApi, $documento)
    {
        $dispatchTrfv = new GpGuiaRemisionDespacho();
        $dispatchTrfv->guia_remision_id = $guia->id;
        $dispatchTrfv->id_movimiento = $responseApi['idMovimiento'];
        $dispatchTrfv->pedido = $documento;
        $dispatchTrfv->codigo = $responseApi['docGp'];
        $dispatchTrfv->save();
    }

    /**
     *
     *
     *   REPOSICION AJUSTE DE GUIA
     */
    public function dispatchGuideReplacementShipment(GpGuiaRemision $guia, Collection $detalle, $user)
    {
        $itemsIds = collect($detalle)->pluck('detalle_id')->toArray();
        if (empty($itemsIds)) {
            return;
        }

        $repositionSales = $this->getRepositionSales($itemsIds);
        if (!$repositionSales || $repositionSales->isEmpty()) {
            throw new \RuntimeException(
                'No hay items para procesar el ajuste de la guía de reposición'
            );
        }
        $grupos = $this->groupItemsByOrderReplacementShipment($detalle, $repositionSales);
        $checkList = false;
        foreach ($grupos as $grupo) {
            $params = $this->deliveryNoteBuilder->buildParamsHeaderTransfer($guia, $grupo['documento'], $grupo['ws_items'], $user);
            $checkList = $this->validateExistsRepositionGuideAjustment($guia, $grupo['documento'], $params, $grupo['items']);
        }
        if ($checkList) {
            $guia->update(['estado' => 'enviada']);
        } else {
            throw new \Exception('No se pudo despachar la guia de reposición');
        }
        return response([
            'success' => true,
            'message' => 'Guía de remisión despachada',
        ]);
    }

    public function groupItemsByOrderReplacementShipment(Collection $detalle, Collection $traslados): array
    {
        $grupos = [];
        foreach ($detalle as $det) {
            $traslado = $traslados->get($det['detalle_id']);
            if (!$traslado) {
                // puedes loggear y continuar
                continue;
            }
            $det->traslado = $traslado;
            $documento = $traslado->Documento;
            $grupos[$documento]['documento'] = $documento;
            $grupos[$documento]['items'][] = $det;

            //items para webservice
            $grupos[$documento]['ws_items'][] = [
                'item' => $det->GUI_REM_CODI_INTERNO,
                'compartimiento' => '',
                'cantidad' => $det->GUI_REM_CANTIDAD,
            ];
        }
        return $grupos;
    }


    public function mapAssigmentNumberGuideRemision()
    {
        $this->logisticsOperationsService->assignAccessCodesForPendingGuides();
    }





    public function reassignDispatchGuideAdjustment($id)
    {
        $guia = GpGuiaRemision::find($id);
        $detalle = GpGuiaRemisionDetalle::where('guia_remision_id', $guia->id)->get();

        $pedidoOrigen = GpPedido::where('pedido', $guia->pedido)->value('pedido_origen');
        $documento = $pedidoOrigen ?: $guia->pedido;
        $pedidoNuevo = $pedidoOrigen ? $guia->pedido : '';
        $dataAjuste = $this->fecthAjustmentGuideDispatch($documento, $pedidoNuevo);


        $ids = $detalle->pluck('detalle_id')->filter()->unique()->values();
        $articulos = $this->getAllDetailordersByGuideDetailsIds($ids);
        $tocoServ = false;
        foreach ($detalle->groupBy('detalle_id') as $detalleId => $row) {
            $art = $articulos->get($detalleId);
            if (!$art) {
                continue;
            }
            if ($art->ART_TIPO_COMPRA == 'SERV') {
                $qty = (float) $row->sum('GUI_REM_CANTIDAD');
                $qty = max(0.0, $qty);
                $nuevoReservado = max(0.0, (float) ($art->reservado ?? 0) - $qty);
                $nuevoTrasladado = (float) ($art->trasladado ?? 0) + $qty;
                $cap = (float) ($art->ART_CANTIDAD ?? 0);
                if ($cap > 0)
                    $nuevoTrasladado = min($nuevoTrasladado, $cap);
                $art->fill([
                    'reservado' => $nuevoReservado,
                    'trasladado' => $nuevoTrasladado
                ])->save();
                $tocoServ = true;
            }
        }

        if ($tocoServ && $guia->estado != 'enviada') {
            $guia->update(['estado' => 'enviada']);
        }
        $groups = $this->groupDispatchItemsBySourceOrder($guia, $detalle);
        if (count($groups) === 0) {
            return $this->markGuideSentAndUpdateOrder($guia, 'Sin ítems para despachar');
        }
        $wsOk = false;
        foreach ($groups as $grupo) {
            if ($guia->rebajado) {
                $wsOk = true;
            }
            foreach ($grupo['items'] as $item) {
                $this->applyDispatchToOrderDetail($item, $item->GUI_REM_CANTIDAD);
                $this->decrementOriginWarehouseReservedStockDispatch($item->detalle_id, $guia->bodega_origen, $item->GUI_REM_CANTIDAD);
            }
            $guia->update(['rebajado' => 1]);
            $this->createDocumentDispatchTrfv($guia, $dataAjuste, $grupo['pedido']);
            $wsOk = true;
        }
        if ($wsOk) {
            if ($guia->estado !== 'enviada') {
                $guia->update(['estado' => 'enviada']);
            }
        }
        $pedido = $this->logisticsOperationsService->updateOrderDispatchStatus($guia);
        $guia->refresh();
        return $guia;
    }


    public function getAllDetailordersByGuideDetailsIds($ids)
    {
        return GpPedidoDetalle::query()->whereIn('id', $ids)
            ->where('reservado', '>', 0)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }


    public function fecthAjustmentGuideDispatch($orderNumber, $newOrderNumber)
    {
        $response = $this->apiDeliveryNote->checkDispatchGuideAdjustment([
            'orderNumber' => $orderNumber,
            'newOrderNumber' => $newOrderNumber
        ]);
        if ($response['success'] === false) {
            throw new \Exception($response['message']);
        }
        return data_get($response, 'data', []);
    }




    public function createAdjustmentDeliveryNote($id)
    {
        return DB::transaction(function () use ($id) {
            $user = GpUsuario::find(6);
            $guia = GpGuiaRemision::find($id);
            $detalle = GpGuiaRemisionDetalle::where('guia_remision_id', $guia->id)->get();


            $ids = $detalle->pluck('detalle_id')->filter()->unique()->values();
            if ($ids->isEmpty()) {
                return $this->markGuideSentAndUpdateOrder($guia, 'Sin ítems válidos');
            }

            $articulos = $this->getAllDetailordersByGuideDetailsIds($ids);

            $tocoServ = false;
            foreach ($detalle->groupBy('detalle_id') as $detalleId => $row) {
                $art = $articulos->get($detalleId);
                if (!$art) {
                    continue;
                }
                if ($art->ART_TIPO_COMPRA == 'SERV') {
                    $qty = (float) $row->sum('GUI_REM_CANTIDAD');
                    $qty = max(0.0, $qty);
                    $nuevoReservado = max(0.0, (float) ($art->reservado ?? 0) - $qty);
                    $nuevoTrasladado = (float) ($art->trasladado ?? 0) + $qty;
                    $cap = (float) ($art->ART_CANTIDAD ?? 0);
                    if ($cap > 0)
                        $nuevoTrasladado = min($nuevoTrasladado, $cap);
                    $art->fill([
                        'reservado' => $nuevoReservado,
                        'trasladado' => $nuevoTrasladado
                    ])->save();
                    $tocoServ = true;
                }
            }

            if ($tocoServ && $guia->estado != 'enviada') {
                $guia->update(['estado' => 'enviada']);
            }

            $groups = $this->groupDispatchItemsBySourceOrder($guia, $detalle);
            if (count($groups) === 0) {
                return $this->markGuideSentAndUpdateOrder($guia, 'Sin ítems para despachar');
            }
            $wsOk = false;
            foreach ($groups as $grupo) {
                $params = $this->deliveryNoteBuilder->buildParameterHeaderAndDetailDispatchShipment($guia, $grupo['pedido'], $grupo['pedido_nuevo'], $grupo['ws_items'], $user);
                $response = $this->apiDeliveryNote->createDispatchShipmentGuideVta($params);
                if ($response['success'] === false) {
                    $guia->update(['procesando' => null]);
                    throw new \Exception($response['message']);
                }
                $docGp = data_get($response, 'data.docGp'); // null si no existe
                if (blank($docGp)) {
                    throw new \Exception('No se ha generado el documento de ajuste de guía en Gp.');
                }

                if ($guia->rebajado) {
                    $wsOk = true;
                }
                foreach ($grupo['items'] as $item) {
                    $this->applyDispatchToOrderDetail($item, $item->GUI_REM_CANTIDAD);
                    $this->decrementOriginWarehouseReservedStockDispatch($item->detalle_id, $guia->bodega_origen, $item->GUI_REM_CANTIDAD);
                }
                $guia->update(['rebajado' => 1]);
                $this->createDocumentDispatchTrfv($guia, $response['data'], $grupo['pedido']);
                $wsOk = true;
            }

            if ($wsOk) {
                if ($guia->estado !== 'enviada') {
                    $guia->update(['estado' => 'enviada']);
                }
            }
            $pedido = $this->logisticsOperationsService->updateOrderDispatchStatus($guia);

            return $guia->fresh();
        });
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
                'estado' => $order->estado,
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


    public function processAjusteWevOrderGUideRemision($id)
    {
        $guia = GpGuiaRemision::find($id);
        $pedido = $this->logisticsOperationsService->updateOrderDispatchStatus($guia);
        return $pedido;
    }



    public function getDeliveryNoteDispatchWithAjustment($fechaInicio, $fechaFin)
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

    public function getDeliveryNoteTransferAndRepositionAjustment($fechaInicio, $fechaFin, $estado = null)
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



    /***
     *
     *
     *
     *  procesos de reajuste de guias de transferencias y reposiciones -JGMS
     *
     *
     *
     * */


    public function processTransferGuideAdjustment($id, $orderNumber = null)
    {
        $processed = false;
        $user = GpUsuario::find(6); //adminweb
        $order = $orderNumber ? GpPedido::where('pedido', $orderNumber)->first() : null;
        $orderId = $order ? $order->id : null;
        $guia = GpGuiaRemision::find($id);
        $detalles = GpGuiaRemisionDetalle::where('guia_remision_id', $guia->id)->get();
        $itemsIds = collect($detalles)->pluck('detalle_id')->toArray();
        $transferSales = $this->getTransferSales($itemsIds, $orderId);
        if (!$transferSales || $transferSales->isEmpty()) {
            throw new \RuntimeException(
                'No hay traslados de ventas pendientes para procesar el ajuste de la guía de transferencia'
            );
        }
        $grupos = $this->groupItemsByOrderTransferVta($detalles, $transferSales);

        foreach ($grupos as $grupo) {
            $params = $this->deliveryNoteBuilder->buildParamsHeaderTransfer($guia, $grupo['pedido'], $grupo['ws_items'], $user);
            $processed = $this->validateExistenceTransferGuideAdjustment($guia, $params, $grupo['items'], $grupo['pedido']);
        }
        if ($processed) {
            $guia->update(['estado' => 'enviada']);
        } else {
            throw new \Exception('No se pudo procesar el ajuste de la guía de transferencia');
        }
        return $guia->fresh();
    }

    public function getTransferSales($itemsIds, $orderId = null): Collection
    {
        return GpTrasladosVentas::query()
            ->join('gp_pedidos', 'gp_pedidos.id', '=', 'gp_traslados_ventas.pedido_id')
            ->whereIn('gp_traslados_ventas.id', $itemsIds)
            ->where(function ($q) {
                $q->where('gp_traslados_ventas.completado', 0)->orWhereNull('gp_traslados_ventas.completado');
            })->when(!is_null($orderId), fn($q) => $q->where('gp_traslados_ventas.pedido_id', $orderId))
            ->get(['gp_traslados_ventas.*', 'gp_pedidos.pedido'])
            ->keyBy('id');
    }


    /** validar existencia de guia de transferencia */
    public function validateExistenceTransferGuideAdjustment($guia, $params, $groupItems, $pedido) : bool
    {
        $cabecera = data_get($params, 'cabecera', []);
        $payloadHeader = $this->deliveryNoteBuilder->buildingTransferGuideAdjustmentPayload($cabecera);
        $responseHeaderValidate = $this->apiDeliveryNote->checkTransferGuideAdjustment($payloadHeader);
        $resHeader = data_get($responseHeaderValidate, 'data');

        $docGp = data_get($resHeader, 'docGp', '');
        $idGuiaRemision = data_get($resHeader, 'idGuiaRemision', null);
        if (!empty($resHeader) && !blank($docGp)) {
            $already = $this->transferMovementExistsWeb($docGp, $idGuiaRemision, $pedido);
            if ($already) {
                return DB::transaction(function () use ($groupItems) {
                    $this->processReservedTransferedAndDecrementStock($groupItems);
                    return true;
                });
            }
            return DB::transaction(function () use ($groupItems, $guia, $resHeader, $pedido) {
                $this->processReservedTransferedAndDecrementStock($groupItems);
                $this->createDocumentGuideTrfv($guia, $resHeader, $pedido);
                return true;
            });
        }
        /** en caso de que no exista la guia de transferencia debe crearla */
        if (empty($resHeader) && (($responseHeaderValidate['success'] ?? null) === false)) {
            $response = $this->apiDeliveryNote->createTransferGuideVta($params);
            if ($response['success'] === false) {
                throw new \Exception($response['message']);
            }
            $docGp = data_get($response, 'data.docGp'); // null si no existe
            if (blank($docGp)) {
                throw new \Exception('No se ha generado el documento de ajuste de guía en Gp.');
            }
           return  DB::transaction(function () use ($guia, $pedido, $response, $groupItems) {
                $this->processReservedTransferedAndDecrementStock($groupItems);
                $this->createDocumentGuideTrfv($guia, $response['data'], $pedido);
                return true;
            });
        }
        return false;
    }

    public function transferMovementExistsWeb($docGp, $idGuiaRemision, $pedido)
    {
        return GpGuiaRemisionTransferencia::query()
        ->where('guia_remision_id', $idGuiaRemision) // ← sin doble $
        ->where('pedido', $pedido)
        ->where('codigo', trim((string) $docGp))
        ->exists();
    }

    public function hasDispatchAdjustmentLocal($docGp, $idGuiaRemision, $pedido)
    {
        return GpGuiaRemisionDespacho::where([
            ['guia_remision_id', $idGuiaRemision],
            ['pedido', $pedido],
            ['codigo', trim((string) $docGp)],
        ])->exists();
    }
    public function processReservedTransferedAndDecrementStock($groupItems)
    {
        foreach ($groupItems as $item) {
            $this->common->moveReservedToTransferredForTrfvLine($item->traslado, $item->GUI_REM_CANTIDAD);
            $this->decrementOriginWarehouseReservedStock($item->traslado, $item->GUI_REM_CANTIDAD);
        }
    }


    /** procso para reajuste y validacion para reposiciones */

    public function processRepositionGuideAdjustment($id, $documento = null)
    {
        $processed = false;
        $user = GpUsuario::find(6); //adminweb
        $guia = GpGuiaRemision::find($id);
        $detalles = GpGuiaRemisionDetalle::where('guia_remision_id', $guia->id)->get();
        $itemsIds = collect($detalles)->pluck('detalle_id')->toArray();
        if (empty($itemsIds)) {
            return;
        }

        $repositionSales = $this->getRepositionSales($itemsIds, $documento);
        if (!$repositionSales || $repositionSales->isEmpty()) {
            throw new \RuntimeException(
                'No hay items para procesar el ajuste de la guía de reposición'
            );
        }
        $grupos = $this->groupItemsByOrderReplacementShipment($detalles, $repositionSales);
        foreach ($grupos as $grupo) {
            $params = $this->deliveryNoteBuilder->buildParamsHeaderTransfer($guia, $grupo['documento'], $grupo['ws_items'], $user);
            $processed = $this->validateExistsRepositionGuideAjustment($guia, $grupo['documento'], $params, $grupo['items']);
        }
        if ($processed) {
            $guia->update(['estado' => 'enviada']);
        } else {
            throw new \Exception('No se pudo despachar la guia de reposición');
        }
        return $guia->fresh();
    }


    public function getRepositionSales($itemsIds, $documento = null)
    {
        return GpTrasladosReposicion::whereIn('id', $itemsIds)
            ->where(function ($q) {
                $q->where('gp_traslados_reposicions.completado', 0)->orWhereNull('gp_traslados_reposicions.completado');
            })
            ->when(!is_null($documento), fn($q) => $q->where('gp_traslados_reposicions.Documento', $documento))
            ->get()->keyBy('id');
    }

    public function validateExistsRepositionGuideAjustment($guia, $documento, $params, $groupItems) : bool
    {
        $cabecera = data_get($params, 'cabecera', []);
        $payloadHeader = $this->deliveryNoteBuilder->buildingRepositionGuideAdjustmentPayload($cabecera);
        $responseHeaderValidate = $this->apiDeliveryNote->checkRepositionGuideAdjustment($payloadHeader);
        $resHeader = data_get($responseHeaderValidate, 'data');
        $docGp = data_get($resHeader, 'docGp', '');
        $idGuiaRemision = data_get($resHeader, 'idGuiaRemision', null);

        if (!empty($resHeader) && !blank($docGp)) {
            $already = $this->transferMovementExistsWeb($docGp, $idGuiaRemision, $documento);
            if ($already) {
                return DB::transaction(function () use ($groupItems) {
                    $this->generateMovementRepositionAdjustment($groupItems);
                    return true;
                });
            }
            // A2: Existe en GP pero no local: aplica ambos pasos en TX
            return DB::transaction(function () use ($groupItems, $guia, $resHeader, $documento) {
                $this->generateMovementRepositionAdjustment($groupItems);
                $this->createDocumentGuideTrfv($guia, $resHeader, $documento);
                return true;
            });
        }

        if (empty($resHeader) && ($responseHeaderValidate['success'] ?? null) === false) {
            $response = $this->apiDeliveryNote->createReplacementShipmentGuideVta($params);
            if ($response['success'] === false) {
                throw new \Exception($response['message']);
            }
            $docGp = data_get($response, 'data.docGp'); // null si no existe
            if (blank($docGp)) {
                throw new \Exception('No se ha generado el documento de ajuste de guía en Gp.');
            }

           return  DB::transaction(function () use ($groupItems, $guia, $response, $documento) {
                $this->generateMovementRepositionAdjustment($groupItems);
                $this->createDocumentGuideTrfv($guia, $response['data'], $documento);
                return true;
            });
        }
        return false;
    }


    public function generateMovementRepositionAdjustment($groupItems)
    {
        foreach ($groupItems as $item) {
            $this->common->moveReservedToTransferredForTrfvLine($item->traslado, $item->GUI_REM_CANTIDAD);
        }
    }


    /** valiuar existencia de guia de despacho  */
    public function validateExistsDispatchGuideAdjustment($guia, $pedido, $params, $groupItems): bool
    {

        $cabecera = data_get($params, 'cabecera', []);
        $payloadHeader = $this->deliveryNoteBuilder->buildingDispatchGuideAdjustmentPayload($cabecera);
        $checkResp = $this->apiDeliveryNote->checkDispatchGuideAdjustment($payloadHeader);
        $resHeader = data_get($checkResp, 'data');
        $docGp = data_get($resHeader, 'docGp', '');
        $idGuiaRemision = data_get($resHeader, 'idGuiaRemision', null);
        $existLocal = $this->hasDispatchAdjustmentLocal($docGp, $idGuiaRemision, $pedido);
        if (!empty($resHeader) && !blank($docGp)) {
            if ($existLocal) {
                return DB::transaction(function () use ($guia, $groupItems) {
                    $this->processReservedDispatchedAndDecrementStock($guia, $groupItems);
                    $guia->update(['rebajado' => 1]);
                    return true;
                });
            }
            if (!$existLocal) {
                return DB::transaction(function () use ($groupItems, $guia, $resHeader, $pedido) {
                    $this->processReservedDispatchedAndDecrementStock($guia, $groupItems);
                    $this->createDocumentDispatchTrfv($guia, $resHeader, $pedido);
                    $guia->update(['rebajado' => 1]);
                    return true;
                });
            }
        }

        if (empty($resHeader) && $checkResp['success'] === false) {
            $response = $this->apiDeliveryNote->createDispatchShipmentGuideVta($params);
            if (($response['success'] ?? null) === false) {
                throw new \Exception($response['message'] ?? 'Error creando ajuste de guía en GP.');
            }
            $docGp = data_get($response, 'data.docGp'); // null si no existe
            if (blank($docGp)) {
                throw new \Exception('No se ha generado el documento de ajuste de guía en GP.');
            }
           return DB::transaction(function () use ($groupItems, $guia, $response, $pedido) {
                $this->processReservedDispatchedAndDecrementStock($guia, $groupItems);
                $this->createDocumentDispatchTrfv($guia, $response['data'], $pedido);
                $guia->update(['rebajado' => 1]);
                return true;
            });
        }
        return false;
    }


    public function processReservedDispatchedAndDecrementStock($guia, $groupItems)
    {
        foreach ($groupItems as $item) {
            $this->applyDispatchToOrderDetail($item, $item->GUI_REM_CANTIDAD);
            $this->decrementOriginWarehouseReservedStockDispatch($item->detalle_id, $guia->bodega_origen, $item->GUI_REM_CANTIDAD);
        }
    }




    public function getSecuencialCommon($establecimiento, $ptoEmision): string{
       return $this->common->getNextGuideSequential($establecimiento,  $ptoEmision);
    }

}
