<?php

namespace App\Services\Venta\Contracts;

interface BillingServiceInterface
{
  public function consultStatusAccountClient(array $data);
}
