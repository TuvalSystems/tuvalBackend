<?php

namespace App\Services\Inventario\Bodegas;

use App\Enums\bodegas\guiaremision\DeliveryNoteStatus;
use App\Enums\EstablishmentGuideSequential;
use App\Models\despachos\GpGuiaRemision;
use App\Models\despachos\GpGuiaRemisionDetalle;
use App\Models\despachos\GpGuiaRemisionTransferencia;
use App\Models\GpBodega;
use App\Models\GpPedido;
use App\Models\GpPuntoVenta;
use App\Models\GpRelacionPuntoVentaBodega;
use App\Models\GpTempGuiasDespachoBlock;
use App\Models\GpTransportista;
use App\Models\GpUsuario;
use App\Models\GpVehiculo;
use App\Models\Reservas\GpGuiaRemisionReserva;
use App\Services\Api\Bodegas\ApiDeliverNoteService;
use App\Services\Inventario\Bodegas\Contracts\DeliveryNoteCommonServiceInterface;
use Illuminate\Support\Arr;
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
            $trr    = $det->traslado;
            if (!$trr || !$trr->cotizacion) {
                continue;
            }
            $cot            = $trr->cotizacion->cotizacion;
            $groups[$cot] ??= ['cotizacion' => $cot, 'items' => [], 'ws_items' => []];

            $groups[$cot]['items'][] = $det;

            //items para enviar al webservice de la guia de reserva
            $groups[$cot]['ws_items'][] = [
                'item'              => $det->art_code,
                'compartimento'     => '',
                'cantidad'          => (float) $det->cantidad,
            ];
        }
        return $groups;
    }

    public function groupItemsByQuotesToReceivedReserved(array $items, Collection $movimientos, Collection $traslados): array
    {
        $groups = [];
        foreach ($items as $itm) {
            $trm = $traslados->get($itm['traslado_id']);
            if (!$trm) {
                continue;
            }
            $cot                = $itm['c_cotizacion'];
            $mov                = $movimientos->get($itm['c_cotizacion']) ?? null;
            $itm['traslado']    = $trm;

            $groups[$cot]['pedido']         = $cot;
            $groups[$cot]['items'][]        = $itm;
            $groups[$cot]['transferencia']  = $mov?->codigo ?? '';
            $groups[$cot]['ws_items'][] = [
                'item'                      => Arr::get($itm, 'art_code', ''),
                'compartimento'             => Arr::get($itm, 'compartimiento', ''),
                'cantidad'                  => (float) data_get($itm, 'recibir', 0)
            ];
        }
        return $groups;
    }




    public function moveReservedToTransferredForTrfvLine($trl, $qty)
    {
        $cantidad   = (float) ($trl->cantidad ?? 0);
        $trasladado = (float) ($trl->trasladado ?? 0);
        $reservado  = (float) ($trl->reservado ?? 0);

        $restante   = max(0.0, $cantidad - $trasladado);
        $delta      = max(0.0, min((float) $qty, $restante));
        if ($delta <= 0) {
            $trl->completado = (int) (($trasladado) >= $cantidad);
            $trl->save();
            return;
        }
        $nuevoTrasladado    = min($cantidad, $trasladado + $delta);
        $nuevoReservado     = max(0.0, $reservado - $delta);

        $trl->trasladado    = $nuevoTrasladado;
        $trl->reservado     = $nuevoReservado;
        $trl->completado    = (int) (($nuevoTrasladado) >= $cantidad);
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

    public function isGuideBlockedForOrder($guia): bool
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


    /**
     *  funciones para utilizadas para recibir guias de remision de transferencia y reposicion
     */
    public function checkMovementExistsWeb(int $guiaId, string $order, string $docGp, string $hash, string $typeMov, string $type): bool
    {
        return GpGuiaRemisionTransferencia::where('guia_remision_id', $guiaId)
            ->where('pedido', $order)
            ->where('codigo', trim((string) $docGp))
            ->where('hash_interno', trim((string) $hash))
            ->where('tipo_movimiento', $typeMov)
            ->where('tipo', $type)
            ->exists();
    }

    public function createMovementTransferSales(GpGuiaRemision $guia, string $order, array $data, string $hash, string $typeMov, string $type): GpGuiaRemisionTransferencia
    {
        $movement = new GpGuiaRemisionTransferencia();
        $movement->fill([
            'guia_remision_id'      => $guia->id,
            'id_movimiento'         => data_get($data, 'idMovimiento', ''),
            'tipo_movimiento'       => $typeMov,
            'tipo'                  => $type,
            'hash_interno'          => $hash,
            'pedido'                => $order,
            'codigo'                => trim((string) data_get($data, 'docGp', '')),
        ]);
        $movement->save();
        return $movement;
    }

    public function validatePendingForReceivedSalesTransfer(GpGuiaRemision $guia): bool
    {
        $completa = !GpGuiaRemisionDetalle::where('guia_remision_id', $guia->id)
            ->whereColumn('recibido', '<', 'GUI_REM_CANTIDAD')->exists();
        if ($completa) {
            $guia->estado = DeliveryNoteStatus::RECIBIDA->value;
            $guia->save();
        }
        return $completa;
    }


    /**
     * Genera un UUID v3 (Simulado con MD5) determinista.
     * Mismo Input (Guía + Pedido + Items) = Mismo Hash.
     * Esto permite idempotencia en reintentos.
     */
    public function generateDeterministicUuidDispatch(int $guiaId, string $pedido, array $items, Collection $articulos): string
    {
        // 1. Preparamos los datos de cada item involucrado en este grupo
        $dataPoints = [];

        foreach ($items as $item) {
            // Buscamos el detalle del pedido original para obtener su "historia" (trasladado)
            $art = $articulos->get($item->detalle_id);

            // Usamos 0 si por alguna razón no se encuentra el articulo (safety check)
            $trasladadoHistorico = $art ? (float)$art->trasladado : 0;
            $cantidadDespachando = (float)$item->GUI_REM_CANTIDAD;

            // Creamos la firma única por item:
            // ID + CUANTO MUEVO + CUANTO LLEVABA
            $dataPoints[] = sprintf(
                "ROW:%s|DET:%s|QTY:%s|HIST:%s",
                $item->id, //id del detalle de la guia
                $item->detalle_id, // id del detalle del pedido
                $cantidadDespachando,
                $trasladadoHistorico
            );
        }

        // 2. IMPORTANTE: Ordenamos los items.
        // Si en el reintento la DB devuelve los items en otro orden, el hash NO debe cambiar.
        sort($dataPoints);

        // 3. Creamos la semilla maestra
        $seed = sprintf(
            "GUID:%s|ORD:%s|ITEMS:[%s]",
            $guiaId,
            $pedido,
            implode(';', $dataPoints)
        );

        // 4. Generamos MD5 y formateamos a UUID (36 chars)
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


    /**
     *
     *  function generada para recibir guias de remision de transferencia de ventas y reposicion
     *
     */
    public function generateDeterministicUuidReceived(int $guiaId, string $order, string $numTransfer, array $items): string
    {
        $dataPoints = [];
        $ids        = collect($items)->pluck('id');
        $detalles   = GpGuiaRemisionDetalle::whereIn('id', $ids)->get(
            ['id', 'GUI_REM_CODI_INTERNO', 'GUI_REM_CANTIDAD', 'recibido']
        )->keyBy('id');
        foreach ($items as  $itm) {
            $id         = data_get($itm, 'id');
            $dbItem     = $detalles->get($id);
            if (!$dbItem) {
                continue;
            }
            $qty            = data_get($itm, 'recibido', 0);
            $cantidad       = data_get($itm, 'GUI_REM_CANTIDAD', 0);
            $recibidoH      = data_get($dbItem, 'recibido', 0);
            $pendiente  = max(0, $cantidad - $recibidoH);

            $dataPoints[] = sprintf(
                "ROW:%s|QTY:%.4f|REC_H:%.4f |PEND:%.4f",
                data_get($itm, 'id'),
                $qty,
                $recibidoH,
                $pendiente
            );
        }

        sort($dataPoints);
        $seed = sprintf(
            "GUID:%s|QT:%s|TRF:%s|ITEMS:[%s]",
            $guiaId,
            $order,
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
}
