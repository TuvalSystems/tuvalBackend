<?php

namespace App\Repositories\Cobranzas\Contracts;
use Illuminate\Support\Collection;

interface CollectionRepositoryInterface
{
    public function getPaymentsByDepositReference(string $reference): Collection;


    public function getAppliedBalancesByBox(int $boxId, string $date);
    public function getDepositsByBox(int $boxId, string $date);
    public function getChequesByBox(int $boxId, string $date, bool $posfechados = false);
    public function getCashPaymentsByBox(int $boxId, string $date);
    public function getRetencionsByBox(int $boxId, string $date);
    public function getCardPaymentsByBox(int $boxId, string $date);
}
