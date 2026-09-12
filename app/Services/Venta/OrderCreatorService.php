<?php

namespace App\Services\Venta;

use App\Enums\Ventas\OrderOrigenEnum;
use App\Enums\Ventas\OrderStatus;
use App\Models\GpCotizacion;
use App\Models\GpPedido;
use App\Models\GpPedidoDetalle;
use App\Models\GpPedidoImagen;
use App\Models\GpPuntoVenta;
use App\Models\GpUsuario;
use App\Models\Reservas\GpCotizacionReservaLog;
use App\Services\Aprobaciones\Contracts\ApprovalOrderServiceInterface;
use App\Services\Builders\Venta\Contracts\OrderBuilderInterface;
use App\Services\Funciones\Contracts\InvoiceServiceInterface;
use App\Services\Inventario\Stock\Contracts\StockWarehouseServiceInterface;
use App\Services\Venta\Contracts\OrderCreatorServiceInterface;
use App\Services\Venta\Utils\Contracts\OrderAndQuotationServiceInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class OrderCreatorService implements OrderCreatorServiceInterface
{

    public function __construct(
        protected readonly OrderBuilderInterface $builder,
        protected readonly ApprovalOrderServiceInterface $approval,
        protected readonly StockWarehouseServiceInterface $stockService,
        protected readonly OrderAndQuotationServiceInterface $orderAndQuotationService,
        protected readonly InvoiceServiceInterface $invoiceService,
    ) {
        // Inyecta dependencias si es necesario
    }
    public function createOrder(array $data, GpUsuario $user, array $existingImages = [], array $newImages = [], array $pfdsExistings = [], array $pfdsNew = []): array
    {
        $pedido = DB::transaction(function () use ($data, $user,  $existingImages, $newImages, $pfdsExistings, $pfdsNew) {
            $quotationId            = data_get($data, 'cotizacion_id');
            if (!$quotationId) {
                throw new \DomainException('La cotizacion es obligatoria para crear el pedido.');
            }

            $direccionFacturacion   = (int) data_get($data, 'direccion_facturacion_id');
            $direccionDespacho      = (int)data_get($data, 'direccion_entrega_id');
            if ($direccionFacturacion) {
                $this->orderAndQuotationService->validateActiveSucursal($direccionFacturacion, 'facturación');
            }

            if ($direccionDespacho) {
                $this->orderAndQuotationService->validateActiveSucursal($direccionDespacho, 'despacho/entrega');
            }
            $quoteBlock = GpCotizacion::where('id', $quotationId)->lockForUpdate()->first();
            if (!$quoteBlock) {
                throw new \DomainException('La cotizacion no existe o no esta disponible.');
            }

            $pedidoExistente = GpPedido::where('cotizacion_id', $quoteBlock->id)
                ->whereNotIn('estado', [
                    OrderStatus::ANULADA->value,
                    OrderStatus::ELIMINADO->value,
                    OrderStatus::RECHAZADO->value,
                ])
                ->oldest('id')
                ->lockForUpdate()
                ->first();

            if ($pedidoExistente) {
                Log::warning("Intento duplicado de creacion de pedido para cotizacion {$quotationId}. Se retorna pedido existente {$pedidoExistente->pedido}.");
                return $pedidoExistente;
            }

            if ($quoteBlock->estado === 'procesado' || $quoteBlock->facturado == 1) {
                Log::warning("Inconsistencia detectada: Cotización {$quotationId} estaba marcada como procesada pero sin pedido. Creando pedido ahora...");
            }

            $pedido = $this->prepareOrderHeader($data, $user);
            if (!empty($data['items'])) {
                $this->saveOrderDetails($pedido, $data['items'] ?? []);
            }
            $this->syncImages($pedido, $existingImages, $newImages);
            $this->syncPdfs($pedido, $pfdsExistings, $pfdsNew);
            if ($pedido->exists && $pedido->cotizacion_id) {
                $quoteBlock->estado = 'procesado';
                $quoteBlock->facturado = 1;
                $quoteBlock->save();
            }

            return $pedido;
        });

        $invoiceError = null;
        try {
            $this->invoiceService->checkSendInvoice($pedido);
        } catch (\Exception $e) {
            Log::error("Error facturando pedido {$pedido->id}: " . $e->getMessage());
            $invoiceError = $e->getMessage();
        }
        return [
            'pedido' => $pedido->refresh(),
            'invoice_error' => $invoiceError
        ];
    }



    protected function prepareOrderHeader(array $data, GpUsuario $user): GpPedido
    {
        $puntoVenta                     = GpPuntoVenta::where('PTO_VTA_ID', $data['punto_venta_id'])->first();
        $approvalCommercial             = $this->approval->approveOrder($data);
        $motivoLimpio                   = $this->sanitizeString($approvalCommercial['motivo'] ?? '');
        $approvalCredit                 = $this->prepareApprovalCreditOrder($data);
        $requieresApproval              = $approvalCredit != 'no';
        $motivoCrediticio               = $requieresApproval ? $approvalCredit : null;
        $requiereAprobacionCrediticia   = $requieresApproval ? 'yes' : 'no';
        $despacho                       = data_get($data, 'tipo_entrega', 'retiro');
        $tipoDespacho                   = ($despacho == 'retiro') ? 'Retiro en bodega' : 'Despacho en ruta';
        $arStrings = [
            'motivo_comercial'                  => $motivoLimpio,
            'requiere_aprobacion_crediticia'    => $requiereAprobacionCrediticia,
            'motivo_crediticio'                 => $motivoCrediticio,
            'tipo_despacho'                     => $tipoDespacho,
            'origen'                            => data_get($data, 'origen'),
            'facturacion_reserva'               => null
        ];
        $newOrder = $this->builder->formPayloadOrderV2($data, $user, $puntoVenta,  $approvalCommercial, $arStrings);
        $newOrder->save();
        return $newOrder;
    }


    protected function prepareApprovalCreditOrder(array $data)
    {
        $total = data_get($data, 'total', 0);
        $condicionPago = data_get($data, 'condicion_pago_id', 'CONTADO');
        $rucCliente = data_get($data, 'ruc', '');
        return $this->approval->checkoutApprovalCreditOrder($condicionPago, $total, $rucCliente);
    }



    protected function sanitizeString(string $text): string
    {
        if (empty($text)) {
            return '';
        }
        $limpio = iconv('UTF-8', 'UTF-8//IGNORE', $text);
        if (!$limpio) {
            $encoding = mb_detect_encoding($text, mb_detect_order(), true);
            $limpio = mb_convert_encoding($text, 'UTF-8', $encoding ?: 'ISO-8859-1');
        }
        return $limpio;
    }



    protected function saveOrderDetails(GpPedido $order, array $items)
    {
        $usedItemLExcluded = [];

        $this->validateStockTotalsForOrderItems($order, $items);

        foreach ($items  as $key => $item) {
            $artCodigo          = trim((string) data_get($item, 'ART_CODIGO'));
            $isTypeL            = trim((string) data_get($item, 'ART_TIPO_COMPRA')) === 'L';
            $selectedArtCodigo  = $artCodigo;
            if ($isTypeL) {
                $hasStock           = $this->stockService->checkValidateDisponibleItemL($artCodigo);
                $alreadyUsed        = in_array($artCodigo, $usedItemLExcluded, true);
                if (!$hasStock || $alreadyUsed) {
                    $replacementCodeL   = $this->stockService->getFirstAvailablePurchaseArticle($item, $usedItemLExcluded);
                    if (!$replacementCodeL) {
                        throw new \DomainException("No se encontró un artículo tipo L disponible para el código {$artCodigo}");
                    }
                    $item['ART_CODIGO'] = trim((string) $replacementCodeL);
                    $selectedArtCodigo  = $replacementCodeL;
                }

                $usedItemLExcluded[] = $selectedArtCodigo;
                $usedItemLExcluded = array_values(array_unique($usedItemLExcluded));
            }
            $detail = $this->builder->formPayloadOrderDetail($order->id, $item, $key);
            $detail->save();

            if ($item['proveedores']) {
                $this->storeDetailProviders($item['proveedores'], $detail, $order);
            }
            if ($item['stock']) {
                $this->storeDetailStock($item['stock'], $detail, $order);
            }

            if ($isTypeL) {
                // si te daba deadlock aquí, muévelo más abajo (ver comentario)
                $this->stockService->markArticleUnavailable($selectedArtCodigo);
            }
        }
    }


    protected function storeDetailProviders(array $providers, GpPedidoDetalle $detail, GpPedido $newOrder)
    {
        foreach ($providers as $provider) {
            $recibido = isset($provider['recibido']) ? $provider['recibido'] : 'no';
            $newProvider = $this->builder->formPayloadOrderDetailProvider($provider, $newOrder->id, $detail->id, $recibido);
            $newProvider->save();
        }
    }


    protected function storeDetailStock(array $stock, GpPedidoDetalle $detail, GpPedido $newOrder)
    {
        $requiereAprobacion = $newOrder->requiere_aprobacion_crediticia === 'yes' || $newOrder->requiere_aprobacion_comercial === 'yes';
        $quoteReservedId = $this->getReservedOriginQuote($newOrder->cotizacion_id);


        foreach ($stock as $item) {
            $seleccionado   = data_get($item, 'seleccionado', 0);
            $disponible     = data_get($item, 'STO_ART_STOCK_DISPONIBLE', 0);
            $bodegaItem     = data_get($item, 'STO_ART_BODEGA', '');


            $stockValidado = $disponible;

            if ($seleccionado > 0) {
                $newFormatStock = $this->orderAndQuotationService->getAndValidateOrderDetailStock($detail, $item, $newOrder->id, $quoteReservedId);
                if (!$newFormatStock) {
                    throw new \DomainException("No se pudo obtener el stock del artículo {$detail->ART_CODIGO} en la bodega {$bodegaItem}");
                }
                $stockValidado = $newFormatStock['STO_ART_STOCK_DISPONIBLE'];
                if ($stockValidado < $seleccionado) {
                    throw new \DomainException("El stock disponible del artículo {$detail->ART_CODIGO} en la bodega {$bodegaItem} es insuficiente para la cantidad seleccionada.");
                }
            }
            $reservado = $requiereAprobacion ? 0 : $seleccionado;
            $newStock = $this->builder->formPayloadOrderDetailStock($item, $newOrder->id, $detail->id, $stockValidado, $reservado);
            $newStock->save();
        }
    }

    protected function validateStockTotalsForOrderItems(GpPedido $order, array $items): void
    {
        $stockPairs = $this->stockPairsFromOrderItems($items);

        if (empty($stockPairs)) {
            return;
        }

        $this->stockService->lockStockWarehousePairs($stockPairs);

        $quoteReservedId = $this->getReservedOriginQuote($order->cotizacion_id);
        $stocksResult = $this->stockService->fetchStockBatchWarehouses($stockPairs);
        $stockMap = collect($this->stockService->transformStockResponse($stocksResult))
            ->keyBy(fn($stock) => data_get($stock, 'STO_ART_ARTICULO') . '|' . data_get($stock, 'STO_ART_BODEGA'));

        $groups = collect($items)
            ->filter(fn($item) => !in_array(data_get($item, 'ART_TIPO_COMPRA'), ['L', 'SERV'], true))
            ->flatMap(function ($item) {
                return collect(data_get($item, 'stock', []))
                    ->filter(fn($stock) => (float) data_get($stock, 'seleccionado', 0) > 0)
                    ->map(fn($stock) => [
                        'articulo'     => trim((string) data_get($item, 'ART_CODIGO')),
                        'bodega'       => trim((string) data_get($stock, 'STO_ART_BODEGA')),
                        'seleccionado' => (float) data_get($stock, 'seleccionado', 0),
                        'decimales'    => (int) data_get($item, 'ART_DECIMAL_CANTIDAD', 0),
                    ]);
            })
            ->groupBy(fn($item) => $item['articulo'] . '|' . $item['bodega']);

        foreach ($groups as $group) {
            $first = $group->first();
            $articulo = $first['articulo'];
            $bodega = $first['bodega'];
            $decimales = $first['decimales'];
            $stockBase = (float) data_get($stockMap->get($articulo . '|' . $bodega), 'STO_ART_STOCK_DISPONIBLE', 0);
            $stockBase = $decimales === 0 ? (int) $stockBase : round($stockBase, $decimales);
            $reservas = $this->stockService->getReservedStockByWarehouses($articulo, [$bodega], $order->id, true, $quoteReservedId);
            $disponible = max(0, $stockBase - (float) data_get($reservas, $bodega, 0));
            $disponible = $decimales === 0 ? (int) $disponible : round($disponible, $decimales);
            $requerido = $group->sum('seleccionado');

            if ($disponible < $requerido) {
                throw new \DomainException(
                    "El stock disponible del articulo {$articulo} en la bodega {$bodega} es insuficiente. " .
                        "Disponible: {$disponible}, requerido: {$requerido}."
                );
            }
        }
    }

    protected function stockPairsFromOrderItems(array $items): array
    {
        return collect($items)
            ->filter(fn($item) => !in_array(data_get($item, 'ART_TIPO_COMPRA'), ['L', 'SERV'], true))
            ->flatMap(function ($item) {
                return collect(data_get($item, 'stock', []))
                    ->filter(fn($stock) => (float) data_get($stock, 'seleccionado', 0) > 0)
                    ->map(fn($stock) => [
                        'articulo' => trim((string) data_get($item, 'ART_CODIGO')),
                        'bodega'   => trim((string) data_get($stock, 'STO_ART_BODEGA')),
                    ]);
            })
            ->filter(fn($stock) => $stock['articulo'] !== '' && $stock['bodega'] !== '')
            ->unique(fn($stock) => $stock['articulo'] . '|' . $stock['bodega'])
            ->sortBy(fn($stock) => $stock['articulo'] . '|' . $stock['bodega'])
            ->values()
            ->all();
    }

    protected function getReservedOriginQuote(?int $quoteId): ?int
    {
        if (!$quoteId) {
            return null;
        }
        $esReserva = GpCotizacion::where('id', $quoteId)
            ->where('origen', OrderOrigenEnum::RESERVA->value)
            ->exists();
        if (!$esReserva) {
            return null; // Si no es reserva, salimos inmediatamente
        }
        return GpCotizacionReservaLog::where('cotizacion_destino_id', $quoteId)
            ->value('cotizacion_origen_id');
    }




    protected function syncImages(GpPedido $pedido, array $keepUrls, array $newFiles)
    {
        $dbImages = GpPedidoImagen::where('pedido_id', $pedido->id)->get();
        $urlsRegistradas = [];

        foreach ($dbImages as $dbImg) {
            $fullUrl = asset('storage/' . $dbImg->ruta);
            $urlsRegistradas[] = $fullUrl;
            if (!in_array($fullUrl, $keepUrls)) {
                if (Storage::disk('public')->exists($dbImg->ruta)) {
                    Storage::disk('public')->delete($dbImg->ruta);
                }
                $dbImg->delete();
            }
        }

        // logica para clonar para volver a pedir
        $urlsParaCopiar = array_diff($keepUrls, $urlsRegistradas);
        if (!empty($urlsParaCopiar)) {
            $this->orderAndQuotationService->copyImagesFromUrls($pedido, $urlsParaCopiar);
        }

        if (!empty($newFiles)) {
            $this->storeImagesQuotation($pedido, $newFiles);
        }
    }

    protected function storeImagesQuotation(GpPedido $pedido, array $files)
    {
        Log::info("STORE IMAGES: Iniciando proceso. Cantidad de archivos: " . count($files));
        foreach ($files as $file) {
            // Validamos que sea un archivo real antes de procesar
            if (!$file instanceof \Illuminate\Http\UploadedFile) {
                continue;
            }

            $cleanName = str_replace(' ', '_', $file->getClientOriginalName());
            $uniqueName = time() . '_' . $cleanName;

            $path = $file->storeAs(
                "pedidos/operaciones_img/{$pedido->id}",
                $uniqueName,
                'public'
            );

            GpPedidoImagen::create([
                'pedido_id'         => $pedido->id,
                'ruta'              => $path, // Guardamos ruta relativa
                'nombre_original'   => $file->getClientOriginalName(),
                'tipo_mime'         => $file->getMimeType(),
                'tamano'            => $file->getSize()
            ]);
        }
    }

    protected function syncPdfs(GpPedido $order, array $keepUrls, array $newFiles)
    {
        $mediaItems = $order->getMedia('documentos_pdf');

        // 1.- identificar que PDFS actuales deben conservarse
        $mediaToKeeps = $mediaItems->filter(function (Media $media) use ($keepUrls) {
            return in_array($media->getUrl(), $keepUrls) || in_array($media->getFullUrl(), $keepUrls, true);
        });

        //2.- eliminar del modelo lo que no este en keepUrls
        $order->clearMediaCollectionExcept('documentos_pdf', $mediaToKeeps);

        //3 .- detectatr cuales url son externas a esta cotizacion y deben copiarse
        $registerUrls = $mediaItems
            ->flatMap(fn(Media $media) => [$media->getUrl(), $media->getFullUrl()])
            ->unique()
            ->values()
            ->all();

        $urlsToCopy = array_values(array_diff($keepUrls, $registerUrls));
        if (!empty($urlsToCopy)) {
            $this->orderAndQuotationService->copyPdfsFromUrls($order, $urlsToCopy);
        }

        //4.- almacenar nuevos archivos
        if (!empty($newFiles)) {
            $this->storePdfsQuotation($order, $newFiles);
        }
    }

    protected function storePdfsQuotation(GpPedido $order, array $files)
    {

        foreach ($files as  $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }
            $originalName   = $file->getClientOriginalName();
            $cleanName      = (string) Str::of($originalName)->replace(' ', '_')->ascii();
            $uniqueName     = time() . '_' . $cleanName;

            $order
                ->addMedia($file)
                ->usingName(pathinfo($originalName, PATHINFO_FILENAME))
                ->usingFileName($uniqueName)
                ->withCustomProperties([
                    'original_name' => $originalName
                ])
                ->toMediaCollection('documentos_pdf', 'public');
        }
    }
}
