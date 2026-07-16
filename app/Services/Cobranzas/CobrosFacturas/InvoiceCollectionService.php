<?php

namespace App\Services\Cobranzas\CobrosFacturas;

use App\Enums\Ventas\TaxRateEnum;
use App\Exports\Cobranzas\CobrosFacturas\ClientAccountStatementDetailExport;
use App\Models\Cobranzas\GpCobrosDeposito;
use App\Models\Cobranzas\GpCobrosRetenciones;
use App\Models\GpCierreCaja;
use App\Models\GpCliente;
use App\Models\GpUsuario;
use App\Repositories\Cobranzas\CobrosFacturas\Contracts\InvoiceCollectionRepositoryInterface;
use App\Services\Api\Cobros\ApiBillingService;
use App\Services\Cobranzas\CobrosFacturas\Contracts\InvoiceCollectionServiceInterface;
use Carbon\Carbon;
use DomainException;
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



    /** funcion para obtener las facturas  */

    public function getInvoiceCollectionsByClient(int $id)
    {

        $client = GpCliente::find($id);
        if (!$client) {
            throw new DomainException('Cliente no encontrado');
        }
        $detailAccount      = $this->getStatusStatementDetailApi($client->CLI_RUC);
        $docsfilters        = $detailAccount->where('tipo', 'Facturas')->pluck('documento');
        $docsNotFilter      = $detailAccount->pluck('documento');
        $afterDocs          = GpCobrosRetenciones::whereIn('ret_factura', $docsfilters)->get()->keyBy('ret_factura');

        $pendingDeposits    = GpCobrosDeposito::with('depositosFactura', function ($q) use ($docsNotFilter) {
            $q->whereIn('id_factura', $docsNotFilter);
        })->where('estado', 'iniciado')->get();

        $detailAccount = $detailAccount
            ->filter(fn($det) => (float) $det->saldoCliente > 0)
            ->each(function ($det) {
                $sub            = round(TaxRateEnum::IVA_12->calculateSubtotal($det->montoOriginal), 4);
                $det->subtotal  = $sub;
                $det->iva       = round($det->montoOriginal - $sub, 2);
            })
            ->values();



        $invoiceRetention = $detailAccount->filter(function ($det) use ($afterDocs) {
            $docDet = $afterDocs->get($det->documento);
            return !$docDet && $det->tipo === 'Facturas';
        })->values();

        return [
            'facturas'                  => $detailAccount,
            'facturas_retencions'       => $invoiceRetention,
            'retencionesGeneradas'      => $afterDocs,
            'depositosPendientes'       => $pendingDeposits,
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
}
