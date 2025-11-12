<?php

namespace App\Services\Builders\Venta\Contracts;

use App\Models\GpUsuario;

interface QuotationBuilderInterface
{
    // Define los métodos que debe implementar el servicio

 public function fromPayloadReservedToQuotation(array $data,  array $requiereApproval, GpUsuario $user, $origen);
 public function formPayloadReservedToDetail( $cotizacionId,  $item, $totalLines);
 public function formPayloadReservedToDetaiLStock($cotizacionId, $detalleId ,$stock, $line);
}
