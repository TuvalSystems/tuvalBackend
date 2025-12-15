<?php

namespace App\Services\Builders\Venta\Contracts;

use App\Models\GpUsuario;

interface QuotationBuilderInterface
{
    // Define los métodos que debe implementar el servicio

    public function fromPayloadReservedToQuotation(array $data, array $requiereApproval, GpUsuario $user, $origen);
    public function formPayloadReservedToDetail($cotizacionId, $item, $totalLines);
    public function formPayloadReservedToDetaiLStock($cotizacionId, $detalleId, $stock, $line);


    /** proceso de clientes */

    public function formPayloadSendClientGp($data, $nivelPrice);
    public function formPayloadClientWeb(array $data, string $nivelPrecio): array;
    public function formPayloadClientSucursal(array $data): array;
}
