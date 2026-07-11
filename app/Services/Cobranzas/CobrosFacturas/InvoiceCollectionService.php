<?php

namespace App\Services\Cobranzas\CobrosFacturas;

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



    /** impresion de estado de cuenta cliente execel  */

    public function generateClientAccountStatementPdf(int $clientId): array
    {
        $client = GpCliente::find($clientId);
        if (!$client) {
            throw new DomainException('Cliente no encontrado');
        }

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
            'cliente' => $client,
            'detalle' => $detail,
            'resumen' => $summary,
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
}
