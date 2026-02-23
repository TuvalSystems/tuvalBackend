<?php

namespace App\Services\Bodegas;

use App\Enums\EstablishmentGuideSequential;
use App\Models\despachos\GpGuiaRemision;
use App\Models\GpBodega;
use App\Models\GpPedido;
use App\Models\GpPuntoVenta;
use App\Models\GpRelacionPuntoVentaBodega;
use App\Models\GpTempGuiasDespachoBlock;
use App\Models\GpTransportista;
use App\Models\GpUsuario;
use App\Models\GpVehiculo;
use App\Models\reservas\GpGuiaRemisionReserva;
use App\Services\Api\Bodegas\ApiDeliverNoteService;
use App\Services\Bodegas\Contracts\DeliveryNoteCommonServiceInterface;
use Illuminate\Support\Collection;

class DeliveryNoteCommonService implements DeliveryNoteCommonServiceInterface
{

    public function __construct(protected readonly ApiDeliverNoteService $apiDeliveryNote) {}


    public function getNextGuideSequential(string $establecimiento, string $ptoEmision): string
    {
        //1.- ULTIMA DE GUIAS NORMALES
        $lastNormal = GpGuiaRemision::where('GUI_REM_ESTABLECIMIENTO', $establecimiento)
            ->where('GUI_REM_PTO_EMISION', $ptoEmision)
            ->max('GUI_REM_SECUENCIAL');

        //2.- ULTIMA DE GUIAS DE RESERVAS
        $lastReservation = GpGuiaRemisionReserva::where('establecimiento', $establecimiento)
            ->where('pto_emision', $ptoEmision)
            ->max('secuencial');

        $n1 = $this->toSequentialInt($lastNormal);
        $n2 = $this->toSequentialInt($lastReservation);

        $current = max($n1, $n2);
        if ($current === 0) {
            $current = $this->getBaseSequentialByEstablishment($establecimiento);
        }
        return str_pad($current + 1, 9, '0', STR_PAD_LEFT);
    }


    protected function toSequentialInt(?string $value): int
    {
        if (empty($value)) {
            return 0;
        }
        return (int) ltrim($value, '0');
    }


    protected function getBaseSequentialByEstablishment(string $establecimiento): int
    {
        $enum = EstablishmentGuideSequential::tryFromCode($establecimiento);
        $base = $enum?->baseSequential() ?? '000000';
        return (int) ltrim($base, '0');
    }



    public function groupItemsByOrderReservation(Collection $details): array
    {

        $groups = [];
        foreach ($details as $det) {
            $trr = $det->traslado;
            if (!$trr || !$trr->cotizacion) {
                continue;
            }
            $cot = $trr->cotizacion->cotizacion;
            $groups[$cot] ??= ['cotizacion' => $cot, 'items' => [], 'ws_items' => []];

            $groups[$cot]['items'][] = $det;

            //items para enviar al webservice de la guia de reserva
            $groups[$cot]['ws_items'][] = [
                'item' => $det->art_code,
                'compartimiento' => '',
                'cantidad' => (float) $det->cantidad,
            ];
        }
        return $groups;
    }




    public function moveReservedToTransferredForTrfvLine($trl, $qty)
    {
        $cantidad = (float) ($trl->cantidad ?? 0);
        $trasladado = (float) ($trl->trasladado ?? 0);
        $reservado = (float) ($trl->reservado ?? 0);

        $restante = max(0.0, $cantidad - $trasladado);
        $delta = max(0.0, min((float) $qty, $restante));
        if ($delta <= 0) {
            $trl->completado = (int) (($trasladado) >= $cantidad);
            $trl->save();
            return;
        }
        $nuevoTrasladado = min($cantidad, $trasladado + $delta);
        $nuevoReservado = max(0.0, $reservado - $delta);

        $trl->trasladado = $nuevoTrasladado;
        $trl->reservado = $nuevoReservado;
        $trl->completado = (int) (($nuevoTrasladado) >= $cantidad);
        $trl->save();
    }


    /** obtencion de informacion de vehiculo */

    public function getVehicleAndCarrierInfo($payload): array
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



    public function getDirection($ptoWarehouse): string
    {
        $pto = GpPuntoVenta::where('PTO_VTA_ID', $ptoWarehouse)->select('PTO_VTA_DIRECCION_1', 'PTO_VTA_DIRECCION_2')->first();
        return $pto ? trim($pto->PTO_VTA_DIRECCION_1 . ' ' . $pto->PTO_VTA_DIRECCION_2) : '';
    }



    public function getWarehouseDescriptionOrigin($ptoWarehouse)
    {
        return GpBodega::where('BOD_BODEGA', $ptoWarehouse)->value('BOD_DESCRIPCION');
    }


    public function formatGuideNumber(string $ptoVta, string $ptoEmision, string $secuencia): string
    {
        $pv = str_pad($ptoVta, 3, '0', STR_PAD_LEFT);
        $pe = str_pad($ptoEmision, 3, '0', STR_PAD_LEFT);
        $sec = str_pad($secuencia, 9, '0', STR_PAD_LEFT);
        return "$pv-$pe-$sec";
    }


    /** proceso de reasignacion de clave de acceso para guias de remision global  */

    public function assigmentAccessCodeToGuidePending($guia)
    {
        if (!empty($guia->GUI_REM_CODIGO)) {
            return $guia->GUI_REM_CODIGO;
        }

        $res = $this->apiDeliveryNote->getAccessCodeByNumberDocDelivery([
            'numDoc' => $guia->GUI_REM_GUIA_REMISION,
        ]);
        $ok = data_get($res, 'success') === true;
        $msg = data_get($res, 'message', 'Error desconocido');
        $code = data_get($res, 'data.claveAcceso');

        if (!$ok) {
            throw new \DomainException($msg);
        }
        if (!is_string($code) || $code === '') {
            throw new \UnexpectedValueException('La API no retornó una claveAcceso válida.');
        }

        $updated = GpGuiaRemision::whereKey($guia->getKey())
            ->whereNull('GUI_REM_CODIGO')
            ->update(['GUI_REM_CODIGO' => $code]);

        return $updated ? $code : (string) $guia->fresh()->GUI_REM_CODIGO;
    }


    /** funciones temporales de bloqueo de guia */

    public function isGUideBlockedForOrder($guia): bool
    {
        return GpPedido::where('pedido', $guia->pedido)->where('pendiente_salida', true)->exists();
    }

    public function createTemporaryDeliveryNoteBlocksOrder($guia, GpUsuario $user)
    {
        $new            = new GpTempGuiasDespachoBlock();
        $new->guia_id   = $guia->id;
        $new->pedido    = $guia->pedido;
        $new->user_id   = $user->id;
        $new->save();
    }


    /** funcion para obtener el punto de venta de cada bodega */

    public function getPtoVtaByWarehouse($bodega): ?string
    {
        return GpRelacionPuntoVentaBodega::where('PTO_VTA_BOD_BODEGA', $bodega)->value('PTO_VTA_BOD_PUNTO_VENTA');
    }
}
