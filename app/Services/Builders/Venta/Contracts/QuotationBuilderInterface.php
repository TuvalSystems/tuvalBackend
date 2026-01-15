<?php

namespace App\Services\Builders\Venta\Contracts;

use App\Models\GpTempCart;
use App\Models\GpTempCartProveedores;
use App\Models\GpTempCartStock;
use App\Models\GpUsuario;

interface QuotationBuilderInterface
{
    // Define los métodos que debe implementar el servicio
    public function formPayloadSendStoreReminderGp($quote): array;
    public function fromPayloadReservedToQuotation(array $data, array $requiereApproval, GpUsuario $user, $origen);
    public function formPayloadReservedToDetail($cotizacionId, $item, $totalLines);
    public function formPayloadReservedToDetaiLStock($cotizacionId, $detalleId, $stock, $line);


    /** proceso de clientes */

    public function formPayloadSendClientGp($data, $nivelPrice);
    public function formPayloadClientWeb(array $data, string $nivelPrecio): array;
    public function formPayloadClientSucursal(array $data): array;


    /** proceso de cart  */
    public function formPayloadAddCartTemp(int $clienteId, string $ptoVtaId, int $sucursalId, array $article, GpUsuario $user, int $orden): GpTempCart;
    public function formPayloadAddCartTempStock(array $stock, int $cartId):GpTempCartStock ;
    public function formPayloadAddCartTempProvider(array $provider, $cardId): GpTempCartProveedores;

    }
