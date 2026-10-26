<?php

namespace App\Services\Cobranzas\CobrosFacturas;

use App\Enums\Cobranzas\CobrosFacturas\CollectionStatusEnum;
use App\Enums\Ventas\TaxRateEnum;
use App\Exports\Cobranzas\CobrosFacturas\ClientAccountStatementDetailExport;
use App\Models\Cobranzas\Cobros\GpCobro;
use App\Models\Cobranzas\Cheques\GpCobrosCheques;
use App\Models\Cobranzas\Cheques\GpCobrosChequesFacturas;
use App\Models\Cobranzas\Depositos\GpCobrosDeposito;
use App\Models\Cobranzas\Depositos\GpCobrosDepositosFacturas;
use App\Models\Cobranzas\Efectivo\GpCobrosEfectivoFactura;
use App\Models\Cobranzas\Efectivo\GpCobrosEfectivoMonto;
use App\Models\Cobranzas\Retenciones\GpCobrosRetenciones;
use App\Models\Cobranzas\Bancos\GpCajaBancos;
use App\Models\Cobranzas\Cajas\GpCierreCaja;
use App\Models\Ventas\Clientes\GpCliente;
use App\Models\Cobranzas\Aplicaciones\GpCobrosAplicacionMonto;
use App\Models\Cobranzas\Aplicaciones\GpCobrosTarjetaFactura;
use App\Models\Cobranzas\Aplicaciones\GpCobrosTarjetaMonto;
use App\Models\Admin\Usuarios\GpUsuario;
use App\Repositories\Cobranzas\CobrosFacturas\Contracts\InvoiceCollectionRepositoryInterface;
use App\Services\Api\Cobros\ApiBillingService;
use App\Services\Cobranzas\CobrosFacturas\Contracts\InvoiceCollectionServiceInterface;
use Carbon\Carbon;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Excel as MaatwebsiteExcel;
use Maatwebsite\Excel\Facades\Excel;

use function Psl\Str\uppercase;

class InvoiceCollectionService implements InvoiceCollectionServiceInterface
{
    public function __construct(
        protected readonly InvoiceCollectionRepositoryInterface $invRep,
        protected readonly ApiBillingService $api
    ) {}


    /** funcion para verificar el estado de la caja basada en el usuario y punto de venta */
    public function getStatusBoxedUser(int $pvId, string $pvCode, ?string $date, GpUsuario $user): array
    {
        $currentDate = filled($date) ? Carbon::parse($date)->toDateString() : Carbon::now()->toDateString();
        $cashBox = $this->invRep->queryGetRelationShipUserBox($user, $pvId, $pvCode);
        if (!$cashBox) {
            throw new DomainException('No existe caja asignada al usuario en este punto de venta');
        }
        return [
            'id'            => $cashBox->id,
            'cajaCodigo'    => $cashBox->CAJA_CODIGO,
            'descripcion'   => $cashBox->CAJA_DESCRIPCION,
            'estado'        => $this->resolveCashBoxStatus($cashBox->id, $currentDate),
        ];
    }


    private function resolveCashBoxStatus(int $boxId, string $date): string
    {
        $hasOpenCashBox = GpCierreCaja::where('caja_id', $boxId)
            ->where('fecha', $date)->where('estado', 'abierto')
            ->exists();
        if ($hasOpenCashBox) {
            return 'abierto';
        }

        $hasClosedCashBox = GpCierreCaja::where('caja_id', $boxId)
            ->where('fecha', $date)->where('estado', 'cerrado')
            ->exists();

        if ($hasClosedCashBox) {
            return 'cerrado';
        }
        $hasPreviousOpenCashBox = GpCierreCaja::where('caja_id', $boxId)
            ->where('fecha', '<', $date)
            ->where('estado', 'abierto')
            ->exists();
        return $hasPreviousOpenCashBox ? 'sin_cierre' : 'cerrado';
    }


    /** funcion para aperturar cajas  */
    public function openCashBoxed(int $pvId,  GpUsuario $user)
    {
        $now = Carbon::now();
        $currentDate = $now->toDateString();
        $cashBox = $this->invRep->queryGetRelationShipUserBox($user, $pvId);
        if (!$cashBox) {
            throw new DomainException('No existe caja asignada al usuario en este punto de venta');
        }

        $cashBoxClosure = DB::transaction(function () use ($cashBox, $currentDate, $now) {
            $closure = GpCierreCaja::where('caja_id', $cashBox->id)
                ->where('fecha', $currentDate)
                ->lockForUpdate()
                ->first();

            if ($closure) {
                $closure->fill([
                    'estado'            => 'abierto',
                    'fecha_reapertura'  =>  $now->toDateTimeString(),
                ]);
                $closure->save();
                return $closure;
            }

            return GpCierreCaja::create([
                'caja_id'           => $cashBox->id,
                'CAJA_CODIGO'       => $cashBox->CAJA_CODIGO,
                'fecha'             => $currentDate,
                'estado'            => 'abierto',
                'fecha_apertura'    => $now->toDateTimeString(),
            ]);
        });
        return [
            'id'            => $cashBoxClosure->id,
            'caja_id'       => $cashBoxClosure->caja_id,
            'cajaCodigo'    => $cashBoxClosure->CAJA_CODIGO,
            'fecha'         => $cashBoxClosure->fecha,
            'fechaApertura' => $cashBoxClosure->fecha_apertura,
            'estado'        => $cashBoxClosure->estado
        ];
    }



    /** impresion de estado de cuenta cliente excel  y pdf */

    public function generateClientAccountStatementPdf(int $clientId): array
    {
        $client = GpCliente::find($clientId);
        if (!$client) {
            throw new DomainException('Cliente no encontrado');
        }
        $accountStatement = $this->buildClientAccountStatementData($client);
        $infoPdf = [
            'cliente' => $client,
            'detalle' => data_get($accountStatement, 'detail'),
            'resumen' => data_get($accountStatement, 'summary'),
        ];
        $html   = view('pdf.cobranzas.cobros-facturas.estado-cuenta-cliente', $infoPdf)->render();
        $pdf    = app('dompdf.wrapper');
        $pdf->setPaper('a4', 'portrait');
        $pdf->loadHTML($html);
        $binary = $pdf->output();
        $filename = 'ESTADO-CUENTA-CLIENTE-' . Str::slug($client->CLI_RUC, '-') . '-' . '.pdf';

        return [
            'binary'        => $binary,
            'filename'      => $filename,
            'content_type'  => 'application/pdf',
        ];
    }

    public function generateClientAccountStatementExcel(int $clientId): array
    {
        $client = GpCliente::find($clientId);
        if (!$client) {
            throw new DomainException('Cliente no encontrado');
        }

        $accountStatement = $this->buildClientAccountStatementData($client);
        $detail = data_get($accountStatement, 'detail', collect());
        $binary = Excel::raw(
            new ClientAccountStatementDetailExport($detail),
            MaatwebsiteExcel::XLSX
        );
        $fileName = 'ESTADO-CUENTA-CLIENTE-'
            . Str::slug($client->CLI_RUC, '-')
            . '.xlsx';

        return [
            'binary'        => $binary,
            'filename'      => $fileName,
            'content_type'  =>
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];
    }


    private  function buildClientAccountStatementData(GpCliente $client): array
    {
        $detail = $this->getCustomerStatusStatementDetail($client->CLI_RUC);
        $summary = $this->getCustomerStatusStatementSummary($client->CLI_RUC);
        $detail->each(function ($det) {
            if (blank($det->condicionPago)) {
                $days = $this->dateDiffInDays(
                    $det->fechaEmision,
                    $det->fechaVencimiento
                );

                $det->condicionPago = $days . ($days === 1 ? ' DÍA' : ' DÍAS');
            }
        });
        return [
            'client' => $client,
            'detail' => $detail,
            'summary' => $summary,
        ];
    }

    private function getCustomerStatusStatementDetail(string $ruc): Collection
    {
        $response = $this->api->getClientAccountStatement([
            'ruc' => $ruc
        ]);

        return collect(data_get($response, 'data', []))
            ->map(fn(array $detail) => (object) $detail);
    }

    private function getCustomerStatusStatementSummary(string $ruc): Collection
    {
        $response = $this->api->getClientAccountStatementSummary([
            'ruc' => $ruc
        ]);
        return collect(data_get($response, 'data', []));
    }

    private function dateDiffInDays(string $date1, string $date2): int
    {
        try {
            $startDate  = Carbon::parse($date1)->startOfDay();
            $endDate    = Carbon::parse($date2)->startOfDay();
            return (int) $startDate->diffInDays($endDate, true);
        } catch (\Throwable $e) {
            throw new DomainException(
                'No se pudo calcular la diferencia de días porque una de las fechas no es válida.'
            );
        }
    }



    /** funcion para obtener las facturas y facturas retenciones
     * son sus respectivos valores y validaciones
     */

    public function getInvoiceCollectionsByClient(int $id): array
    {

        $client = GpCliente::find($id);
        if (!$client) {
            throw new DomainException('Cliente no encontrado');
        }
        $detailAccount      = $this->getStatusStatementDetailApi($client->CLI_RUC);
        $invoiceDocuments = $detailAccount
            ->where('tipo', 'Facturas')
            ->pluck('documento')
            ->filter()
            ->unique()
            ->values();

        $allDocuments = $detailAccount
            ->pluck('documento')
            ->filter()
            ->unique()
            ->values();

        $generatedRetentions            = GpCobrosRetenciones::whereIn('ret_factura', $invoiceDocuments)->get()->keyBy('ret_factura');
        $pendingDepositsByInvoice       = GpCobrosDepositosFacturas::whereIn('id_factura', $allDocuments)
            ->whereHas('cobroDeposito', function ($query) {
                $query->where('estado', 'iniciado');
            })
            ->selectRaw('id_factura, SUM(monto) AS monto_pendiente')
            ->groupBy('id_factura')
            ->pluck('monto_pendiente', 'id_factura');

        $detailAccount = $detailAccount
            ->map(function ($det) use ($pendingDepositsByInvoice) {
                $pendingAmount = (float) $pendingDepositsByInvoice->get($det->documento, 0);

                $total                  = round((float) $det->montoOriginal, 2);
                $det->saldoCliente      = max(0, (float) $det->saldoCliente - $pendingAmount);
                $det->saldoBaseCliente  = max(0, (float) $det->saldoBaseCliente - $pendingAmount);
                $det->saldo             = $det->saldoCliente;
                $sub                    = round(TaxRateEnum::IVA_12->calculateSubtotal($total), 4);
                $det->subtotal          = $sub;
                $det->iva               = round($total - $sub, 2);
                $det->depositoPendiente = (float) round($pendingAmount, 2);
                return $det;
            })
            ->filter(fn($det) => (float) $det->saldoCliente > 0)
            ->values();



        $invoiceRetention = $detailAccount->filter(function ($det) use ($generatedRetentions) {
            return $det->tipo === 'Facturas'
                && !$generatedRetentions->has(
                    $det->documento
                );
        })->values();

        return [
            'facturas'                  => $detailAccount,
            'facturas_retenciones'       => $invoiceRetention,
        ];
    }


    private function getStatusStatementDetailApi(string $ruc): ?Collection
    {

        $response = $this->api->getDetailedAccountStatus([
            'ruc'           =>  $ruc,
            'idCompania'     => config('services.api_gp.company'),
        ]);

        return collect(data_get($response, 'data.detalle', []))
            ->map(fn(array $detail) => (object) $detail);
    }

    /** funcion para listar los documentos pendientes de cobros
     *  despues eliminar a funcion que esta en CustomerPaymentApplicationService
     *  - updatePendingApplicationDocumentBalances
     */

    public function getUnappliedCollectionsByClient(int $clientId): ?Collection
    {
        $client = GpCliente::find($clientId);
        if (!$client) {
            throw new DomainException('Cliente no encontrado');
        }
        return $this->fetchUnappliedCollectionsFromGp($client->CLI_RUC);
    }

    protected function fetchUnappliedCollectionsFromGp(string $ruc): Collection
    {
        return collect(data_get($this->api->getUnappliedCollections([
            'ruc' => $ruc
        ]), 'data', []));
    }

    /**
     * funcion para obtener las listas de los bancos
     */

    public function getBanks(): array
    {
        $banks = GpCajaBancos::query()
            ->whereIn('CB_TIPO', [
                'BANCO_TUVAL',
                'BANCO_CLIENTE',
                'TARJETA'
            ])->where(function ($query) {
                $query->where('CB_TIPO', '<>', 'BANCO_TUVAL')
                    ->orWhere('estado', 'activo');
            })->get()
            ->groupBy('CB_TIPO');

        return [
            'bancos_tuval'      => $banks->get('BANCO_TUVAL', collect())->values(),
            'bancos_clientes'   => $banks->get('BANCO_CLIENTE', collect())->values(),
            'bancos_tarjetas'   => $banks->get('TARJETA', collect())->values(),
        ];
    }


    public function retentionReceiptExistsForClient(int $clientId, string $receiptNumber): bool
    {
        return  GpCobrosRetenciones::query()
            ->where('ret_comprobante', trim($receiptNumber))
            ->whereHas('cobro',   fn(Builder $query) =>
            $query->where('cliente_id', $clientId))
            ->exists();
    }

    public function depositReceiptExistsForClient(int $clientId, int $bankId, string $receiptNumber): bool
    {
        return GpCobrosDeposito::query()
            ->where('comprobante', $receiptNumber)
            ->where('banco_id', $bankId)
            ->where('estado', '!=', 'rechazado')
            ->exists();
    }

    public function checkReceiptExistsForClient(string $account, string $numCheck): bool
    {
        return GpCobrosCheques::query()
            ->where('cuenta', $account)
            ->where('cheque', $numCheck)
            ->exists();
    }


    public function fetchListCollection(array $filters): LengthAwarePaginator
    {
        $clientId = data_get($filters, 'cliente_id');
        $search = data_get($filters, 'search', '');
        $perpage = data_get($filters, 'perpage', 10);
        $sortField = data_get($filters, 'sortField', 'created_at');
        $sortOrder = strtolower(data_get($filters, 'sortOrder', 'desc')) === 'asc'
            ? 'asc'
            : 'desc';

        $allowedSorts = [
            'numero_recibo'     => 'gc.numero_recibo',
            'fecha'             => 'gc.fecha'
        ];


        $orderColumn = data_get($allowedSorts, $sortField, 'gc.created_at');

        $query = GpCobro::query()
            ->select([
                'gc.id',
                'gc.numero_recibo',
                'gc.user_id',
                'gc.cliente_id',
                'gc.caja_id',
                'gc.fecha',
                'gc.estado',
                'gc.alerta',
                'gc.created_at'
            ])
            ->selectRaw(
                'EXISTS (
                    SELECT 1
                    FROM gp_cobros_depositos AS gcd
                    WHERE gcd.cobro_id = gc.id
                        AND gcd.estado = ?
                ) AS deposito_pendiente',
                [CollectionStatusEnum::INICIADO->value]
            )
            ->from('gp_cobros as gc')
            ->where('gc.cliente_id', $clientId);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('gc.numero_recibo', 'like', "%{$search}%");
            });
        }

        $collections = $query
            ->orderBy($orderColumn, $sortOrder)
            ->paginate($perpage);

        $collections->getCollection()->transform(function (GpCobro $collection) {
            $collection->deposito_pendiente = (bool) $collection->deposito_pendiente;

            return $collection;
        });

        return $collections;
    }



    public function printCollectionPdf(int $collectionId, GpUsuario $user): array
    {
        $infoPdf = $this->buildCollectionPdfData($collectionId, $user);
        $html = view('pdf.cobranzas.cobros-facturas.cobro', $infoPdf)->render();
        $pdf = app('dompdf.wrapper');
        $pdf->setPaper('a4', 'portrait');
        $pdf->loadHTML($html);
        $binary = $pdf->output();
        $receiptNumber = data_get($infoPdf, 'cobro.numero_recibo', $collectionId);
        $filename = 'COBRO-' . Str::upper(Str::slug((string) $receiptNumber, '-')) . '.pdf';

        return [
            'binary'        => $binary,
            'filename'      => $filename,
            'content_type'  => 'application/pdf',
        ];
    }

    private function buildCollectionPdfData(int $collectionId, GpUsuario $user): array
    {
        $collection = GpCobro::with([
            'puntoVenta:id,PTO_VTA_DESCRIPCION',
            'usuarioCobro:id,USERID,USERNAME',
            'cliente:id,CLI_RAZON_SOCIAL,CLI_DIRECCION,CLI_RUC,CLI_TELEFONO_1,CLI_CIUDAD'
        ])->where('id', $collectionId)
            ->select([
                'id',
                'numero_recibo',
                'user_id',
                'pv_id',
                'cliente_id',
                'caja_id',
                'fecha',
                'estado'
            ])->firstOrFail();

        $retention = $this->getRetentionSummaryByCollection($collection->id);
        $cash = $this->getCashSummaryByCollection($collectionId);
        $deposits = $this->getDepositSummaryByCollection($collectionId);
        $check = $this->getCheckSummaryByCollection($collectionId);
        $card = $this->getCardSummaryByCollection($collectionId);
        $applications = $this->getApplyBalanceSummayByCollection($collectionId);

        $summaries = collect([
            $retention,
            $cash,
            $deposits,
            $check,
            $card,
            $applications,
        ]);

        $totalCollection = round($summaries->sum(fn($summary) => (float) data_get($summary, 'totalCobro', 0)), 2);
        $totalApplied = round($summaries->sum(fn($summary) => (float) data_get($summary, 'totalAplicado', 0)), 2);

        return [
            'cobro'         => $collection,
            'usuario'       => $collection->usuarioCobro ?? $user,
            'retencion'     => $retention,
            'efectivo'      => $cash,
            'depositos'     => $deposits,
            'cheque'        => $check,
            'tarjeta'       => $card,
            'aplicaciones'  => $applications,
            'totalCobro'    => $totalCollection,
            'totalAplicado' => $totalApplied,
            'saldo'         => round($totalCollection - $totalApplied, 2),
        ];
    }

    private function getRetentionSummaryByCollection(int $collectionId): array
    {
        $retentions = GpCobrosRetenciones::query()
            ->where('cobro_id', $collectionId)
            ->where('estado_aplicado', 1)
            ->get([
                'id',
                'ret_factura',
                'ret_comprobante',
                'ret_fecha_emision',
                'ret_fecha_vencimiento',
                'ret_iva',
                'ret_porcentaje_iva',
                'ret_renta',
                'ret_porcentaje_renta',
                'ret_iva_srv',
                'ret_porcentaje_iva_srv',
                'ret_renta_srv',
                'ret_porcentaje_renta_srv',
                'total',
            ]);

        $totalRetention = round(
            $retentions->sum(
                fn($retention) => (float) $retention->total
            ),
            2
        );

        return [
            'retenciones'   => $retentions->values()->all(),
            'totalAplicado' => $totalRetention,
            'totalCobro'    => $totalRetention,
        ];
    }

    private function getCashSummaryByCollection(int $collectionId): array
    {
        $cashAmounts  = GpCobrosEfectivoMonto::where('cobro_id', $collectionId)
            ->get([
                'id',
                'cobro_id',
                'monto',
                'fecha',
                'banco',
                'comprobante',
            ]);

        $cashInvoice = GpCobrosEfectivoFactura::query()
            ->where('cobro_id', $collectionId)
            ->where('estado_aplicado', 1)
            ->get([
                'id',
                'id_factura',
                'monto'
            ]);

        $totalCollection = round($cashAmounts->sum('monto'), 2);
        $totalApply = round($cashInvoice->sum('monto'), 2);

        return [
            'montos'        => $cashAmounts,
            'facturas'      => $cashInvoice,
            'totalAplicado' => $totalApply,
            'totalCobro'    => $totalCollection,

        ];
    }

    private function getDepositSummaryByCollection(int $collectionId): array
    {
        $deposits = GpCobrosDeposito::with([
            'bancoDeposito:id,CB_DESCRIPCION',
            'facturas' =>
            function ($q) {
                $q->select(['id', 'cobro_id', 'deposito_id', 'id_factura', 'monto'])
                    ->where('estado_aplicado', 1);
            }
        ])
            ->whereIn('estado', ['revisado', 'enviado'])
            ->where('cobro_id', $collectionId)->get([
                'id',
                'cobro_id',
                'banco_id',
                'banco as banco_codigo',
                'tipo',
                'comprobante',
                'fecha',
                'monto'
            ]);

        $deposits->each(function ($deposit) {
            $deposit->setAttribute(
                'totalAplicado',
                round((float) $deposit->facturas->sum('monto'), 2)
            );
        });

        return [
            'depositos'     => $deposits,
            'totalCobro'    => round((float) $deposits->sum('monto'), 2),
            'totalAplicado' => round((float) $deposits->sum('totalAplicado'), 2),
        ];
    }

    private function getCheckSummaryByCollection(int $collectionId): array
    {
        $check = GpCobrosCheques::with([
            'banco:id,CB_DESCRIPCION',
        ])->where('cobro_id', $collectionId)->get([
            'id',
            'cobro_id',
            'banco_id',
            'banco as banco_codigo',
            'cuenta',
            'cheque',
            'fecha_emision',
            'fecha_vencimiento',
            'monto'
        ]);

        $checkInvoices = GpCobrosChequesFacturas::where('cobro_id', $collectionId)
            ->where('estado_aplicado', 1)
            ->get([
                'id',
                'id_factura',
                'monto'
            ]);


        return [
            'cheque'        => $check,
            'facturas'      => $checkInvoices,
            'totalCobro'    => round($check->sum('monto'), 2),
            'totalAplicado' => round($checkInvoices->sum('monto'), 2)
        ];
    }


    private function getCardSummaryByCollection(int $collectionId): array
    {
        $cardSummary = GpCobrosTarjetaMonto::query()
            ->leftJoin('gp_caja_bancos as gcb', 'gcb.CB_ID', '=', 'gp_cobros_tarjeta_montos.banco')
            ->where('gp_cobros_tarjeta_montos.cobro_id', $collectionId)
            ->select([
                'gp_cobros_tarjeta_montos.id',
                'gp_cobros_tarjeta_montos.cobro_id',
                'gp_cobros_tarjeta_montos.banco',
                'gcb.CB_DESCRIPCION as banco_descripcion',
                'gp_cobros_tarjeta_montos.comprobante',
                'gp_cobros_tarjeta_montos.comentario',
                'gp_cobros_tarjeta_montos.fecha',
                'gp_cobros_tarjeta_montos.monto'
            ])->first();

        $cardInvoices = GpCobrosTarjetaFactura::where('estado_aplicado', 1)
            ->where('cobro_id', $collectionId)->get([
                'id',
                'cobro_id',
                'id_factura',
                'estado_aplicado',
                'monto'
            ]);

        return [
            'tarjeta'       => $cardSummary,
            'facturas'      => $cardInvoices,
            'totalCobro'    => round((float) ($cardSummary?->monto ?? 0), 2),
            'totalAplicado' => round($cardInvoices->sum('monto'), 2),
        ];
    }



    private function getApplyBalanceSummayByCollection(int $collectionId)
    {
        $applySummary = GpCobrosAplicacionMonto::with([
            'facturasAplicadas' => function ($q) {
                $q->select(['id', 'cobro_id', 'aplicacion_id', 'id_factura', 'monto', 'estado_aplicado'])
                    ->where('estado_aplicado', 1);
            }
        ])
            ->where('cobro_id', $collectionId)
            ->get([
                'id',
                'cobro_id',
                'cobro',
                'tipo',
                'monto'
            ]);

        $applySummary->each(function ($apply) {
            $apply->setAttribute(
                'totalAplicado',
                round((float) $apply->facturasAplicadas->sum('monto'), 2)
            );
        });

        return [
            'aplicaciones'  =>  $this->groupApplyBalanceSummaryByCollectionDocument($applySummary),
            'totalCobro'    => round($applySummary->sum('monto'), 2),
            'totalAplicado' => round($applySummary->sum('totalAplicado'), 2),
        ];
    }

    private function groupApplyBalanceSummaryByCollectionDocument(Collection $applySummary): Collection
    {
        return $applySummary
            ->groupBy(fn($apply) => trim((string) data_get($apply, 'cobro', '')))
            ->map(function (Collection $items, string $collectionDocument) {
                $invoices = $this->groupAppliedInvoicesForPdf($items);

                return [
                    'cobro'                 => $collectionDocument,
                    'monto'                 => round($items->sum('monto'), 2),
                    'totalAplicado'         => round($invoices->sum('monto'), 2),
                    'facturasAplicadas'     => $invoices,
                ];
            })
            ->values();
    }

    private function groupAppliedInvoicesForPdf(Collection $applications): Collection
    {
        return $applications
            ->flatMap(fn($application) => data_get($application, 'facturasAplicadas', collect()))
            ->groupBy(fn($invoice) => trim((string) data_get($invoice, 'id_factura', '')))
            ->map(function (Collection $invoiceItems, string $invoiceDocument) {
                return [
                    'id_factura' => $invoiceDocument,
                    'monto'      => round($invoiceItems->sum('monto'), 2),
                ];
            })
            ->values();
    }
}
