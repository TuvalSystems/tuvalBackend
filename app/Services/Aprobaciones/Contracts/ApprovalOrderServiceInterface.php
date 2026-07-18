<?php

namespace App\Services\Aprobaciones\Contracts;

use App\Models\GpPedido;
use App\Models\GpUsuario;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ApprovalOrderServiceInterface
{

    public function approveOrder(array $data): array;
    public function checkoutApprovalCreditOrder(string $condId, float $total, string $ruc): string;
    public function getAllOrdersPendingApprovals(string $ptoVta, GpUsuario $user): Collection;
    public function getAllOrdersForGlobalConsultation(string $ptoVta): Collection;
    public function fetchOrdersPendingCreditApproval(array $filters): LengthAwarePaginator;

    public function accountStatusClient(int $id): array;

    public function validateOrderStockForApproval(int $id): ?string;
    public function denyOrderBeenWaiting(int $id, GpUsuario $user): GpPedido;

    /** rechazo de pedidos en aprobacion comercial  */
    public function rejectCommercialOrder(int $id, GpUsuario $approver, ?string $alert = null): GpPedido;
    public function rejectCreditOrder(int $id, GpUsuario $user, ?string $alert = null): GpPedido;

      //function cuando cumple las condiciones para la aprobación
    public function approveCreditOrder(int $id, GpUsuario $user, ?string $msg = null): GpPedido;

    /** funciones para aprobacion comercial  */

    public function approveComercialOrder(int $id, GpUsuario $user, ?string $msg = null): GpPedido;


    }
