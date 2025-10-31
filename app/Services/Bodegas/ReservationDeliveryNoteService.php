<?php

namespace App\Services\Bodegas;

use App\Models\GpCotizacionDetalleStock;
use App\Models\reservas\GpTrasladosReservas;
use App\Models\GpPuntoVenta;
use App\Models\GpTransportista;
use App\Models\GpUsuario;
use App\Models\GpVehiculo;
use App\Models\reservas\GpGuiaRemisionReserva;
use App\Models\reservas\GpGuiaRemisionReservaDetalle;
use App\Services\Bodegas\Contracts\DeliveryNoteCommonServiceInterface;
use App\Services\Bodegas\Contracts\ReservationDeliveryNoteServiceInterface;
use App\Services\Builders\Bodega\Contracts\ReservationDeliveryNoteParamsBuilderInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReservationDeliveryNoteService implements ReservationDeliveryNoteServiceInterface
{

    protected $common;
    protected $builder;
    public function __construct(
        DeliveryNoteCommonServiceInterface $common,
        ReservationDeliveryNoteParamsBuilderInterface $builder
    ) {
        $this->common = $common;
        $this->builder = $builder;
    }


    public function createDeliveryNoteFromReservation($payload)
    {
        $this->validateItemsTransferReserved($payload['detalle']);
        $user = GpUsuario::find(Auth::user()->id);
        $datavehicle = $this->getVehicleAndCarrierInfo($payload);
        $secuencial = $this->common->getNextGuideSequential($payload['pto_origen'], '001');
        $dirOrigin = $this->getDirection($payload['pto_origen']);
        $dirDestination = $this->getDirection($payload['pto_destino']);
        $numGuide = $this->formatGuideNumber($payload['pto_origen'], '001', $secuencial);
        $bodGuide = $this->getWarehouseDescriptionOrigin($payload['pto_origen']);
        $guia = DB::transaction(function () use ($payload, $user, $datavehicle, $secuencial, $dirDestination, $numGuide, $bodGuide, $dirOrigin) {
            $guia = $this->saveGuideRemisionReservation($payload, $user, $datavehicle, $secuencial, $dirDestination, $numGuide, $bodGuide, $dirOrigin);
            $guia->save();
            $this->saveDetailGuideRemisionReservation($payload['detalle'], $guia->id);
            // procesos web mysql
            DB::afterCommit(function () use ($payload) {
                //execute sps
            });
            return $guia;
        });

        return $guia;
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

        $detTraslados = GpTrasladosReservas::whereIn('id', $itemsIds)->get();
        if ($detTraslados->count() !== count($itemsIds)) {
            throw new \RuntimeException(
                'Hay items que no se encuentran en la base de datos, por favor actualizar la lista de items'
            );
        }
        $detallesGuia = $this->getQtyDetailGuideRemisionReserved($itemsIds);
        $arrMsg = [];
        $detStock = GpCotizacionDetalleStock::whereIn('id', $dQoteIds)->get()->keyBy('id');
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
                $arrMsg[] = 'El item ' . $itemProcess['art_code'] .
                    ' con cantidad ' . $selectNow .
                    ' excede la cantidad disponible en la guia de reposicion ' . $pending .
                    ' y la cantidad autorizada de reserva ' . $qtyStockAuth;
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

     public function getDirection($ptoWarehouse)
    {
        $pto = GpPuntoVenta::where('PTO_VTA_ID', $ptoWarehouse)->select('PTO_VTA_DIRECCION_1', 'PTO_VTA_DIRECCION_2')->first();
        return $pto ? trim($pto->PTO_VTA_DIRECCION_1 . ' ' . $pto->PTO_VTA_DIRECCION_2) : '';
    }



    public function getWarehouseDescriptionOrigin($ptoWarehouse)
    {
       return GpPuntoVenta::where('PTO_VTA_ID', $ptoWarehouse)->value('PTO_VTA_DESCRIPCION');

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

    public function saveDetailGuideRemisionReservation($items, $guiaId){
        foreach ($items as $value) {
            $detail = $this->builder->buildReservationGuideDetails($value, $guiaId);
            $detail->save();
        }
    }

}
