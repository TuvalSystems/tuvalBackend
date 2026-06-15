<?php

namespace App\Services\Inventario\Bodegas\Contracts;

use App\Models\despachos\GpGuiaRemision;

interface LogisticsOperationsServiceInterface
{
      public function assignAccessCodesForPendingGuides();

      /** para el caso de reservas de guias */
      public function assignAccessCodeForPendingGuide($guia);
       public function updateOrderDispatchStatus(GpGuiaRemision $guia);

}
