<?php

namespace App\Services\Builders\Venta\Contracts;

use App\Models\GpCotizacion;
use App\Models\GpCotizacionDetalle;
use App\Models\GpCotizacionDetalleProveedores;
use App\Models\GpCotizacionDetalleStock;
use App\Models\GpPuntoVenta;
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
    public function formPayloadClientWeb(array $data, string $nivelPrecio, GpUsuario $user): array;
    public function formPayloadClientSucursal(array $data, int $clienteId, GpUsuario $user): array;


    /** proceso de creacion de cotizacion  */
    public function formPayloadHeaderQuotation(GpCotizacion $quotation, array $data, GpUsuario $user, GpPuntoVenta $puntoVenta, array $requiredApprovalCommercial, string $motivoLimpio, string $tipoDespacho): GpCotizacion;
    public function formPayloadDetailQuotation(int $quotationId, array $articles): GpCotizacionDetalle;

    public function formPayloadProvidersDetailQuotation(int $quoteId, int $detailId, array $provider): GpCotizacionDetalleProveedores;
    public function formPayloadStocksDetailQuotation(int $quoteId, int $detailId, array $stock): GpCotizacionDetalleStock;
}
