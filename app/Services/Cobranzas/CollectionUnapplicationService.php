<?php

namespace App\Services\Cobranzas;

use App\Services\Api\Cobros\ApiBillingService;
use App\Services\Cobranzas\Contracts\CollectionUnapplicationServiceInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CollectionUnapplicationService implements CollectionUnapplicationServiceInterface
{
    public function __construct(protected ApiBillingService $api) {}

    public function fetchAppliedCollections(array $filters): LengthAwarePaginator
    {
        $sortField = data_get($filters, 'sortField', 'ultima_fecha_aplicacion');
        $sortOrder = data_get($filters, 'sortOrder', 'desc');
        $search =  data_get($filters, 'search');
        $perPage = data_get($filters, 'perpage', 10);
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
                    ->orWhere('res.tipos_medio', 'like', '%' . $search . '%')
                ;
            });
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





    public function getAllDetailCollection(string $ruc, string $factura)
    {

        $query = DB::table('vw_cobros_aplicaciones_facturas_detalle as det')
            ->where(function ($q) use ($ruc, $factura) {
                $q->where('ruc', $ruc)
                    ->where('factura', $factura);
            })->get();
        $docsApplys             = $this->getPaymentApplyGp($factura, $ruc);
        $collectionDocs         = $this->filterCollectionIds($docsApplys);
        $receiptsDocs           = $this->filterReceiptNumbers($docsApplys);

        $applyDocsLookup = collect($collectionDocs)
            ->filter()
            ->mapWithKeys(fn($doc) => [$doc => true]);

        $applyReceiptLookup  = collect($receiptsDocs)
            ->filter()
            ->mapWithKeys(fn($doc) => [$doc => true]);


        $actives    = collect($query)
            ->where('estado_aplicado', 1)
            ->map(function ($item) use ($applyDocsLookup, $applyReceiptLookup) {
                $documentoGp   = $this->normalizeDocument($item->documento_gp ?? null);
                $existsInGp     = $applyDocsLookup->has($documentoGp) || $applyReceiptLookup->has($item->numero_recibo);

                $item->can_unapplication = !$existsInGp;
                $item->unapplication_message = $existsInGp
                    ? 'No se puede desaplicar porque el documento se encuentra aplicado en GP.'
                    : 'Disponible para desaplicar.';

                return $item;
            });

        $inactives  = collect($query)->where('estado_aplicado', 0)->values();
        return [
            'activos'           => $actives,
            'inactivos'         => $inactives,
            'collectionsIds'    => $collectionDocs,
            'receiptNumbers'    => $receiptsDocs,

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
}
