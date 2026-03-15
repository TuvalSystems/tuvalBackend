<?php

namespace App\Services\Venta;

use App\Enums\Ventas\QuotationMode;
use App\Models\borradores\CotizacionBorrador;
use App\Models\GpUsuario;
use App\Services\Venta\Contracts\QuoteDraftServiceInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class QuoteDraftService implements QuoteDraftServiceInterface
{

    public function __construct()
    {
        // Inyecta dependencias si es necesario
    }


    public function sync(string $draftId, string $ptoVtaId, array $data, GpUsuario $user)
    {
        return Cache::lock("sync_draft_{$draftId}", 10)->get(function () use ($draftId, $ptoVtaId, $data, $user) {
            return DB::transaction(function () use ($draftId, $ptoVtaId, $data, $user) {
                CotizacionBorrador::updateOrCreate([
                    'draft_id'  => $draftId,
                    'user_id'   => $user->id
                ], [
                    'punto_venta_id' => $ptoVtaId,
                    'data'           => $data
                ]);
            });
        });
    }


    public function getDraft(string $draftId, GpUsuario $user)
    {
        return CotizacionBorrador::where('draft_id', $draftId)->where('user_id', $user->id)->first();
    }


    public function listDrafts(?string $ptoVtaId, GpUsuario $user)
    {
        $drafts = CotizacionBorrador::where('user_id', $user->id)
            ->where('punto_venta_id', $ptoVtaId)
            ->orderBy('updated_at', 'desc')->get();

        $lista = $drafts->map(function ($draft) {
            $data = $draft->data;

            $clienteNombre      = data_get($data, 'cliente.CLI_RAZON_SOCIAL', 'Borrador sin cliente');
            $bodegaFacturacion  = data_get($data, 'bodega_facturacion', 'Borrador sin bodega');
            $itemsCount         = count(data_get($data, 'items', []));


            return [
                'draft_id'              => $draft->draft_id,
                'created_temp_at'       => $draft->updated_at->timestamp * 1000,
                'cliente'               => $clienteNombre,
                'bodega_facturacion'    => $bodegaFacturacion,
                'items_count'           => $itemsCount,
                'total'                 => data_get($data, 'total', 0),
                'mode'                  => data_get($data, 'mode', QuotationMode::CREATE->value),
            ];
        });
        return $lista->filter(function ($b) {
            return $b['cliente'] !== 'Borrador sin cliente' || $b['items_count'] > 0;
        })->values();
    }



    public function deleteDraft(string $draftId, GpUsuario $user)
    {
        return CotizacionBorrador::where('draft_id', $draftId)->where('user_id', $user->id)->delete();
    }
}
