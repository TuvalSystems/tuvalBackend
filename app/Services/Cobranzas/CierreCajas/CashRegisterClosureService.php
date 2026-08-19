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

        $openBox = GpCierreCaja::where('caja_id', $boxId)
            ->where('estado', 'abierto')
            ->orderBy('fecha', 'desc')
            ->first();

        if (!$openBox) {
            throw new DomainException(
                'La caja no tiene ningún cierre abierto.'
            );
        }
        $openBox->update([
            'estado'        => 'cerrado',
            'fecha_cierre'  => Carbon::now()->format('Y-m-d H:i:s'),
        ]);

        return [
            'usuario_caja' => $userBox,
            'cierre_caja'   => $openBox
        ];
    }
}
