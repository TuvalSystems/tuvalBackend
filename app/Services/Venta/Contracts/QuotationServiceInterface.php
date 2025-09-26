<?php

namespace App\Services\Venta\Contracts;

use App\Models\GpUsuario;

interface QuotationServiceInterface
{

    public function getNonSalesReasons();

    public function updateNonSaleAndReminderData($data);
    public function getQuotationsWithReminderToday();

    /**
     *  recuperar cotizacion
     */
    public function restoreQuotation($data, $user);
    public function getStockGeneralRestoreQuotation($articulos, $bodegas);


    //  enviar cotizaciones sql server
    public function sendStoreReminderGp($cotizacion);


    /** lista de items alternativos para cotizacione s */

    public function getItemsAlternatives($item);

    /**
     * actualizaciones de campos de gp_temp_cart
     */
    public function syncAndGetCheckAlternativo($data, $articulo);
    public function updateCheckAlternativeItem($id, $checkAlternative);


    /**
     *  imprimir cotizacion pdf
     */

    public function printQuotationPdf($id);

    /***
     * API para obtener listado de cotizaciones con novedades
     */
    public function consultQuotationsNews($data);


    /** funcion para obtener el detalle de la cotizacion  */

    public function detailQuotation($data);

    /** funcion para llenar la cotizacion  */
    public function fillQuotation($data, $user);

    /** funcion para obtener el stock del articulo en la cotizacion  */
    public function getStockItemForWarehouse($article, $warehouse);

    /** funcion para volver a pedir cotizacion  */
    public function requestQuotationAgain($data, $user);

    /** funcion para guardado de cotización  */
    public function storeQuotation($data, $user);

    /** funcion para obtener el margen del item l en cotizaciónes */
    public function getMarginItem($artArticulo);

    /** función dedicada para guardar proveedores */
    public function saveProviderQuotationTemp(array $data);


    /** procesos de cliente */

    public function getSegmentosSqlServer();
    public function getSubSegmentosSqlServer($segmento);
    public function storeClientQuotation(array $data, GpUsuario $user);

    public function checkClientWithSegmentExist($ruc);
    public function updateClientWithSegment($cliente, $segmentoId, $subsegmentoId);



    /*** prueba  */

    public function consultaStockPrueba($articulo, $bodega);


    /**
     *  reservas
     */

    public function hasAnyReserveState(int $id);
    public function saveQuotationHeaderReserved(array $reserva, GpUsuario $user);
    public function fetchListTipeReserved();

    public function fetchListReservedPending($perPage, $search, $sortBy, $sortOrder);
    public function getQuotationWithDetails(int $id);
    public function getQuotationWithDetailsAndStock(int $id);
     public function getQuotationWithDetailsAndActiveStockById(int $id);
    public function denyApplicationReservedQuotation(array $data, $gpUser);
    public function approvalApplicationReservedQuotation(array $data, $gpUser);
    public function fetchListApprovedQuotation($perPage, $search, $sortBy, $sortOrder, $fecha_desde, $fecha_hasta, $estado, $ptoVtaId,  $clienteRuc, $userId);

    public function processReservedQuotation (array $data, $user);
     public function processReservedQuotationInvoiceDemand($cotizacion, $user);
    public function recalculateQuotation($quotationId);
}
