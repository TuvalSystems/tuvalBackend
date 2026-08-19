<?php

namespace App\Services\Cobranzas;

use App\Models\GpPuntoVenta;
use App\Models\GpUsuario;
use App\Repositories\Cobranzas\Contracts\CashClosureRepositoryInterface;
use App\Repositories\Cobranzas\Contracts\CollectionRepositoryInterface;
use App\Repositories\User\Contracts\UserRepositoryInterface;
use App\Services\Cobranzas\Contracts\CollectionServiceInterface;
use Illuminate\Support\Str;
use RuntimeException;

class CollectionService implements CollectionServiceInterface
{

    public function __construct(
        protected readonly CashClosureRepositoryInterface $cashCloseRepository,
        protected readonly UserRepositoryInterface $userRepository,
        protected readonly CollectionRepositoryInterface $collectRepository
    ) {
        // Inyecta dependencias si es necesario
    }


    public function listPointSalesByUSer(GpUsuario $user)
    {
        $isAdmin = in_array($user->perfil_id, [1, 2]) || $user->is_admin_cobros;
        if ($isAdmin) {
            return GpPuntoVenta::has('cajasDisponibles')
                ->get(['id', 'PTO_VTA_ID', 'PTO_VTA_DESCRIPCION']);
        }
        return $user->puntoVentaPermitidos()
            ->get(['gp_punto_ventas.id', 'gp_punto_ventas.PTO_VTA_ID', 'gp_punto_ventas.PTO_VTA_DESCRIPCION'])->makeHidden('pivot');
    }


    public function listBoxesByPointSales(int $pvId, GpUsuario $user)
    {
        $ptoVta = GpPuntoVenta::where('id', $pvId)->first();
        if (!$ptoVta) {
            throw new \RuntimeException("Punto de venta no encontrado");
        }

        $isAdmin = in_array($user->perfil_id, [1, 2]) || $user->is_admin_cobros;
        if (!$isAdmin) {
            $tieneAccesoAlPV = $user->puntoVentaPermitidos()
                ->where('gp_punto_ventas.id', $ptoVta->id)
                ->exists();

            if (!$tieneAccesoAlPV) {
                throw new \RuntimeException("No tienes permisos para ver las cajas de este punto de venta.");
            }
        }
        $query = $ptoVta->cajasDisponibles();
        $query->has('operariosAsignados');

        $query->with(['operariosAsignados' => function ($q) {
            $q->select('gp_usuarios.id', 'gp_usuarios.USERID', 'gp_usuarios.USERNAME');
        }]);

        $cajas = $query->get(['gp_cajas.id', 'CAJA_CODIGO', 'CAJA_DESCRIPCION']);
        return $cajas->map(function ($caja) {
            $operario = $caja->operariosAsignados->first();
            return [
                'id'                => $caja->id,
                'caja_codigo'       => $caja->CAJA_CODIGO,
                'caja_descripcion'  => $caja->CAJA_DESCRIPCION,
                'operario_id'       => $operario->id,
                'userId'            => $operario->USERID,
                'userName'          => $operario->USERNAME

            ];
        });
    }


    public function getBoxClosureByDate(int $boxId, string $date)
    {
        $boxOwner = $this->userRepository->findAssignmentByBox($boxId);
        if (!$boxOwner) {
            throw new \RuntimeException("Caja no encontrada");
        }
        $resumen =  $this->cashCloseRepository->isBoxClosed($boxId, $date, $boxOwner->id);

        if ($resumen) {
            $cierre = $this->cashCloseRepository->getBoxClosureSummary($boxId, $date, $boxOwner->id);
            return json_decode($cierre->consulta, true);
        }

        return $this->getCashClosureReport($boxId, $date, $boxOwner);
    }


    protected function getCashClosureReport(int $boxId, string $date, GpUsuario $user)
    {
        $userBox        = $this->getOperatorContext($user, $boxId);
        $applyBalanced  = $this->collectRepository->getAppliedBalancesByBox($boxId, $date);
        $depositos      = $this->collectRepository->getDepositsByBox($boxId, $date);
        $chequesAlDia   = $this->collectRepository->getChequesByBox($boxId, $date, false);
        $chequesPos     = $this->collectRepository->getChequesByBox($boxId, $date, true);
        $efectivo       = $this->collectRepository->getCashPaymentsByBox($boxId, $date);
        $retenciones    = $this->collectRepository->getRetencionsByBox($boxId, $date);
        $tarjeta        = $this->collectRepository->getCardPaymentsByBox($boxId, $date);
        return [
            'caja_usuario'              => data_get($userBox, 'CAJ_USER_CAJA', 'N/A'),
            'fecha'                     => $date,
            'usuario'                   => $userBox,
            'aplicacion_saldos'         => $applyBalanced,
            'aplicacion_saldos_total'   => round($applyBalanced->unique('id')->sum('monto'), 2),
            'depositos'                 => $depositos,
            'depositos_total'           => round($depositos->sum('monto'), 2),
            'cheques_aldia'             => $chequesAlDia,
            'cheques_aldia_total'       => round($chequesAlDia->sum('monto'), 2),
            'cheques_posfechados'       => $chequesPos,
            'cheques_posfechados_total' => round($chequesPos->sum('monto'), 2),
            'efectivo'                  => $efectivo,
            'efectivo_total'            => round($efectivo->sum('monto'), 2),
            'retenciones'               => $retenciones,
            'retenciones_total'         => round($retenciones->sum('total'), 2),
            'tarjeta'                   => $tarjeta,
            'tarjeta_total'             => round($tarjeta->sum('monto'), 2)
        ];
    }

    public function getOperatorContext(GpUsuario $user, int $boxId)
    {
        $userBox = $this->userRepository->findUserBoxAssigment($user->id, $boxId);
        $caja           = $userBox->cajasAsignadas->first();
        if (!$caja) {
            throw new \RuntimeException("La caja seleccionada no está asignada al usuario o no está activa.");
        }
        $puntoVenta     = $caja->puntosVenta->first();
        $userData = $userBox->only(['id', 'USERID', 'USERNAME', 'perfil_id']);
        return  array_merge($userData, [
            'CAJ_USER_CAJA'         => $caja->CAJA_CODIGO,
            'punto_venta_id'        => $puntoVenta->id,
            'PTO_VTA_ID'            => $puntoVenta->PTO_VTA_ID,
            'PTO_VTA_DESCRIPCION'   => $puntoVenta->PTO_VTA_DESCRIPCION ?? 'N/A'
        ]);
    }



    public function generateCashClosurePdfByUser(int $boxId, string $date)
    {
        $viewData = $this->getBoxClosureByDate($boxId, $date);
        $caja = data_get($viewData, 'caja_usuario');

        $html   = view('cierredecajausuarioPDF', $viewData)->render();
        $pdf    = app('dompdf.wrapper');
        $pdf->setPaper('a4', 'portrait');
        $pdf->loadHTML($html);
        $binary = $pdf->output();

        $filename = Str::upper('consulta-de-caja-usuario-' . Str::slug($caja, '-') . '.pdf');

        return [
            'binary'        => $binary,
            'filename'      => $filename,
            'content_type'  => 'application/pdf',
        ];
    }

    public function generateCashClosurePdf(int $boxId, string $date)
    {

        $boxOwner = $this->userRepository->findAssignmentByBox($boxId);
        if (!$boxOwner) {
            throw new RuntimeException("Caja no encontrada o no tiene un usuario asignado.");
        }
        $resumen =  $this->cashCloseRepository->isBoxClosed($boxId, $date, $boxOwner->id);
        if (!$resumen) {
            throw new RuntimeException("La caja no se encuentra cerrada o no fue creada");
        }
        if ($resumen) {
            $cierre = $this->cashCloseRepository->getBoxClosureSummary($boxId, $date, $boxOwner->id);
            $viewData = json_decode($cierre->datos, true);
            $userBox = data_get($viewData, 'usuario.USERID', 'N/A');
            $html   = view('cierredecajaPDF', $viewData)->render();
            $pdf    = app('dompdf.wrapper');
            $pdf->setPaper('a4', 'portrait');
            $pdf->loadHTML($html);
            $binary = $pdf->output();

            $filename = Str::upper('cierre-de-caja-' . Str::slug($userBox, '-')  . '.pdf');

            return [
                'binary'        => $binary,
                'filename'      => $filename,
                'content_type'  => 'application/pdf',
            ];
        }
    }
}
