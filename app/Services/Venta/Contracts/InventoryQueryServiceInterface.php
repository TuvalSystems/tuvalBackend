<?php

namespace App\Services\Venta\Contracts;

use App\Models\Articulos\GpArticulo;

interface InventoryQueryServiceInterface
{
    // Define los métodos que debe implementar el servicio
    public function getConsultStock($data);
    public function getConsultItemTransito($data);
    public function fetchStockInTransit();
    public function fetchStockInTransitTransfers();
        public function fetchStockTransitReservations();
     public function fetchStockInTransitConsolidated();
    /** funcion para consulta de cupo de cliente */
    public function getAvailableSpace(string $ruc);
    /** funcion de consulta de reservados  */
    public function listReservedInventory();
    public function listReservedWebInventory(?string $item);
    /**
     * funcion encargada de buscar articulos
     * @param array $filters
     */
    public function searchWithDetails(array $filters);
    public function findAvailableTypeLArticle(
        array $excludedArticleCodes,
        float $taxPercentage
    ): ?GpArticulo;
    public function fetchAllTerritories();
    public function fetchAllProvinces();
    public function fetchCitiesByProvince(string $provId);
    public function listReservedStockForItems(string $items);
    public function getArticleExposition(string $artCode, string $pvCode);
}
