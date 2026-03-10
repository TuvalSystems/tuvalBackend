<?php

namespace App\Services\Venta;

use App\Enums\Ventas\OrderOrigenEnum;
use App\Models\GpCotizacion;
use App\Models\GpPedido;
use App\Models\GpPedidoImagen;
use App\Models\GpPuntoVenta;
use App\Models\reservas\GpCotizacionReservaLog;
use App\Services\Aprobaciones\Contracts\ApprovalOrderServiceInterface;
use App\Services\Builders\Venta\Contracts\OrderBuilderInterface;
use App\Services\Funciones\Contracts\InvoiceServiceInterface;
use App\Services\Reposiciones\Contracts\StockWarehouseServiceInterface;
use App\Services\Venta\Contracts\OrderCreatorServiceInterface;
use App\Services\Venta\Utils\Contracts\OrderAndQuotationServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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
    public function createOrder(array $data, $user, array $existingImages = [], array $newImages = []): array
    {
        $pedido = DB::transaction(function () use ($data, $user,  $existingImages, $newImages) {
            $quotationId = data_get($data, 'cotizacion_id');
            $quoteBlock = GpCotizacion::where('id', $quotationId)->lockForUpdate()->first();
            if ($quoteBlock && ($quoteBlock->estado === 'procesado' || $quoteBlock->facturado == 1)) {
                $pedidoExistente = GpPedido::where('cotizacion_id', $quoteBlock->id)
                    ->where('estado', '!=', 'anulada') // Importante: ignorar anulados
                    ->first();
                if ($pedidoExistente) {
                    return $pedidoExistente;
                }
                Log::warning("Inconsistencia detectada: Cotización {$quotationId} estaba marcada como procesada pero sin pedido. Creando pedido ahora...");
            }

            $pedido = $this->prepareOrderHeader($data, $user);
            if (!empty($data['items'])) {
                $this->saveOrderDetails($pedido, $data['items'] ?? []);
            }
            $this->syncImages($pedido, $existingImages, $newImages);
            if ($pedido->exists && $pedido->cotizacion_id) {
                // Actualizamos directamente a través de la relación del pedido
                $pedido->gpcotizacion()->update([
                    'estado' => 'procesado',
                    'facturado' => 1
                ]);
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



    protected function prepareOrderHeader(array $data, $user): GpPedido
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

        foreach ($items  as $key => $item) {
            $artCodigo = data_get($item, 'ART_CODIGO');
            $isTypeL = data_get($item, 'ART_TIPO_COMPRA') === 'L';
            $hasStock = $this->stockService->checkValidateDisponibleItemL($artCodigo);

            if ($isTypeL && !$hasStock) {
                $replacementCodeL = $this->stockService->getFirstAvailablePurchaseArticle($item, $usedItemLExcluded);
                if (!$replacementCodeL) {
                    throw new \DomainException("No se encontró un artículo tipo L disponible para el código {$artCodigo}");
                }

                $item['ART_CODIGO'] = $replacementCodeL;
                $usedItemLExcluded[] = $replacementCodeL;
            }
            $detail = $this->builder->formPayloadOrderDetail($order->id, $item, $key);
            $detail->save();

            if ($item['proveedores']) {
                $this->storeDetailProviders($item['proveedores'], $detail, $order);
            }
            if ($item['stock']) {
                $this->storeDetailStock($item['stock'], $detail, $order, $detail->ART_DECIMAL_CANTIDAD);
            }

            if ($isTypeL) {
                // si te daba deadlock aquí, muévelo más abajo (ver comentario)
                $this->stockService->markArticleUnavailable($artCodigo);
            }
        }
    }


    protected function storeDetailProviders($providers, $detail, $newOrder)
    {
        foreach ($providers as $provider) {
            $recibido = isset($provider['recibido']) ? $provider['recibido'] : 'no';
            $newProvider = $this->builder->formPayloadOrderDetailProvider($provider, $newOrder->id, $detail->id, $recibido);
            $newProvider->save();
        }
    }


    protected function storeDetailStock($stock, $detail, $newOrder, int $decimalQty)
    {
        $requiereAprobacion = $newOrder->requiere_aprobacion_crediticia === 'yes' || $newOrder->requiere_aprobacion_comercial === 'yes';
        $quoteReservedId = $this->getReservedOriginQuote($newOrder->cotizacion_id);


        foreach ($stock as $item) {
            $seleccionado   = round(data_get($item, 'seleccionado', 0), $decimalQty);
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
            $newStock = $this->builder->formPayloadOrderDetailStock($item, $newOrder->id, $detail->id, $stockValidado, $reservado, $decimalQty);
            $newStock->save();
        }
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
            $this->copyImagesFromUrls($pedido, $urlsParaCopiar);
        }

        if (!empty($newFiles)) {
            $this->storeImagesQuotation($pedido, $newFiles);
        }
    }

    protected function copyImagesFromUrls(GpPedido $pedido, array $urls)
    {
        $baseUrl = asset('storage') . '/';
        $disk = Storage::disk('public');
        foreach ($urls as $url) {
            $relativePath = str_replace($baseUrl, '', $url);
            if (Storage::disk('public')->exists($relativePath)) {
                $fileName = basename($relativePath);
                $newPath = "pedidos/operaciones_img/{$pedido->id}/{$fileName}";
                if (!$disk->exists($newPath)) {
                    $disk->copy($relativePath, $newPath);

                    GpPedidoImagen::create([
                        'pedido_id'       => $pedido->id,
                        'ruta'            => $newPath,
                        'nombre_original' => $fileName,
                        'tipo_mime'       => $this->getMimeTypeSafe($disk, $newPath),
                        'tamano'          => $disk->size($newPath)
                    ]);
                }
            }
        }
    }

    protected function getMimeTypeSafe($disk, $path)
    {
        try {
            return $disk->mimeType($path);
        } catch (\Exception $e) {
            // Fallback simple por extensión si el driver falla
            return mime_content_type(storage_path('app/public/' . $path)) ?: 'application/octet-stream';
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
}
