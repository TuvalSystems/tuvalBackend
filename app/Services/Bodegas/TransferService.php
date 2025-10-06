<?php

namespace App\Services\Bodegas;

use App\Models\GpPedido;
use App\Models\GpRelacionPuntoVentaBodega;
use App\Repositories\Bodegas\Contracts\TransferRepositoryInterface;
use App\Services\Bodegas\Contracts\LogisticsOperationsServiceInterface;
use App\Services\Bodegas\Contracts\TransferServiceInterface;
use App\Services\Funciones\Contracts\InvoiceServiceInterface;
use App\Services\Reposiciones\Contracts\StockWarehouseServiceInterface;

class TransferService implements TransferServiceInterface
{

    protected $invoiceService;
    protected $logisticsOperationsService;
    protected $stockWarehouseService;
    protected $transferRepository;
    public function __construct(
        InvoiceServiceInterface $invoiceService,
        LogisticsOperationsServiceInterface $logisticsOperationsService,
        StockWarehouseServiceInterface $stockWarehouseService,
        TransferRepositoryInterface $transferRepositoryInterface
    ) {
        $this->invoiceService = $invoiceService;
        $this->logisticsOperationsService = $logisticsOperationsService;
        $this->stockWarehouseService = $stockWarehouseService;
        $this->transferRepository = $transferRepositoryInterface;
    }



    public function fetchSalesTransfers($id, $pedido = null, $compartimiento = null){
        $bodega = GpRelacionPuntoVentaBodega::where('PTO_VTA_BOD_PUNTO_VENTA', $id)->value('PTO_VTA_BOD_BODEGA');
        $trasladosVentas = $this->transferRepository->findPendingSalesTransfersByOriginWarehouse($bodega, $pedido);
        if ($trasladosVentas->isEmpty()) {
            return [];
        }
        return $trasladosVentas;
    }





    public function checkOrderTranfer()
    {
        $pedidos = GpPedido::where('estado', 'traslado')
            ->get();
        foreach ($pedidos as $pedido) {
            // $pedidosControl->checkenviarfactura($pedido, true);
            $this->invoiceService->checkSendInvoice($pedido, true);
        }
    }
}
