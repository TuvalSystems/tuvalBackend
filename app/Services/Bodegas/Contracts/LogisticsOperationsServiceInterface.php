<?php

namespace App\Services\Bodegas\Contracts;

use App\Models\despachos\GpGuiaRemision;

interface LogisticsOperationsServiceInterface
{
      public function assignAccessCodesForPendingGuides();
       public function updateOrderDispatchStatus(GpGuiaRemision $guia);
}
