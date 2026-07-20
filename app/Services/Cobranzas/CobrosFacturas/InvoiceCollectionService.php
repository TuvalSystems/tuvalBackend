<?php

namespace App\Services\Cobranzas\CobrosFacturas;

use App\Enums\Ventas\TaxRateEnum;
use App\Exports\Cobranzas\CobrosFacturas\ClientAccountStatementDetailExport;
use App\Models\Cobranzas\GpCobrosDepositosFacturas;
use App\Models\Cobranzas\GpCobrosRetenciones;
use App\Models\GpCajaBancos;
use App\Models\GpCierreCaja;
use App\Models\GpCliente;
use App\Models\GpUsuario;
use App\Repositories\Cobranzas\CobrosFacturas\Contracts\InvoiceCollectionRepositoryInterface;
use App\Services\Api\Cobros\ApiBillingService;
use App\Services\Cobranzas\CobrosFacturas\Contracts\InvoiceCollectionServiceInterface;
use Carbon\Carbon;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Excel as MaatwebsiteExcel;
use Maatwebsite\Excel\Facades\Excel;

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
}
