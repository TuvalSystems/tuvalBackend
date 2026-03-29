<?php

namespace App\Services\Bodegas;

use App\Enums\Ventas\OrderStatus;
use App\Models\GpPedido;
use App\Models\GpRelacionPuntoVentaBodega;
use App\Models\GpUsuario;
use App\Models\traslados\GpTrasladosVentas;
use App\Notifications\User\UserNotification;
use App\Repositories\Bodegas\Contracts\TransferRepositoryInterface;
use App\Services\Bodegas\Contracts\LogisticsOperationsServiceInterface;
use App\Services\Bodegas\Contracts\TransferServiceInterface;
use App\Services\Funciones\Contracts\FunctionsServiceInterface;
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
        protected readonly StockWarehouseServiceInterface $stockWarehouseService,
        protected readonly TransferRepositoryInterface $transferRepository,
        protected readonly FunctionsServiceInterface $functionService
    ) {}



    public function fetchSalesTransfers($id, $pedido = null, $compartimiento = null)
    {
        $bodegas = [];
        $bodega = GpRelacionPuntoVentaBodega::where('PTO_VTA_BOD_PUNTO_VENTA', $id)->value('PTO_VTA_BOD_BODEGA');
        $trasladosVentas = $this->transferRepository->findPendingSalesTransfersByOriginWarehouse($bodega, $pedido);
        if ($trasladosVentas->isEmpty()) {
            return [];
        }

        $compMap = [];
        $uniqueKeys = $trasladosVentas->map(fn($t) => $t->ART_CODIGO . '@' . $bodega)->unique()->values();
        foreach ($uniqueKeys as $key) {
            [$art, $bod] = explode('@', $key);
            $params = ['articulo' => $art, 'bodega' => $bod, 'compartimento' => ''];
            $resp = $this->stockWarehouseService->fetchStockWarehouseCompartiment($params);
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
            $guias                  = $this->transferRepository->findGuidesToWarehouseDestinoAndOrigin($bodega, $destID);
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





    public function checkOrderTranfer()
    {
        $pedidos = GpPedido::where('estado', OrderStatus::TRASLADO)
            ->get();
        foreach ($pedidos as $pedido) {
            $this->invoiceService->checkSendInvoice($pedido, true);
        }
    }


    public function cancelPicking(array $payload, GpUsuario $user)
    {
        $transferId = data_get($payload, 'id');
        $orderId    = data_get($payload, 'pedido_id');
        $codeOrder  = data_get($payload, 'pedido');
        $artCode    = data_get($payload, 'ART_CODIGO');
        GpTrasladosVentas::where('id', $transferId)->update([
            'picking' => 0
        ]);

        $hasPicking = GpTrasladosVentas::where('pedido_id', $orderId)
            ->where('picking', '=', 1) // O where('picking', '>', 0) según tu DB
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
}
