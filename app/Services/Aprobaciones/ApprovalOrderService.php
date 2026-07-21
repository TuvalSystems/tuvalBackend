<?php

namespace App\Services\Aprobaciones;

use App\Enums\Ventas\OrderStatus;
use App\Models\aprobacion\GpReglaAprobacion;
use App\Models\GpCotizacion;
use App\Models\GpCotizacionDetalle;
use App\Models\GpPedido;
use App\Models\GpPedidoDetalle;
use App\Models\GpPedidoDetalleStock;
use App\Models\GpPuntoVenta;
use App\Models\GpRegistroCopia;
use App\Models\GpUsuario;
use App\Models\traslados\GpTrasladosVentas;
use App\Notifications\User\UserNotification;
use App\Repositories\Pedidos\Contracts\OrderRepositoryInterface;
use App\Services\Api\ApiGpService;
use App\Services\Aprobaciones\Contracts\ApprovalOrderServiceInterface;
use App\Services\Aprobaciones\Utils\Contracts\ApprovalFunctionsServiceInterface;
use App\Services\Funciones\Contracts\FunctionsServiceInterface;
use App\Services\Funciones\Contracts\InvoiceServiceInterface;
use App\Services\Inventario\Stock\Contracts\StockWarehouseServiceInterface;
use App\Traits\RegisterAudit;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

class ApprovalOrderService implements ApprovalOrderServiceInterface
{
    use RegisterAudit;

    public function __construct(
        protected readonly FunctionsServiceInterface $functionService,
        protected readonly ApprovalFunctionsServiceInterface $approvalFuntions,
        protected readonly OrderRepositoryInterface $repository,
        protected readonly ApiGpService $api,
        protected readonly StockWarehouseServiceInterface $stockService,
        protected readonly InvoiceServiceInterface $invoiceService

    ) {}


    /** * CHECK DE APROBACION COMERCIAL **/

    public function approveOrder(array $data): array
    {
        $quoteId = data_get($data, 'cotizacion_id', null);
        $quoteOrigin = empty($quoteId) ? null : GpCotizacion::find($quoteId);
        $motivosAprobacionComercial = [];

        $rgCopy = GpRegistroCopia::where('cotizacion_destino', $quoteId)->where('tipo', 'volver_a_pedir')->where('destino_aprobado', false)->first();
        if ($rgCopy) {
            $esPedido = !empty($rgCopy->pedido_origen);
            $idOrigen = $esPedido ? $rgCopy->pedido_origen : $rgCopy->cotizacion_origen;
            $origenEntidad = $esPedido ? GpPedido::find($idOrigen) : GpCotizacion::find($idOrigen);
            $desde = $esPedido ? 'pedido' : 'cotizacion';
            $prefijo = $esPedido ? 'PED' : 'COT';
            $codigoVisual = $prefijo . str_pad($idOrigen, 8, "0", STR_PAD_LEFT);
            $fechaTxt = optional($origenEntidad)->created_at
                ? optional($origenEntidad)->created_at->format('Y-m-d')
                : 'S/F';


            if ($origenEntidad && $origenEntidad->requiere_aprobacion_comercial === 'yes' && $origenEntidad->aprobado_comercial !== 'yes') {
                $motivosAprobacionComercial[] = $this->approvalFuntions->requiredApprovalResponse(
                    "Requiere aprobación comercial. La Cotización del pedido es desde Volver a Pedir estado 'Espera'. Obligatorio aprobar. Desde $desde: $codigoVisual, fecha: $fechaTxt"
                );
            }
        }


        $nivelCliente           = $this->approvalFuntions->getNivelFormaPago('DESC_FORMA_PAGO_CONDICION_PAGO', $data['condicion_pago_original_id']);
        $nivelCotizacion        = $this->approvalFuntions->getNivelFormaPago('DESC_FORMA_PAGO_CODIGO', $data['forma_pago_id']);
        $rebajaCotizacion       = $this->approvalFuntions->getPorcentilDiscount('DESC_FORMA_PAGO_CODIGO', $data['forma_pago_id']);
        $prcCLienteDescuento    = $this->approvalFuntions->getPorcentilDiscountCommercialCliente($data['ruc']);

        $dsctGlobalCliente = (float) $prcCLienteDescuento - (float) $rebajaCotizacion;

        if (!$nivelCliente) {
            $motivosAprobacionComercial[] = $this->approvalFuntions->requiredApprovalResponse("Forma de pago no encontrada en la base de datos.");
        }

        if (!$nivelCotizacion) {
            $motivosAprobacionComercial[] = $this->approvalFuntions->requiredApprovalResponse("Forma de pago de cotizacion no encontrada en la base de datos.");
        }


        if ($quoteOrigin) {
            $nivelCotizacionAnterior = $this->approvalFuntions->getNivelFormaPago('DESC_FORMA_PAGO_CODIGO', $quoteOrigin->DESC_FORMA_PAGO_CODIGO);
            if ($quoteOrigin->requiere_aprobacion_comercial === 'yes' && $quoteOrigin->aprobado_comercial !== 'yes') {
                $motivosAprobacionComercial[] = $this->approvalFuntions->requiredApprovalResponse(
                    "Requiere aprobación comercial. La cotización no fue aprobada con anterioridad (ID: $quoteId)."
                );
            }

            if ($nivelCotizacion > $nivelCotizacionAnterior && $nivelCotizacion > $nivelCliente) {
                $motivosAprobacionComercial[] = $this->approvalFuntions->requiredApprovalResponse("Forma de pago de cotizacion mayor que la del cliente: $nivelCotizacion > $nivelCliente");
            }

            if ($data['descuento_articulo'] != $data['descuento_original'] && $data['descuento_articulo'] != $dsctGlobalCliente) {
                $motivosAprobacionComercial[] = $this->approvalFuntions->requiredApprovalResponse("Porcentaje de descuento general diferente a la original: $data[descuento_articulo] > $data[descuento_original]");
            }
        } else {
            if ($nivelCotizacion > $nivelCliente) {
                $motivosAprobacionComercial[] = $this->approvalFuntions->requiredApprovalResponse("Forma de pago de cotizacion mayor que la del cliente: $nivelCotizacion > $nivelCliente");
            }
            if ($data['descuento_articulo'] != $data['descuento_original'] && $data['descuento_articulo'] != $dsctGlobalCliente) {
                $motivosAprobacionComercial[] = $this->approvalFuntions->requiredApprovalResponse("Porcentaje de descuento general diferente a la original: $data[descuento_articulo] > $data[descuento_original]");
            }
        }

        $ptoVenta = GpPuntoVenta::where('PTO_VTA_ID', $data['punto_venta_id'])->firstOrFail();
        $motivosDetalle = $this->validateOrdersDetailToApprove($data['items'], $ptoVenta, $dsctGlobalCliente);
        if (!empty($motivosDetalle)) {
            $motivosAprobacionComercial = array_merge($motivosAprobacionComercial, $motivosDetalle);
        }

        return $this->approvalFuntions->finalizeApprovalReasons($motivosAprobacionComercial);
    }

    protected function validateOrdersDetailToApprove(array $detalles, GpPuntoVenta $ptoVta, float $dsctGlobalCliente): array
    {
        $detailMotivos = [];

        // Pre-cargar detalles de BD
        $ids = collect($detalles)->pluck('detalle_id')->filter();
        $dbDetails = GpCotizacionDetalle::whereIn('id', $ids)->get()->keyBy('id');

        foreach ($detalles as $detalle) {
            $detailId = data_get($detalle, 'detalle_id');
            $detOriginal = $detailId ? $dbDetails->get($detailId) : null;

            // 1. DEFINIR LA "VERDAD" ORIGINAL (Normalización)
            // La lógica es: Si existe en BD, esa es la verdad. Si no (es nuevo), confiamos en el 'original' que envía el front.
            $valorOriginalReal      = $detOriginal ? $detOriginal->ART_VALOR : $detalle['ART_VALOR_ORIGINAL'];
            $descOriginalReal       = $detOriginal ? $detOriginal->ART_DESCRIPCION : $detalle['ART_DESCRIPCION_ORIGINAL'];
            $descuentoOriginalReal  = $detOriginal ? $detOriginal->descuento : $detalle['descuento_original'];

            // --- VALIDACIONES COMUNES ---

            // A. Validación de Descuento
            // Comparamos directamente contra la variable normalizada
            if ($detalle['descuento'] != $detalle['descuento_original'] && $detalle['descuento'] != $dsctGlobalCliente && $detalle['descuento'] != $descuentoOriginalReal) {
                $detailMotivos[] = $this->approvalFuntions->requiredApprovalResponse("Descuento del detalle modificado. Nuevo: {$detalle['descuento']}% vs Original: {$dsctGlobalCliente}%");
            }

            // B. Validación de Valor (Precio)
            // Comparamos directamente contra la variable normalizada
            if ($detalle['ART_VALOR'] != $detalle['ART_VALOR_ORIGINAL'] && $detalle['ART_VALOR'] != $valorOriginalReal) {
                // Mensaje genérico de cambio
                $mensaje = "El valor del producto ha cambiado. Nuevo: {$detalle['ART_VALOR']} vs Original: {$detalle['ART_VALOR_ORIGINAL']}";

                // Si quieres mantener el mensaje específico que tenías para items existentes:
                if ($detOriginal) {
                    $mensaje = "El valor del producto ha cambiado. Nuevo: {$detalle['ART_VALOR']} vs Original: $valorOriginalReal";
                }
                $detailMotivos[] = $this->approvalFuntions->requiredApprovalResponse($mensaje);
            }

            // C. Validaciones NO Tipo L (Standard)
            if ($detalle['ART_TIPO_COMPRA'] != 'L') {
                // C.1 Descripción
                if ($detalle['ART_DESCRIPCION'] != $detalle['ART_DESCRIPCION_ORIGINAL'] && $detalle['ART_DESCRIPCION'] != $descOriginalReal) {
                    $detailMotivos[] =  $this->approvalFuntions->requiredApprovalResponse("La descripción del producto cambió. Nueva: '{$detalle['ART_DESCRIPCION']}...' vs Original: '$descOriginalReal...'");
                }

                // C.2 Exceso de Precio
                // Usamos la variable normalizada $valorOriginalReal como base
                if ($valorOriginalReal > 0) {
                    $porcMaxSubida  = $ptoVta->PTO_VTA_MAXIMO_PORC_SUBIDA_PRECIO;
                    $factor         = 1 + ($porcMaxSubida / 100);
                    $precioLimite   = $valorOriginalReal * $factor;
                    $precioActual   = $detalle['ART_VALOR'];
                    if ($precioActual > $precioLimite) {
                        $exceso = $precioActual - $precioLimite;
                        $excesoPct = round(($exceso / $valorOriginalReal) * 100, 2);

                        $detailMotivos[] =  $this->approvalFuntions->requiredApprovalResponse("El valor del producto ({$precioActual}) supera el límite permitido de {$porcMaxSubida}% "
                            . "(límite: {$precioLimite}), excede en {$exceso} "
                            . "({$excesoPct}% sobre el valor original).");
                    }
                }
            }

            // D. Validaciones Tipo L
            if ($detalle['ART_TIPO_COMPRA'] == 'L') {
                $detailMotivos[] = $this->approvalFuntions->requiredApprovalResponse('Se requiere aprobación por ser Item L');
                $valorOriginalL = round(data_get($detalle, 'valor_original_l', 0), 4);
                $valor = round(data_get($detalle, 'ART_VALOR', 0), 4);

                if ($valorOriginalL > $valor) {
                    $detailMotivos[] = $this->approvalFuntions->requiredApprovalResponse("El valor del producto ($" . $valor . ") es INFERIOR al precio mínimo permitido ($" . $valorOriginalL . ")");
                }
                // ... (resto de tu lógica L se mantiene igual) ...
                // Solo recuerda revisar lo de las consultas N+1 aquí si el rendimiento es crítico
                $porcentajeMargen =  $this->approvalFuntions->getTypeArticleLQuotationOrOrder($detalle['ART_CODIGO']);

                if (!$porcentajeMargen) {
                    $detailMotivos[] = $this->approvalFuntions->requiredApprovalResponse("Porcentaje de margen no encontrado en la base de datos.");
                } else {
                    foreach ($detalle['proveedores'] as $proveedor) {
                        $margenPermitido = $porcentajeMargen->DATOS_ITEMS_L_MARGEN;
                        if ($proveedor['MARGEN'] < $margenPermitido) {
                            $detailMotivos[] = $this->approvalFuntions->requiredApprovalResponse("Margen del producto L es menor al margen aprobado en la cotización.");
                        }
                    }
                }

                /** validacion del precio total del proveedor <= precio de $300 */
                $providersDetail = data_get($detalle, 'proveedores', []);
                foreach ($providersDetail as $provider) {
                    $valorProvider = round(data_get($provider, 'TOTAL', 0), 4);
                    if ($valorProvider < 300) {
                        $detailMotivos[] = $this->approvalFuntions->requiredApprovalResponse(
                            "El valor total del proveedor ({$valorProvider}) está por debajo del límite permitido de $300."
                        );
                    }
                }
            }
        }

        return $detailMotivos;
    }

    /** funcion de validacion de stock de articulos de pedidos  */

    public function validateOrderStockForApproval(int $id): ?string
    {
        $order                  = GpPedido::findOrFail($id);
        $quoteReservedOriginId  = $this->approvalFuntions->validateExistsReserve($order->cotizacion_id);
        $detalles               = $this->getDetailArticlesWithStock($order->id);
        $flatDetails            = $this->stockFormatterForUpdateApproval($detalles);
        $parmas                 = $this->stockFormatterParametersUpdateApproval($flatDetails);
        $stocksResult           = $this->getStockWarehouseAsyncAndFormatter($parmas);
        $newFormatStock         = $this->formattedFlatDetailWithStockGeneral($flatDetails, $stocksResult);
        return  $this->getAlertWithoutStockArticles($detalles, $newFormatStock, $order->id, $quoteReservedOriginId);
    }

    protected function getDetailArticlesWithStock(int $orderId): EloquentCollection
    {
        return GpPedidoDetalle::with('pedidoDetalleStocks')
            ->where('pedido_id', $orderId)
            ->where('ART_TIPO_COMPRA', '!=', 'L')->get();
    }
    protected function stockFormatterForUpdateApproval(EloquentCollection $detalles): array
    {
        $detailsById = $detalles->keyBy('id');

        return $detalles
            ->pluck('pedidoDetalleStocks')
            ->flatten()
            ->map(function ($stock) use ($detailsById) {
                $detalle = $detailsById->get($stock->detalle_id);
                if (!$detalle) {
                    return null;
                }
                return [
                    'stock_id'                 => $stock->id,
                    'pedido_id'                => $stock->pedido_id,
                    'detalle_id'               => $stock->detalle_id,
                    'STO_ART_ARTICULO'         => $detalle->ART_CODIGO,
                    'STO_ART_BODEGA'           => $stock->STO_ART_BODEGA,
                    'STO_ART_STOCK_DISPONIBLE' => $stock->STO_ART_STOCK_DISPONIBLE,
                    'seleccionado'             => $stock->seleccionado,
                ];
            })
            ->filter()
            ->values()
            ->toArray();
    }

    protected function stockFormatterParametersUpdateApproval(array $newDetails): array
    {
        return collect($newDetails)
            ->map(fn($d) => ['articulo' => $d['STO_ART_ARTICULO'], 'bodega' => $d['STO_ART_BODEGA']])
            ->unique()
            ->values()
            ->toArray();
    }

    protected function getStockWarehouseAsyncAndFormatter(array $parmas): array
    {
        $stocksResult = $this->stockService->fetchStockBatchWarehouses($parmas);
        return $this->stockService->transformStockResponse($stocksResult);
    }

    protected function formattedFlatDetailWithStockGeneral(array $flatDetails, array $stocksResult): SupportCollection
    {
        $stockMap = collect($stocksResult)->keyBy(function ($stock) {
            $art    = data_get($stock, 'STO_ART_ARTICULO', '');
            $bodega = data_get($stock, 'STO_ART_BODEGA', '');
            return $art . '|' . $bodega;
        });
        return collect($flatDetails)->map(function ($detail) use ($stockMap) {
            $key = data_get($detail, 'STO_ART_ARTICULO') . '|' . data_get($detail, 'STO_ART_BODEGA');
            $stockDisponible = data_get($stockMap->get($key), 'STO_ART_STOCK_DISPONIBLE', 0);
            return [
                'stock_id'                  => data_get($detail, 'stock_id', null),
                'cotizacion_id'             => data_get($detail, 'cotizacion_id', null),
                'detalle_id'                => data_get($detail, 'detalle_id', null),
                'STO_ART_ARTICULO'          => data_get($detail, 'STO_ART_ARTICULO', null),
                'STO_ART_BODEGA'            => data_get($detail, 'STO_ART_BODEGA', null),
                'STO_ART_STOCK_DISPONIBLE'  => $stockDisponible,
                'seleccionado'              => data_get($detail, 'seleccionado', 0)
            ];
        });
    }

    protected function getAlertWithoutStockArticles(EloquentCollection $detalles, SupportCollection $newFormatStock, ?int $orderId = null, ?int $quoteReservedOriginId = null): ?string
    {
        $allFormattedStock = collect();
        foreach ($detalles as $detail) {
            $stockList = $this->stockService->formatStockDisponibleApprovalCreditOrComercial($newFormatStock, $detail, false, $orderId, $quoteReservedOriginId);
            if (!empty($stockList)) {
                $allFormattedStock = $allFormattedStock->merge($stockList);
            }
        }

        if ($allFormattedStock->isEmpty()) {
            return null;
        }
        $alert = null;
        $groupStock = $allFormattedStock->unique('stock_id')->groupBy(fn($item) => $item['STO_ART_ARTICULO'] . '|' . $item['STO_ART_BODEGA']);
        foreach ($groupStock as $group) {
            $firstItemStock     = $group->first();
            $art                = data_get($firstItemStock, 'STO_ART_ARTICULO', '');
            $bodega             = data_get($firstItemStock, 'STO_ART_BODEGA', '');
            $disponibleReal     = (float) data_get($firstItemStock, 'STO_ART_STOCK_DISPONIBLE', 0);
            $totalRequerido     = $group->sum('seleccionado');
            if ($disponibleReal < $totalRequerido) {
                $alert .= "El artículo <b>{$art}</b> en bodega <b>{$bodega}</b> tiene stock insuficiente.<br>" .
                    "Stock Disponible: <b>{$disponibleReal}</b> <br>" .
                    "Cantidad Requerida (Suma filas): <b>{$totalRequerido}</b><br><hr>";
            }
        }
        return empty($alert) ? null : $alert;
    }

    /*** check aprobacion crediticia * * *    * */
    public function checkoutApprovalCreditOrder(string $condId, float $total, string $ruc): string
    {
        if ($condId == 'CONTADO') {
            return 'no';
        }
        $cupoDisponible = $this->functionService->getClientCheckAvailableQuota($ruc);
        $cupo = data_get($cupoDisponible, 'cupoDisponibleGp', 0);

        if ($total > $cupo) {
            return 'Cupo';
        }

        $documentosVencidos = $this->functionService->getClientDocumentExpired($ruc);
        $pendiente = data_get($documentosVencidos, 'pendienteGpMc', 0);

        if ($pendiente > 0) {
            return 'Documentos Vencidos';
        }

        $documentosSinSoporte = $this->functionService->getClientDocumentWithoutSupport($ruc);
        $saldo = data_get($documentosSinSoporte, 'saldoPendiente', 0);

        if ($saldo > 0) {
            return 'Saldo Pendiente';
        }

        return 'no';
    }


    /** aprobacion crediticia y comercial  */

    /***  FUNCIONES PARA LA APROBACIÓN CREDITICIA DE PEDIDOS */


    public function approveCreditOrder(int $id, GpUsuario $user, ?string $msg = null): GpPedido
    {
        $order = GpPedido::where('id', $id)
            ->where('estado', OrderStatus::ESPERA)
            ->lockForUpdate()->first();
        if (!$order) {
            throw new \RuntimeException("Pedido no encontrado");
        }
        $order->fill([
            'aprobado_crediticio'               => 'yes',
            'aprobador_crediticio_id'           => $user->id,
            'fecha_hora_aprobacion_crediticia'  => Carbon::now()->format('Y-m-d H:i:s'),
            'aprobado_crediticio_mensaje'       => $msg,
        ]);

        $camposModificados = array_keys($order->getDirty());
        $oldOrder = [
            'pedido_anterior' => Arr::only(
                $order->getOriginal(),
                $camposModificados
            ),
        ];
        $order->save();
        $newOrder = [
            'pedido_nuevo' => Arr::only(
                $order->fresh()->toArray(),
                $camposModificados
            ),
        ];
        $this->updateOrderStockOnCreditOrCommercialApproval($order, 'crediticia');

        DB::afterCommit(function () use ($order) {
            $this->invoiceService->checkSendInvoice($order);
        });
        $this->createHistoryRecord(
            $user,
            $order,
            'APPROVAL_CREDIT_ORDER',
            $oldOrder,
            $newOrder
        );
        return $order->refresh();
    }



    protected function cleanDetailOrderStock(GpPedido $pedido): void
    {
        $bodega = $pedido->BOD_BODEGA;
        $detalles = GpPedidoDetalle::where('pedido_id', $pedido->id)->pluck('id')->toArray();
        GpPedidoDetalleStock::whereIn('detalle_id', $detalles)
            ->where('STO_ART_BODEGA', $bodega)->update(['reservado' => 0]);
    }



    /**  FUNCIONES DE APROBACIÓN COMERCIAL DE PEDIDOS Y COTIZACIONES  */

    public function approveComercialOrder(int $id, GpUsuario $user, ?string $msg = null): GpPedido
    {
        $order = GpPedido::where('id', $id)->where('estado', OrderStatus::ESPERA)
            ->lockForUpdate()->first();
        if (!$order) {
            throw new \RuntimeException("Pedido no encontrado");
        }
        $order->fill([
            'aprobado_comercial'                => 'yes',
            'aprobador_comercial_id'            => $user->id,
            'fecha_hora_aprobacion_comercial'   => Carbon::now()->format('Y-m-d H:i:s'),
            'aprobado_comercial_mensaje'        => $msg
        ]);
        $camposModificados = array_keys($order->getDirty());
        $oldOrder = [
            'pedido_anterior' => Arr::only(
                $order->getOriginal(),
                $camposModificados
            ),
        ];

        $order->save();
        $newOrder = [
            'pedido_nuevo' => Arr::only(
                $order->fresh()->toArray(),
                $camposModificados
            ),
        ];
        $this->updateOrderStockOnCreditOrCommercialApproval($order, 'comercial');
        DB::afterCommit(function () use ($order) {
            $orderFresh = GpPedido::find($order->id);
            $this->invoiceService->checkSendInvoice($orderFresh);
        });
        $this->createHistoryRecord(
            $user,
            $order,
            'APPROVAL_COMERCIAL_ORDER',
            $oldOrder,
            $newOrder
        );
        return $order->refresh();
    }




    protected function updateOrderStockOnCreditOrCommercialApproval(GpPedido $order, string $typeApproval): void
    {
        $requiresComercialApproval = $order->requiere_aprobacion_comercial === 'yes';
        $requiresCreditApproval = $order->requiere_aprobacion_crediticia === 'yes';
        $isComercialApproved = $order->aprobado_comercial === 'yes';
        $isCreditApproved = $order->aprobado_crediticio === 'yes';

        // solo requiere aprobacion comercial
        if ($requiresComercialApproval && !$requiresCreditApproval) {
            if ($isComercialApproved && !$isCreditApproved && $typeApproval == 'comercial') {
                $this->updateStockOnOrderApproval($order);
            }
        }

        // solo requiere aprobacion crediticia
        if (!$requiresComercialApproval && $requiresCreditApproval) {
            if (!$isComercialApproved && $isCreditApproved && $typeApproval == 'crediticia') {
                $this->updateStockOnOrderApproval($order);
            }
        }
        // requiere ambas aprobaciones
        if ($requiresComercialApproval && $requiresCreditApproval) {
            if ($isComercialApproved && $isCreditApproved && $typeApproval == 'crediticia') {
                $this->updateStockOnOrderApproval($order);
            }
        }
    }


    protected function updateStockOnOrderApproval(GpPedido $order): void
    {
        $quoteReservedOriginId = $this->approvalFuntions->validateExistsReserve($order->cotizacion_id);
        $detalles = $this->getDetailArticlesWithStock($order->id);
        if ($detalles->isEmpty()) {
            return;
        }
        $flatDetails    = $this->stockFormatterForUpdateApproval($detalles);
        $parmas         = $this->stockFormatterParametersUpdateApproval($flatDetails);
        $stocksResult   = $this->getStockWarehouseAsyncAndFormatter($parmas);
        $newFormatStock = $this->formattedFlatDetailWithStockGeneral($flatDetails, $stocksResult);
        $this->updateOrderDetailStockAndGetStockDisponible($detalles, $newFormatStock, $order->id, $quoteReservedOriginId);
    }








    protected function updateOrderDetailStockAndGetStockDisponible(EloquentCollection $detalles, SupportCollection $newFormatStock, int $orderId, ?int $quoteReservedOriginId = null): void
    {
        $allFormattedStock = collect();
        foreach ($detalles as $detail) {
            $stockList = $this->stockService->formatStockDisponibleApprovalCreditOrComercial($newFormatStock, $detail, true, $orderId, $quoteReservedOriginId);
            if (!empty($stockList)) {
                $allFormattedStock = $allFormattedStock->merge($stockList);
            }
        }
        if ($allFormattedStock->isEmpty()) {
            return;
        }
        foreach ($allFormattedStock as $stock) {
            if (isset($stock['stock_id']) && ($st = GpPedidoDetalleStock::find($stock['stock_id']))) {
                $st->STO_ART_STOCK_DISPONIBLE   = $stock['STO_ART_STOCK_DISPONIBLE'];
                $st->reservado                  = $stock['seleccionado'];
                $st->save();
            }
        }
    }


    /** rechazo de aprobacion comercial de pedido por stock o por forma manual*/
    public function rejectCommercialOrder(int $id, GpUsuario $approver, ?string $alert = null): GpPedido
    {
        $order = GpPedido::where('id', $id)
            ->lockForUpdate()
            ->first();

        if (!$order) {
            throw new \Exception("Pedido no encontrado", 500);
        }
        $isRejectedByStock = !empty($alert);

        $order->estado                 = OrderStatus::RECHAZADO;
        $order->aprobado_comercial     = 'no';
        $order->aprobador_comercial_id = $approver->id;
        $order->fecha_hora_rechazado   = now();
        $order->rechazado              = 1;

        $order->save();
        GpTrasladosVentas::where('pedido_id', $order->id)->delete();
        $this->updateStockOnOrderApproval($order);
        $this->cleanDetailOrderStock($order);
        $notificationTitle = $isRejectedByStock
            ? 'Pedido rechazado por falta de stock'
            : 'Pedido rechazado';

        $notificationMessage = $isRejectedByStock
            ? $alert
            : "El pedido {$order->pedido} ha sido rechazado por el usuario {$approver->USERID}";

        DB::afterCommit(function () use ($order, $notificationTitle, $notificationMessage) {
            $orderUser = GpUsuario::find($order->user_id);

            if ($orderUser) {
                $orderUser->notify(new UserNotification(
                    $notificationTitle,
                    $notificationMessage
                ));
            }
        });
        return $order;
    }


    /** rechazo de aprobacion crediticio por stock o por rechazo manual  */
    public function rejectCreditOrder(int $id, GpUsuario $user, ?string $alert = null): GpPedido
    {
        $order = GpPedido::where('id', $id)
            ->whereNotIn('estado', [
                OrderStatus::ELIMINADO,
                OrderStatus::ANULADA,
            ])
            ->lockForUpdate()
            ->first();

        if (!$order) {
            throw new \Exception("Pedido no encontrado", 500);
        }

        $isRejectedByStock = !empty($alert);

        $order->fill([
            'estado'                       => OrderStatus::RECHAZADO,
            'aprobado_crediticio'          => 'no',
            'aprobador_crediticio_id'      => $user->id,
            'fecha_hora_rechazado'         => now(),
            'rechazado'                    => 1,
        ]);


        $modifiedFields = array_keys($order->getDirty());
        $oldOrder = [
            'pedido_anterior' => Arr::only(
                $order->getOriginal(),
                $modifiedFields
            ),
        ];
        $order->save();
        $newOrder = [
            'pedido_nuevo' => Arr::only(
                $order->fresh()->toArray(),
                $modifiedFields
            ),
        ];
        GpTrasladosVentas::where('pedido_id', $order->id)->delete();
        $this->updateStockOnOrderApproval($order);
        $this->cleanDetailOrderStock($order);

        $this->createHistoryRecord(
            $user,
            $order,
            $isRejectedByStock ? 'DENY_CREDIT_ORDER_STOCK' : 'DENY_CREDIT_ORDER',
            $oldOrder,
            $newOrder
        );

        $notificationTitle = $isRejectedByStock
            ? 'Pedido rechazado por falta de stock'
            : 'Pedido rechazado';

        $notificationMessage = $isRejectedByStock
            ? $alert
            : "El pedido {$order->pedido} ha sido rechazado";

        DB::afterCommit(function () use ($order, $notificationTitle, $notificationMessage) {
            $orderUser = GpUsuario::find($order->user_id);

            if ($orderUser) {
                $orderUser->notify(new UserNotification(
                    $notificationTitle,
                    $notificationMessage
                ));
            }
        });

        return $order->fresh();
    }


    public function getAllOrdersPendingApprovals(string $ptoVta, GpUsuario $user): SupportCollection
    {
        $filteredOrders = collect([]);
        $user->load(['vendedor.jerarquia.relaciones', 'vendedor.zonas']);
        $query  = $this->repository->fetchPendingOrders($ptoVta)->with(['detallepedido', 'gpcotizacion', 'usuario.vendedor.zonas']);
        $orders = $query->get();
        $orders->each(function ($order) {
            $order->users_autorizadores = $this->getPossibleAuthorizersForOrders($order);
        });

        // para los adminis del sistema
        if (in_array($user->perfil_id, [1, 2])) {
            $filteredOrders =  $orders;
        } elseif (in_array($user->perfil_id, [28])) {
            $filteredOrders =  $this->filterOrdersForVicePresidency($orders, $user);
        } elseif ($user->vendedor) {
            $filteredOrders = $this->filterOrdersByTySeller($orders, $user);
        }

        $allRucs = $orders->pluck('CLI_RUC')
            ->merge($orders->pluck('CLI_RUC'))
            ->unique()
            ->toArray();
        $segmentDataByRuc = $this->approvalFuntions->getClientSegments($allRucs);
        return $filteredOrders->map(function ($order) use ($segmentDataByRuc) {
            $ruc                        = $order->CLI_RUC;
            $order->cotizacion          = $order->gpcotizacion->cotizacion;
            $segmentData                = $segmentDataByRuc[$ruc] ?? ['segmento' => null, 'subsegmento' => null];
            $order->segmento            = data_get($segmentData, 'segmento');
            $order->subsegmento         = data_get($segmentData, 'subsegmento');
            return $order->makeHidden(['detallepedido', 'gpcotizacion', 'usuario.vendedor.zonas', 'usuario']);
        });
    }

    protected function filterOrdersForVicePresidency(SupportCollection $orders, GpUsuario $user): SupportCollection
    {
        return $orders->filter(function ($order) use ($user) {
            return in_array($user->id, $order->authorizer_ids_list ?? []);
        })->values();
    }

    protected function filterOrdersByTySeller(SupportCollection $orders, GpUsuario $user): SupportCollection
    {
        return $orders->filter(function ($order) use ($user) {
            return in_array($user->id, $order->authorizer_ids_list ?? []);
        })->values();
    }

    protected function getPossibleAuthorizersForOrders(GpPedido $order): string
    {
        $tempAuthorizers    = [];
        $sellerOrder        = $order->usuario->vendedor ?? null;
        if (!$sellerOrder) {
            return '';
        }
        $hasModifications = $order->detallepedido->contains(function ($detail) {
            $priceChange = $this->approvalFuntions->calculatePriceVariationPercentage($detail->ART_VALOR_ORIGINAL, $detail->ART_VALOR);
            $discount    = $this->approvalFuntions->calculateDiscountChange($detail->descuento_original, $detail->descuento);
            return $priceChange != 0 || $discount != 0;
        });

        $zonasQuote             = $sellerOrder->zonas->pluck('id')->toArray() ?? [];
        $rules = GpReglaAprobacion::with(['usuarioAutorizador.vendedor.jerarquia.relaciones'])
            ->where(function ($q) use ($zonasQuote) {
                $q->whereIn('zona_id', $zonasQuote)
                    ->orWhereHas('usuarioAutorizador', function ($u) {
                        $u->where('perfil_id', 28);
                    });
            })
            ->get();
        $rulesByUser = $rules->groupBy('user_autorizador_id');
        foreach ($rulesByUser as $userId => $ruleUser) {
            $autorizador = $ruleUser->first()->usuarioAutorizador;
            $vendedorAutorizador = $autorizador->vendedor ?? null;
            if (!$hasModifications) {
                $nivelJerarquia = data_get($vendedorAutorizador, 'jerarquia.nivel');
                if ($nivelJerarquia == 2) {
                    continue;
                }
            }
            if ($autorizador->perfil_id !== 28) {
                if (!$vendedorAutorizador || !$vendedorAutorizador->jerarquia) {
                    continue;
                }
                $hijosDelJefe = $vendedorAutorizador->jerarquia->relaciones->pluck('tipo_vendedor_hijo_id')->toArray();
                if (!in_array($sellerOrder->tipo_vendedor_id, $hijosDelJefe)) {
                    continue;
                }
            }
            $puedeAprobar = $order->detallepedido->every(function ($detail) use ($ruleUser) {
                $priceChange = $this->approvalFuntions->calculatePriceVariationPercentage($detail->ART_VALOR_ORIGINAL, $detail->ART_VALOR);
                $discount    = $this->approvalFuntions->calculateDiscountChange($detail->descuento_original, $detail->descuento);

                return $ruleUser->contains(function ($rule) use ($priceChange, $discount) {
                    $priceOk =  ($priceChange >= $rule->descuento_min && $priceChange <= $rule->descuento_max);
                    $discountOk =  ($discount >= $rule->descuento_min && $discount <= $rule->descuento_max);
                    return $priceOk && $discountOk;
                });
            });
            if ($puedeAprobar) {
                $nombre                 = $autorizador->USERID ?? 'Usuario Desconocido';
                $maxDescuentoUsuario    = $ruleUser->max('descuento_max');
                $tempAuthorizers[] = [
                    'id'        => $autorizador->id,
                    'nombre'    => $nombre,
                    'max_desc'  => $maxDescuentoUsuario
                ];
            }
        }
        $sortedAuthorizers = collect($tempAuthorizers)->sortBy('max_desc');
        $order->authorizer_ids_list = $sortedAuthorizers->pluck('id')->toArray();
        return $sortedAuthorizers->pluck('nombre')->implode(', ');
    }


    /** funcion para visualizar todas las cotizaciones pendientes de aprobar (consulta) */
    public function getAllOrdersForGlobalConsultation(string $ptoVta): SupportCollection
    {
        $query = $this->repository->fetchPendingOrders($ptoVta)
            ->with(['detallepedido', 'gpcotizacion', 'usuario.vendedor.zonas']);
        $orders = $query->get();
        $orders->each(function ($order) {
            $order->users_autorizadores = $this->getPossibleAuthorizersForOrders($order);
        });
        $allRucs = $orders->pluck('CLI_RUC')->unique()->toArray();
        $segmentDataByRuc = $this->approvalFuntions->getClientSegments($allRucs);
        return $orders->map(function ($order) use ($segmentDataByRuc) {
            $ruc = $order->CLI_RUC;
            $order->cotizacion  = $order->gpcotizacion->cotizacion ?? null;
            $segmentData        = $segmentDataByRuc[$ruc] ?? ['segmento' => null, 'subsegmento' => null];
            $order->segmento    = data_get($segmentData, 'segmento');
            $order->subsegmento = data_get($segmentData, 'subsegmento');
            return $order->makeHidden(['detallepedido', 'gpcotizacion', 'usuario.vendedor.zonas', 'usuario']);
        });
    }


    /**
     * lista de pedidos pendientes de aprobación crediticia
     */

    public function fetchOrdersPendingCreditApproval(array $filters): LengthAwarePaginator
    {
        $search     = data_get($filters, 'search', '');
        $perpage    = data_get($filters, 'perpage', 10);
        $sortField  = data_get($filters, 'sortField', 'created_at');
        $sortOrder  = strtolower(data_get($filters, 'sortOrder', 'desc')) === 'asc' ? 'asc' : 'desc';
        if (!$sortField || trim($sortField) === '') {
            $sortField = 'created_at';
        }

        $allowedSorts = [
            'id'           => 'pd.id',
            'pedido'       => 'pd.pedido',
            'ruc'          => 'pd.CLI_RUC',
            'razon_social' => 'pd.CLI_RAZON_SOCIAL',
            'USERID'       => 'pd.USERID',
            'agencia'      => 'pd.PTO_VTA_DESCRIPCION',
            'motivo'       => 'pd.motivo_crediticio',
            'created_at'   => 'pd.created_at',
            'total'        => 'pd.total',
        ];

        $orderColumn = data_get($allowedSorts, $sortField, 'pd.created_at');
        $query = GpPedido::query()
            ->select([
                'pd.id',
                'pd.pedido',
                'pd.CLI_RAZON_SOCIAL AS razon_social',
                'pd.CLI_RUC as ruc',
                'pd.USERID',
                'pd.PTO_VTA_DESCRIPCION as agencia',
                'pd.motivo_crediticio as motivo',
                'pd.total',
                'pd.created_at',
                'pd.requiere_aprobacion_comercial',
                'pd.aprobado_comercial',
                'pd.aprobador_comercial_id',
                'pd.fecha_hora_aprobacion_comercial',
                'pd.estado'
            ])
            ->from('gp_pedidos as pd')
            ->where('pd.requiere_aprobacion_crediticia', 'yes')
            ->where('pd.aprobado_crediticio', 'no')
            ->whereNotIn('estado', [
                OrderStatus::ELIMINADO,
                OrderStatus::ANULADA,
                OrderStatus::RECHAZADO,
                OrderStatus::FACTURADO,
                OrderStatus::ENVIANDO,
                OrderStatus::PROCESADO,
            ]);
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('pd.USERID', 'like', "%{$search}%")
                    ->orWhere('pd.pedido', 'like', "%{$search}%")
                    ->orWhere('pd.CLI_RUC', 'like', "%{$search}%")
                    ->orWhere('pd.CLI_RAZON_SOCIAL', 'like', "%{$search}%")
                    ->orWhere('pd.PTO_VTA_DESCRIPCION', 'like', "%{$search}%");
            });
        }

        return $query->orderBy($orderColumn, $sortOrder)
            ->paginate($perpage)->through(function ($row) {
                $res                = $this->getSegmentClient($row->ruc);
                $row->segmento      = data_get($res, 'segmento', null);
                $row->subsegmento   = data_get($res, 'subsegmento', null);
                $row->pendiente_aprobacion_comercial = $this->approvalFuntions->validatePendingCommercialApproval($row);
                return $row;
            });
    }

    public function getSegmentClient(string $ruc): array
    {
        $response = $this->api->fetchClientSegment([
            'ruc' => $ruc,
        ]);
        $data = data_get($response, 'data', []);
        return is_array($data) ? $data : (array) $data;
    }





    public function accountStatusClient(int $id): array
    {
        $order = GpPedido::findOrFail($id);
        return [
            'pedido'                            => $order,
            'cupoDisponible'                    => $this->functionService->getClientCheckAvailableQuota($order->CLI_RUC),
            'documentosVencidos'                => $this->functionService->getListClientDocumentExpired($order->CLI_RUC),
            'documentosSinSoporte'              => $this->functionService->getListClientDocumentWithoutSupport($order->CLI_RUC),
            'pendiente_aprobacion_comercial'    => $this->approvalFuntions->validatePendingCommercialApproval($order),
        ];
    }


    public function denyOrderBeenWaiting(int $id, GpUsuario $user): GpPedido
    {
        return DB::transaction(function () use ($id, $user) {
            $order = GpPedido::where('id', $id)
                ->lockForUpdate()
                ->first();
            if (!$order) {
                throw new \RuntimeException("No se encontro el pedido");
            }
            $order->fill([
                'estado'                => OrderStatus::RECHAZADO,
                'user_rechazo_id'       => $user->id,
                'fecha_hora_rechazado'  => Carbon::now()->format('Y-m-d H:i:s'),
                'rechazado'             => 1,
            ]);
            $camposModificados = array_keys($order->getDirty());
            $oldOrder = [
                'pedido_anterior' => Arr::only(
                    $order->getOriginal(),
                    $camposModificados
                ),
            ];
            $order->save();
            $newOrder = [
                'pedido_nuevo' => Arr::only(
                    $order->fresh()->toArray(),
                    $camposModificados
                ),
            ];
            $this->createHistoryRecord(
                $user,
                $order,
                'DENY_ORDER',
                $oldOrder,
                $newOrder
            );
            return $order->refresh();
        });
    }
}
