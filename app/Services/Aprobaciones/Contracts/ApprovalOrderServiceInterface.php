<?php

namespace App\Services\Aprobaciones\Contracts;

use App\Models\GpUsuario;
use Illuminate\Database\Eloquent\Collection;

interface ApprovalOrderServiceInterface
{

    public function approveOrder($data);
    public function checkoutApprovalCreditOrder($condId, $total, $ruc): string;
    public function getAllOrdersPendingApprovals(string $ptoVta, GpUsuario $user): Collection;
}
