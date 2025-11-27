<?php

namespace App\Services\Bodegas;

use App\Models\GpBodega;
use App\Models\GpCotizacionDetalleStock;
use App\Models\reservas\GpTrasladosReservas;
use App\Models\GpPuntoVenta;
use App\Models\GpTransportista;
use App\Models\GpUsuario;
use App\Models\GpVehiculo;
use App\Models\reservas\GpGuiaRemisionReserva;
use App\Models\reservas\GpGuiaRemisionReservaDetalle;
use App\Models\reservas\GpGuiaRemisionReservaMovimiento;
use App\Services\Api\Bodegas\ApiDeliverNoteService;
use App\Services\Bodegas\Contracts\DeliveryNoteCommonServiceInterface;
use App\Services\Bodegas\Contracts\LogisticsOperationsServiceInterface;
use App\Services\Bodegas\Contracts\ReservationDeliveryNoteServiceInterface;
use App\Services\Builders\Bodega\Contracts\ReservationDeliveryNoteParamsBuilderInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;


class ReservationDeliveryNoteService implements ReservationDeliveryNoteServiceInterface
{
    protected $common;
    protected $builder;
    protected $apiDelivery;
    protected $logistic;
    public function __construct(
        DeliveryNoteCommonServiceInterface $common,
        ReservationDeliveryNoteParamsBuilderInterface $builder,
        ApiDeliverNoteService $apiDeliveryNote,
        LogisticsOperationsServiceInterface $logisticsOperations
    ) {
        $this->common = $common;
        $this->builder = $builder;
        $this->apiDelivery = $apiDeliveryNote;
        $this->logistic = $logisticsOperations;
    }


    public function createDeliveryNoteFromReservation($payload)
    {
        $datavehicle = $this->getVehicleAndCarrierInfo($payload);
        $dirOrigin = $this->getDirection($payload['pto_origen']);
        $dirDestination = $this->getDirection($payload['pto_destino']);
        $bodGuide = $this->getWarehouseDescriptionOrigin($payload['bod_origen']);
        $idemKey = $this->makeIdemKey($payload);
        $guia = DB::transaction(function () use ($payload, $datavehicle, $dirOrigin, $dirDestination, $bodGuide, $idemKey) {

            $existing = GpGuiaRemisionReserva::where('idempotency_key', $idemKey)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                DB::afterCommit(function () use ($existing) {
                    $this->sendReservationDeliveryNoteToGp($existing);
                    $existing->update(['estado' => 'enviada']);
                });
                return $existing;
            }
            $this->validateItemsTransferReserved($payload['detalle']);
            $user = GpUsuario::find(Auth::user()->id);
            $secuencial = $this->common->getNextGuideSequential($payload['pto_origen'], '001');
            $numGuide = $this->formatGuideNumber($payload['pto_origen'], '001', $secuencial);
            $guia = $this->saveGuideRemisionReservation($payload, $user, $datavehicle, $secuencial, $dirDestination, $numGuide, $bodGuide, $dirOrigin);
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
            'pto_origen' => $payload['pto_origen'],
            'pto_destino' => $payload['pto_destino'],
            'bod_origen' => $payload['bod_origen'] ?? null,
            'vehiculo' => $payload['vehiculo_id'] ?? $payload['placa'] ?? null,
            'transportista' => $payload['transporte_id'] ?? $payload['identificacion'] ?? null,
            'fecha_inicio' => $payload['fecha_inicio'] ?? null,
            'fecha_fin' => $payload['fecha_fin'] ?? null,
            'detalle' => collect($payload['detalle'])->map(fn($d) => [
                'id' => $d['id'],
                'sel' => (float) ($d['seleccionado'] ?? 0),
            ])->sortBy('id')->values()->all(),
        ];
        return hash('sha256', json_encode($base));
    }

    public function validateItemsTransferReserved($items)
    {
        $items = collect($items);
        if ($items->isEmpty()) {
            throw new \RuntimeException(
                'No hay items para procesar el ajuste de la guía de reposición'
            );
        }
        $itemsIds = $items->pluck('id')->toArray();
        $dQoteIds = $items->pluck('stock_id')->toArray();
        $itemsPkId = $items->keyBy('id');
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
            $rowsDetalle = $detallesGuia->get($tr->id, collect());
            $itemProcess = $itemsPkId->get($tr->id);
            $stockRow = $detStock->get($tr->stock_id);
            if (!$stockRow) {
                $arrMsg[] = "El item {$itemProcess->art_code} no tiene stock asociado válido";
                continue;
            }
            // calculos
            $selectNow = (float) $itemProcess['seleccionado'];
            $pending = (float) $itemProcess['pending_transfer'];
            $qtyStockAuth = (float) $stockRow->cantidad_autorizada_reserva;
            $qtyGuiaRemision = $rowsDetalle->where('art_code', $itemProcess['art_code'])->sum('cantidad');
            $sumQty = $qtyGuiaRemision + $selectNow;
            $sumHistory = $pending + $qtyStockAuth;

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

    public function getDirection($ptoWarehouse)
    {
        $pto = GpPuntoVenta::where('PTO_VTA_ID', $ptoWarehouse)->select('PTO_VTA_DIRECCION_1', 'PTO_VTA_DIRECCION_2')->first();
        return $pto ? trim($pto->PTO_VTA_DIRECCION_1 . ' ' . $pto->PTO_VTA_DIRECCION_2) : '';
    }



    public function getWarehouseDescriptionOrigin($ptoWarehouse)
    {
        return GpBodega::where('BOD_BODEGA', $ptoWarehouse)->value('BOD_DESCRIPCION');

    }


    public function getVehicleAndCarrierInfo($payload)
    {
        $datos = [
            'vehiculo' => [],
            'transportista' => [],
        ];

        if ($payload['modo'] === 'local') {
            $transportista = GpTransportista::where('id', $payload['transporte_id'])->first();
            $vehiculo = GpVehiculo::where('id', $payload['vehiculo_id'])->first();
            $datos['vehiculo'] = [
                'placa' => $vehiculo?->placa,
            ];
            $datos['transportista'] = [
                'nombre' => $transportista?->nombre,
                'identificacion' => $transportista?->identificacion,
                'tipo_identificacion' => $transportista?->tipo_identificacion
            ];
            return $datos;
        }
        $datos['vehiculo'] = [
            'placa' => $payload['placa'],
        ];
        $datos['transportista'] = [
            'nombre' => $payload['nombre_transporte'],
            'identificacion' => $payload['identificacion'],
            'tipo_identificacion' => $payload['tipo_identificacion']
        ];

        return $datos;

    }

    public function formatGuideNumber($ptoVta, $ptoEmision, $secuencia): string
    {
        return (string) trim($ptoVta . '-' . $ptoEmision . '-' . $secuencia);
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
        $seleccionado = (float) $value['seleccionado'] ?? 0;
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
        $success = $response['success'] ?? false;
        if (!$success) {
            throw new \Exception($response['message']);
        }
        return $success;
    }


    public function validateExistenceDeliveryNote($guia)
    {
        $response = $this->apiDelivery->checkExistenceDeliveryNote([
            'numDoc' => $guia->numero_guia
        ]);
        return $response;
    }





    /*** proceso para generar el pdf de guia de remision pendiente */


    public function generatePdfReservedDeliveryNote($payload)
    {
        $id = $payload['id'];
        $guia = GpGuiaRemisionReserva::find($id);
        $detalle = GpGuiaRemisionReservaDetalle::with([
            'detallecotizacion' => function ($q) {
                $q->select('id', 'CHECK_ALTERNATIVO');
            }
        ])->where('guia_id', $guia->id)->get();

        $vieData = [
            'guia' => $guia,
            'claveAcceso' => $guia->clave_acceso,
            'detalle' => $detalle,
            'direccion' => $this->getDirection($guia->establecimiento),
            'descripcion' => $this->getDescriptionWarehouse($guia->establecimiento),
            'pesoTotal' => $this->calculateKgTotal($detalle->pluck('traslado_id')->toArray()),
            'quoteList' => $this->getListQuotesTranfers($detalle->pluck('traslado_id')->toArray())
        ];

        $html = view('pdf.guia-remision-reserva-pendiente', $vieData)->render();
        $pdf = app('dompdf.wrapper');
        $pdf->setPaper('a4', 'portrait');
        $pdf->loadHTML($html);
        $binary = $pdf->output();
        $filename = 'guia-remision-' . Str::slug($guia->numero_guia) . '.pdf';
        return [
            'binary' => $binary,
            'filename' => $filename,
            'content_type' => 'application/pdf'

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
    public function dispatchGuideReservation($payload): array|Collection|GpGuiaRemisionReserva|Model|null
    {
        $processed = false;
        $user = GpUsuario::whereKey(Auth::id())->value('USERID') ?: 'adminWeb';
        $id = (int) data_get($payload, 'id');
        $guia = GpGuiaRemisionReserva::find($id);
        $details = $this->getDetailsGuideWithTransfers($guia->id);
        if ($details->isEmpty()) {
            throw new \RuntimeException('No hay items para procesar el ajuste de la guía de reserva');
        }
        $groups = $this->common->groupItemsByOrderReservation($details);
        if (empty($groups)) {
            throw new \RuntimeException('No hay items para procesar el ajuste de la guía de reserva');
        }
        foreach ($groups as $quote => $group) {
            $params = $this->builder->buildReservedDeliveryNotePayload($guia, $quote, $group['ws_items'], $user);
            $ok = $this->validateExistenceReservationGuideAdjustment($guia, $params, $group['items'], $quote);
            if ($ok) {
                $processed = true;
            }
        }
        if (!$processed) {
            throw new \Exception('No se pudo procesar el ajuste de la guía de Reserva');

        }
        $guia->update(['estado' => 'enviada']);
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



    public function validateExistenceReservationGuideAdjustment(GpGuiaRemisionReserva $guia, array $params, array $groupItems, string $quote): bool
    {
        $cabecera = data_get($params, 'cabecera', []);
        $payloadValidate = $this->builder->buildReservationGuideValidationPayload($cabecera);
        $resValidate = $this->apiDelivery->checkReservationGuideAdjustment($payloadValidate);
        $headerRespValidate = data_get($resValidate, 'data');
        $validateSuccess = data_get($resValidate, 'success');
        $docGpValidate = trim(data_get($headerRespValidate, 'docGp', ''));

        /** cuando existe y solo necesita reasignar y procesar los movimientos de entrada y salida */
        if (!empty($headerRespValidate) && filled($docGpValidate)) {
            $already = $this->movementExistsWeb($guia->id, $quote, $docGpValidate);
            if ($already) {
                return true;
            }
            return DB::transaction(function () use ($groupItems, $guia, $headerRespValidate, $quote, $already) {
                $this->processReservationGuideDecrementStock($groupItems);
                $this->createMovementReservation($guia, $headerRespValidate, $quote);
                return true;
            });
        }
        /** en caso de que no exista la guia de reserva debe crearla */
        if (empty($headerRespValidate) && $validateSuccess === false) {
            $resp = $this->apiDelivery->createReservationGuideAjustment($params);
            $success = data_get($resp, 'success', false);
            $data = data_get($resp, 'data', []);
            $msg = data_get($resp, 'message');
            $docGp = trim(data_get($resp, 'data.docGp', '')); // null si no existe
            if (!$success) {
                throw new \Exception($msg ?: 'Error creando el ajuste de guía en GP.');
            }
            if ($docGp === '') {
                throw new \Exception('No se ha generado el documento de ajuste de guía en Gp.');
            }

            return DB::transaction(function () use ($groupItems, $guia, $data, $quote) {
                $this->processReservationGuideDecrementStock($groupItems);
                $this->createMovementReservation($guia, $data, $quote);
                return true;
            });
        }
        return false;
    }


    public function movementExistsWeb($guiaId, $quote, $docGp): bool
    {
        return GpGuiaRemisionReservaMovimiento::query()
            ->where('guia_id', $guiaId)
            ->where('tipo_movimiento', 'TRC')
            ->where('cotizacion', $quote)
            ->where('codigo', trim((string) $docGp))
            ->exists();
    }

    public function processReservationGuideDecrementStock($groupItems)
    {
        foreach ($groupItems as $item) {
            $this->common->moveReservedToTransferredForTrfvLine($item->traslado, $item->cantidad);
        }
    }

    public function createMovementReservation($guia, $responseApi, $quote): GpGuiaRemisionReservaMovimiento
    {
        return GpGuiaRemisionReservaMovimiento::firstOrCreate([
            'guia_id' => $guia->id,
            'tipo_movimiento' => 'TRC',
            'cotizacion' => $quote,
            'id_movimiento' => data_get($responseApi, 'idMovimiento'),
            'codigo' => trim((string) data_get($responseApi, 'docGp', '')),
        ]);
    }

}
