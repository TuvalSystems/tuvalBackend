<?php

namespace App\Services\Cobranzas\CierreCajas;

use App\Enums\Cobranzas\CobrosFacturas\CollectionStatusEnum;
use App\Events\Cobranzas\CierreCaja\CollectionInvoiceUpdatedEvent;
use App\Models\Cobranzas\GpCobro;
use App\Models\Cobranzas\GpCobrosCheques;
use App\Models\Cobranzas\GpCobrosDeposito;
use App\Models\Cobranzas\GpCobrosEfectivoMonto;
use App\Models\Cobranzas\GpCobrosRetenciones;
use App\Models\GpCierreCaja;
use App\Models\GpCobrosTarjetaMonto;
use App\Models\GpUsuario;
use App\Repositories\User\Contracts\UserRepositoryInterface;
use App\Services\Cobranzas\CierreCajas\Contracts\CashRegisterClosureServiceInterface;
use App\Services\Cobranzas\Contracts\CollectionServiceInterface;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CashRegisterClosureService implements CashRegisterClosureServiceInterface
{
    public function __construct(
        protected readonly UserRepositoryInterface $userRepository,
        protected readonly CollectionServiceInterface $collectService
    ) {
        // Inyecta dependencias
    }

    public function cashCloseBox(int $boxId, int $pvId, string $pvCode, GpUsuario $user): GpCierreCaja
    {

        $userBox = $this->userRepository->findAssignmentByBoxAndSalesPoint(
            $user->id,
            $boxId,
            $pvId,
            trim($pvCode)
        );

        if (!$userBox) {
            throw new DomainException('El usuario no tiene habilitada esta caja en el punto de venta seleccionado.');
        }

        return DB::transaction(function () use ($boxId, $user): GpCierreCaja {
            $openBox = GpCierreCaja::where('caja_id', $boxId)
                ->where('estado', 'abierto')
                ->orderBy('fecha', 'desc')
                ->lockForUpdate()
                ->first();

            if (!$openBox) {
                throw new DomainException(
                    'La caja no tiene ningún cierre abierto.'
                );
            }

            $dateClose   = Carbon::now()->format('Y-m-d H:i:s');
            $closureDate = Carbon::parse($openBox->fecha)->toDateString();
            $closeBox    = $this->buildCashBoxClosureSummary($openBox, $user, $boxId, $closureDate, $dateClose);
            $consultBox  = $this->collectService->getBoxClosureByDate($boxId, $closureDate);

            $openBox->update([
                'estado'        => 'cerrado',
                'fecha_cierre'  => $dateClose,
                'datos'         => json_encode($closeBox),
                'consulta'      => json_encode($consultBox)
            ]);
            event(new CollectionInvoiceUpdatedEvent('refresh', $boxId, $user->USERID));
            return $openBox;
        });
    }


    private function buildCashBoxClosureSummary(GpCierreCaja $openBox, GpUsuario $user, int $boxId, string $closureDate,  string $dateClose): array
    {
        $openBox->datos     = null;
        $openBox->consulta  = null;

        $collections = GpCobro::where('caja_id', $boxId)
            ->whereDate('fecha', $closureDate)
            ->where('estado', CollectionStatusEnum::ENVIADO->value)
            ->get();
        $userData               = $this->collectService->getOperatorContext($user, $boxId);
        $collectIds             = $collections->pluck('id');
        $sumRetention           = $this->calculateRetentionTotals($collectIds);
        $totalRetention         = (float) round(data_get($sumRetention, 'ret_iva') + data_get($sumRetention, 'ret_renta'), 2);
        $totalCash              = $this->calculateCashTotal($collectIds);
        $totalPostDatedCheck    = $this->calculatePostdatedCheckTotal($collectIds, $closureDate);
        $totalCheckDay          = $this->calculateTodatedCheckTotal($collectIds, $closureDate);
        $totalDeposit           = $this->calculateDepositTotal($collectIds);
        $totalCard              = $this->calculateCardTotal($collectIds);

        $totalCollection = round(
            $totalRetention
                + $totalCash
                + $totalPostDatedCheck
                + $totalCheckDay
                + $totalDeposit
                + $totalCard,
            2
        );

        return [
            'fecha'             => $dateClose,
            'usuario'           => $userData,
            'cierreDeCaja'      => $openBox,
            'retenciones'       => $sumRetention,
            'efectivo'          => $totalCash,
            'cheques'           => $totalCheckDay,
            'cheques_p'         => $totalPostDatedCheck,
            'depositos'         => $totalDeposit,
            'tarjetas'          => $totalCard,
            'total'             => $totalCollection,
        ];
    }

    private function calculateRetentionTotals(Collection $collectIds): array
    {
        $totals = GpCobrosRetenciones::whereIn('cobro_id', $collectIds)
            ->selectRaw('
            COALESCE(SUM(CASE WHEN enviado_iva_gp = 1 THEN ret_iva ELSE 0 END), 0) AS ret_iva,
            COALESCE(SUM(CASE WHEN enviado_renta_gp = 1 THEN ret_renta ELSE 0 END), 0) AS ret_renta
        ')
            ->first();

        return [
            'ret_iva'   => round((float) $totals->ret_iva, 2),
            'ret_renta' => round((float) $totals->ret_renta, 2),
        ];
    }

    private function calculateCashTotal(Collection $collectIds): float
    {
        return round(
            (float) GpCobrosEfectivoMonto::whereIn('cobro_id', $collectIds)
                ->sum('monto'),
            2
        );
    }

    private function calculatePostdatedCheckTotal(Collection $collectIds, string $date): float
    {
        return round(
            (float) GpCobrosCheques::whereIn('cobro_id', $collectIds)
                ->where('fecha_vencimiento', '>', $date)
                ->whereExists(function ($query) {
                    $query->selectRaw('1')
                        ->from('gp_cobros_cheques_montos')
                        ->whereColumn(
                            'gp_cobros_cheques_montos.cobro_id',
                            'gp_cobros_cheques.cobro_id'
                        )
                        ->where('gp_cobros_cheques_montos.enviado_gp', true);
                })->sum('monto'),
            2
        );
    }

    private function calculateTodatedCheckTotal(Collection $collectIds, string $date): float
    {
        return round(
            (float) GpCobrosCheques::whereIn('cobro_id', $collectIds)
                ->where('fecha_vencimiento', $date)
                ->whereExists(function ($query) {
                    $query->selectRaw('1')
                        ->from('gp_cobros_cheques_montos')
                        ->whereColumn(
                            'gp_cobros_cheques_montos.cobro_id',
                            'gp_cobros_cheques.cobro_id'
                        )
                        ->where('gp_cobros_cheques_montos.enviado_gp', true);
                })->sum('monto'),
            2
        );
    }

    private function calculateDepositTotal(Collection $collectIds): float
    {
        return round((float) GpCobrosDeposito::whereIn('cobro_id', $collectIds)
            ->where('estado', '!=', CollectionStatusEnum::INICIADO->value)
            ->sum('monto'), 2);
    }

    private function calculateCardTotal(Collection $collectIds): float
    {
        return round((float) GpCobrosTarjetaMonto::whereIn('cobro_id', $collectIds)
            ->sum('monto'), 2);
    }

    private function formatCashBoxClosureSummary(array $cashBox): array
    {
        return [
            'retencionIva'      => data_get($cashBox, 'retenciones.ret_iva', 0),
            'retencionRenta'    => data_get($cashBox, 'retenciones.ret_renta', 0),
            'efectivo'          => data_get($cashBox, 'efectivo'),
            'cheques'           => data_get($cashBox, 'cheques'),
            'cheques_p'         => data_get($cashBox, 'cheques_p'),
            'depositos'         => data_get($cashBox, 'depositos'),
            'tarjetas'          => data_get($cashBox, 'tarjetas'),
            'total'             => data_get($cashBox, 'total'),
        ];
    }
    public function getCashBoxClosureSummary(int $boxId, int $pvId, string $pvCode, string $date, GpUsuario $user): array
    {

        $userBox    = $this->userRepository->findAssignmentByBoxAndSalesPoint(
            $user->id,
            $boxId,
            $pvId,
            trim($pvCode)
        );

        if (!$userBox) {
            throw new DomainException('El usuario no tiene habilitada esta caja en el punto de venta seleccionado.');
        }
        $dateClose   = Carbon::now()->format('Y-m-d H:i:s');
        $closureDate = Carbon::parse($date)->toDateString();

        $cashBoxClosure = GpCierreCaja::where('caja_id', $boxId)
            ->where('fecha', $closureDate)
            ->whereIn('estado', ['abierto', 'cerrado'])
            ->first();

        if (!$cashBoxClosure) {
            throw new DomainException(
                'La caja no tiene cierre registrado para la fecha seleccionada.'
            );
        }

        $storedCashBox = $cashBoxClosure->datos
            ? json_decode($cashBoxClosure->datos, true)
            : null;
        $cashBox = $cashBoxClosure->estado === 'cerrado' && is_array($storedCashBox)
            ? $storedCashBox
            : $this->buildCashBoxClosureSummary($cashBoxClosure, $user, $boxId, $closureDate, $dateClose);

        return $this->formatCashBoxClosureSummary($cashBox);
    }
}
