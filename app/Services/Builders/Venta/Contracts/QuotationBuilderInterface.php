<?php

namespace App\Services\Builders\Venta\Contracts;

use App\Models\Articulos\GpArticulo;
use App\Models\GpCliente;
use App\Models\GpCotizacion;
use App\Models\GpCotizacionDetalle;
use App\Models\GpCotizacionDetalleProveedores;
use App\Models\GpCotizacionDetalleStock;
use App\Models\GpPuntoVenta;
use App\Models\GpUsuario;
use App\Models\Ventas\Cotizaciones\HiCotizacion;
use App\Models\Ventas\Cotizaciones\HiCotizacionDetalle;
use App\Models\Ventas\Cotizaciones\HiCotizacionDetalleProveedores;
use App\Models\Ventas\Cotizaciones\HiCotizacionDetalleStock;
use App\Models\Ventas\TipoDespachos\TipoDespacho;
use App\Models\Ventas\Vendedores\GpVendedor;

interface QuotationBuilderInterface
{
    // Define los métodos que debe implementar el servicio
    public function formPayloadSendStoreReminderGp(GpCotizacion $quote): array;
    public function fromPayloadReservedToQuotation(array $data, array $requiereApproval, GpUsuario $user, string $origen): GpCotizacion;
    public function formPayloadReservedToDetail(int $cotizacionId, GpCotizacionDetalle $item, float $totalLines): GpCotizacionDetalle;
    public function formPayloadReservedToDetaiLStock(int $cotizacionId, int $detalleId, GpCotizacionDetalleStock $stock, array $line): GpCotizacionDetalleStock;


    /** proceso de creacion de cotizacion  */
     public function formPayloadHeaderQuotation(GpCotizacion $quotation,  GpUsuario $user, GpCliente $client,  TipoDespacho $typeDispatch, GpVendedor $seller, GpPuntoVenta $pointSales ,array $data, array $requiredApprovalCommercial, string $motivoLimpio): GpCotizacion;
    public function formPayloadDetailQuotation(int $quotationId, array $articles, string $lvlPrice): GpCotizacionDetalle;

    public function formPayloadProvidersDetailQuotation(int $quoteId, int $detailId, array $provider): GpCotizacionDetalleProveedores;
    public function formPayloadStocksDetailQuotation(int $quoteId, int $detailId, array $stock): GpCotizacionDetalleStock;

    public function payloadQuotationHeaderDraft(GpUsuario $user, GpCliente $cliente, array $info): GpCotizacion;
    public function payloadQuotationDetailDraft(GpArticulo $art, int $quoteId, GpCliente $cliente): void;


    /** proceso de creacion de historico de cotizaciones */
    public function formPayloadHistoryHeaderQuote(GpCotizacion $quote, int $version): HiCotizacion;
    public function formPayloadHistoryDetailQuote(int $cotId, int $detId, int $hCotId, GpCotizacionDetalle $detail): HiCotizacionDetalle;
    public function formPayloadHistoryDetailQuoteProvider(int $hQuoteId, int $hDetailId, int $quoteId, int $detId, GpCotizacionDetalleProveedores $prov): HiCotizacionDetalleProveedores;
    public function formPayloadHistoryDetailQuoteStock(int $hQuoteId, int $hDetailId, int $quoteId, int $detId, GpCotizacionDetalleStock $stock): HiCotizacionDetalleStock;
}
