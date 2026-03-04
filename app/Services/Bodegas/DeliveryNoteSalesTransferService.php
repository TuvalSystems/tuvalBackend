<?php

namespace App\Services\Bodegas;

use App\Constants\Bodegas\DeliveryNoteConstants;
use App\Enums\bodegas\guiaremision\DeliveryNoteStatus;
use App\Enums\bodegas\guiaremision\DeliveryNoteType;
use App\Enums\bodegas\guiaremision\ReasonForTransfer;
use App\Enums\bodegas\guiaremision\TransactionType;
use App\Enums\Ventas\OrderStatus;
use App\Models\despachos\GpGuiaRemision;
use App\Models\despachos\GpGuiaRemisionDetalle;
use App\Models\despachos\GpGuiaRemisionTransferencia;
use App\Models\GpPedido;
use App\Models\GpPedidoDetalle;
use App\Models\GpPedidoDetalleStock;
use App\Models\GpUsuario;
use App\Models\traslados\GpTrasladosVentas;
use App\Notifications\User\UserNotification;
use App\Services\Api\Bodegas\ApiDeliverNoteService;
use App\Services\Bodegas\Contracts\DeliveryNoteCommonServiceInterface;
use App\Services\Bodegas\Contracts\DeliveryNoteSalesTransferServiceInterface;
use App\Services\Bodegas\Contracts\LogisticsOperationsServiceInterface;
use App\Services\Builders\Bodega\Contracts\DeliveryNoteDispatchParamsBuilderInterface;
use App\Services\Builders\Bodega\Contracts\DeliveryNoteSalesTransferBuilderInterface;
use App\Services\Funciones\Contracts\InvoiceServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class DeliveryNoteSalesTransferService implements DeliveryNoteSalesTransferServiceInterface
{

    public function __construct(
        protected readonly DeliveryNoteCommonServiceInterface $common,
        protected readonly ApiDeliverNoteService $api,
        protected readonly LogisticsOperationsServiceInterface $logistic,
        protected readonly DeliveryNoteDispatchParamsBuilderInterface $builderDispatch,
        protected readonly DeliveryNoteSalesTransferBuilderInterface $builder,
        protected readonly InvoiceServiceInterface $invoiceService,
    ) {}

    public function createDeliveryNoteSalesTransfer(array $payload, GpUsuario $user)
    {
        $items              = data_get($payload, 'items', []);
        $limitQty           = $this->validateQtyTransferedItems($items);
        if ($limitQty) {
            throw new \RuntimeException($limitQty);
        }
        $tipo               = DeliveryNoteType::TRANSFERENCIA->value;
        $ptoEmision         = DeliveryNoteConstants::PTO_EMISION_DEFAULT;
        $primerItem         = data_get($payload, 'items.0', []);
        $documento          = data_get($primerItem, 'pedido', []);
        $bodegaOrigen       = data_get($primerItem, 'bodega_origen');
        $bodegaDestino      = data_get($primerItem, 'bodega_destino');
        $bodGuide           = $this->common->getWarehouseDescriptionOrigin($bodegaOrigen);
        $ptoOrigen          = $this->common->getPtoVtaByWarehouse($bodegaOrigen);
        $ptoDestino         = $this->common->getPtoVtaByWarehouse($bodegaDestino);
        $dirOrigen          = $this->common->getDirection($ptoOrigen);
        $dirDestino         = $this->common->getDirection($ptoDestino);
        $secuencial         = $this->common->getNextGuideSequential($ptoOrigen, $ptoEmision);
        $numGuide           = $this->common->formatGuideNumber($ptoOrigen, $ptoEmision, $secuencial);
        $idemKey            = $this->makeIdempotencyKeySalesTransfer($payload,  $tipo, $primerItem,  $ptoOrigen, $ptoDestino, $secuencial, $numGuide);

        $infoGuia = [
            'tipo'              => $tipo,
            'ptoEmision'        => $ptoEmision,
            'numeroGuia'        => $numGuide,
            'user'              => $user,
            'idemKey'           => $idemKey,
            'secuencial'        => $secuencial,
            'bodGuide'          => $bodGuide,
            'bodegaOrigen'      => $bodegaOrigen,
            'bodegaDestino'     => $bodegaDestino,
            'dirOrigin'         => $dirOrigen,
            'dirDestino'        => $dirDestino,
            'ptoOrigen'         => $ptoOrigen,
            'documento'         => $documento,
            'docuSustento'      => DeliveryNoteConstants::DOC_SUSTENTO_FACTURA,
            'motivoTraslado'    => ReasonForTransfer::TRANSFERENCIA->value,

        ];
        $guia = DB::transaction(function () use ($payload, $infoGuia, $items) {
            $existing = GpGuiaRemision::where('idempotency_key', data_get($infoGuia, 'idemKey'))
                ->where('tipo', DeliveryNoteType::TRANSFERENCIA->value)
                ->where('estado', DeliveryNoteStatus::PENDIENTE->value)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                $existing->update(['estado' => DeliveryNoteStatus::PENDIENTE->value]);
                return $existing->refresh();
            }

            $guia = $this->saveHeaderDeliveryNoteSalesTransferWeb($payload, $infoGuia);
            $this->saveDetailDeliveryNoteSalesTransferWeb($guia->id, $items);


            $this->updateOrderAndDetail($items);
            return $guia;
        });
        $this->sendDeliveryNoteSalesTransferToGp($guia);
        return $guia->refresh();
    }


    protected function makeIdempotencyKeySalesTransfer(array $payload, string $tipo, array  $primerItem,  string $ptoOrigen, string $ptoDestino, string $secuencial, string $numGuide): string
    {
        $base = [
            'pto_origen'        => $ptoOrigen,
            'pto_destino'       => $ptoDestino,
            'tipo'              => $tipo,
            'bodega_origen'     => data_get($primerItem, 'pedido.bodega_origen'),
            'bodega_destino'    => data_get($primerItem, 'pedido.bodega_destino'),
            'secuencial'        => $secuencial,
            'num_guide'         => $numGuide,
            'fecha_inicio'      => data_get($payload, 'fecha_inicio'),
            'fecha_fin'         => data_get($payload, 'fecha_fin'),
            'detalle'           => collect(data_get($payload, 'items', []))->map(fn($item) => [
                'id'            => data_get($item, 'id'),
                'art_code'      => data_get($item, 'ART_CODIGO'),
                'pedido_id'     => data_get($item, 'pedido_id'),
                'pedido'        => data_get($item, 'pedido'),
                'sel'           => (float) (data_get($item, 'seleccionado', 0)),
                'pendiente'     => (float) (data_get($item, 'pendiente', 0)),
            ])->sortBy('id')->values()->all(),
        ];

        return hash('sha256', json_encode($base));
    }

    protected function sendDeliveryNoteSalesTransferToGp($guide)
    {
        $detalle    = GpGuiaRemisionDetalle::where('guia_remision_id', $guide->id)->get();
        $validate   = $this->validateExistenceDeliveryNoteDisptach($guide->GUI_REM_GUIA_REMISION); //valida la existencia de la guia de remision
        $status     = data_get($validate, 'success', false);
        if ($status) {
            $this->common->assigmentAccessCodeToGuidePending($guide);
            return $guide->refresh();
        }
        $this->sendToDataDeliveryNote($guide, $detalle);
        $this->common->assigmentAccessCodeToGuidePending($guide);
        return $guide->refresh();
    }

    protected function validateExistenceDeliveryNoteDisptach($numGuide)
    {
        return  $this->api->checkExistenceDeliveryNote([
            'numDoc' => $numGuide
        ]);
    }


    protected function sendToDataDeliveryNote($guide, $detalle)
    {
        $response  = $this->api->createDeliveryNote([
            'cabecera'      => $this->builderDispatch->buildDeliveryNoteHeaderToGp($guide),
            'destinatario'  => $this->builderDispatch->buildDeliveryNoteDestinationToGp($guide),
            'detalle'       => $this->prepateDetailReservation($guide, $detalle)
        ]);
        $success = data_get($response, 'success', false);
        if (!$success) {
            throw new \RuntimeException(data_get($response, 'message', 'Opps! Algo salio mal'));
        }
        return $success;
    }


    protected function prepateDetailReservation($guide, $items): array
    {
        $detail = [];
        foreach ($items as $key => $value) {
            $detail[] = $this->builderDispatch->buildDeliveryNoteDetailToGp($guide, $value, $key + 1);
        }
        return $detail;
    }

    protected function saveHeaderDeliveryNoteSalesTransferWeb($payload, $infoGuia)
    {
        $guia = $this->builder->buildDeliveryNoteSalesTransferHeader($payload, $infoGuia);
        $guia->save();
        return $guia->refresh();
    }

    protected function saveDetailDeliveryNoteSalesTransferWeb($guideId, $detalle)
    {
        foreach ($detalle as $val) {
            $detail = $this->builderDispatch->buildDeliveryNoteDispatchDetail($guideId, $val);
            $detail->save();
        }
    }


    protected function updateOrderAndDetail($items)
    {
        foreach ($items as $item) {
            $pedidoId           = data_get($item, 'pedido_id');
            $trasladoId         = data_get($item, 'id');
            $cantSeleccionada   = (float) data_get($item, 'seleccionado', 0);
            GpTrasladosVentas::where('id', $trasladoId)->update([
                'reservado' => DB::raw("COALESCE(reservado, 0) + {$cantSeleccionada}")
            ]);
            GpPedido::where('id', $pedidoId)->update(['transferencia_en_proceso' => 1]);
        }
    }

    protected function validateQtyTransferedItems($items): ?string
    {
        $ids          = collect($items)->pluck('id');
        $transfers    = GpTrasladosVentas::whereIn('id', $ids)->get(['id', 'ART_CODIGO', 'cantidad', 'reservado', 'trasladado'])->keyBy('id');
        foreach ($items as $itm) {
            $id             = data_get($itm, 'id');
            $seleccionado   = (float) data_get($itm, 'seleccionado', 0);
            $dbTransfer     = $transfers->get($id);
            if ($dbTransfer) {
                $actual         = (float)$dbTransfer->reservado + (float)$dbTransfer->trasladado;
                $disponible     = max(0, $dbTransfer->cantidad - $actual);
                if (($actual + $seleccionado) > $dbTransfer->cantidad) {
                    return "El ítem {$dbTransfer->ART_CODIGO} excede el límite permitido. (Máximo disponible: {$disponible}).  Este traslado ya ha sido generado, por favor refresque su ventana.";
                }
            }
        }
        return null;
    }




    /** proceso de generar el egreso de la transferencia de venta  */

    public function dispatchDeliveryNoteSalesTransfer(int $guiaId, GpUsuario $user)
    {
        $guia = GpGuiaRemision::findOrFail($guiaId);
        if ($guia->estado == DeliveryNoteStatus::ENVIADA->value) {
            throw new \RuntimeException("La guia de despacho ya fue enviada por otro usuario, por favor refresque su ventana.");
        }
        $detalles   = GpGuiaRemisionDetalle::where('guia_remision_id', $guia->id)->get();
        if ($detalles->isEmpty()) {
            throw new \RuntimeException("Esta guia de Venta no tiene detalles");
        }
        $itemsIds = collect($detalles)->pluck('detalle_id')->toArray();
        $traslados = $this->getTransferSales($itemsIds);
        if (!$traslados || $traslados->isEmpty()) {
            throw new \RuntimeException(
                'No hay traslados de ventas pendientes para procesar esta guía de venta'
            );
        }

        $grupos = $this->groupItemsByOrderTransferVta($detalles, $traslados);
        foreach ($grupos as $order => $grupo) {
            $hash = $this->generateDeterministicUuidDispatchSalesTransfer($guia->id, $order, $grupo['items'], $traslados);
            $params = $this->builder->buildDeliveryNoteReceivedPayload(
                $guia,
                $order,
                $grupo['ws_items'],
                $user->USERID,
                $hash,
                null
            );
            $this->dispatchDeliveryNoteSalesTransferWeb($guia, $order, $params, $grupo['items'], $hash);
        }

        $guia->update(['estado' => DeliveryNoteStatus::ENVIADA->value]);
        return $guia->fresh();
    }

    protected function dispatchDeliveryNoteSalesTransferWeb(GpGuiaRemision $guia, string $order, array $params, array $items, string $hash)
    {
        $transaction        = TransactionType::TRD;
        $typeMov            = $transaction->value;
        $type               = $transaction->type();
        $response           = $this->api->createTransferGuideVta($params);
        $success            = data_get($response, 'success', false);
        $msg                = data_get($response, 'message');
        $docGp              = trim(data_get($response, 'data.docGp', ''));

        if (!$success) {
            $cabecera               = data_get($params, 'cabecera', []);
            $pyValidate             = $this->builder->buildDeliveryNoteValidateReceivedSalesTransfer($cabecera, $typeMov, $type);
            $respValidate           = $this->api->checkReceivedGuideSalesTransfered($pyValidate);
            $headerRespValidate     = data_get($respValidate, 'data');
            $docGpValidate          = trim(data_get($headerRespValidate, 'docGp', ''));
            if (!empty($headerRespValidate) && filled($docGpValidate)) {
                // Usamos el documento que encontró la validación
                $docGp  = $docGpValidate;
                $data   = $headerRespValidate;
            } else {
                // Si falló la creación y la validación tampoco trajo nada útil, lanzamos el error.
                throw new \RuntimeException($msg ?: 'Error creando el ajuste de guía en GP.');
            }
        } else {
            $data = data_get($response, 'data', []);
        }
        if ($docGp === '') {
            throw new \RuntimeException('No se ha generado el documento de ajuste de guía en Gp.');
        }

        $already = $this->common->checkMovementExistsWeb($guia->id, $order, $docGp, $hash, $typeMov, $type);
        return DB::transaction(function () use ($guia, $order, $data, $items, $hash, $typeMov, $type, $already) {
            if (!$already) {
                $this->processReservedTransferedAndDecrementStock($items);
                $this->common->createMovementTransferSales($guia, $order, $data, $hash, $typeMov, $type);
            }
        });
    }



    protected function getTransferSales($itemsIds, $orderId = null): Collection
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

    protected function groupItemsByOrderTransferVta(Collection $detalle, Collection $traslados): array
    {
        $grupos = [];
        foreach ($detalle as $det) {
            $traslado = $traslados->get($det['detalle_id']);
            if (!$traslado) {
                continue;
            }
            $det->traslado              = $traslado;
            $pedido                     = $traslado->pedido;
            $grupos[$pedido]['pedido']  = $pedido;
            $grupos[$pedido]['items'][] = $det;

            //items para webservice
            $grupos[$pedido]['ws_items'][] = [
                'item'          => $det->GUI_REM_CODI_INTERNO,
                'compartimento' => '',
                'cantidad'      => $det->GUI_REM_CANTIDAD,
            ];
        }
        return $grupos;
    }

    protected function generateDeterministicUuidDispatchSalesTransfer(int $guiaId, string $order, array  $items, Collection $traslados)
    {
        $dataPoints = [];
        foreach ($items as $item) {
            $id = data_get($item, 'detalle_id');
            $traslado = $traslados->get($id);

            if (!$traslado) {
                continue;
            }
            $dataPoints[] = sprintf(
                "ROW:%s|TRA:%s|REM:%s|REQ:%s|RES:%s|MOV:%s",
                data_get($item, 'id'),              // ID del detalle guía
                $traslado->id,                      // <--- NUEVO: ID Único del Traslado
                data_get($item, 'GUI_REM_CANTIDAD'), // Cantidad en Guía
                $traslado->cantidad,                // Cantidad Requerida
                $traslado->reservado,               // Cantidad Reservada
                $traslado->trasladado               // Cantidad Movida (Histórico)
            );
        }
        sort($dataPoints);
        $seed = sprintf(
            "GUID:%s|PED:%s|ITEMS:[%s]",
            $guiaId,
            $order,
            implode(';', $dataPoints)
        );
        $hash = md5($seed);
        return sprintf(
            '%08s-%04s-%04s-%04s-%012s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 12, 4),
            substr($hash, 16, 4),
            substr($hash, 20, 12)
        );
    }


    protected function processReservedTransferedAndDecrementStock($groupItems)
    {
        foreach ($groupItems as $item) {
            $this->common->moveReservedToTransferredForTrfvLine($item->traslado, $item->GUI_REM_CANTIDAD);
            $this->decrementOriginWarehouseReservedStock($item->traslado, $item->GUI_REM_CANTIDAD);
        }
    }
    protected function decrementOriginWarehouseReservedStock($trl, $qty)
    {
        $dtP = GpPedidoDetalle::findOrFail($trl->detalle_id);
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








    /***
     *  proceso de recibir guias de transferencia de venta
     */

    public function receiveDeliveryNoteSalesTransfer(array $payload, GpUsuario $user): string
    {
        $guiaId         = data_get($payload, 'id');
        $guia           = GpGuiaRemision::where('id', $guiaId)->first();
        $detalles       = collect(data_get($payload, 'detalle', []));
        $itemsReceived  = $detalles->filter(fn($item) => (float) data_get($item, 'recibido', 0) > 0);

        if ($itemsReceived->isEmpty()) {
            throw new \RuntimeException('No se han recibido items para procesar la guia de transferencia de venta');
        }
        $limiteExcedido = $this->validatePendingsReceived($itemsReceived->all());
        if ($limiteExcedido) {
            throw new \RuntimeException($limiteExcedido);
        }

        $idsTraslados       = $itemsReceived->pluck('detalle_id')->all();
        $traslados          = GpTrasladosVentas::with('pedido:id,pedido')->whereIn('id', $idsTraslados)->get();
        $allOrders          = $traslados->pluck('pedido.pedido')->filter()->unique()->values()->all();
        $movimientos        = GpGuiaRemisionTransferencia::whereIn('pedido', $allOrders)->where('guia_remision_id', $guiaId)->get()->keyBy('pedido');
        $groupKeyTransfers  = $traslados->keyBy('id');
        $groups             = $this->groupItemsByOrder($itemsReceived, $movimientos, $groupKeyTransfers);

        foreach ($groups as $order => $itemGrp) {
            $items = data_get($itemGrp, 'items', []);
            $hash = $this->generateDeterministicUuidReceivedSalesTransfer($guia->id, $order, $itemGrp['transferencia'], $items);
            $params = $this->builder->buildDeliveryNoteReceivedPayload(
                $guia,
                $order,
                $itemGrp['ws_items'],
                $user->USERID,
                $hash,
                $itemGrp['transferencia']

            );
            
            $this->processReceivedSalesTransfer($guia, $items, $params, $order, $user, $hash);
        }
        $isCompleted = $this->common->validatePendingForReceivedSalesTransfer($guia);
        if ($isCompleted) {
            foreach ($allOrders as $numOrder) {
                $pedido = GpPedido::with(['gpcliente', 'usuario'])
                    ->where('pedido', $numOrder)
                    ->where('estado', OrderStatus::TRASLADO)
                    ->first();
                if ($pedido) {
                    $cliente = $pedido->gpcliente;
                    if ($cliente->CLI_FACTURA_POSTERIOR_AL_DESPACHO == 'SI') {
                        $pedido->factura_posterior_despacho = 1;
                        if ($cliente->CLI_DIA_MAXIMO_MES_FACTURACION == 1) {
                            $pedido->factura_a_demanda = 1;
                        } elseif (date('d') > $cliente->CLI_DIA_MAXIMO_MES_FACTURACION) {
                            $pedido->factura_siguiente_mes = 1;
                        }
                        $pedido->save();
                    }
                    $this->invoiceService->checkSendInvoice($pedido, true);
                    $userOrder = $pedido->usuario;
                    $userOrder->notify(new UserNotification('Articulo/s recibidos', 'Articulo/s del pedido ' . $pedido->pedido . ' han sido recibidos COMPLETAMENTE en la bodega ' . $pedido->BOD_BODEGA));
                }
            }
            return 'Guía de remisión recibida completamente';
        } else {
            foreach ($allOrders as $numOrder) {
                $pedido = GpPedido::with('usuario')->where('pedido', $numOrder)
                    ->where('estado', OrderStatus::TRASLADO)->first();
                if ($pedido) {
                    $this->invoiceService->checkTrasladado($pedido);
                    if ($pedido->estado == OrderStatus::ESPERA) {
                        $this->invoiceService->checkSendInvoice($pedido, true);
                        $creatorUser = $pedido->usuario;
                        $creatorUser->notify(new UserNotification('Articulo/s recibidos', "Artículo/s del pedido {$pedido->pedido} han sido recibidos PARCIALMENTE en la bodega {$pedido->BOD_BODEGA}"));
                    }
                }
            }
            return 'Guía de remisión recibida parcialmente';
        }
    }


    protected function groupItemsByOrder($items, $movimientos, $traslados): array
    {
        $groups = [];
        foreach ($items as $itm) {
            $trv = $traslados->get(data_get($itm, 'detalle_id')); //obtengo el traslado+ pedido
            if (!$trv) {
                continue;
            }
            $pedido = data_get($trv, 'pedido.pedido'); //obtengo el numero de pedido
            $mov = data_get($movimientos->get($pedido), 'codigo'); //obtengo el movimiento de la guia de remision transferencia
            $itm['traslado'] = $trv;

            $groups[$pedido]['pedido']          = $pedido;
            $groups[$pedido]['transferencia']   = $mov ?? '';
            $groups[$pedido]['items'][]         = $itm;
            $groups[$pedido]['ws_items'][] = [
                'item'                  => data_get($itm, 'GUI_REM_CODI_INTERNO', ''),
                'compartimento'         => '',
                'cantidad'              => (float) (data_get($itm, 'recibido', 0))
            ];
        }
        return $groups;
    }

    protected function processReceivedSalesTransfer(GpGuiaRemision $guia, array $items, array $params, string $order, GpUsuario $user, $hash)
    {
        $transaction        = TransactionType::RTD;
        $typeMov            = $transaction->value;
        $type               = $transaction->type();
        $resp               = $this->api->createReceiveGuideSalesTransfered($params);
        $success            = data_get($resp, 'success', false);
        $msg                = data_get($resp, 'message');
        $docGp              = trim(data_get($resp, 'data.docGp', ''));

        if (!$success) {
            $cabecera               = data_get($params, 'cabecera', []);
            $pyValidate             = $this->builder->buildDeliveryNoteValidateReceivedSalesTransfer($cabecera, $typeMov, $type);
            $respValidate           = $this->api->checkReceivedGuideSalesTransfered($pyValidate);
            $headerRespValidate     = data_get($respValidate, 'data');
            $docGpValidate          = trim(data_get($headerRespValidate, 'docGp', ''));
            if (!empty($headerRespValidate) && filled($docGpValidate)) {
                // Usamos el documento que encontró la validación
                $docGp  = $docGpValidate;
                $data   = $headerRespValidate;
            } else {
                // Si falló la creación y la validación tampoco trajo nada útil, lanzamos el error.
                throw new \RuntimeException($msg ?: 'Error creando el ajuste de guía en GP.');
            }
        } else {
            $data = data_get($resp, 'data', []);
        }
        if ($docGp === '') {
            throw new \RuntimeException('No se ha generado el documento de ajuste de guía en Gp.');
        }
        $already = $this->common->checkMovementExistsWeb($guia->id, $order, $docGp, $hash, $typeMov, $type);
        DB::transaction(function () use ($guia, $items, $order,  $data, $user, $already, $hash, $typeMov, $type) {
            if (!$already) {
                $this->updateQtyTranferedItemsWeb($guia, $items);
                $this->createRegisterHistorySalesTransfer($guia, $items, $user, $order);
                $this->common->createMovementTransferSales($guia, $order, $data, $hash, $typeMov, $type);
            }
        });
    }


    protected function updateQtyTranferedItemsWeb(GpGuiaRemision $guia, $items)
    {
        foreach ($items as  $item) {
            $traslado =  data_get($item, 'traslado');
            $this->updateQtyOrdersStockWeb($guia, $item, $traslado);
        }
    }


    protected function updateQtyOrdersStockWeb(GpGuiaRemision $guia, $item, $traslado)
    {
        $detalleId          = data_get($traslado, 'detalle_id');
        $guiaDetailId       = data_get($item, 'id');
        $recibido           = max(0, (float) data_get($item, 'recibido', 0));

        GpPedidoDetalleStock::where('detalle_id', $detalleId)
            ->where('STO_ART_BODEGA', $guia->bodega_origen)
            ->where('seleccionado', '>=', $recibido)
            ->decrement('seleccionado', $recibido);

        GpPedidoDetalleStock::where('detalle_id', $detalleId)
            ->where('STO_ART_BODEGA', $guia->bodega_destino)
            ->update([
                'seleccionado' => DB::raw("seleccionado + {$recibido}"),
                'reservado'    => DB::raw("reservado + {$recibido}")
            ]);

        // Evita negativos: limita lo que vas a descontar
        GpGuiaRemisionDetalle::find($guiaDetailId)->increment('recibido', $recibido);
    }


    protected function createRegisterHistorySalesTransfer($guia, $items, $user, $order)
    {
        $ing = $this->builder->createRegisterHistoryHeader($guia, $user, $order, 'traslado_venta');
        $ing->save();
        foreach ($items as $item) {
            $detail = $this->builder->ceateRegisterHistoryDetail($item, $ing);
            $detail->save();
        }
    }


    protected function validatePendingsReceived($items): ?string
    {
        $ids        = collect($items)->pluck('id');
        $detalles   = GpGuiaRemisionDetalle::whereIn('id', $ids)->get(
            ['id', 'GUI_REM_CODI_INTERNO', 'GUI_REM_CANTIDAD', 'recibido']
        )->keyBy('id');
        foreach ($items as $itm) {
            $id         = data_get($itm, 'id');
            $recibido   = data_get($itm, 'recibido', 0);
            $dbItem     = $detalles->get($id);
            if ($dbItem) {
                $actual         = (float)$dbItem->recibido;
                $disponible     = max(0, $dbItem->GUI_REM_CANTIDAD - $actual);

                if (($actual + $recibido) > $dbItem->GUI_REM_CANTIDAD) {
                    return "El ítem {$dbItem->GUI_REM_CODI_INTERNO} excede el límite permitido. (Máximo disponible: {$disponible}).";
                }
            }
        }
        return null;
    }

    protected function generateDeterministicUuidReceivedSalesTransfer(int $guiaId, string $order, string $numTransfer, array $items): string
    {
        $dataPoints = [];
        $ids        = collect($items)->pluck('id');
        $detalles   = GpGuiaRemisionDetalle::whereIn('id', $ids)->get(
            ['id', 'GUI_REM_CODI_INTERNO', 'GUI_REM_CANTIDAD', 'recibido']
        )->keyBy('id');
        foreach ($items as  $itm) {
            $id         = data_get($itm, 'id');
            $dbItem     = $detalles->get($id);
            if (!$dbItem) {
                continue;
            }
            $qty            = data_get($itm, 'recibido', 0);
            $cantidad       = data_get($itm, 'GUI_REM_CANTIDAD', 0);
            $recibidoH      = data_get($dbItem, 'recibido', 0);
            $pendiente  = max(0, $cantidad - $recibidoH);

            $dataPoints[] = sprintf(
                "ROW:%s|QTY:%.4f|REC_H:%.4f |PEND:%.4f",
                data_get($itm, 'id'),
                $qty,
                $recibidoH,
                $pendiente
            );
        }

        sort($dataPoints);
        $seed = sprintf(
            "GUID:%s|QT:%s|TRF:%s|ITEMS:[%s]",
            $guiaId,
            $order,
            $numTransfer,
            implode(';', $dataPoints)
        );
        $hash = md5($seed);
        return sprintf(
            '%08s-%04s-%04s-%04s-%012s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 12, 4),
            substr($hash, 16, 4),
            substr($hash, 20, 12)
        );
    }
}
