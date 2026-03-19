<?php

namespace App\Services\Venta\Utils;

use App\Models\GpRegistroCopia;
use App\Services\Api\ApiGpService;
use App\Models\GpCotizacionDetalle;
use App\Models\GpCotizacionDetalleProveedores;
use App\Models\GpCotizacionDetalleStock;
use App\Models\GpCotizacionImagen;
use App\Models\GpPedido;
use App\Models\GpPedidoDetalle;
use App\Models\GpPedidoDetalleStock;
use App\Models\GpPedidoImagen;
use App\Services\Funciones\Contracts\FunctionsServiceInterface;
use App\Services\Reposiciones\Contracts\StockWarehouseServiceInterface;
use App\Services\Venta\Utils\Contracts\OrderAndQuotationServiceInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class OrderAndQuotationService implements OrderAndQuotationServiceInterface
{
    public function __construct(
        protected readonly ApiGpService $apiGpService,
        protected readonly FunctionsServiceInterface $functionService,
        protected readonly StockWarehouseServiceInterface $stockWarehouseService
    ) {}



    public function getDetailQuotationOrOrder($data, $cliente, $type, $encera = false, $copy = false)
    {

        $warehouse = $this->functionService->getBodegas($data->PTO_VTA_ID, 1);
        $detalles = $this->getQuotationDetailWithSuppliersType($data->id, $copy, $type, $warehouse);
        $this->resetQuotationDiscount($detalles, $cliente, $encera, $data->BOD_BODEGA);
        if ($encera) {
            $codesApi = $detalles->reject(function ($item) {
                return $item->ART_TIPO_COMPRA ===  'L';
            })->pluck('ART_CODIGO')->implode(',') . ',';

            $groupPrices = $this->getPriceListByItem($codesApi);

            $detalles->each(function ($item) use ($cliente, $groupPrices) {
                if ($item->ART_TIPO_COMPRA != 'L') {
                    $myPrice = $groupPrices->get($item->ART_CODIGO, collect());

                    $precioEncontrado = $myPrice->firstWhere('idLista', $cliente->CLI_NIVEL_PRECIO);
                    $valor = $precioEncontrado ? data_get($precioEncontrado, 'valor', 0) : 0;
                    $item->ART_VALOR = $valor;
                    $item->ART_VALOR_ORIGINAL = $valor;
                }
                return $item;
            });
        }
        return $detalles;
    }

    public function getQuotationDetailWithSuppliersType($quotationId, $copy, $type, $warehouse)
    {
        return $type == 'cotizacion' ? $this->getQuotationDetailWithSuppliersTypeQuotation($quotationId, $copy, $warehouse)
            : $this->getQuotationDetailWithSuppliersTypeOrder($quotationId, $copy, $warehouse);
    }



    /** cotizacion */
    public function getQuotationDetailWithSuppliersTypeQuotation($quotationId, $copy, $warehouse)
    {
        $details = GpCotizacionDetalle::where('cotizacion_id', $quotationId)->get();
        $proveedores = GpCotizacionDetalleProveedores::where('cotizacion_id', $quotationId)->whereIn('detalle_id', $details->pluck('id'))->get()
            ->groupBy('detalle_id')->toArray();
        $details->each(function ($item) use ($proveedores, $copy, $warehouse, $quotationId) {
            $item->proveedores = $copy ? collect() : collect($proveedores[$item->id] ?? [])
                ->map(fn($prov) => (object) $prov);
            $item->stock = $this->getFormattedStocksQuotation($quotationId, $item->id, 'cotizacion');
        });
        return $details;
    }
    public function getStocksRequestQuotation($quotationId, $detailId, $typeDetailStock)
    {
        if ($typeDetailStock == 'pedido') {
            return GpPedidoDetalleStock::where('pedido_id', $quotationId)->where('detalle_id', $detailId)->get()->toArray();
        }
        return GpCotizacionDetalleStock::where('cotizacion_id', $quotationId)->where('detalle_id', $detailId)->get()->toArray();
    }

    /**pedido */
    public function getQuotationDetailWithSuppliersTypeOrder($quotationId, $copy, $warehouse)
    {
        $details = GpPedidoDetalle::where('pedido_id', $quotationId)->get();
        $details->each(function ($item) use ($warehouse, $quotationId, $copy) {
            $item->proveedores = collect();
            $item->stock = $this->getFormattedStocksQuotation($quotationId, $item->id, 'pedido');
        });
        return $details;
    }


    /**
     * @return array
     * Retorna una array de stock formateado para volver a facturar
     * o copiar pedido de forma termporal ya que en al consultar el stock
     *  recibe el stock real, pero con el seleccionado en 0
     */
    public function getFormattedStocksQuotation($quotationId, $detailId, $typeDetailStock)
    {
        if ($typeDetailStock == 'pedido') {
            $stock = GpPedidoDetalleStock::where('pedido_id', $quotationId)->where('detalle_id', $detailId)->get()->toArray();
            return $this->formatStockCollection($stock, 'pedido_id');
        }
        $stock = GpCotizacionDetalleStock::where('cotizacion_id', $quotationId)->where('detalle_id', $detailId)->get()->toArray();
        return $this->formatStockCollection($stock, 'cotizacion_id');
    }

    public function formatStockCollection(array $items, string $relationKey): array
    {
        return collect($items)->map(function ($item) use ($relationKey) {
            return [
                'id'                        => null,
                $relationKey                => null,
                'detalle_id'                => null,
                'STO_ART_BODEGA'            => $item['STO_ART_BODEGA'],
                'STO_ART_STOCK_DISPONIBLE'  => 0,
                'seleccionado'              => 0 //$item['seleccionado'],
            ];
        })->toArray();
    }

    public function resetQuotationDiscount(&$detalles, $cliente, $encera, $warehouse)
    {
        if ($encera) {
            $detalles->each(function ($item) use ($cliente, $warehouse) {
                $item->descuento                    = $cliente->CLI_DSTO_COMERCIAL;
                $item->descuento_original           = $cliente->CLI_DSTO_COMERCIAL;
                $item->ART_DESCUENTO                = 0;
                $item->modificador                  = 0;
                $item->ART_DESCRIPCION_ORIGINAL     = $this->restoreDescriptionItemQuotation($item->ART_CODIGO, $warehouse) ?? $item->ART_DESCRIPCION_ORIGINAL;
                $item->ART_DESCRIPCION              = $item->ART_DESCRIPCION_ORIGINAL;
                $item->CHECK_ALTERNATIVO            = false;
            });
        }
    }

    public function restoreDescriptionItemQuotation($articulo, $bodega)
    {
        $params = ['articulo' => $articulo, 'bodega' => $bodega];
        $articulo = $this->stockWarehouseService->fetchStockByOnlyWarehouse($params);
        return $articulo['DESCRIPCION_ARTICULO'] ?? null;
    }


    public function getPriceListByItem(string $articulo)
    {
        $resp = $this->stockWarehouseService->fetchListPricesArticle($articulo);
        return collect($resp)->groupBy('item');
    }



    /** funcion general para el registro de copia para cotizacion y pedido
     * @return int
     */
    public function createRegisterCopy($dataId, $user, $typeCopy, $typeRegisterCopy)
    {
        $registroCopia = new GpRegistroCopia([
            'user_id' => $user->id,
            'USERID' => $user->USERID,
            'tipo' => $typeRegisterCopy,

        ]);
        if ($typeCopy == 'pedido') {
            $registroCopia->pedido_origen = $dataId;
        } else {
            $registroCopia->cotizacion_origen = $dataId;
        }
        $registroCopia->save();
        return $registroCopia->id;
    }

    public function getSegmentoCliente($ruc)
    {
        $response = $this->apiGpService->fetchClientSegment([
            'ruc' => $ruc,
        ]);
        $data = data_get($response, 'data', []);
        return is_array($data) ? $data : (array) $data;
    }


    public function getAndValidateOrderDetailStock($detailOrder, $stock, $orderId, $quotationId = null)
    {
        $params = [
            'articulo'  => $detailOrder->ART_CODIGO,
            'bodega'    =>  data_get($stock, 'STO_ART_BODEGA'),
        ];
        $stocksResult = $this->stockWarehouseService->fetchStockByOnlyWarehouse($params);
        $this->assignmentDisponibleStock($stock, $stocksResult, $detailOrder);
        $this->stockWarehouseService->applyReservedQtyToAvailableStock($stock, $detailOrder, $orderId, $quotationId);
        return $stock;
    }


    public function assignmentDisponibleStock(&$stock, $stockResponseFormatted, $detailOrder)
    {
        $stock['STO_ART_STOCK_DISPONIBLE'] = $stockResponseFormatted['STOCK_DISPONIBLE'] ?? 0;
        $stock['STO_ART_ARTICULO'] = $detailOrder->ART_CODIGO;
    }


    /** funciones
     *  para visualizar el porcentaje de cambio de precio y descuento de un articulo
     */

    public function calculateDiscountFraction(float $originalPrice, float $currentPrice)
    {
        if ($originalPrice == 0.0) {
            return 0.0;
        }
        return ($currentPrice - $originalPrice) / $originalPrice;
    }

    public function calculateDiscountChange(float $originalDiscount, float $currentDiscount)
    {
        $original = $originalDiscount / 100;
        $current = $currentDiscount / 100;
        return $current - $original;
    }


    /***
     *
     *  funcion para obtener las imagenes de operaciones para volver a pedir en cotizacion y pedidos
     */

    public function getAllImagensOperations($quotationId, $type = 'cotizacion')
    {
        $model = ($type == 'pedido')
            ? GpPedidoImagen::where('pedido_id', $quotationId)
            : GpCotizacionImagen::where('cotizacion_id', $quotationId);
        return  $model->get()->map(function ($img) {
            return [
                'id' => $img->id,
                'url' => asset('storage/' . $img->ruta),
                'nombre' => $img->nombre_original
            ];
        });
    }



    public function getImageArticle($codArt): array
    {
        $images = [];
        for ($i = 1; $i <= 3; $i++) {
            $path = "images/productos/{$codArt}_{$i}.jpg";
            $pathAbsolute = public_path($path);
            if (File::exists($pathAbsolute)) {
                $images[] = asset($path);
            }
        }
        return $images;
    }


    /**funcioon para obtener la clasificacion tuval de los items para cotizaciones y pedidos */
    public function getApiSegmentsAbcItem($articles): Collection
    {
        $itemList = is_iterable($articles) ? collect($articles)->values()->toArray() : [$articles];
        $response = $this->stockWarehouseService->fetchListSegmentsAbc($itemList);
        return  collect($response)->groupBy('item');
    }



    //funcion para obtener los archivos pdf de los modelos

    public function getFormattedPdfMedia(HasMedia $model, string $collection = 'documentos_pdf'): array
    {
        return $model->getMedia($collection)->map(
            fn($media) =>
            [
                'id'            => $media->id,
                'url'           => $media->getFullUrl(),
                'nombre'        => $media->getCustomProperty('original_name') ?: $media->file_name,
                'mime_type'     => $media->mime_type,
                'size'          => $media->size,
                'file_name'     => $media->file_name
            ]
        )->values()
            ->toArray();
    }


    /** funcion para copiar imagenes en pedidos  */

    public function copyImagesFromUrls(GpPedido $pedido, array $urls)
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

    /** copia de dcocumentos pdf en pedidos */

    public function copyPdfsFromUrls(GpPedido $order, array $urls)
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
}
