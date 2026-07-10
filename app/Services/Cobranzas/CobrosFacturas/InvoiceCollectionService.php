<?php

namespace App\Services\Cobranzas\CobrosFacturas;

use App\Models\GpCierreCaja;
use App\Models\GpUsuario;
use App\Repositories\Cobranzas\CobrosFacturas\Contracts\InvoiceCollectionRepositoryInterface;
use App\Services\Cobranzas\CobrosFacturas\Contracts\InvoiceCollectionServiceInterface;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;

class InvoiceCollectionService implements InvoiceCollectionServiceInterface
{
    public function __construct(
        protected readonly InvoiceCollectionRepositoryInterface $invRep
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
}
