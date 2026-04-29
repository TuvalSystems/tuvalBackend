<?php

namespace App\Services\Bodegas;

use App\Enums\Ventas\OrderStatus;
use App\Models\GpBodega;
use App\Models\GpPedido;
use App\Models\GpPuntoVenta;
use App\Models\GpRelacionPuntoVentaBodega;
use App\Models\GpUsuario;
use App\Models\traslados\GpTrasladosVentas;
use App\Notifications\User\UserNotification;
use App\Repositories\Bodegas\Contracts\TransferRepositoryInterface;
use App\Services\Bodegas\Contracts\LogisticsOperationsServiceInterface;
use App\Services\Bodegas\Contracts\TransferServiceInterface;
use App\Services\Funciones\Contracts\InvoiceServiceInterface;
use App\Services\Reposiciones\Contracts\StockWarehouseServiceInterface;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class TransferService implements TransferServiceInterface
{


    public function __construct(
        protected readonly InvoiceServiceInterface $invoiceService,
        protected readonly LogisticsOperationsServiceInterface $logisticsOperationsService,
        protected readonly StockWarehouseServiceInterface $stockService,
        protected readonly TransferRepositoryInterface $repository,
    ) {}



    public function fetchSalesTransfers($id, $pedido = null, $compartimiento = null)
    {
        $bodegas = [];
        $bodega = GpRelacionPuntoVentaBodega::where('PTO_VTA_BOD_PUNTO_VENTA', $id)->value('PTO_VTA_BOD_BODEGA');
        $trasladosVentas = $this->repository->findPendingSalesTransfersByOriginWarehouse($bodega, $pedido);
        if ($trasladosVentas->isEmpty()) {
            return [];
        }

        $compMap = [];
        $uniqueKeys = $trasladosVentas->map(fn($t) => $t->ART_CODIGO . '@' . $bodega)->unique()->values();
        foreach ($uniqueKeys as $key) {
            [$art, $bod] = explode('@', $key);
            $params = ['articulo' => $art, 'bodega' => $bod, 'compartimento' => ''];
            $resp = $this->stockService->fetchStockWarehouseCompartiment($params);
            $compMap[$key] = Arr::get($resp, 'COMPARTIMIENTO', '');
        }

        foreach ($trasladosVentas as $t) {
            $reservado          = (float) data_get($t, 'reservado', 0);
            $trasladado         = (float) data_get($t, 'trasladado', 0);
            $cantidad           = (float) data_get($t, 'cantidad', 0);
            $pendiente          = round($cantidad - $reservado - $trasladado, (float) data_get($t, 'ART_DECIMAL_CANTIDAD'), 0);
            $t->pendiente       = $pendiente;
            $t->fecha           = Carbon::parse($t->fecha)->format('Y-m-d H:i');
            $key                = $t->ART_CODIGO . '@' . $bodega;
            $t->compartimiento  = $compMap[$key] ?? '';
            // Filtro por compartimiento (prefijo)
            if ($compartimiento !== null && $compartimiento !== 'null') {
                if (!Str::startsWith($t->compartimiento, (string) $compartimiento)) {
                    continue;
                }
            }

            $letra = '';
            if ($t->compartimiento !== '' && Str::contains($t->compartimiento, '-')) {
                $letra = explode('-', $t->compartimiento)[0] ?? '';
            }
            $dest = $t->bodega_destino;
            if (!isset($bodegas[$dest])) {
                $bodegas[$dest] = [
                    'bodega'            => $t->BOD_DESCRIPCION,
                    'BOD_BODEGA'        => $t->BOD_BODEGA,
                    'generar'           => false,
                    'traslados'         => [],
                    'pedidos'           => [],
                    'compartimientos'   => [],
                    'tipo'              => 'transferencia'
                ];
            }

            $bodegas[$dest]['traslados'][]                  = $t;
            $bodegas[$dest]['pedidos'][$t->pedido]          = $t->pedido;
            if ($letra !== '') {
                $bodegas[$dest]['compartimientos'][$letra]  = $letra;
            }
        }
        if (empty($bodegas)) {
            return [];
        }

        // 5) Guías por bodega destino (una sola ida a BD por bodega)
        foreach ($bodegas as $destID => &$bod) {
            $guias                  = $this->repository->findGuidesToWarehouseDestinoAndOrigin($bodega, $destID);
            $bod['guias']           = $guias->values()->all();
            $bod['ultimaguia']      = $guias->first();
            $bod['pedidos']         = array_values($bod['pedidos']);
            $bod['compartimientos'] = array_values($bod['compartimientos']);
            $bod['peso_total']      = collect($bod['traslados'])->reduce(function ($carry, $t) {
                $peso               = (float) ($t->ART_PESO ?? 0);
                return $carry + ($peso * (float) $t->pendiente);
            }, 0.0);
        }
        unset($bod);
        $bodegas = array_values($bodegas);
        $this->logisticsOperationsService->assignAccessCodesForPendingGuides();
        if ($pedido !== null && $pedido !== 'null') {
            return $bodegas[0]['traslados'] ?? [];
        }

        return $bodegas;
    }



    public function fetchSalesTransfersSunnaries(string $ptoVtaId)
    {
        $bodOrigin = GpRelacionPuntoVentaBodega::where('PTO_VTA_BOD_PUNTO_VENTA', $ptoVtaId)->value('PTO_VTA_BOD_BODEGA');
        $items = $this->repository->getAllSalesTransfersByWarehouse($bodOrigin)
            ->filter(fn($t) => (float) $t->pending_transfer > 0)
            ->map(function ($item) {
                $item->pending_transfer         = (float) $item->pending_transfer;
                $item->art_pesoKg               = $item->pending_transfer * (float) $item->ART_PESO;
                $item->bodega_destino_desc      = $item->bodDestino->BOD_DESCRIPCION;
                $item->ptovtaId                 = $item->relacionPuntoVenta ? $item->relacionPuntoVenta->PTO_VTA_BOD_PUNTO_VENTA : null;
                return $item;
            })->groupBy('bodega_destino');

        $guiasGrouped = $this->repository->findCountPendingGuidesByOriginWarehouse($bodOrigin)->map(function ($item) {
            return [
                'bodega_destino'        => $item->bodega_destino,
                'total'                 => $item->total,
                'bodega_destino_desc'   => $item->guiaBodegaDestino->BOD_DESCRIPCION,
                'ptovtaId'              => $item->guiaPuntoVentaDestino->PTO_VTA_BOD_PUNTO_VENTA
            ];
        });
        $allDestinosIds =  $items->keys()->merge($guiasGrouped->keys())->unique();
        return $allDestinosIds->map(function ($destId) use ($items, $guiasGrouped) {
            $traslados      = $items->get($destId); // Puede ser null
            $guiaInfo       = $guiasGrouped->get($destId); // Puede ser null
            $bodegaNombre   = $traslados ? $traslados->first()->bodega_destino_desc : data_get($guiaInfo, 'bodega_destino_desc', 'Desconocida');
            return [
                'bodega'            => $bodegaNombre,
                'ptovtaId'          => $traslados ? $traslados->first()->ptovtaId : data_get($guiaInfo, 'ptovtaId'), // Usamos el que entra por parámetro, es más seguro
                'pesoTon'           => $traslados ? round($traslados->sum('art_pesoKg') / 1000, 2) : 0,
                'totalItems'        => $traslados ? $traslados->count() : 0,
                'totalGuidePending' => data_get($guiaInfo, 'total', 0),
            ];
        })->values()->all();
    }


    public function getSalesTransferHeadersBetweenWarehouses(string $origen, string $destino)
    {
        $map = GpRelacionPuntoVentaBodega::query()
            ->whereIn('PTO_VTA_BOD_PUNTO_VENTA', [$origen, $destino])
            ->pluck('PTO_VTA_BOD_BODEGA', 'PTO_VTA_BOD_PUNTO_VENTA');

        $waOrigin       = $map[$origen] ?? null;
        $waDestination  = $map[$destino] ?? null;
        if (!$waOrigin || !$waDestination) {
            return [];
        }
        $waDestinationDesc = GpBodega::where('BOD_BODEGA', $waDestination)->value('BOD_DESCRIPCION');
        $transferHeaders = $this->repository->getAllSalesTransfersByWarehouseOriginAndDestination($waOrigin, $waDestination);
        if ($transferHeaders->isEmpty()) {
            return [];
        }
        $items = $transferHeaders->filter(fn($tr) => (float) $tr->pending_transfer > 0)->map(function ($item) {
            $qty                        = (float) $item->pending_transfer;
            $kg                         = (float) $item->ART_PESO;
            $item->art_pesoKg           = $qty * $kg;
            $item->orderNumber          = $item->pedido->pedido;
            $item->usuario              = $item->pedido->USERID;
            $item->ART_CANTIDAD_DECIMAL = (float) $item->articulo->ART_DECIMAL_CANTIDAD;
            //elimino esas relaciones porque no quiero cargarlas en la data
            $item->makeHidden(['pedido', 'articulo', 'bodDestino', 'relacionPuntoVenta']);
            return $item;
        })->values();

        $pesoTotal = round($items->sum('art_pesoKg') / 1000, 2);
        $listOrdersByWarehouse = $this->getFiltersNumOrdersByWarehouses($items);
        return [
            'origen'            => $waOrigin,
            'ptoOrigin'         => $origen,
            'bodDestination'    => $waDestination,
            'ptoDestination'    => $destino,
            'destination'       => $waDestinationDesc,
            'pesoTotal'         => $pesoTotal,
            'listsOrders'       => $listOrdersByWarehouse
        ];
    }
    protected function getFiltersNumOrdersByWarehouses($items)
    {
        return collect($items)
            ->pluck('orderNumber')
            ->filter()
            ->unique()
            ->values()
            ->map(fn($c) => $c);
    }



    public function getListSalesTransferDetailsBetweenWarehouses(string $origen, string $destino, ?string $pedido = null)
    {
        $map = GpRelacionPuntoVentaBodega::query()
            ->whereIn('PTO_VTA_BOD_PUNTO_VENTA', [$origen, $destino])
            ->pluck('PTO_VTA_BOD_BODEGA', 'PTO_VTA_BOD_PUNTO_VENTA');

        $waOrigin       = $map[$origen] ?? null;
        $waDestination  = $map[$destino] ?? null;
        if (!$waOrigin || !$waDestination) {
            return [];
        }
        $traslados = $this->repository->getAllSalesTransfersByWarehouseOriginAndDestination($waOrigin, $waDestination, $pedido);
        if ($traslados->isEmpty()) {
            return [];
        }

        return $traslados->filter(fn($tr) => (float) $tr->pending_transfer > 0)
            ->map(function ($item) use ($waOrigin) {
                $item->orderNumber              = $item->pedido->pedido;
                $item->usuario                  = $item->pedido->USERID;
                $item->ART_CANTIDAD_DECIMAL     = (float) $item->articulo->ART_DECIMAL_CANTIDAD;
                $item->fecha                    = Carbon::parse($item->created_at)->format('Y-m-d H:i:s');
                $artApi                         = $this->fetchItemCompartmentByWarehouse($item->ART_CODIGO, $waOrigin);
                $item->compartimiento           = Arr::get($artApi, 'COMPARTIMIENTO', '');
                $item->existencia               = round(Arr::get($artApi, 'STOCK_DISPONIBLE', 0), 2);
                //elimino esas relaciones porque no quiero cargarlas en la data
                $item->makeHidden(['pedido', 'articulo', 'bodDestino', 'relacionPuntoVenta']);
                return $item;
            })->values();
    }

    protected function fetchItemCompartmentByWarehouse($artCode, $warehouse)
    {
        $params = ['articulo' => $artCode, 'bodega' => $warehouse, 'compartimento' => ''];
        return $this->stockService->fetchStockWarehouseCompartiment($params);
    }

    public function checkOrderTranfer()
    {
        $pedidos = GpPedido::where('estado', OrderStatus::TRASLADO)
            ->get();
        foreach ($pedidos as $pedido) {
            $this->invoiceService->checkSendInvoice($pedido, true);
        }
    }


    public function updatePickingSalesTransfers(array $payload)
    {
        $ids = collect($payload)->pluck('id')->filter()->unique();
        if ($ids->isEmpty()) {
            return 0;
        }
        return GpTrasladosVentas::whereIn('id', $ids)->update(['picking' => 1]);
    }

    public function printPickingSalesTransfers(array $payload)
    {
        $itemsPayload = collect(data_get($payload, 'items'));
        $puntodeventa = GpPuntoVenta::join('gp_relacion_punto_venta_bodegas', 'gp_relacion_punto_venta_bodegas.PTO_VTA_BOD_PUNTO_VENTA', 'gp_punto_ventas.PTO_VTA_ID')
            ->where('gp_relacion_punto_venta_bodegas.PTO_VTA_BOD_BODEGA', data_get($payload, 'origen'))->first()->toarray();
        $viewData = [
            'origen'    => data_get($payload, 'origen'),
            'destino'   => data_get($payload, 'destino'),
            'pesototal' => (float) str_replace(',', '.', (string) ($payload['pesototal'] ?? 0)) ?? 0,
            'rows'      => $itemsPayload->map(function ($item) {
                $pesoUnitGr         = (float) str_replace(',', '.', (string) ($item['ART_PESO'] ?? 0));
                $qty                = (int) ($item['seleccionado'] ?? 0);
                $item['peso_kg']    = round(($pesoUnitGr * $qty), 2);
                return $item;
            })->values()
                ->all(),
            'direccion' => $puntodeventa['PTO_VTA_DIRECCION_1'] . ' ' . $puntodeventa['PTO_VTA_DIRECCION_2'],
        ];
        $html   = view('pdf.picking-transferencia-venta', $viewData)->render();
        $pdf    = app('dompdf.wrapper');
        $pdf->setPaper('a4', 'portrait');
        $pdf->loadHTML($html);
        $binary = $pdf->output();

        $filename = 'picking-' . Str::slug($payload['destino'], '-') . '-' . now()->format('Ymd-His') . '.pdf';

        return [
            'binary'        => $binary,
            'filename'      => $filename,
            'content_type'  => 'application/pdf',
        ];
    }

    public function cancelPicking(array $payload, GpUsuario $user)
    {
        $transferId = data_get($payload, 'id');
        $orderId    = data_get($payload, 'pedido_id');
        $codeOrder  = data_get($payload, 'orderNumber');
        $artCode    = data_get($payload, 'ART_CODIGO');
        GpTrasladosVentas::where('id', $transferId)->update([
            'picking' => 0
        ]);

        $hasPicking = GpTrasladosVentas::where('pedido_id', $orderId)
            ->where('picking', '=', 1)
            ->exists();

        if (!$hasPicking) {
            GpPedido::where('id', $orderId)->update([
                'transferencia_en_proceso' => 0
            ]);
        }
        $title = 'Picking Cancelado';
        $msg = "El usuario {$user->USERID} canceló el picking del artículo {$artCode} del pedido {$codeOrder}";
        $user->notify(new UserNotification($title, $msg));
    }

    public function fetchPendingSalesTransferDeliveryNotes(string $ptoVtaOrigen, string $ptoVtaDestino)
    {
        $map = GpRelacionPuntoVentaBodega::query()
            ->whereIn('PTO_VTA_BOD_PUNTO_VENTA', [$ptoVtaOrigen, $ptoVtaDestino])
            ->pluck('PTO_VTA_BOD_BODEGA', 'PTO_VTA_BOD_PUNTO_VENTA');
        $waOrigin       = $map[$ptoVtaOrigen] ?? null;
        $waDestination  = $map[$ptoVtaDestino] ?? null;
        if (!$waOrigin || !$waDestination) {
            return [];
        }
        return $this->repository->findGuidesPendingByOriginWarehouse($waOrigin, $waDestination);
    }
}
