<?php

namespace App\Services\Cobranzas;

use App\Models\Cobranzas\GpCobrosChequesFacturas;
use App\Models\Cobranzas\GpCobrosDepositosFacturas;
use App\Models\Cobranzas\GpCobrosEfectivoFactura;
use App\Models\Cobranzas\GpCobrosRetenciones;
use App\Models\GpCobrosAplicacionFactura;
use App\Models\GpCobrosTarjetaFactura;
use App\Models\GpUsuario;
use App\Services\Api\Cobros\ApiBillingService;
use App\Services\Cobranzas\Contracts\CollectionUnapplicationServiceInterface;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

use function PHPUnit\Framework\isEmpty;
use function PHPUnit\Framework\isNull;

class CollectionUnapplicationService implements CollectionUnapplicationServiceInterface
{
    public function __construct(protected ApiBillingService $api) {}

    public function fetchAppliedCollections(array $filters): LengthAwarePaginator
    {
        $sortField  = data_get($filters, 'sortField', 'ultima_fecha_aplicacion');
        $sortOrder  = data_get($filters, 'sortOrder', 'desc');
        $search     =  data_get($filters, 'search');
        $perPage    = data_get($filters, 'perpage', 10);
        $ruc        = data_get($filters, 'cliente');
        $sortColumns = [
            'cliente_id'                => 'res.cliente_id',
            'factura'                   => 'res.factura',
            'ruc'                       => 'res.ruc',
            'cliente'                   => 'res.cliente',
            'recibos'                   => 'res.recibos',
            'tipos_medio'               => 'res.tipos_medio',
            'cantidad_aplicaciones'     => 'res.cantidad_aplicaciones',
            'cantidad_medios'           => 'res.cantidad_medios',
            'total_aplicado'            => 'res.total_aplicado',
            'cantidad_documentos_gp'    => 'res.cantidad_documentos_gp',
            'cantidad_documentos_web'   => 'res.cantidad_documentos_web',
            'usuarios_creadores'        => 'res.usuarios_creadores',
            'primera_fecha_cobro'       => 'res.primera_fecha_cobro',
            'ultima_fecha_cobro'        => 'res.ultima_fecha_cobro',
            'primera_fecha_aplicacion'  => 'res.primera_fecha_aplicacion',
            'ultima_fecha_aplicacion'   => 'res.ultima_fecha_aplicacion',
        ];

        $sortBy = $sortColumns[$sortField] ?? 'res.ultima_fecha_aplicacion';
        $sortOrder = strtolower($sortOrder) === 'asc' ? 'asc' : 'desc';


        $query = DB::table('vw_cobros_aplicaciones_facturas_resumen as res')
            ->select([
                'res.cliente_id',
                'res.ruc',
                'res.cliente',
                'res.factura',
                'res.cobro_ids',
                'res.recibos',
                'res.documentos_gp',
                'res.tipos_medio',
                'res.cantidad_aplicaciones',
                'res.cantidad_medios',
                'res.total_aplicado',
                'res.cantidad_documentos_gp',
                'res.cantidad_documentos_web',
                'res.usuarios_creadores',
                'res.primera_fecha_cobro',
                'res.ultima_fecha_cobro',
                'res.primera_fecha_aplicacion',
                'res.ultima_fecha_aplicacion',
            ]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('res.factura', 'like', '%' . $search . '%')
                    ->orWhere('res.ruc', 'like', '%' . $search . '%')
                    ->orWhere('res.cliente', 'like', '%' . $search . '%')
                    ->orWhere('res.recibos', 'like', '%' . $search . '%')
                    ->orWhere('res.documentos_gp', 'like', '%' . $search . '%')
                    ->orWhere('res.tipos_medio', 'like', '%' . $search . '%')
                ;
            });
        }
        if ($ruc) {
            $query->where('res.ruc', '=', $ruc);
        }
        if (!empty($filters['fecha_desde'])) {
            $query->whereDate('res.ultima_fecha_aplicacion', '>=', $filters['fecha_desde']);
        }
        if (!empty($filters['fecha_hasta'])) {
            $query->whereDate('res.ultima_fecha_aplicacion', '<=', $filters['fecha_hasta']);
        }
        return $query
            ->orderBy($sortBy, $sortOrder)
            ->paginate($perPage);
    }





    public function getAllDetailCollection(string $ruc, string $factura, GpUsuario $user)
    {
        $admin = [1, 2];
        $isVisible = in_array($user->perfil_id, $admin, true);
        $query = DB::table('vw_cobros_aplicaciones_facturas_detalle as det')
            ->where(function ($q) use ($ruc, $factura) {
                $q->where('ruc', $ruc)
                    ->where('factura', $factura);
            })->get();
        $checkReceipts      = $this->getCheckReceiptPaymentsGp($factura, $ruc)->map(function ($it) {
            $it['receiptNum'] =  $this->formatReceiptNumber(data_get($it, 'receiptNum'));
            $it['documentGp'] = $this->normalizeDocument(data_get($it, 'documentGp'));
            return $it;
        })->filter(fn($it) => filled(data_get($it, 'receiptNum')))->keyBy('receiptNum');

        $docsApplys             = $this->getPaymentApplyGp($factura, $ruc);
        $collectionDocs         = $this->filterCollectionIds($docsApplys);
        $receiptsDocs           = $this->filterReceiptNumbers($docsApplys);

        $query->map(function ($item) use ($checkReceipts) {
            $tipoMedio = strtoupper(trim($item->tipo_medio ?? ''));
            if ($tipoMedio == 'CHEQUE') {
                $itemGp             = $checkReceipts->get($item->numero_recibo);
                $item->documento_gp = $this->normalizeDocument(
                    data_get($itemGp, 'documentGp', $item->documento_gp ?? null)
                );
            }
            return $item;
        });

        $applyDocsLookup = collect($collectionDocs)
            ->filter()
            ->mapWithKeys(fn($doc) => [$doc => true]);

        $applyReceiptLookup  = collect($receiptsDocs)
            ->filter()
            ->mapWithKeys(fn($doc) => [$doc => true]);


        $actives    = collect($query)
            ->where('estado_aplicado', 1)
            ->map(function ($item) use ($applyDocsLookup, $applyReceiptLookup, $docsApplys) {
                $averageType            = strtoupper(trim($item->tipo_medio ?? ''));
                $documentoGp            = $this->normalizeDocument($item->documento_gp ?? null);
                $isCheckPending         = $averageType === 'CHEQUE' && blank($documentoGp);
                $existsInGp             = $applyDocsLookup->has($documentoGp) || $applyReceiptLookup->has($item->numero_recibo);

                if ($isCheckPending) {
                    $item->can_unapplication = false;
                    $item->unapplication_message = 'Cheque pendiente de contabilizar en GP.';
                } elseif ($existsInGp) {
                    $item->can_unapplication = false;
                    $item->unapplication_message = 'Documento aplicado en GP.';
                } else {
                    $item->can_unapplication = true;
                    $item->unapplication_message = 'Disponible para desaplicar.';
                }
                return $item;
            })->values()->all();

        $inactives  = collect($query)->where('estado_aplicado', 0)->values()->all();
        return [
            'activos'           => $actives,
            'inactivos'         => $inactives,
            'collectionsIds'    => $collectionDocs,
            'receiptNumbers'    => $receiptsDocs,
            'isVisible'         => $isVisible
        ];
    }




    protected function getPaymentApplyGp(string $documento, string $ruc): Collection
    {
        $resp = $this->api->getListCustomerPaymentApply([
            'documento' => $documento,
            'ruc'       => $ruc
        ]);
        return collect(data_get($resp, 'data', []));
    }

    protected function getCheckReceiptPaymentsGp(string $doc, string $ruc): Collection
    {
        $resp = $this->api->getListCustomerCheckReceipts([
            'documento' => $doc,
            'ruc'       => $ruc
        ]);
        return collect(data_get($resp, 'data', []));
    }

    protected function filterCollectionIds(Collection $payments): array
    {
        return $payments
            ->pluck('collectionId')
            ->map(fn($doc) =>  $this->normalizeDocument($doc))
            ->filter(fn($doc) => !empty($doc))
            ->unique()
            ->values()
            ->all();
    }

    protected function filterReceiptNumbers(Collection $payments): array
    {
        return $payments
            ->pluck('numeroRecibo')
            ->map(fn($doc) => trim((string) $this->formatReceiptNumber($doc)))
            ->filter(fn($doc) => !empty($doc))
            ->unique()
            ->values()
            ->all();
    }

    protected function formatReceiptNumber(?string $rc): ?string
    {
        if (empty($rc)) {
            return null;
        }
        $rc = strtoupper(trim($rc));
        if (preg_match('/^(RC-)(\d{1,8})/', $rc, $matches)) {
            return $matches[1] . str_pad($matches[2], 8, '0', STR_PAD_LEFT);
        }

        return $rc;
    }

    protected function normalizeDocument(?string $doc): string
    {
        return strtoupper(trim((string) $doc));
    }




    public function processUpdateUnapplicationItems(array $items, GpUsuario $user)
    {
        $now = Carbon::now();
        $modelsByType = [
            'APLICACION_MONTO' => GpCobrosAplicacionFactura::class,
            'EFECTIVO'         => GpCobrosEfectivoFactura::class,
            'DEPOSITO'         => GpCobrosDepositosFacturas::class,
            'TARJETA'          => GpCobrosTarjetaFactura::class,
            'CHEQUE'           => GpCobrosChequesFacturas::class,
            'RETENCION'        => GpCobrosRetenciones::class,
        ];
        return DB::transaction(function () use ($items, $user, $modelsByType, $now) {
            $updated = 0;
            $errors = [];
            foreach ($items as $item) {
                $type     = data_get($item, 'origen');
                $id       = data_get($item, 'aplicacion_factura_id');
                $obs      = data_get($item, 'observacion_desaplicacion');

                if (empty($type) || empty($id)) {
                    $errors[] = [
                        'origen' => $type,
                        'id'     => $id,
                        'error'  => 'Origen o identificador vacío.',
                    ];

                    continue;
                }

                $modelClass = data_get($modelsByType, $type);

                if (!$modelClass) {
                    $errors[] = [
                        'origen' => $type,
                        'id'     => $id,
                        'error'  => 'Tipo de aplicación no soportado.',
                    ];

                    continue;
                }

                $affected = $modelClass::where('id', $id)
                    ->where('estado_aplicado', 1)
                    ->update([
                        'estado_aplicado'           => 0,
                        'user_desaplicacion_id'     => $user->id,
                        'fecha_desaplicacion'       => $now,
                        'observacion_desaplicacion' => $obs,
                    ]);

                if ($affected === 0) {
                    $errors[] = [
                        'origen' => $type,
                        'id'     => $id,
                        'error'  => 'No se actualizó el registro. Puede que ya esté desaplicado o no exista.',
                    ];

                    continue;
                }

                $updated += $affected;
            }

            return [
                'updated' => $updated,
                'errors'  => $errors,
            ];
        });
    }
}
