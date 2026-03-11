<?php

namespace App\Services\Bodegas;

use App\Enums\bodegas\guiaremision\DeliveryNoteStatus;
use App\Enums\bodegas\guiaremision\TransactionType;
use App\Jobs\Venta\Reservas\NotifyReceivedReservationsJob;
use App\Models\GpArticulo;
use App\Models\GpCotizacionDetalleStock;
use App\Models\reservas\GpTrasladosReservas;
use App\Models\GpPuntoVenta;
use App\Models\GpUsuario;
use App\Models\reservas\GpGuiaRemisionReserva;
use App\Models\reservas\GpGuiaRemisionReservaDetalle;
use App\Models\reservas\GpGuiaRemisionReservaMovimiento;
use App\Services\Api\Bodegas\ApiDeliverNoteService;
use App\Services\Bodegas\Contracts\DeliveryNoteCommonServiceInterface;
use App\Services\Bodegas\Contracts\LogisticsOperationsServiceInterface;
use App\Services\Bodegas\Contracts\ReservationDeliveryNoteServiceInterface;
use App\Services\Builders\Bodega\Contracts\ReservationDeliveryNoteParamsBuilderInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;


class ReservationDeliveryNoteService implements ReservationDeliveryNoteServiceInterface
{

    public function __construct(
        protected readonly DeliveryNoteCommonServiceInterface $common,
        protected readonly ReservationDeliveryNoteParamsBuilderInterface $builder,
        protected readonly ApiDeliverNoteService $apiDelivery,
        protected readonly LogisticsOperationsServiceInterface $logistic
    ) {}


    public function createDeliveryNoteFromReservation($payload, GpUsuario $user)
    {

        $datavehicle    = $this->common->getVehicleAndCarrierInfo($payload);
        $dirOrigin      = $this->common->getDirection($payload['pto_origen']);
        $dirDestination = $this->common->getDirection($payload['pto_destino']);
        $bodGuide       = $this->common->getWarehouseDescriptionOrigin($payload['bod_origen']);
        $idemKey        = $this->makeIdemKey($payload);
        $guia = DB::transaction(function () use ($payload, $datavehicle, $dirOrigin, $dirDestination, $bodGuide, $idemKey, $user) {
            $limit          = $this->validateExistenceDeliveryNoteReservation(data_get($payload, 'detalle'));
            if ($limit) {
                throw new \RuntimeException($limit);
            }

            $existing = GpGuiaRemisionReserva::where('idempotency_key', $idemKey)
                ->where('estado', DeliveryNoteStatus::PENDIENTE->value)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                $existing->touch();
                return $existing;
            }
            $this->validateItemsTransferReserved($payload['detalle']);
            $secuencial = $this->common->getNextGuideSequential($payload['pto_origen'], '001');
            $numGuide   = $this->common->formatGuideNumber($payload['pto_origen'], '001', $secuencial);
            $guia       = $this->saveGuideRemisionReservation($payload, $user, $datavehicle, $secuencial, $dirDestination, $numGuide, $bodGuide, $dirOrigin);
            $guia->idempotency_key = $idemKey;
            $guia->save();
            $this->saveDetailGuideRemisionReservation($payload['detalle'], $guia->id);
            // procesos web mysql
            DB::afterCommit(function () use ($guia) {
                $guia = $this->sendReservationDeliveryNoteToGp($guia);
            });
            return $guia;
        });
        return $guia->refresh();
    }

    public function makeIdemKey(array $payload)
    {
        $base = [
            'pto_origen'        => data_get($payload, 'pto_origen'),
            'pto_destino'       => data_get($payload, 'pto_destino'),
            'bod_origen'        => data_get($payload, 'bod_origen', null),
            'vehiculo'          => data_get($payload, 'vehiculo_id', null) ?? data_get($payload, 'placa', null),
            'transportista'     => data_get($payload, 'transporte_id') ?? data_get($payload, 'identificacion', null),
            'fecha_inicio'      => data_get($payload, 'fecha_inicio', null),
            'fecha_fin'         => data_get($payload, 'fecha_fin', null),
            'detalle'           => collect($payload['detalle'])->map(fn($d) => [
                'id'            => data_get($d, 'id'),
                'sel'           => (float) (data_get($d, 'seleccionado',  0)),
            ])->sortBy('id')->values()->all(),
        ];
        return hash('sha256', json_encode($base));
    }

    public function validateItemsTransferReserved($items)
    {
        $items = collect($items);
        if ($items->isEmpty()) {
            throw new \RuntimeException(
                'No hay items para procesar el ajuste de la guía de reserva'
            );
        }
        $itemsIds   = $items->pluck('id')->toArray();
        $dQoteIds   = $items->pluck('stock_id')->toArray();
        $itemsPkId  = $items->keyBy('id');
        // actualizamos el picking si o si de estos items ya que se va a generar una guia de remision
        $this->updatePickingItemsSelected($itemsIds);
        $detTraslados = GpTrasladosReservas::whereIn('id', $itemsIds)->lockForUpdate()->get();
        if ($detTraslados->count() !== count($itemsIds)) {
            throw new \RuntimeException('Hay items inexistentes; actualice la lista.');
        }

        $detallesGuia = $this->getQtyDetailGuideRemisionReserved($itemsIds);
        $arrMsg = [];
        $detStock = GpCotizacionDetalleStock::whereIn('id', $dQoteIds)->lockForUpdate()->get()->keyBy('id');
        foreach ($detTraslados as $tr) {
            // consultas
            $rowsDetalle    = $detallesGuia->get($tr->id, collect());
            $itemProcess    = $itemsPkId->get($tr->id);
            $stockRow       = $detStock->get($tr->stock_id);
            if (!$stockRow) {
                $arrMsg[] = "El item {$itemProcess->art_code} no tiene stock asociado válido";
                continue;
            }
            // calculos
            $selectNow          = (float) data_get($itemProcess, 'seleccionado', 0);
            $pending            = (float) data_get($itemProcess, 'pending_transfer', 0);
            $qtyStockAuth       = (float) $stockRow->cantidad_autorizada_reserva;
            $qtyGuiaRemision    = $rowsDetalle->where('art_code', $itemProcess['art_code'])->sum('cantidad');
            $sumQty             = $qtyGuiaRemision + $selectNow;
            $sumHistory         = $pending + $qtyStockAuth;

            if ($sumQty > $sumHistory) {
                $arrMsg[] = "Item {$itemProcess['art_code']} sel={$selectNow} excede disponible: guia={$pending} + autoriz={$qtyStockAuth}";
            }
        }
        if ($arrMsg) {
            throw new \RuntimeException(implode(', ', $arrMsg));
        }
    }

    public function getQtyDetailGuideRemisionReserved($trasladosIds)
    {
        return GpGuiaRemisionReservaDetalle::whereIn('traslado_id', $trasladosIds)
            ->where('cantidad', '>', 0)
            ->get()
            ->groupBy('traslado_id');
    }

    public function updatePickingItemsSelected($items)
    {
        return GpTrasladosReservas::whereIn('id', $items)
            ->update(['picking' => 1]);
    }


    public function saveGuideRemisionReservation($payload, $user, $datavehicle, $secuencial, $dirDestination, $numGuide, $bodGuide, $dirOrigin): GpGuiaRemisionReserva
    {
        $guia = $this->builder->buildReservationGuideHeader($payload, $user, $datavehicle, $secuencial, $dirDestination, $numGuide, $bodGuide, $dirOrigin);
        $guia->save();
        return $guia;
    }

    public function saveDetailGuideRemisionReservation($items, $guiaId)
    {
        foreach ($items as $value) {
            $detail = $this->builder->buildReservationGuideDetails($value, $guiaId);
            $detail->save();
            $this->updateItemTranferedReservation($value);
        }
    }

    public function updateItemTranferedReservation($value)
    {
        $tr = GpTrasladosReservas::find($value['id']);
        if (!$tr) {
            throw new \RuntimeException('Traslado de reserva no encontrado');
        }
        $seleccionado = (float) data_get($value, 'seleccionado', 0);
        $tr->increment('reservado', $seleccionado);
    }


    public function sendReservationDeliveryNoteToGp($guide)
    {
        $detalle = GpGuiaRemisionReservaDetalle::where('guia_id', $guide->id)->get();
        //validar existencia de la guia de remisión
        $validate = $this->validateExistenceDeliveryNote($guide); //valida la existencia de la guia de remision
        $vsuccess = data_get($validate, 'success', false);
        if ($vsuccess) {
            $this->logistic->assignAccessCodeForPendingGuide($guide);
            return $guide;
        }
        $this->sendToDataDeliveryNote($detalle, $guide);
        $this->logistic->assignAccessCodeForPendingGuide($guide);
        return $guide;
    }

    public function prepateDetailReservation($items, $guide)
    {
        $detail = [];
        foreach ($items as $key => $value) {
            $detail[] = $this->builder->builReservationDetailToGp($guide, $value, $key + 1);
        }
        return $detail;
    }

    public function sendToDataDeliveryNote($detalle, $guide)
    {
        $response = $this->apiDelivery->createDeliveryNote([
            'cabecera' => $this->builder->buildReservationHeaderToGp($guide),
            'destinatario' => $this->builder->buildReservationDestinationToGp($guide),
            'detalle' => $this->prepateDetailReservation($detalle, $guide)
        ]);
        $success = data_get($response, 'success', false);
        if (!$success) {
            throw new \Exception($response['message']);
        }
        return $success;
    }


    public function validateExistenceDeliveryNote($guia)
    {
        return $this->apiDelivery->checkExistenceDeliveryNote([
            'numDoc' => $guia->numero_guia
        ]);
    }


    protected function validateExistenceDeliveryNoteReservation(array $items): ?string
    {
        $items      = collect($items);
        $itemsIds   = $items->pluck('id')->toArray();
        $detTraslados = GpTrasladosReservas::whereIn('id', $itemsIds)->get(['id', 'art_code', 'cantidad', 'reservado', 'trasladado'])->keyBy('id');


        $codigosArticulos = $detTraslados->pluck('art_code')->unique();
        $decimalesArticulos = GpArticulo::whereIn('ART_CODIGO', $codigosArticulos)
            ->pluck('ART_DECIMAL_CANTIDAD', 'ART_CODIGO')
            ->all();

        $detalleGuias = GpGuiaRemisionReservaDetalle::whereIn('traslado_id', $itemsIds)
            ->select('traslado_id', DB::raw('SUM(cantidad) as total_generado'))
            ->groupBy('traslado_id')
            ->get()
            ->keyBy('traslado_id');

        foreach ($items as  $value) {
            $id                     = data_get($value, 'id');
            $dbTransfer             = $detTraslados->get($id);
            if (!$dbTransfer) {
                continue;
            }
            $precision              = (int) data_get($decimalesArticulos, $dbTransfer->art_code, 0);
            $seleccionado           = (float) data_get($value, 'seleccionado', 0);
            $yaGenerado             = data_get($detalleGuias->get($id), 'total_generado', 0);
            $yaComprometido         = max(((float)$dbTransfer->reservado + (float)$dbTransfer->trasladado), $yaGenerado);
            $totalFinal             = $yaComprometido + $seleccionado;
            $totalFinalRedondeado   = round($totalFinal, $precision);
            if ($totalFinalRedondeado > (float)$dbTransfer->cantidad) {
                $disponible = max(0, (float)$dbTransfer->cantidad - $yaComprometido);
                return "El ítem {$dbTransfer->art_code} excede el límite. " .
                    "Máximo original: {$dbTransfer->cantidad}. " .
                    "Ya procesado/en guías: {$yaComprometido}. " .
                    "Disponible: {$disponible}. " .
                    "Por favor, refresque la ventana.";
            }
        }
        return null;
    }



    /*** proceso para generar el pdf de guia de remision pendiente */


    public function generatePdfReservedDeliveryNote($payload)
    {
        $id         = data_get($payload, 'id');
        $guia       = GpGuiaRemisionReserva::find($id);
        $vieData    = $this->getDataReservationDeliveryNote($guia);

        $html       = view('pdf.guia-remision-reserva-pendiente', $vieData)->render();
        $pdf        = app('dompdf.wrapper');
        $pdf->setPaper('a4', 'portrait');
        $pdf->loadHTML($html);
        $binary     = $pdf->output();
        $filename   = 'guia-remision-' . Str::slug(data_get($guia, 'numero_guia')) . '.pdf';
        return [
            'binary'        => $binary,
            'filename'      => $filename,
            'content_type'  => 'application/pdf'

        ];
    }

    public function getDataReservationDeliveryNote($guia)
    {

        $detalle = GpGuiaRemisionReservaDetalle::with([
            'detallecotizacion' => function ($q) {
                $q->select('id', 'CHECK_ALTERNATIVO');
            }
        ])->where('guia_id', $guia->id)->get();

        return [
            'guia'          => $guia,
            'claveAcceso'   => $guia->clave_acceso,
            'detalle'       => $detalle,
            'direccion'     => $this->common->getDirection($guia->establecimiento),
            'descripcion'   => $this->getDescriptionWarehouse($guia->establecimiento),
            'pesoTotal'     => $this->calculateKgTotal($detalle->pluck('traslado_id')->toArray()),
            'quoteList'     => $this->getListQuotesTranfers($detalle->pluck('traslado_id')->toArray())
        ];
    }

    public function calculateKgTotal($trasIds)
    {
        if (empty($trasIds)) {
            return [];
        }
        return (float) GpTrasladosReservas::from('gp_traslados_reservas as tr')
            ->join('gp_cotizacion_detalles as cd', 'cd.id', '=', 'tr.detalle_id')
            ->whereIn('tr.id', $trasIds)
            ->where('cd.ART_TIPO_COMPRA', '<>', 'SERV')
            ->selectRaw('ROUND(SUM(COALESCE(tr.art_peso,0) * COALESCE(tr.cantidad,0)), 2) as peso_total')
            ->value('peso_total');
    }

    public function getListQuotesTranfers($ids)
    {
        if (empty($ids)) {
            return [];
        }
        return GpTrasladosReservas::from('gp_traslados_reservas as tr')
            ->join('gp_cotizacions as c', 'c.id', '=', 'tr.cotizacion_id')
            ->whereIn('tr.id', $ids)
            ->distinct()
            ->orderBy('c.cotizacion')
            ->pluck('c.cotizacion')
            ->all();
    }

    public function getDescriptionWarehouse($warehouse)
    {
        return GpPuntoVenta::where('PTO_VTA_ID', $warehouse)->value('PTO_VTA_DESCRIPCION');
    }


    /***
     *
     *
     *
     * funcion para enviar a gp para generar el TRA de la guia de reservas
     *
     *
     *
     * */
    public function dispatchGuideReservation($payload, GpUsuario $user): array|Collection|GpGuiaRemisionReserva|Model|null
    {
        $id         = (int) data_get($payload, 'id');
        $guia       = GpGuiaRemisionReserva::find($id);
        $details    = $this->getDetailsGuideWithTransfers($guia->id);
        if ($details->isEmpty()) {
            throw new \RuntimeException('No hay items para procesar el ajuste de la guía de reserva');
        }
        $groups = $this->common->groupItemsByOrderReservation($details);
        if (empty($groups)) {
            throw new \RuntimeException('No hay items para procesar el ajuste de la guía de reserva');
        }
        foreach ($groups as $quote => $group) {
            $hashInterno  = $this->generateDeterministicUuidReservation($guia->id, $quote, $group['items']);
            $params       = $this->builder->buildReservedDeliveryNotePayload($guia, $quote, $group['ws_items'], $user->USERID, $hashInterno, null);
            $this->validateExistenceReservationGuideAdjustment($guia, $params, $group['items'], $quote, $hashInterno);
        }

        $guia->update(['estado' => DeliveryNoteStatus::ENVIADA->value]);
        return $guia->fresh();
    }


    public function getDetailsGuideWithTransfers(int $guiaId, ?int $quoteId = null): Collection
    {
        return GpGuiaRemisionReservaDetalle::query()
            ->where('guia_id', $guiaId)
            ->whereHas('traslado', fn($q) => $q->pending())
            ->when($quoteId !== null, fn($q) => $q->whereHas('traslado', fn($q) => $q->where('cotizacion_id', $quoteId)))
            ->whereHas('traslado.cotizacion')
            ->with([
                'traslado' => fn($q) => $q->pending(),
                'traslado.cotizacion:id,cotizacion'
            ])
            ->get();
    }



    public function validateExistenceReservationGuideAdjustment(GpGuiaRemisionReserva $guia, array $params, array $groupItems, string $quote, string $hashInterno): mixed
    {
        $transaction        = TransactionType::TRC;
        $typeMov            = $transaction->value;
        $type               = $transaction->type();
        $resp               = $this->apiDelivery->createReservationGuideAjustment($params);
        $success            = data_get($resp, 'success', false);
        $msg                = data_get($resp, 'message');
        $docGp              = trim(data_get($resp, 'data.docGp', ''));

        if (!$success) {
            $cabecera               = data_get($params, 'cabecera', []);
            $payloadValidate        = $this->builder->buildReservationGuideValidationPayload($cabecera,  $typeMov, $type);
            $respValidate           = $this->apiDelivery->checkReservationGuideAdjustment($payloadValidate);
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

        $already = $this->movementExistsWeb($guia->id, $quote, $docGp, $typeMov, $type, $hashInterno);
        return DB::transaction(function () use ($groupItems, $guia, $data, $quote, $already, $typeMov, $type, $hashInterno) {
            if (!$already) {
                $this->processReservationGuideDecrementStock($groupItems);
                $this->createMovementReservation($guia, $data, $quote, $typeMov, $type, $hashInterno);
            }
        });
    }




    public function movementExistsWeb($guiaId, $quote, $docGp, $tipoMovimiento, $tipo, $hashInterno): bool
    {
        return GpGuiaRemisionReservaMovimiento::query()
            ->where('guia_id', $guiaId)
            ->where('cotizacion', $quote)
            ->where('codigo', trim((string) $docGp))
            ->where('hash_interno', trim((string) $hashInterno))
            ->where('tipo_movimiento', $tipoMovimiento)
            ->where('tipo', $tipo)
            ->exists();
    }

    public function processReservationGuideDecrementStock($groupItems)
    {
        foreach ($groupItems as $item) {
            $this->common->moveReservedToTransferredForTrfvLine($item->traslado, $item->cantidad);
            $this->updateStockTransitItem($item->traslado, $item->cantidad);
        }
    }

    public function createMovementReservation($guia, $responseApi, $quote, $tipoMovimiento, $tipo, $hashInterno): GpGuiaRemisionReservaMovimiento
    {
        return GpGuiaRemisionReservaMovimiento::firstOrCreate([
            'guia_id'           => $guia->id,
            'cotizacion'        => $quote,
            'id_movimiento'     => data_get($responseApi, 'idMovimiento'),
            'codigo'            => trim((string) data_get($responseApi, 'docGp', '')),
            'tipo_movimiento'   => $tipoMovimiento,
            'tipo'              => $tipo,
            'hash_interno'      => $hashInterno,
        ]);
    }

    public function updateStockTransitItem($traslado, $qty)
    {
        GpCotizacionDetalleStock::where('id', $traslado->stock_id)
            ->increment('cantidad_transito', $qty);
    }


    protected function generateDeterministicUuidReservation($guiaId, string $quote, array $items): string
    {
        $dataPoints = [];
        foreach ($items as  $item) {
            $traslado            = $item->traslado;
            $reservedHistory     = $traslado ? (float) $traslado->reservado : 0;
            $qtyMovement         = (float) $item->cantidad;

            $dataPoints[] = sprintf(
                "ROW:%s|QTY:%.4f|ST_RES:%.4f",
                $item->id,
                $qtyMovement,
                $reservedHistory
            );
        }
        sort($dataPoints);
        $seed = sprintf(
            "GUID:%s|QT:%s|ITEMS:[%s]",
            $guiaId,
            $quote,
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








    /***
     *
     *   PROCESO DE RECIBIR GUIAS DE REMISION DE RESERVAS
     *
     */


    public function receiveItemsFromTransferred(int $guiaId, array $items, GpUsuario $user): GpGuiaRemisionReserva
    {
        $limitQty = $this->validateReceivedQty($items);
        if ($limitQty) {
            throw new \RuntimeException($limitQty);
        }
        $gpUser     = GpUsuario::whereKey($user->id)->first() ?? GpUsuario::where('USERID', 'adminWeb')->first();
        $guia       = GpGuiaRemisionReserva::with('cotizaciones')->find($guiaId);
        if (!$guia) {
            throw new \RuntimeException("Guia de reserva no encontrada");
        }
        $movimientos    = $this->getMovimentsByGuiaId($guia->id, $guia->quotes); // me obtiene todos los movimientos de la guia tomando la cotizacion como clave
        $itmTrIds       = collect($items)->pluck('traslado_id')->filter()->unique()->toArray();
        $traslados      = GpTrasladosReservas::whereIn('id', $itmTrIds)->get()->keyBy('id');
        $groupsItems    = $this->common->groupItemsByQuotesToReceivedReserved($items, $movimientos, $traslados);

        foreach ($groupsItems as $quote => $group) {
            $hashInterno    = $this->generateDeterministicUuidReceivedReservation($guia->id, $quote, $group['transferencia'], $group['items']);
            $params         = $this->builder->buildReservedDeliveryNotePayload($guia, $quote, $group['ws_items'], $user->USERID, $hashInterno, $group['transferencia']);
            $this->processReceivedReservationAndCreateAdjustment($guia, $params, $group['items'], $quote, $gpUser, $hashInterno);
        }

        $exists = $this->validateExistsForReceivedUpdateStatusGuide($guia);
        $this->sendNotificacionReceivedItems($guia, $exists, $items, $gpUser);
        return $guia->refresh();
    }


    protected function getMovimentsByGuiaId(int $guiaId, array $quotes)
    {
        return GpGuiaRemisionReservaMovimiento::where('guia_id', $guiaId)->where('tipo_movimiento', TransactionType::TRC->value)->whereIn('cotizacion', $quotes)->get()->keyBy('cotizacion');
    }

    protected function processReceivedReservationAndCreateAdjustment(GpGuiaRemisionReserva $guia, array $params, array $items, string $quote, GpUsuario $user, string $hashInterno)
    {
        $transaction        = TransactionType::RTC;
        $typeMov            = $transaction->value;
        $type               = $transaction->type();
        $resp               = $this->apiDelivery->createReceiveGuideReservationAjustment($params);
        $success            = data_get($resp, 'success', false);
        $msg                = data_get($resp, 'message');
        $docGp              = trim(data_get($resp, 'data.docGp', ''));

        if (!$success) {
            $cabecera           = data_get($params, 'cabecera', []);
            $pyValidate         = $this->builder->buildReservationGuideValidationPayload($cabecera,  $typeMov, $type);
            $resValidate        = $this->apiDelivery->checkReservationGuideAdjustment($pyValidate);
            $headerRespValidate = data_get($resValidate, 'data');
            $docGpValidate      = trim(data_get($headerRespValidate, 'docGp', ''));
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

        $already = $this->movementExistsWeb($guia->id, $quote, $docGp, $typeMov, $type, $hashInterno);
        DB::transaction(function () use ($guia, $items, $data, $quote, $already, $user, $typeMov, $type, $hashInterno) {
            if (!$already) {
                $this->updateQtyTransferedItems($guia, $items);
                $this->createMovementReservation($guia, $data, $quote, $typeMov, $type, $hashInterno);
                $this->createRegisterHistoryIng($guia, $items, $user, $quote);
            }
        });
    }





    protected function updateQtyTransferedItems(GpGuiaRemisionReserva $guia, array $items)
    {
        foreach ($items as $itm) {
            $trasladoId         = data_get($itm, 'traslado_id');
            $traslado           = GpTrasladosReservas::with('reserva')->find($trasladoId);
            $liberarStock       = in_array($traslado->reserva->estado, ['cancelado', 'rechazada']);
            $this->updateQtyReceivedDetailGuide($itm);
            $this->updateQtyStockQuotationTransferred($guia, $itm, $liberarStock);
            $this->updateStatusReservation($itm, $traslado->reserva);
        }
    }

    protected function updateQtyReceivedDetailGuide($item)
    {
        $recibir    = max(0, (float) (data_get($item, 'recibir', 0)));
        $gd         = GpGuiaRemisionReservaDetalle::find($item['id']);
        $pend       = max(0, (float) $gd->cantidad - (float) $gd->recibido);
        if ($pend <= 0) {
            return;
        }
        $gd->increment('recibido', min($recibir, $pend));
    }

    protected function updateQtyStockQuotationTransferred(GpGuiaRemisionReserva $guia, array $item, bool $liberarStock)
    {
        $detalleId          = (int) Arr::get($item, 'detalle_id');
        $qty = (float) max(0, Arr::get($item, 'recibir', 0));
        if ($qty <= 0 || !$detalleId) {
            return;
        }
        $stockOrigen        = GpCotizacionDetalleStock::where('detalle_id', $detalleId)->where('STO_ART_BODEGA', $guia->bodega_origen)->first();
        $stockDestino       = GpCotizacionDetalleStock::where('detalle_id', $detalleId)->where('STO_ART_BODEGA', $guia->bodega_destino)->first();

        // Evita negativos: limita lo que vas a descontar
        $decTras                            = min($qty, (float) $stockOrigen->cantidad_traslado);
        $decSel                             = min($qty, (float) $stockOrigen->seleccionado);
        $stockOrigen->cantidad_reservada    = max(0, (float) $stockOrigen->cantidad_reservada - $decTras);
        $stockOrigen->cantidad_traslado     = max(0, (float) $stockOrigen->cantidad_traslado - $decTras);
        $stockOrigen->seleccionado          = max(0, (float) $stockOrigen->seleccionado - $decSel);
        $stockOrigen->cantidad_transito     = (float) max(0, $stockOrigen->cantidad_transito - $qty);
        $stockOrigen->save();

        if ($liberarStock) {
            $stockDestino->cantidad_reservada   = 0;
            $stockDestino->seleccionado         = 0;
            $stockOrigen->cantidad_transito     = 0;
        } else {
            // En destino sumas lo recibido a "seleccionado" (ajusta si tu modelo espera otro campo)
            $stockDestino->cantidad_reservada   = (float) $stockDestino->cantidad_reservada + $qty;
            $stockDestino->seleccionado         = (float) $stockDestino->seleccionado + $qty;
        }

        $stockDestino->save();
    }

    protected function updateStatusReservation($itm, $reserva)
    {
        if (!$reserva) {
            return;
        }

        // BLOQUEO DE SEGURIDAD:
        // Si la reserva está cancelada o rechazada, NO la tocamos.
        // Ya cumplió su ciclo de vida y el stock se liberó en el paso anterior.
        if (in_array($reserva->estado, ['cancelado', 'rechazada'])) {
            return;
        }
        $existePendiente = GpCotizacionDetalleStock::query()
            ->from('gp_cotizacion_detalle_stocks as ds')
            ->join('gp_traslados_reservas as tr', 'tr.cotizacion_id', '=', 'ds.cotizacion_id')
            ->where('tr.reserva_id', $reserva->id)
            ->where('ds.cantidad_traslado', '>', 0)
            ->exists();
        if (!$existePendiente && $reserva->estado !== 'aprobado') {
            $reserva->update(['estado' => 'aprobado']); // GpReserva
        }
    }



    protected function createRegisterHistoryIng($guia, $items, GpUsuario $user, $quote)
    {
        $ing = $this->builder->createRegisterHistortyHeader($guia, $user, $quote);
        $ing->save();
        foreach ($items as $item) {
            $de = $this->builder->createRegisterHistoryDetail($item, $ing);
            $de->save();
        }
    }


    protected function validateExistsForReceivedUpdateStatusGuide(GpGuiaRemisionReserva $guia): bool
    {
        $completa = !GpGuiaRemisionReservaDetalle::where('guia_id', $guia->id)
            ->whereColumn('recibido', '<', 'cantidad')->exists();
        if ($completa) {
            $guia->update(['estado' => 'recibida']);
            return true;
        }
        return false;
    }


    protected function sendNotificacionReceivedItems(GpGuiaRemisionReserva $guia, bool $exists, array $items, GpUsuario $userReceived)
    {
        $idsItems = collect($items)->pluck('id')->toArray();
        $detalles = GpGuiaRemisionReservaDetalle::with(['cotizacion:id,cotizacion', 'traslado.reserva:id,user_ingreso_reserva_id'])->whereIn('id', $idsItems)->where('guia_id', $guia->id)->get()->keyBy('id');
        foreach ($items as $dt) {
            $detGuia    = $detalles->get($dt['id']);
            $qtyFmt     = number_format((float) $dt['recibir'], 2, '.', '');
            $title      = $exists ? 'Articulo recibido completo' : 'Articulo recibido parcial';
            $msg        = sprintf(
                'Se ha recibido la cantidad de %s del artículo %s en la bodega %s (cotización %s) por parte de %s.',
                $qtyFmt,
                $detGuia->art_code,
                $guia->bodega_destino,
                $detGuia->cotizacion?->cotizacion ?? '-',
                $userReceived->USERID ?? '-'
            );
            /** notificacion al usuario que su item fue recibido */
            NotifyReceivedReservationsJob::dispatch($title, $msg, $detGuia->traslado->reserva->user_ingreso_reserva_id);
        }
    }


    protected function validateReceivedQty(array $items): ?string
    {
        $idsItems       = collect($items)->pluck('id')->toArray();
        $detalleGuias   = GpGuiaRemisionReservaDetalle::whereIn('id', $idsItems)->get(['id', 'art_code', 'cantidad', 'recibido'])->keyBy('id');
        foreach ($items as $value) {
            $detGuia = $detalleGuias->get($value['id']);
            if ($detGuia) {
                $recibir        = (float) data_get($value, 'recibir', 0);
                $actual         = (float) $detGuia->recibido;
                $disponible     =  max(0,  $detGuia->cantidad - $actual);

                if (($actual + $recibir) > $detGuia->cantidad) {
                    return "El ítem {$detGuia->art_code} excede el límite permitido. (Máximo disponible: {$disponible}).";
                }
            }
        }
        return null;
    }


    protected function generateDeterministicUuidReceivedReservation(int $guiaId, string $quote, string $numTransfer, array $items): string
    {
        $dataPoints = [];
        foreach ($items as  $item) {
            $recibido   = max(0, (float) data_get($item, 'recibir', 0));
            $pendiente  = max(0, (float) data_get($item, 'pending_received', 0));
            $dataPoints[] = sprintf(
                "ROW:%s|QTY:%.4f|ST_RES:%.4f",
                data_get($item, 'id'),
                $recibido,
                $pendiente
            );
        }
        sort($dataPoints);
        $seed = sprintf(
            "GUID:%s|QT:%s|TRF:%s|ITEMS:[%s]",
            $guiaId,
            $quote,
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









    /*** funcion para obtebner el detalle de la guia de remision de reserva para vista de consulta  */

    public function getDetailReservationDeliveryNoteById($id): array
    {
        $guia = GpGuiaRemisionReserva::find($id);
        return $this->getDataReservationDeliveryNote($guia);
    }
}
