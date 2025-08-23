<?php

namespace App\Services\Bodegas;

use App\Models\GpPedido;
use App\Models\GpPuntoVenta;
use App\Models\GpOrdenDespacho;
use App\Models\GpPedidoDetalle;
use App\Models\despachos\GpGuiaRemision;
use App\Models\traslados\GpTrasladosVentas;
use App\Models\despachos\GpGuiaRemisionDetalle;
use App\Models\reposicion\GpTrasladosReposicion;
use App\Models\despachos\GpGuiaRemisionTransferencia;
use App\Services\Bodegas\Contracts\DeliveryNoteServiceInterface;
use Illuminate\Support\Collection;

class DeliveryNoteService implements DeliveryNoteServiceInterface
{
    /**
     *  SERVICIO DEDICADO AL  GUIAREMISIONCONTROLLER
     */

    // protected $invoiceService;
    public function __construct()
    {
        // $this->invoiceService = $invoiceService;
    }


    /**
     * funcion para consulta del detalle de la guia para visualizar en bodega - consultas
     */

    public function getDeliveryNoteDetailByType(int|string $guiaId): array
    {
        $guia = GpGuiaRemision::findOrFail($guiaId);
        $detalle = GpGuiaRemisionDetalle::where('guia_remision_id',  $guia->id)
            ->get(['id', 'guia_remision_id', 'detalle_id', 'GUI_REM_CODI_INTERNO', 'GUI_REM_CANTIDAD', 'GUI_REM_CODI_ADICIONAL', 'GUI_REM_UNIDAD_MEDIDA', 'GUI_REM_DESCRIPCION']);
        $pesoTotal = 0.0;
        if ($detalle->isNotEmpty()) {
            $ids = $detalle->pluck('detalle_id')->toArray();
            $pedidoDetalle = $this->getDetailByTypeGuide($guia->tipo, $ids);
            $checksAlternativos = $this->getCheckAlternativeByDetailGuide($ids);
            $detalle = $detalle->map(function ($det) use ($pedidoDetalle, $checksAlternativos, &$pesoTotal) {
                $art = $pedidoDetalle->get($det->detalle_id);

                if (($art->ART_TIPO_COMPRA ?? null) == 'SERV') return null;

                $cantidad = (float) $det->GUI_REM_CANTIDAD;
                $peso = ($art == null || $art->ART_PESO == null) ? 0.0 : (float) $art->ART_PESO;
                $pesoItem = $peso * $cantidad;
                $pesoTotal += $pesoItem;

                $det->setAttribute('CHECK_ALTERNATIVO', $checksAlternativos->get($det->detalle_id) ?? 0);
                $det->setAttribute('ART_PESO', $peso);
                return $det;
            })->filter()->values();
        }
        $transferencias = GpGuiaRemisionTransferencia::where('guia_remision_id', $guia->id)
            ->pluck( 'pedido')
            ->toArray();
        return [
            'detalle' => $detalle,
            'transferencias' => $transferencias,
            'peso_total' =>  round($pesoTotal, 2),
        ];
    }



    public function getDetailByTypeGuide(string $type, array $ids): Collection
    {
        if (empty($ids)) {
            return collect();
        }
        return match ($type) {
            'despacho' => GpPedidoDetalle::whereIn('id', $ids)->get()->keyBy('id'),
            'reposicion' => GpTrasladosReposicion::whereIn('id', $ids)->get()->keyBy('id'),
            'transferencia' => GpTrasladosVentas::whereIn('id', $ids)->get()->keyBy('id'),
            default => collect(),
        };
    }

    public function getCheckAlternativeByDetailGuide(array $ids): Collection
    {
        if (empty($ids)) {
            return collect();
        }

        return GpPedidoDetalle::whereIn('id', $ids)
            ->pluck('CHECK_ALTERNATIVO', 'id');
    }
}
