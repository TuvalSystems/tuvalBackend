<?php

namespace App\Services\Bodegas;

use App\Models\despachos\GpGuiaRemision;
use App\Models\GpOrdenDespacho;
use App\Models\GpPedido;
use App\Models\GpPedidoDetalle;
use App\Services\Api\Bodegas\ApiDeliverNoteService;
use App\Services\Bodegas\Contracts\LogisticsOperationsServiceInterface;
use Illuminate\Support\Facades\Log;

class LogisticsOperationsService implements LogisticsOperationsServiceInterface
{

    protected $apiDeliveryNote;
    public function __construct(ApiDeliverNoteService $apiDeliveryNote)
    {
        $this->apiDeliveryNote = $apiDeliveryNote;
    }


    /** funcion para asignar el numero de  GUI_REM_GUIA_REMISION a cada guia de remision */
    public function assignAccessCodesForPendingGuides()
    {
        $guias = GpGuiaRemision::whereNull('GUI_REM_CODIGO')->get();

        foreach ($guias as $guia) {
            $result = $this->apiDeliveryNote->getAccessCodeByNumberDocDelivery([
                'numDoc' => $guia->GUI_REM_GUIA_REMISION,
            ]);
            if ($result['success'] === false) {
                throw new \Exception($result['message']);
            }
            $response = $result['data'];
            $guia->GUI_REM_CODIGO = $response['claveAcceso'];
            $guia->save();
        }
    }

    //remplaza la funcion de checkdespacho - DespachoController
    public function updateOrderDispatchStatus(GpGuiaRemision $guia)
    {
        $pedido = GpPedido::where('pedido', $guia->pedido)->lockForUpdate()->first();
        if (!$pedido) {
            Log::warning('Pedido no encontrado al verificar despacho', [
                'guia_id' => $guia->id,
                'pedido'  => $guia->pedido,
            ]);
            return null;
        }

        $hasPending = GpPedidoDetalle::query()->where('pedido_id', $pedido->id)
            ->whereRaw('COALESCE(trasladado,0) + COALESCE(devuelto,0) < COALESCE(ART_CANTIDAD,0)')
            ->exists();
        if (!$hasPending) {
            $pedido->fill([
                'despachado' => 1,
                'despacho_en_proceso' => null
            ])->save();
            GpOrdenDespacho::query()
                ->where('pedido_id', $pedido->id)
                ->update(['estado' => 'despachada']);
        }
        return $pedido->refresh();
    }
}
