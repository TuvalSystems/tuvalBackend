<?php

namespace App\Repositories\Cobranzas\Contracts;

interface CollectionRepositoryInterface
{

    public function getAppliedBalancesByBox(int $boxId, string $date);
    public function getDepositsByBox(int $boxId, string $date);
    public function getChequesByBox(int $boxId, string $date, bool $posfechados = false);
    public function getCashPaymentsByBox(int $boxId, string $date);
    public function getRetencionsByBox(int $boxId, string $date);
    public function getCardPaymentsByBox(int $boxId, string $date);
}
