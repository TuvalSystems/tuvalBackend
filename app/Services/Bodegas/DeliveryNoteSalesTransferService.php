<?php

namespace App\Services\Bodegas;

use App\Constants\Bodegas\DeliveryNoteConstants;
use App\Enums\bodegas\guiaremision\DeliveryNoteStatus;
use App\Enums\bodegas\guiaremision\DeliveryNoteType;
use App\Enums\bodegas\guiaremision\ReasonForTransfer;
use App\Enums\Ventas\OrderStatus;
use App\Models\despachos\GpGuiaRemision;
use App\Models\despachos\GpGuiaRemisionDetalle;
use App\Models\despachos\GpGuiaRemisionTransferencia;
use App\Models\GpPedido;
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



    ) {
        // Inyecta dependencias si es necesario
    }

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
            return $guia;
        });
        $this->updateOrderAndDetail($items);
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
        $detalle = GpGuiaRemisionDetalle::where('guia_remision_id', $guide->id)->get();
        $validate = $this->validateExistenceDeliveryNoteDisptach($guide->GUI_REM_GUIA_REMISION); //valida la existencia de la guia de remision
        $status = data_get($validate, 'success', false);
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
            throw new \Exception(data_get($response, 'message', 'Opps! Algo salio mal'));
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
            GpPedido::where('id', data_get($item, 'pedido_id'))->update(['transferencia_en_proceso' => 1]);
            GpTrasladosVentas::where('id', data_get($item, 'id'))->increment('reservado', data_get($item, 'seleccionado', 0));
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
        $limiteExcedido = $this->validatePendingForReceivedSalesTransfer($itemsReceived->all());
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
            $params = $this->builder->buildDeliveryNoteReceivedPayload(
                $guia,
                $order,
                $itemGrp['transferencia'],
                $itemGrp['ws_items'],
                $user->USERID
            );
            $this->processReceivedSalesTransfer($guia, $items, $params, $order, $user);
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

    protected function processReceivedSalesTransfer(GpGuiaRemision $guia, array $items, array $params, string $order, GpUsuario $user)
    {
        $resp       = $this->api->createReceiveGuideSalesTransfered($params);
        $success    = data_get($resp, 'success', false);
        $msg        = data_get($resp, 'message');
        $docGp      = trim(data_get($resp, 'data.docGp', ''));

        if (!$success) {
            $cabecera               = data_get($params, 'cabecera', []);
            $pyValidate             = $this->builder->buildDeliveryNoteValidateReceivedSalesTransfer($cabecera, 'RTD');
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
        $already = $this->common->checkMovementExistsWeb($guia->id, $order, $docGp);
        DB::transaction(function () use ($guia, $items, $order, $docGp, $data, $user, $already) {
            if (!$already) {
                $this->updateQtyTranferedItemsWeb($guia, $items);
                $this->createRegisterHistorySalesTransfer($guia, $items, $user, $order);
                $this->common->createMovementTransferSales($guia, $order, $docGp);
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
            ->where('STO_ART_BODEGA', $guia->bodega_origen)->decrement('seleccionado', $recibido);

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


    protected function validatePendingForReceivedSalesTransfer($items): ?string
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
}
