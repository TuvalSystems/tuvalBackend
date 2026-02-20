<?php

namespace App\Services\Bodegas;

use App\Constants\Bodegas\DeliveryNoteConstants;
use App\Enums\bodegas\guiaremision\DeliveryNoteStatus;
use App\Enums\bodegas\guiaremision\DeliveryNoteType;
use App\Enums\bodegas\guiaremision\ReasonForTransfer;
use App\Events\Bodegas\Dashboard\DispatchPendings;
use App\Models\GpUsuario;
use App\Models\GpPedidoDetalle;
use App\Models\GpClienteSucursal;
use Illuminate\Support\Facades\DB;
use App\Models\despachos\GpGuiaRemision;
use App\Models\despachos\GpGuiaRemisionDetalle;
use App\Services\Api\Bodegas\ApiDeliverNoteService;
use App\Services\Bodegas\Contracts\DeliveryNoteCommonServiceInterface;
use App\Services\Bodegas\Contracts\DeliveryNoteDispatchServiceInterface;
use App\Services\Bodegas\Contracts\LogisticsOperationsServiceInterface;
use App\Services\Builders\Bodega\Contracts\DeliveryNoteDispatchParamsBuilderInterface;


class DeliveryNoteDispatchService implements DeliveryNoteDispatchServiceInterface
{

    public function __construct(
        protected readonly DeliveryNoteCommonServiceInterface $common,
        protected readonly ApiDeliverNoteService $apiDelivery,
        protected readonly LogisticsOperationsServiceInterface $logistic,
        protected readonly DeliveryNoteDispatchParamsBuilderInterface $builder
    ) {}


    /**
     * PROCESO DE CREACION DE GUIA DE DESPACHO
     */

    public function createDeliveryNoteFromDispatch($payload, GpUsuario $user)
    {
        $tipo           = DeliveryNoteType::DESPACHO->value;
        $ptoEmision     = DeliveryNoteConstants::PTO_EMISION_DEFAULT;
        $pedido         = data_get($payload, 'orden.pedido', []);
        $direccionEnvio = GpClienteSucursal::where('id', data_get($pedido, 'sucursal_id'))->value('CLI_SUC_DIRECCION');
        $secuencial     = $this->common->getNextGuideSequential(data_get($payload, 'orden.PTO_VTA_ID'), $ptoEmision);
        $dirOrigin      = $this->common->getDirection(data_get($payload, 'orden.PTO_VTA_ID'));
        $bodGuide       = $this->common->getWarehouseDescriptionOrigin(data_get($payload, 'orden.BOD_BODEGA'));
        $idemKey        = $this->makeIdemKeyDispatch($payload, $pedido, $tipo);
        $numGuide       = $this->common->formatGuideNumber(data_get($payload, 'orden.PTO_VTA_ID'), $ptoEmision, $secuencial);
        $items          = data_get($payload, 'items', []);

        $infoGuia = [
            'tipo'              => $tipo,
            'ptoEmision'        => $ptoEmision,
            'numeroGuia'        => $numGuide,
            'user'              => $user,
            'idemKey'           => $idemKey,
            'secuencial'        => $secuencial,
            'bodGuide'          => $bodGuide,
            'dirOrigin'         => $dirOrigin,
            'pedido'            => $pedido,
            'direccionEnvio'    => $direccionEnvio,
            'docuSustento'      => DeliveryNoteConstants::DOC_SUSTENTO_FACTURA,
            'motivoTraslado'    => ReasonForTransfer::VENTA->value,
        ];
        $guia = DB::transaction(function () use ($payload, $infoGuia, $items) {
            $existing = GpGuiaRemision::where('idempotency_key', data_get($infoGuia, 'idemKey'))
                ->lockForUpdate()
                ->first();
            if ($existing) {
                DB::afterCommit(function () use ($existing) {
                    $this->sendDeliveryNoteDispatchToGp($existing);
                    $existing->update(['estado' => DeliveryNoteStatus::PENDIENTE->value]);
                });
                return $existing;
            }

            //proceso de creacion de guia de remision web
            $guia = $this->saveHeaderDeliveryNoteDispatchWeb($payload, $infoGuia);
            $this->saveDetaildeliveryNoteDispatchWeb($guia->id, $items);
            $this->updateDetailOrder($items);
            return $guia;
        });
        //despues de que se completara la guia procede a enviarse a gp para que este proceso no entre en el
        //flujo de la transaccion de mysql
        $this->sendDeliveryNoteDispatchToGp($guia);
        if ($this->common->isGUideBlockedForOrder($guia)) {
            $this->common->createTemporaryDeliveryNoteBlocksOrder($guia, $user);
        }
        event(new DispatchPendings('refresh')); //evento para la actualizadon de dashboard de items serv pendientes y ordenes de despachos
        return $guia->refresh();
    }


    protected function makeIdemKeyDispatch(array $payload, array $pedido, string $tipo)
    {
        $base = [
            'bodega'        => data_get($pedido, 'BOD_BODEGA'),
            'pedido'        => data_get($pedido, 'pedido'),
            'tipo'          => $tipo,
            'sucursal_id'   => data_get($pedido, 'sucursal_id'),
            'pto_vta_id'    => data_get($pedido, 'PTO_VTA_ID'),
            'vehiculo'      => data_get($payload, 'vehiculo.id') ?? data_get($payload, 'vehiculo.placa'),
            'transportista' => data_get($payload, 'transportista.id') ?? data_get($payload, 'transportista.identificacion'),
            'fecha_inicio'  => data_get($payload, 'fecha_inicio'),
            'fecha_fin'     => data_get($payload, 'fecha_fin'),
            'detalle'       => collect(data_get($payload, 'items'))->map(fn($d) => [
                'id'            => data_get($d, 'id'),
                'sel'           => (float) (data_get($d, 'seleccionado',  0)),
                'pendiente'      => (float) (data_get($d, 'pendiente',  0)),
            ])->sortBy('id')->values()->all(),
        ];

        return hash('sha256', json_encode($base));
    }


    protected function saveHeaderDeliveryNoteDispatchWeb($payload, $infoGuia)
    {
        $guia = $this->builder->buildDeliveryNoteDispatchHeader($payload, $infoGuia);
        $guia->save();
        return $guia->refresh();
    }

    protected function saveDetaildeliveryNoteDispatchWeb($guideId, $detalle)
    {
        foreach ($detalle as $val) {
            $detail = $this->builder->buildDeliveryNoteDispatchDetail($guideId, $val);
            $detail->save();
        }
    }

    protected function updateDetailOrder($items)
    {
        foreach ($items as $item) {
            GpPedidoDetalle::where('id', $item['id'])
                ->increment('reservado', data_get($item, 'seleccionado', 0));
        }
    }



    protected function sendDeliveryNoteDispatchToGp($guide)
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
        return  $this->apiDelivery->checkExistenceDeliveryNote([
            'numGuia' => $numGuide
        ]);
    }

    protected function sendToDataDeliveryNote($guide, $detalle)
    {
        $response  = $this->apiDelivery->createDeliveryNote([
            'cabecera'      => $this->builder->buildDeliveryNoteHeaderToGp($guide),
            'destinatario'  => $this->builder->buildDeliveryNoteDestinationToGp($guide),
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
            $detail[] = $this->builder->buildDeliveryNoteDetailToGp($guide, $value, $key + 1);
        }
        return $detail;
    }
}
