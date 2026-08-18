<?php

namespace App\Services\Cobranzas\CierreCajas;

use App\Models\GpCierreCaja;
use App\Models\GpUsuario;
use App\Repositories\User\Contracts\UserRepositoryInterface;
use App\Services\Cobranzas\CierreCajas\Contracts\CashRegisterClosureServiceInterface;
use Carbon\Carbon;
use DomainException;

class CashRegisterClosureService implements CashRegisterClosureServiceInterface
{
    public function __construct(
        protected readonly UserRepositoryInterface $userRepository
    ) {
        // Inyecta dependencias
    }

    public function cashCloseBox(int $boxId, int $pvId, string $pvCode, GpUsuario $user): array
    {
        $dateNow = Carbon::now()->format('Y-m-d');
        $userBox = $this->userRepository->findAssignmentByBoxAndSalesPoint(
            $user->id,
            $boxId,
            $pvId,
            trim($pvCode)
        );

        if (!$userBox) {
            throw new DomainException('El usuario no tiene habilitada esta caja en el punto de venta seleccionado.');
        }

        $clousureBox = GpCierreCaja::where('caja_id', $boxId)
            ->where('fecha', $dateNow)->where('estado', 'abierto')->first();

        $previousBox = GpCierreCaja::where('caja_id', $boxId)
            ->where('estado', 'abierto')
            ->orderBy('fecha', 'desc')
            ->first();

        return [
            'usuario_caja' => $userBox
        ];
    }
}
