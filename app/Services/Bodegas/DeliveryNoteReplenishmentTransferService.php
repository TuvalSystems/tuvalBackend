<?php

namespace App\Services\Bodegas;

use App\Constants\Bodegas\DeliveryNoteConstants;
use App\Enums\bodegas\guiaremision\DeliveryNoteStatus;
use App\Enums\bodegas\guiaremision\DeliveryNoteType;
use App\Enums\bodegas\guiaremision\ReasonForTransfer;
use App\Models\despachos\GpGuiaRemision;
use App\Models\despachos\GpGuiaRemisionDetalle;
use App\Models\despachos\GpGuiaRemisionTransferencia;
use App\Models\GpUsuario;
use App\Models\reposicion\GpTrasladosReposicion;
use App\Services\Api\Bodegas\ApiDeliverNoteService;
use App\Services\Bodegas\Contracts\DeliveryNoteCommonServiceInterface;
use App\Services\Bodegas\Contracts\DeliveryNoteReplenishmentTransferServiceInterface;
use App\Services\Bodegas\Contracts\LogisticsOperationsServiceInterface;
use App\Services\Builders\Bodega\Contracts\DeliveryNoteDispatchParamsBuilderInterface;
use App\Services\Builders\Bodega\Contracts\DeliveryNoteSalesTransferBuilderInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DeliveryNoteReplenishmentTransferService implements DeliveryNoteReplenishmentTransferServiceInterface
{

    public function __construct(
        protected readonly DeliveryNoteCommonServiceInterface $common,
        protected readonly ApiDeliverNoteService $api,
        protected readonly LogisticsOperationsServiceInterface $logistic,
        protected readonly DeliveryNoteDispatchParamsBuilderInterface $builderDispatch,
        protected readonly DeliveryNoteSalesTransferBuilderInterface $builder
    ) {}


    public function createDeliveryNoteReplenishmentTransfer(array $payload, GpUsuario $user)
    {
        $items              = data_get($payload, 'items', []);
        $error = $this->validateQtyReplenishmentTransfer($items);
        if ($error) {
            abort(409, $error);
        }


        $tipo               = DeliveryNoteType::REPOSICION->value;
        $ptoEmision         = DeliveryNoteConstants::PTO_EMISION_DEFAULT;
        $primerItem         = data_get($payload, 'items.0', []);
        $documento          = data_get($primerItem, 'Documento', []);
        $bodegaOrigen       = data_get($primerItem, 'bodega_origen');
        $bodegaDestino      = data_get($primerItem, 'bodega_destino');
        $bodGuide           = $this->common->getWarehouseDescriptionOrigin($bodegaOrigen);
        $ptoOrigen          = $this->common->getPtoVtaByWarehouse($bodegaOrigen);
        $ptoDestino         = $this->common->getPtoVtaByWarehouse($bodegaDestino);
        $dirOrigen          = $this->common->getDirection($ptoOrigen);
        $dirDestino         = $this->common->getDirection($ptoDestino);
        $secuencial         = $this->common->getNextGuideSequential($ptoOrigen, $ptoEmision);
        $numGuide           = $this->common->formatGuideNumber($ptoOrigen, $ptoEmision, $secuencial);
        $idemKey            = $this->makeIdempotencyKeyReplenishmentTransfer($payload,  $tipo, $primerItem,  $ptoOrigen, $ptoDestino, $secuencial, $numGuide);

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
                ->where('tipo', DeliveryNoteType::REPOSICION->value)
                ->where('estado', DeliveryNoteStatus::PENDIENTE->value)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                $existing->update(['estado' => DeliveryNoteStatus::PENDIENTE->value]);
                return $existing->refresh();
            }
            try {
                $guia = $this->saveHeaderDeliveryNoteReplenishmentTransferWeb($payload, $infoGuia);
                $this->saveDetailDeliveryNoteReplenishmentTransferWeb($guia->id, $items);
                $this->updateTransferReposition($items);
                return $guia;
            } catch (\Throwable $e) {
                if ($e->errorInfo[1] == 1062) {
                    abort(409, 'El número de guía (' . data_get($infoGuia, 'numeroGuia') . ') acaba de ser tomado por otro proceso. Por favor, intente generar la guía nuevamente.');
                }
                throw $e;
            }
        });
        $this->sendDeliveryNoteSalesTransferToGp($guia);
        return $guia->refresh();
    }


    protected function makeIdempotencyKeyReplenishmentTransfer(array $payload, string $tipo, array $primerItem, string $ptoOrigen, string $ptoDestino, string $secuencial, string $numGuide): string
    {
        $base = [
            'pto_origen'        => $ptoOrigen,
            'pto_destino'       => $ptoDestino,
            'tipo'              => $tipo,
            'bodega_origen'     => data_get($primerItem, 'bodega_origen'),
            'bodega_destino'    => data_get($primerItem, 'bodega_destino'),
            'secuencial'        => $secuencial,
            'num_guide'         => $numGuide,
            'fecha_inicio'      => data_get($payload, 'fecha_inicio'),
            'fecha_fin'         => data_get($payload, 'fecha_fin'),
            'detalle'           => collect(data_get($payload, 'items', []))->map(fn($item) => [
                'id'            => data_get($item, 'id'),
                'art_code'      => data_get($item, 'ART_CODIGO'),
                'cabecera_id'   => data_get($item, 'cabecera_id'),
                'Documento'     => data_get($item, 'Documento'),
                'seleccionado'  => (float) (data_get($item, 'seleccionado', 0)),
                'pendiente'     => (float) (data_get($item, 'pendiente', 0)),
            ])->sortBy('id')->values()->all(),
        ];

        return hash('sha256', json_encode($base));
    }


    protected function saveHeaderDeliveryNoteReplenishmentTransferWeb($payload, $infoGuia)
    {
        $guia = $this->builder->buildDeliveryNoteSalesTransferHeader($payload, $infoGuia);
        $guia->save();
        return $guia->refresh();
    }

    protected function saveDetailDeliveryNoteReplenishmentTransferWeb($guideId, $detalle)
    {
        foreach ($detalle as $val) {
            $detail = $this->builderDispatch->buildDeliveryNoteDispatchDetail($guideId, $val);
            $detail->save();
        }
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


    protected function updateTransferReposition($items)
    {
        foreach ($items as $item) {
            GpTrasladosReposicion::where('id', data_get($item, 'id'))
                ->increment('reservado', (float) data_get($item, 'seleccionado', 0));
        }
    }


    protected function validateQtyReplenishmentTransfer(array $items)
    {
        $ids = collect($items)->pluck('id');
        $repositions  = GpTrasladosReposicion::whereIn('id', $ids)
            ->get(['id', 'cantidad', 'reservado', 'trasladado'])
            ->keyBy('id');

        foreach ($items as $itm) {
            $id = data_get($itm, 'id');
            $seleccionado = (float) data_get($itm, 'seleccionado', 0);
            $dbReposition = $repositions->get($id);

            if ($dbReposition) {
                $actual = (float)$dbReposition->reservado + (float)$dbReposition->trasladado;
                $disponible = max(0, $dbReposition->cantidad - $actual);
                if (($actual + $seleccionado) > $dbReposition->ART_CANTIDAD) {
                    return "El ítem {$dbReposition->ART_CODIGO} excede el límite permitido. (Máximo disponible: {$disponible}).  Este traslado ya ha sido generado, por favor refresque su ventana.";
                }
            }
            return null;
        }
    }



    /** proceso de recibir una guia de reposicion */

    public function receiveDeliveryNoteReplenishmentTransfer(array $payload, GpUsuario $user): string
    {
        $idempotencyKey     = (string) Str::uuid();
        $guiaId             = data_get($payload, 'id');
        $guia               = GpGuiaRemision::where('id', $guiaId)->first();
        $detalles           = collect(data_get($payload, 'detalle', []));
        $itemsReceived      = $detalles->filter(fn($item) => (float) data_get($item, 'recibido', 0) > 0);
        if ($itemsReceived->isEmpty()) {
            throw new \RuntimeException('No se han recibido items para procesar la guia de transferencia de reposición');
        }

        $idsTraslados       = $itemsReceived->pluck('detalle_id')->all();
        $traslados          = GpTrasladosReposicion::whereIn('id', $idsTraslados)->get();
        $allDocuments       = $traslados->pluck('Documento')->filter()->unique()->values()->all();
        $movimientos        = GpGuiaRemisionTransferencia::whereIn('pedido', $allDocuments)->where('guia_remision_id', $guiaId)->get()->keyBy('pedido');
        $groupKeyTransfers  = $traslados->keyBy('id');
        $groups             = $this->groupItemsByOrder($itemsReceived, $movimientos, $groupKeyTransfers);
        foreach ($groups as $order =>  $group) {
            $itemGrp    = data_get($group, 'items', []);
            $movimiento = data_get($group, 'transferencia', '');
            $wsItems    = data_get($group, 'ws_items', []);
            $params     = $this->builder->buildDeliveryNoteReceivedPayload($guia, $order, $movimiento, $wsItems, $user->USERID);
            $this->processReceivedDeliveryReplenishmentTransfer($guia,  $itemGrp, $params, $order, $user);
        }
        $isCompleted = $this->common->validatePendingForReceivedSalesTransfer($guia);
        if ($isCompleted) {
            return 'Guía de remisión recibida completamente';
        }
        return 'Guía de remisión recibida parcialmente';
    }


    protected function groupItemsByOrder($items, $movimientos, $traslados): array
    {
        $groups = [];
        foreach ($items as $itm) {
            $trv = $traslados->get(data_get($itm, 'detalle_id'));
            if (!$trv) {
                continue;
            }
            $pedido             = data_get($trv, 'Documento');
            $mov                = data_get($movimientos->get($pedido), 'codigo');
            $itm['traslado']    = $trv;

            $groups[$pedido]['pedido']          = $pedido;
            $groups[$pedido]['transferencia']   = $mov ?? '';
            $groups[$pedido]['items'][]         = $itm;
            $groups[$pedido]['ws_items'][]      = [
                'item'                  => data_get($itm, 'GUI_REM_CODI_INTERNO', ''),
                'compartimento'         => data_get($itm, 'compartimiento', ''),
                'cantidad'              => (float) (data_get($itm, 'recibido', 0))
            ];
        }
        return $groups;
    }


    protected function processReceivedDeliveryReplenishmentTransfer(GpGuiaRemision $guia, array $items, array $params, string $order, GpUsuario $user)
    {
        $resp       = $this->api->createReceiveGuideReplenishmentTransfered($params);
        $success    = data_get($resp, 'success', false);
        $msg        = data_get($resp, 'message');
        $docGp      = trim(data_get($resp, 'data.docGp', ''));
        if (!$success) {
            $cabecera                   = data_get($params, 'cabecera', []);
            $pyValidate                 = $this->builder->buildDeliveryNoteValidateReceivedSalesTransfer($cabecera, 'RTR');
            $respValidate               = $this->api->checkReceivedGuideSalesTransfered($pyValidate);
            $headerRespValidate         = data_get($respValidate, 'data');
            $docGpValidate              = trim(data_get($headerRespValidate, 'docGp', ''));
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
                $this->updateQtyTranferedItemsWeb($items);
                $this->createRegisterHistorySalesTransfer($guia, $items, $user, $order);
                $this->common->createMovementTransferSales($guia, $order, $docGp);
            }
        });
    }



    protected function updateQtyTranferedItemsWeb(array $items)
    {
        $ids            = collect($items)->pluck('id');
        $detallesGuias  = GpGuiaRemisionDetalle::whereIn('id', $ids)->get()->keyBy('id');
        foreach ($items as  $item) {
            $idd                = data_get($item, 'id');
            $cantidadRecibida   = data_get($item, 'recibido', 0);

            if ($detail = $detallesGuias->get($idd)) {
                $detail->increment('recibido', $cantidadRecibida);
            }
        }
    }


    protected function createRegisterHistorySalesTransfer($guia, $items, $user, $order)
    {
        $ing = $this->builder->createRegisterHistoryHeader($guia, $user, $order, 'traslado_reposicion');
        $ing->save();
        foreach ($items as $item) {
            $detail = $this->builder->ceateRegisterHistoryDetail($item, $ing);
            $detail->save();
        }
    }
}
