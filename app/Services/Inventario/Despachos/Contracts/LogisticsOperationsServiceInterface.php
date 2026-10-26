<?php

namespace App\Services\Inventario\Despachos\Contracts;

use App\Models\Inventario\GuiasRemision\GpGuiaRemision;

interface LogisticsOperationsServiceInterface
{
      public function assignAccessCodesForPendingGuides();

      /** para el caso de reservas de guias */
      public function assignAccessCodeForPendingGuide($guia);
       public function updateOrderDispatchStatus(GpGuiaRemision $guia);

}
