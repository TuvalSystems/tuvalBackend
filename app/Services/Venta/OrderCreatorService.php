<?php

namespace App\Services\Venta;

use App\Enums\Ventas\OrderOrigenEnum;
use App\Enums\Ventas\OrderStatus;
use App\Models\GpCotizacion;
use App\Models\GpPedido;
use App\Models\GpPedidoImagen;
use App\Models\GpPuntoVenta;
use App\Models\Reservas\GpCotizacionReservaLog;
use App\Services\Aprobaciones\Contracts\ApprovalOrderServiceInterface;
use App\Services\Builders\Venta\Contracts\OrderBuilderInterface;
use App\Services\Funciones\Contracts\InvoiceServiceInterface;
use App\Services\Reposiciones\Contracts\StockWarehouseServiceInterface;
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
    public function createOrder(array $data, $user, array $existingImages = [], array $newImages = [], array $pfdsExistings = [], array $pfdsNew = []): array
    {
        $pedido = DB::transaction(function () use ($data, $user,  $existingImages, $newImages, $pfdsExistings, $pfdsNew) {
            $quotationId = data_get($data, 'cotizacion_id');
            $quoteBlock = GpCotizacion::where('id', $quotationId)->lockForUpdate()->first();
            if ($quoteBlock && ($quoteBlock->estado === 'procesado' || $quoteBlock->facturado == 1)) {
                $pedidoExistente = GpPedido::where('cotizacion_id', $quoteBlock->id)
                    ->where('estado', '!=', OrderStatus::ANULADA) // Importante: ignorar anulados
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
            $this->syncPdfs($pedido, $pfdsExistings, $pfdsNew);
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
            $artCodigo          = trim((string) data_get($item, 'ART_CODIGO'));
            $isTypeL           = trim((string) data_get($item, 'ART_TIPO_COMPRA')) === 'L';
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


    protected function storeDetailProviders($providers, $detail, $newOrder)
    {
        foreach ($providers as $provider) {
            $recibido = isset($provider['recibido']) ? $provider['recibido'] : 'no';
            $newProvider = $this->builder->formPayloadOrderDetailProvider($provider, $newOrder->id, $detail->id, $recibido);
            $newProvider->save();
        }
    }


    protected function storeDetailStock($stock, $detail, $newOrder)
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
            $this->copyPdfsFromUrls($order, $urlsToCopy);
        }

        //4.- almacenar nuevos archivos
        if (!empty($newFiles)) {
            $this->storePdfsQuotation($order, $newFiles);
        }
    }


    protected function copyPdfsFromUrls(GpPedido $order, array $urls)
    {
        foreach ($urls as $url) {
            $sourceMedia = $this->findMediaByUrl($url, 'documentos_pdf');
            if (!$sourceMedia) {
                continue;
            }

            $alreadyExists = $order
                ->getMedia('documentos_pdf')
                ->contains(function (Media $existing) use ($sourceMedia) {
                    return $existing->file_name === $sourceMedia->file_name && (int) $existing->size === (int) $sourceMedia->size;
                });

            if ($alreadyExists) {
                continue;
            }

            $sourceMedia->copy($order, 'documentos_pdf', 'public');
        }
    }

    protected function findMediaByUrl($url, string $collectionName = 'documentos_pdf'): ?Media
    {
        $path = parse_url($url, PHP_URL_PATH);
        $fileName = basename($path ?? '');

        if (!$fileName) {
            return null;
        }

        return Media::query()
            ->where('collection_name', $collectionName)
            ->where('file_name', $fileName)
            ->get()
            ->first(function (Media $media) use ($url) {
                return $media->getUrl() === $url || $media->getFullUrl() === $url;
            });
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
