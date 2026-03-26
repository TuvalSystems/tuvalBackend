<?php

namespace App\Services\Bodegas;

use App\Constants\Bodegas\DeliveryNoteConstants;
use App\Enums\bodegas\guiaremision\DeliveryNoteStatus;
use App\Enums\bodegas\guiaremision\DeliveryNoteType;
use App\Enums\bodegas\guiaremision\ReasonForTransfer;
use App\Enums\bodegas\guiaremision\TransactionType;
use App\Events\Bodegas\Dashboard\DispatchPendings;
use App\Models\GpUsuario;
use App\Models\GpPedidoDetalle;
use App\Models\GpClienteSucursal;
use Illuminate\Support\Facades\DB;
use App\Models\despachos\GpGuiaRemision;
use App\Models\despachos\GpGuiaRemisionDespacho;
use App\Models\despachos\GpGuiaRemisionDetalle;
use App\Models\GpArticulo;
use App\Models\GpPedido;
use App\Models\GpPedidoDetalleStock;
use App\Models\GpRelacionPuntoVentaBodega;
use App\Services\Api\Bodegas\ApiDeliverNoteService;
use App\Services\Bodegas\Contracts\DeliveryNoteCommonServiceInterface;
use App\Services\Bodegas\Contracts\DeliveryNoteDispatchServiceInterface;
use App\Services\Bodegas\Contracts\LogisticsOperationsServiceInterface;
use App\Services\Builders\Bodega\Contracts\DeliveryNoteDispatchParamsBuilderInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DeliveryNoteDispatchService implements DeliveryNoteDispatchServiceInterface
{

    public function __construct(
        protected readonly DeliveryNoteCommonServiceInterface $common,
        protected readonly ApiDeliverNoteService $api,
        protected readonly LogisticsOperationsServiceInterface $logistic,
        protected readonly DeliveryNoteDispatchParamsBuilderInterface $builder
    ) {}


    /**
     * PROCESO DE CREACION DE GUIA DE DESPACHO
     */

    public function createDeliveryNoteFromDispatch($payload, GpUsuario $user)
    {
        $items          = data_get($payload, 'items', []);
        $tipo           = DeliveryNoteType::DESPACHO->value;
        $ptoEmision     = DeliveryNoteConstants::PTO_EMISION_DEFAULT;
        $pedido         = data_get($payload, 'orden.pedido', []);
        $ptoVta         = GpRelacionPuntoVentaBodega::where('PTO_VTA_BOD_BODEGA', data_get($payload, 'orden.BOD_BODEGA'))->value('PTO_VTA_BOD_PUNTO_VENTA');
        if (!$ptoVta) {
            throw new \RuntimeException("No se encontró un Punto de Venta configurado para la bodega: " . data_get($payload, 'orden.BOD_BODEGA'));
        }
        $direccionEnvio = GpClienteSucursal::where('id', data_get($pedido, 'sucursal_id'))->value('CLI_SUC_DIRECCION');
        $dirOrigin      = $this->common->getDirection($ptoVta);
        $bodGuide       = $this->common->getWarehouseDescriptionOrigin(data_get($payload, 'orden.BOD_BODEGA'));
        $idemKey        = $this->makeIdemKeyDispatch($payload, $pedido, $tipo, $ptoVta);


        $lock = Cache::lock('create_dispatch_guide_' . $idemKey, 30);
        if (!$lock->get()) {
            throw new \RuntimeException("Esta guía ya está en proceso de creación. Por favor, espere un momento o actualice la página.");
        }

        try {
            $lockSequential = Cache::lock("lock_sequential_{$ptoVta}_{$ptoEmision}", 15);
            $infoGuia = [
                'tipo'              => $tipo,
                'ptoEmision'        => $ptoEmision,
                'user'              => $user,
                'idemKey'           => $idemKey,
                'bodGuide'          => $bodGuide,
                'dirOrigin'         => $dirOrigin,
                'pedido'            => $pedido,
                'direccionEnvio'    => $direccionEnvio,
                'docuSustento'      => DeliveryNoteConstants::DOC_SUSTENTO_FACTURA,
                'motivoTraslado'    => ReasonForTransfer::VENTA->value,
                'ptoVta'           => $ptoVta
            ];

            $guia = $lockSequential->block(5, function () use ($payload, &$infoGuia, $items, $ptoVta, $ptoEmision) {
                return DB::transaction(function () use ($payload, &$infoGuia, $items, $ptoVta, $ptoEmision) {
                    $error = $this->validateQtyDetails($items);
                    if ($error) {
                        throw new \RuntimeException($error);
                    }
                    $existing = GpGuiaRemision::where('idempotency_key', data_get($infoGuia, 'idemKey'))
                        ->where('tipo', DeliveryNoteType::DESPACHO->value)->where('estado', DeliveryNoteStatus::PENDIENTE->value)
                        ->lockForUpdate()
                        ->first();
                    if ($existing) {
                        return $existing;
                    }
                    $secuencial             = $this->common->getNextGuideSequential($ptoVta, $ptoEmision);
                    $numGuide               = $this->common->formatGuideNumber($ptoVta, $ptoEmision, $secuencial);
                    $infoGuia['secuencial'] = $secuencial;
                    $infoGuia['numeroGuia'] = $numGuide;
                    $guia = $this->saveHeaderDeliveryNoteDispatchWeb($payload, $infoGuia);
                    $this->saveDetaildeliveryNoteDispatchWeb($guia->id, $items);
                    return $guia;
                });
            });

            if ($guia->wasRecentlyCreated) {
                $this->sendDeliveryNoteDispatchToGp($guia);
                if ($this->common->isGUideBlockedForOrder($guia)) {
                    $this->common->createTemporaryDeliveryNoteBlocksOrder($guia, $user);
                }
            }
            $this->updateDetailOrder($items);
            event(new DispatchPendings('refresh'));
            return $guia->refresh();
        } finally {
            $lock->release();
        }
    }


    protected function makeIdemKeyDispatch(array $payload, array $pedido, string $tipo, string $ptoVta): string
    {
        $base = [
            'bodega'        => data_get($pedido, 'BOD_BODEGA'),
            'pedido'        => data_get($pedido, 'pedido'),
            'tipo'          => $tipo,
            'sucursal_id'   => data_get($pedido, 'sucursal_id'),
            'pto_vta_id'    => $ptoVta,
            'vehiculo'      => data_get($payload, 'vehiculo.id') ?? data_get($payload, 'vehiculo.placa'),
            'transportista' => data_get($payload, 'transportista.id') ?? data_get($payload, 'transportista.identificacion'),
            'fecha_inicio'  => data_get($payload, 'fecha_inicio'),
            'fecha_fin'     => data_get($payload, 'fecha_fin'),
            'detalle'       => collect(data_get($payload, 'items'))->map(fn($d) => [
                'id'            => data_get($d, 'id'),
                'sel'           => (float) (data_get($d, 'seleccionado',  0)),
                'pendiente'     => (float) (data_get($d, 'pendiente',  0)),
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
        $ordenCompra    = GpPedido::where('pedido', $guide->pedido)->value('orden_de_compra');
        $detalle        = GpGuiaRemisionDetalle::where('guia_remision_id', $guide->id)->get();
        $validate       = $this->validateExistenceDeliveryNoteDispatch($guide->GUI_REM_GUIA_REMISION); //valida la existencia de la guia de remision
        if (data_get($validate, 'success', false)) {
            $this->common->assigmentAccessCodeToGuidePending($guide);
            return $guide->refresh();
        }

        try {
            $this->sendToDataDeliveryNote($guide, $detalle, $ordenCompra);
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'Cabecera ya existente')) {
                Log::warning("Guía duplicada detectada en ERP al enviar: " . $guide->GUI_REM_GUIA_REMISION);
                $this->common->assigmentAccessCodeToGuidePending($guide);
                return $guide->refresh();
            }
            throw $e;
        }
        $this->common->assigmentAccessCodeToGuidePending($guide);
        return $guide->refresh();
    }

    protected function validateExistenceDeliveryNoteDispatch($numGuide)
    {
        return  $this->api->checkExistenceDeliveryNote([
            'numDoc' => $numGuide
        ]);
    }

    protected function sendToDataDeliveryNote($guide, $detalle, ?string $ordenCompra)
    {
        $response  = $this->api->createDeliveryNote([
            'cabecera'      => $this->builder->buildDeliveryNoteHeaderToGp($guide, $ordenCompra),
            'destinatario'  => $this->builder->buildDeliveryNoteDestinationToGp($guide, $ordenCompra),
            'detalle'       => $this->prepareDetailReservation($guide, $detalle)
        ]);
        $success = data_get($response, 'success', false);
        if (!$success) {
            throw new \Exception(data_get($response, 'message', 'Opps! Algo salio mal'));
        }
        return $success;
    }


    protected function prepareDetailReservation($guide, $items): array
    {
        $detail = [];
        foreach ($items as $key => $value) {
            $detail[] = $this->builder->buildDeliveryNoteDetailToGp($guide, $value, $key + 1);
        }
        return $detail;
    }

    protected function validateQtyDetails(array $items): ?string
    {
        $ids = collect($items)->pluck('id');
        $detalles = GpPedidoDetalle::whereIn('id', $ids)
            ->lockForUpdate()
            ->get(['id', 'ART_CODIGO', 'reservado', 'trasladado', 'ART_CANTIDAD'])
            ->keyBy('id');

        $codigosArticulos = $detalles->pluck('ART_CODIGO')->unique();
        $decimalesArticulos = GpArticulo::whereIn('ART_CODIGO', $codigosArticulos)
            ->pluck('ART_DECIMAL_CANTIDAD', 'ART_CODIGO')
            ->all();

        $detalleGuias = GpGuiaRemisionDetalle::whereIn('detalle_id', $ids)
            ->whereHas('guia', function ($q) {
                $q->where('tipo', DeliveryNoteType::DESPACHO->value);
            })
            ->select('detalle_id', DB::raw('SUM(GUI_REM_CANTIDAD) as total_generado'))
            ->groupBy('detalle_id')
            ->get()
            ->keyBy('detalle_id');

        foreach ($items as $itm) {
            $id             = data_get($itm, 'id');
            $dbItem         = $detalles->get($id);
            if (!$dbItem) {
                continue;
            }
            $precision              = (int) data_get($decimalesArticulos, $dbItem->ART_CODIGO, 0);
            $seleccionado           = (float) data_get($itm, 'seleccionado', 0);
            $yaGenerado             = data_get($detalleGuias->get($id), 'total_generado', 0);
            $yaComprometido         = max(((float)$dbItem->reservado + (float)$dbItem->trasladado), $yaGenerado);
            $totalFinal             = $yaComprometido + $seleccionado;
            $totalFinalRedondeado   = round($totalFinal, $precision);
            if ($totalFinalRedondeado > (float)$dbItem->ART_CANTIDAD) {
                $disponible = max(0, (float)$dbItem->ART_CANTIDAD - $yaComprometido);
                return "El ítem {$dbItem->ART_CODIGO} excede el límite. " .
                    "Máximo original: {$dbItem->ART_CANTIDAD}. " .
                    "Ya procesado/en guías: {$yaComprometido}. " .
                    "Disponible: {$disponible}. " .
                    "Por favor, refresque la ventana.";
            }
        }
        return null;
    }




    /**
     *
     * PROCESO DE DESPACHO DE GUIAS DE REMISION DE DESPACHO
     *
     */

    public function dispatchGuideTransferShipment(int $guiaId, GpUsuario $user): array
    {
        $guia = GpGuiaRemision::findOrFail($guiaId);
        if ($guia->estado == DeliveryNoteStatus::ENVIADA->value) {
            throw new \RuntimeException("La guia de despacho ya fue enviada por otro usuario");
        }

        if ($this->common->isGuideBlockedForOrder($guia)) {
            throw new \RuntimeException("El pedido {$guia->pedido} tiene activo el bloqueo de despacho (Pendiente de registrarse el mov. en Kárdex).");
        }
        $detalles   = GpGuiaRemisionDetalle::where('guia_remision_id', $guiaId)->get();

        if ($detalles->isEmpty()) {
            throw new \RuntimeException("La guia de despacho no tiene detalles");
        }

        $ids        = $detalles->pluck('detalle_id')->filter()->unique()->values();
        $articulos  = $this->getPhysicalOrderDetailsByIds($ids);
        $artServs   = $this->getServiceOrderDetailsByIds($ids);
        $tocoServ   = (bool) $artServs->isNotEmpty();

        foreach ($detalles->groupBy('detalle_id') as $detalleId => $row) {
            $art = $artServs->get($detalleId);
            if (!$art) {
                continue;
            }
            $qty                    = (float) $row->sum('GUI_REM_CANTIDAD');
            $qty                    = max(0.0, $qty);
            $nuevoReservado         = max(0.0, (float) ($art->reservado ?? 0) - $qty);
            $nuevoTrasladado        = (float) ($art->trasladado ?? 0) + $qty;
            $cap                    = (float) ($art->ART_CANTIDAD ?? 0);
            if ($cap > 0) {
                $nuevoTrasladado    = min($nuevoTrasladado, $cap);
            }
            $art->fill([
                'reservado'         => $nuevoReservado,
                'trasladado'        => $nuevoTrasladado
            ])->save();
        }

        $groups = $this->groupDispatchItemsBySourceOrder($guia, $detalles, $articulos);
        if ($tocoServ && count($groups) === 0) {
            $pedido = $this->logistic->updateOrderDispatchStatus($guia);
            $guia->update(['estado' => DeliveryNoteStatus::ENVIADA->value]);
            return [
                'guia'      => $guia,
                'services'  => $tocoServ,
                'pedido'    => $pedido
            ];
        }

        foreach ($groups as $order => $grupo) {
            $hashInterno = (string)$this->common->generateDeterministicUuidDispatch($guia->id, $order, $grupo['items'],  $articulos); //definimos el hasInterno para manejarlo en el proceso de guia de despacho
            $params      = $this->builder->buildParameterHeaderAndDetailDispatchShipment($guia, $grupo['pedido'], $grupo['pedido_nuevo'], $grupo['ws_items'], $user, $hashInterno);

            $this->sendItemsDeliveryNoteDispatchToGp($guia, $order, $params, $grupo['items'], $hashInterno);
        }

        $guia->update(['estado' => DeliveryNoteStatus::ENVIADA->value]);
        $pedido = $this->logistic->updateOrderDispatchStatus($guia);

        return [
            'guia'      => $guia,
            'services'  => $tocoServ,
            'pedido'    => $pedido
        ];
    }


    protected function getPhysicalOrderDetailsByIds($ids): Collection
    {
        return GpPedidoDetalle::query()->whereIn('id', $ids)
            ->where('reservado', '>', 0)
            ->where('ART_TIPO_COMPRA', '!=', 'SERV')
            ->get()
            ->keyBy('id');
    }

    protected function getServiceOrderDetailsByIds($ids)
    {
        return GpPedidoDetalle::query()->whereIn('id', $ids)
            ->where('reservado', '>', 0)
            ->where('ART_TIPO_COMPRA', 'SERV')
            ->get()
            ->keyBy('id');
    }

    protected function groupDispatchItemsBySourceOrder(GpGuiaRemision $guia, Collection $detalle, Collection $articulos): array
    {
        $pedidoOrigen       = GpPedido::where('pedido', $guia->pedido)->value('pedido_origen');
        $documento          = $pedidoOrigen ?: $guia->pedido;
        $pedidoNuevo        = $pedidoOrigen ? $guia->pedido : '';
        $groups             = [];

        foreach ($detalle as $item) {
            $detallePedido                      = $articulos->get($item->detalle_id);
            if (!$detallePedido) {
                continue;
            }
            $doc                                = $documento;
            $groups[$doc]['pedido']             = $documento;
            $groups[$doc]['pedido_nuevo']       = $pedidoNuevo;
            $groups[$doc]['items'][]            = $item;
            $groups[$doc]['ws_items'][]         = [
                'item'                              => $item->GUI_REM_CODI_INTERNO,
                'compartimento'                     => '',
                'cantidad'                          => (float)$item->GUI_REM_CANTIDAD,
            ];
        }

        return $groups;
    }


    protected function sendItemsDeliveryNoteDispatchToGp(GpGuiaRemision $guia, string $pedido, array $params, array $groupItems, string $hashInterno)
    {
        $transaction    = TransactionType::DSP;
        $typeMov        = $transaction->value;
        $type           = $transaction->type();
        $response       = $this->api->createDispatchShipmentGuideVta($params);
        $success        = data_get($response, 'success', false);
        $msg            = data_get($response, 'message');
        $docGp          = trim(data_get($response, 'data.docGp', ''));
        if (!$success) {
            $cabecera               = data_get($params, 'cabecera', []);
            $payloadHeader          = $this->builder->buildDeliveryNoteValidateDispatchShipment($cabecera, $typeMov, $type);
            $respValidate           = $this->api->checkDispatchGuideAdjustment($payloadHeader);
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
        $already = $this->existMovementDispatchWeb($guia->id, $docGp, $pedido, $hashInterno,  $typeMov, $type);
        return DB::transaction(function () use ($groupItems, $guia, $data, $pedido,  $typeMov, $type, $hashInterno, $already) {
            if (!$already) {
                $this->processReservedDispatchedAndDecrementStock($guia, $groupItems);
                $this->createDocumentDispatchTrfv($guia, $data, $pedido, $typeMov, $type, $hashInterno);
                $guia->update(['rebajado' => 1]);
            }
        });
    }


    protected function existMovementDispatchWeb(int $guiaId, string $docGp, string $order, string $hashInterno, string $typeMov, string $type): bool
    {

        return GpGuiaRemisionDespacho::where([
            ['guia_remision_id',    $guiaId],
            ['pedido',              $order],
            ['codigo',              trim((string) $docGp)],
            ['hash_interno',        trim((string) $hashInterno)],
            ['tipo_movimiento',     $typeMov],
            ['tipo',                $type],
        ])->exists();
    }


    protected function processReservedDispatchedAndDecrementStock($guia, $groupItems)
    {
        foreach ($groupItems as $item) {
            $this->applyDispatchToOrderDetail($item, $item->GUI_REM_CANTIDAD);
            $this->decrementOriginWarehouseReservedStockDispatch($item->detalle_id, $guia->bodega_origen, $item->GUI_REM_CANTIDAD);
        }
    }

    protected function applyDispatchToOrderDetail($item, $qty)
    {
        $dt = GpPedidoDetalle::where('id', $item->detalle_id)->lockForUpdate()->first();

        $rsvdac = (float)  data_get($dt, 'reservado', 0);
        $trlsac = (float)  data_get($dt, 'trasladado', 0);
        $cantac = (float)  data_get($dt, 'ART_CANTIDAD', 0);

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

    public function createDocumentDispatchTrfv(GpGuiaRemision $guia, $responseApi, string $documento, string $typeMov, string $type, string $hashInterno)
    {
        $dispatchTrfv = new GpGuiaRemisionDespacho();
        $dispatchTrfv->guia_remision_id     = $guia->id;
        $dispatchTrfv->id_movimiento        =  data_get($responseApi, 'idMovimiento', '');
        $dispatchTrfv->pedido               = $documento;
        $dispatchTrfv->codigo               = data_get($responseApi, 'docGp', '');
        $dispatchTrfv->hash_interno         = $hashInterno;
        $dispatchTrfv->tipo_movimiento      = $typeMov;
        $dispatchTrfv->tipo                 = $type;
        $dispatchTrfv->save();
    }

    protected function decrementOriginWarehouseReservedStockDispatch($detalleId, $warehouse, $qty)
    {
        $det = GpPedidoDetalle::find($detalleId);
        if ($det->ART_TIPO_COMPRA != 'L') {
            $stock = GpPedidoDetalleStock::where('detalle_id', $detalleId)
                ->where('STO_ART_BODEGA', $warehouse)
                ->lockForUpdate()
                ->first();
            $reservado = data_get($stock, 'reservado', 0);
            $nuevo = max(0, $reservado - $qty);
            $stock->reservado = $nuevo;
            $stock->save();
        }
    }
}
