<?php

namespace App\Services\Inventario\Bodegas;

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
use App\Models\Articulos\GpArticulo;
use App\Models\GpPedido;
use App\Models\GpPedidoDetalleStock;
use App\Models\GpRelacionPuntoVentaBodega;
use App\Services\Api\Bodegas\ApiDeliverNoteService;
use App\Services\Builders\Bodega\Contracts\DeliveryNoteDispatchParamsBuilderInterface;
use App\Services\Inventario\Bodegas\Contracts\DeliveryNoteCommonServiceInterface;
use App\Services\Inventario\Bodegas\Contracts\DeliveryNoteDispatchServiceInterface;
use App\Services\Inventario\Bodegas\Contracts\LogisticsOperationsServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
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

    public function createDeliveryNoteFromDispatch(array $payload, GpUsuario $user): GpGuiaRemision
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

            $guia = $lockSequential->block(15, function () use ($payload, &$infoGuia, $items, $ptoVta, $ptoEmision) {
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
                    $this->updateDetailOrder($items);
                    return $guia;
                });
            });

            if ($guia->wasRecentlyCreated) {
                $this->sendDeliveryNoteDispatchToGp($guia);
                if ($this->common->isGUideBlockedForOrder($guia)) {
                    $this->common->createTemporaryDeliveryNoteBlocksOrder($guia, $user);
                }
            }

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


    protected function saveHeaderDeliveryNoteDispatchWeb(array $payload, array $infoGuia): GpGuiaRemision
    {
        $guia = $this->builder->buildDeliveryNoteDispatchHeader($payload, $infoGuia);
        $guia->save();
        return $guia->refresh();
    }

    protected function saveDetaildeliveryNoteDispatchWeb(int $guideId, array  $detalle): void
    {
        foreach ($detalle as $val) {
            $detail = $this->builder->buildDeliveryNoteDispatchDetail($guideId, $val);
            $detail->save();
        }
    }

    protected function updateDetailOrder(array $items): void
    {
        foreach ($items as $item) {
            GpPedidoDetalle::where('id', $item['id'])
                ->increment('reservado', data_get($item, 'seleccionado', 0));
        }
    }



    protected function sendDeliveryNoteDispatchToGp(GpGuiaRemision $guide): GpGuiaRemision
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
                Log::channel('despachos')->warning("Guía duplicada detectada en ERP al enviar: " . $guide->GUI_REM_GUIA_REMISION);
                $this->common->assigmentAccessCodeToGuidePending($guide);
                return $guide->refresh();
            }
            throw $e;
        }
        $this->common->assigmentAccessCodeToGuidePending($guide);
        return $guide->refresh();
    }

    protected function validateExistenceDeliveryNoteDispatch(string $numGuide): array
    {
        return  $this->api->checkExistenceDeliveryNote([
            'numDoc' => $numGuide
        ]);
    }

    protected function sendToDataDeliveryNote(GpGuiaRemision $guide, Collection $detalle, ?string $ordenCompra): bool
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


    protected function prepareDetailReservation(GpGuiaRemision $guide, Collection $items): array
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

        if ($this->hasItemsWithIncompleteMasterData($ids)) {
            throw new \RuntimeException("Error de Integridad: Se detectaron artículos (I/S) con 'Tipo de Compra' vacío en GP. Corrija el maestro de artículos para permitir el rebaje de stock.");
        }

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


    protected function getPhysicalOrderDetailsByIds(SupportCollection $ids): Collection
    {
        return GpPedidoDetalle::query()->whereIn('id', $ids)
            ->where('reservado', '>', 0)
            ->where('ART_TIPO_COMPRA', '!=', 'SERV')
            ->get()
            ->keyBy('id');
    }

    protected function getServiceOrderDetailsByIds(SupportCollection $ids): Collection
    {
        return GpPedidoDetalle::query()->whereIn('id', $ids)
            ->where('reservado', '>', 0)
            ->where('ART_TIPO_COMPRA', 'SERV')
            ->get()
            ->keyBy('id');
    }

    protected function hasItemsWithIncompleteMasterData(SupportCollection $ids): bool
    {
        return GpPedidoDetalle::query()
            ->whereIn('id', $ids)
            ->where('reservado', '>', 0)
            ->where(function ($query) {
                $query->whereNull('ART_TIPO_COMPRA')
                    ->orWhere('ART_TIPO_COMPRA', '');
            })
            ->where(function ($query) {
                $query->where('ART_CODIGO', 'LIKE', '%I')
                    ->orWhere('ART_CODIGO', 'LIKE', '%S');
            })
            ->exists();
    }

    protected function groupDispatchItemsBySourceOrder(GpGuiaRemision $guia, Collection $detalle, Collection $articulos): array
    {
        $pedidoOrigen       = GpPedido::where('pedido', $guia->pedido)->value('pedido_origen');
        $documento          = (string) ($pedidoOrigen ?: $guia->pedido);
        $pedidoNuevo        = $pedidoOrigen ? (string) $guia->pedido : '';
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


    protected function sendItemsDeliveryNoteDispatchToGp(GpGuiaRemision $guia, string $pedido, array $params, array $groupItems, string $hashInterno): void
    {
        $transaction    = TransactionType::DSP;
        $typeMov        = $transaction->value;
        $type           = $transaction->type();
        $cabecera       = data_get($params, 'cabecera', []);
        $payloadHeader  = $this->builder->buildDeliveryNoteValidateDispatchShipment($cabecera, $typeMov, $type);
        $lock           = Cache::lock("dispatch_adjustment_{$hashInterno}", 300);

        if (!$lock->get()) {
            throw new \RuntimeException('Este ajuste de guia ya esta en proceso. Por favor, espere un momento.');
        }

        try {
            $existing = $this->findMovementDispatchByHash($guia->id, $pedido, $hashInterno, $typeMov, $type);
            if ($existing && filled($existing->codigo)) {
                return;
            }

            $data = $this->findDispatchAdjustmentInGp($payloadHeader);
            if (!$data) {
                $response = $this->api->createDispatchShipmentGuideVta($params);
                $success  = data_get($response, 'success', false);
                $msg      = data_get($response, 'message');
                $data     = $success ? data_get($response, 'data', []) : null;

                if (!$success || !is_array($data) || blank(data_get($data, 'docGp'))) {
                    $data = $this->findDispatchAdjustmentInGp($payloadHeader);
                    if (!$data) {
                        throw new \RuntimeException($msg ?: 'Error creando el ajuste de guia en GP.');
                    }
                }
            }

            $this->registerDispatchAdjustmentResult($guia, $pedido, $data, $groupItems, $hashInterno, $typeMov, $type);
        } finally {
            $lock->release();
        }
    }


    protected function findMovementDispatchByHash(int $guiaId, string $order, string $hashInterno, string $typeMov, string $type): ?GpGuiaRemisionDespacho
    {
        return GpGuiaRemisionDespacho::where([
            ['guia_remision_id',    $guiaId],
            ['pedido',              $order],
            ['hash_interno',        trim((string) $hashInterno)],
            ['tipo_movimiento',     $typeMov],
            ['tipo',                $type],
        ])->first();
    }

    protected function findDispatchAdjustmentInGp(array $payloadHeader): ?array
    {
        $response = $this->api->checkDispatchGuideAdjustment($payloadHeader);
        $data     = data_get($response, 'data');

        if (!is_array($data) || blank(data_get($data, 'docGp'))) {
            return null;
        }

        return $data;
    }

    protected function registerDispatchAdjustmentResult(
        GpGuiaRemision $guia,
        string $pedido,
        array $data,
        array $groupItems,
        string $hashInterno,
        string $typeMov,
        string $type
    ): void {
        if (blank(data_get($data, 'docGp'))) {
            throw new \RuntimeException('No se ha generado el documento de ajuste de guia en Gp.');
        }

        DB::transaction(function () use ($guia, $pedido, $data, $groupItems, $hashInterno, $typeMov, $type) {
            $existing = GpGuiaRemisionDespacho::where([
                ['guia_remision_id',    $guia->id],
                ['pedido',              $pedido],
                ['hash_interno',        trim((string) $hashInterno)],
                ['tipo_movimiento',     $typeMov],
                ['tipo',                $type],
            ])->lockForUpdate()->first();

            if ($existing && filled($existing->codigo)) {
                return;
            }

            $this->processReservedDispatchedAndDecrementStock($guia, $groupItems);

            if ($existing) {
                $existing->id_movimiento    = data_get($data, 'idMovimiento');
                $existing->codigo           = data_get($data, 'docGp', '');
                $existing->save();
            } else {
                $this->createDocumentDispatchTrfv($guia, $data, $pedido, $typeMov, $type, $hashInterno);
            }

            $guia->update(['rebajado' => 1]);
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


    protected function processReservedDispatchedAndDecrementStock(GpGuiaRemision $guia, array $groupItems): void
    {
        foreach ($groupItems as $item) {
            $this->applyDispatchToOrderDetail($item, $item->GUI_REM_CANTIDAD);
            $this->decrementOriginWarehouseReservedStockDispatch($item->detalle_id, $guia->bodega_origen, $item->GUI_REM_CANTIDAD);
        }
    }

    protected function applyDispatchToOrderDetail(GpGuiaRemisionDetalle $item, float $qty): GpPedidoDetalle
    {
        $dt = GpPedidoDetalle::where('id', $item->detalle_id)->lockForUpdate()->first();
        if (!$dt) {
            throw new \RuntimeException("No se encontro el detalle de pedido {$item->detalle_id} para despachar.");
        }

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

    public function createDocumentDispatchTrfv(GpGuiaRemision $guia, array $responseApi, string $documento, string $typeMov, string $type, string $hashInterno): GpGuiaRemisionDespacho
    {
        $dispatchTrfv = new GpGuiaRemisionDespacho();
        $dispatchTrfv->guia_remision_id     = $guia->id;
        $dispatchTrfv->id_movimiento        = data_get($responseApi, 'idMovimiento');
        $dispatchTrfv->pedido               = $documento;
        $dispatchTrfv->codigo               = data_get($responseApi, 'docGp', '');
        $dispatchTrfv->hash_interno         = $hashInterno;
        $dispatchTrfv->tipo_movimiento      = $typeMov;
        $dispatchTrfv->tipo                 = $type;
        $dispatchTrfv->save();
        return $dispatchTrfv;
    }

    protected function decrementOriginWarehouseReservedStockDispatch(int $detalleId, string $warehouse, float $qty): void
    {
        $det = GpPedidoDetalle::find($detalleId);
        if (!$det) {
            throw new \RuntimeException("No se encontro el detalle de pedido {$detalleId} para actualizar stock reservado.");
        }
        if ($det->ART_TIPO_COMPRA != 'L') {
            $stock = GpPedidoDetalleStock::where('detalle_id', $detalleId)
                ->where('STO_ART_BODEGA', $warehouse)
                ->lockForUpdate()
                ->first();
            if (!$stock) {
                throw new \RuntimeException("No se encontro stock reservado para el detalle {$detalleId} en bodega {$warehouse}.");
            }
            $reservado = data_get($stock, 'reservado', 0);
            $nuevo = max(0, $reservado - $qty);
            $stock->reservado = $nuevo;
            $stock->save();
        }
    }



    public function replicateDeliveryNoteDispatch(int $id): GpGuiaRemision
    {
        $guideOrigin = GpGuiaRemision::where('id', $id)->where('tipo', DeliveryNoteType::DESPACHO->value)->first();
        if (!$guideOrigin) {
            throw new \RuntimeException('El identificador no es de una guia de despacho, verifique nuevamente');
        }
        $pedidoOrigen = GpPedido::where('pedido', $guideOrigin->pedido)->first();
        if (!$pedidoOrigen) {
            throw new \RuntimeException("No se encontro el pedido {$guideOrigin->pedido} para duplicar la guia.");
        }
        $detailOrigin = GpGuiaRemisionDetalle::where('guia_remision_id', $id)->get();
        $secuencial             = $this->common->getNextGuideSequential($guideOrigin->GUI_REM_ESTABLECIMIENTO, $guideOrigin->GUI_REM_PTO_EMISION);
        $numGuide               = $this->common->formatGuideNumber($guideOrigin->GUI_REM_ESTABLECIMIENTO, $guideOrigin->GUI_REM_PTO_EMISION, $secuencial);
        $idemKey = $this->makeIdemKeyDispatchReplicate($guideOrigin, $pedidoOrigen, $detailOrigin);
        $newGuide = $this->createNewHeaderDeliveryNoteReplicate($guideOrigin, $secuencial, $numGuide,  $idemKey);
        $this->createNewDetailDeliveryNoteReplicate($detailOrigin, $newGuide);
        $this->sendDeliveryNoteDispatchToGp($newGuide);
        return $newGuide;

    }



    protected function createNewHeaderDeliveryNoteReplicate(GpGuiaRemision $guia, string $sec, string $numGuide, string $idemKey): GpGuiaRemision
    {
        $newGuide                           = $guia->replicate();
        $newGuide->GUI_REM_SECUENCIAL       = $sec;
        $newGuide->GUI_REM_GUIA_REMISION    = $numGuide;
        $newGuide->idempotency_key          = $idemKey;
        $newGuide->GUI_REM_CODIGO           = null;
        $newGuide->save();
        return $newGuide;
    }

    protected function makeIdemKeyDispatchReplicate(GpGuiaRemision $guia, GpPedido $orderOrigin, Collection $detailOrigin): string
    {
        $base = [
            'bodega'        => $guia->GUI_REM_BODEGA,
            'pedido'        => $guia->pedido,
            'tipo'          => $guia->tipo,
            'sucursal_id'   => $orderOrigin->sucursal_id,
            'pto_vta_id'    => $guia->GUI_REM_ESTABLECIMIENTO,
            'vehiculo'      => $guia->GUI_REM_PLACA,
            'transportista' => $guia->GUI_REM_IDENTIFICACION_TRANSPORTISTA,
            'fecha_inicio'  => $guia->GUI_REM_FECHA_INICIO,
            'fecha_fin'     => $guia->GUI_REM_FECHA_FIN,
            'detalle'       => collect($detailOrigin)->map(fn($d) => [
                'id'            => data_get($d, 'detalle_id'),
                'sel'           => (float) (data_get($d, 'GUI_REM_CANTIDAD',  0)),
                'pendiente'     => (float) (data_get($d, 'pendiente',  0)),
            ])->sortBy('id')->values()->all(),
        ];

        return hash('sha256', json_encode($base));
    }


    protected function createNewDetailDeliveryNoteReplicate(Collection $detailOrigin, GpGuiaRemision $newGuide): void
    {
        foreach ($detailOrigin as  $oldDetail) {
            $nDetail = $oldDetail->replicate();
            $nDetail->guia_remision_id = $newGuide->id;
            $nDetail->save();
        }
    }
}
