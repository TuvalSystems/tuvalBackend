<?php

namespace App\Services\Bodegas;

use App\Constants\Bodegas\DeliveryNoteConstants;
use App\Enums\bodegas\guiaremision\DeliveryNoteStatus;
use App\Enums\bodegas\guiaremision\DeliveryNoteType;
use App\Enums\bodegas\guiaremision\ReasonForTransfer;
use App\Models\despachos\GpGuiaRemision;
use App\Models\despachos\GpGuiaRemisionDetalle;
use App\Models\GpUsuario;
use App\Models\reposicion\GpTrasladosReposicion;
use App\Services\Api\Bodegas\ApiDeliverNoteService;
use App\Services\Bodegas\Contracts\DeliveryNoteCommonServiceInterface;
use App\Services\Bodegas\Contracts\DeliveryNoteReplenishmentTransferServiceInterface;
use App\Services\Bodegas\Contracts\LogisticsOperationsServiceInterface;
use App\Services\Builders\Bodega\Contracts\DeliveryNoteDispatchParamsBuilderInterface;
use App\Services\Builders\Bodega\Contracts\DeliveryNoteSalesTransferBuilderInterface;
use Illuminate\Support\Facades\DB;

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
        $items              = data_get($payload, 'items', []);

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
            $guia = $this->saveHeaderDeliveryNoteReplenishmentTransferWeb($payload, $infoGuia);
            $this->saveDetailDeliveryNoteReplenishmentTransferWeb($guia->id, $items);
            $this->updateTransferReposition($items);
            return $guia;
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


    protected function updateTransferReposition($items){
        foreach ($items as $item) {
            GpTrasladosReposicion::where('id', data_get($item, 'id'))
               ->increment('reservado', (float) data_get($item, 'seleccionado', 0));
        }
    }
}
