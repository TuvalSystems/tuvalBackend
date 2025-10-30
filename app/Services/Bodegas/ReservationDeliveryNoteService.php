<?php

namespace App\Services\Bodegas;

use App\Models\GpCotizacionDetalleStock;
use App\Models\reservas\GpTrasladosReservas;
use App\Models\GpPuntoVenta;
use App\Models\GpTransportista;
use App\Models\GpVehiculo;
use App\Models\reservas\GpGuiaRemisionReservaDetalle;
use App\Services\Bodegas\Contracts\ReservationDeliveryNoteServiceInterface;
use Illuminate\Support\Facades\DB;

class ReservationDeliveryNoteService implements ReservationDeliveryNoteServiceInterface
{

    public function __construct()
    {
        // Inyecta dependencias si es necesario
    }


    public function createDeliveryNoteFromReservation($payload)
    {
        $this->validateItemsTransferReserved($payload['detalle']);
        $datavehicle = $this->getinfoVehicle($payload);
        $dirDestination = $this->getDirectionDestination($payload['pto_destino']);
        // return $datavehicle;
        $guia = DB::transaction(function () use ($payload) {
            $guia = [];
            // procesos web mysql
            DB::afterCommit(function () use ($payload) {
                //execute sps
            });
            return $guia;
        });

        return $dirDestination;
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

    public function getDirectionDestination($ptoWarehouse)
    {
        $pto = GpPuntoVenta::where('PTO_VTA_ID', $ptoWarehouse)->select('PTO_VTA_DIRECCION_1', 'PTO_VTA_DIRECCION_2')->first();
        return $pto ? trim($pto->PTO_VTA_DIRECCION_1 . ' ' . $pto->PTO_VTA_DIRECCION_2) : '';
    }


    public function getinfoVehicle($payload)
    {
        $datos = [];
        if ($payload['modo'] === 'local') {
            $transportista = GpTransportista::where('id', $payload['transporte_id'])->first();
            $vehiculo = GpVehiculo::where('id', $payload['vehiculo_id'])->first();
            $datos['vehiculo'] = [
                'placa' => $vehiculo->placa,
            ];
            $datos['transportista'] = [
                'nombre' => $transportista->nombre,
                'identificacion' => $transportista->identificacion,
                'tipo_identificacion' => $transportista->tipo_identificacion
            ];
        } else {
            $datos['vehiculo'] = [
                'placa' => $payload['placa'],
            ];
            $datos['transportista'] = [
                'nombre' => $payload['nombre_transporte'],
                'identificacion' => $payload['identificacion'],
                'tipo_identificacion' => $payload['tipo_identificacion']
            ];
        }
        return $datos;

    }

}
