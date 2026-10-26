<?php

namespace App\Services\Venta;

use App\Enums\Ventas\QuotationMode;
use App\Models\Ventas\Clientes\GpCliente;
use App\Models\Ventas\Cotizaciones\GpCotizacion;
use App\Models\Ventas\Cotizaciones\GpCotizacionDetalle;
use App\Models\Ventas\Cotizaciones\GpCotizacionImagen;
use App\Models\Admin\PuntosVenta\GpPuntoVenta;
use App\Models\Ventas\Documentos\GpRegistroCopia;
use App\Models\Admin\Usuarios\GpUsuario;
use App\Models\Ventas\Clientes\ClienteConfiguracion;
use App\Models\Inventario\Despachos\TipoDespacho;
use App\Models\Ventas\Vendedores\GpVendedor;
use App\Repositories\Venta\Contracts\QuotationRepositoryInterface;
use App\Services\Aprobaciones\Contracts\ApprovalQuotationServiceInterface;
use App\Services\Aprobaciones\Utils\FreightApproval;
use App\Services\Builders\Venta\Contracts\QuotationBuilderInterface;
use App\Services\Venta\Contracts\QuotationCreatorServiceInterface;
use App\Services\Venta\Cotizaciones\Contracts\QuotationHistoryCreatorServiceInterface;
use App\Services\Venta\Utils\Contracts\OrderAndQuotationServiceInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class QuotationCreatorService implements QuotationCreatorServiceInterface
{

    public function __construct(
        protected readonly QuotationRepositoryInterface $repository,
        protected readonly QuotationBuilderInterface $builder,
        protected readonly ApprovalQuotationServiceInterface $approval,
        protected readonly OrderAndQuotationServiceInterface $orderAndQuotationService,
        protected readonly QuotationHistoryCreatorServiceInterface $history

    ) {}


    public function createOrUpdate(array $data, GpUsuario $user, array $existingImages = [], array $newImages = [], array $pfdsExistings  = [], array $pfdsNew  = []): GpCotizacion
    {
        return DB::transaction(function () use ($data, $user,  $existingImages, $newImages, $pfdsExistings, $pfdsNew) {
            $quoteId                = data_get($data, 'cotizacion_id', null);
            $direccionFacturacion   = (int) data_get($data, 'direccion_facturacion_id');
            $direccionDespacho      = (int)data_get($data, 'direccion_entrega_id');
            $client                 = GpCliente::find(data_get($data, 'cliente_id'));
            $pointSales             = GpPuntoVenta::find(data_get($data, 'pv_id'));
            $clientConfig           = ClienteConfiguracion::where('pv_id', $pointSales->id)
                ->where('cliente_id', $client->id)
                ->where('estado', 1)
                ->firstOrFail();
            $typeDispatch = TipoDespacho::where('id', data_get($data, 'tipo_despacho_id'))->first();

            if (!$clientConfig) {
                throw new \RuntimeException("No se ha encontrado la configuración de este cliente");
            }
            $seller = GpVendedor::where('id', $clientConfig->vendedor_id)->firstOrFail();
            // --- VALIDACIONES DE NEGOCIO ---
            if ($direccionFacturacion) {
                $this->orderAndQuotationService->validateActiveSucursal($direccionFacturacion, 'facturación');
            }

            if ($direccionDespacho) {
                $this->orderAndQuotationService->validateActiveSucursal($direccionDespacho, 'despacho/entrega');
            }

            if (!empty($quoteId)) {
                GpCotizacionDetalle::where('cotizacion_id', $quoteId)
                    ->delete();
            }
            $cotizacion = $this->prepareQuotationHeader($data, $client, $pointSales, $typeDispatch, $seller, $user);
            if (!empty($data['items'])) {
                $this->saveQuotationDetails($cotizacion->id, $client->CLI_NIVEL_PRECIO, $data['items'] ?? []);
            }
            $registerCopyId = data_get($data, 'registro_copia', null);
            if (!empty($registerCopyId)) {
                $this->updateRegisterCopyQuotation($registerCopyId, $cotizacion->id);
            }
            if ($cotizacion->wasRecentlyCreated) {
                $cotizacion->refresh();
            }
            $this->syncImages($cotizacion, $existingImages, $newImages);
            $this->syncPdfs($cotizacion, $pfdsExistings, $pfdsNew);
            $mode = data_get($data, 'mode', 'create');
            if ($mode == QuotationMode::UPDATE->value) {
                $cotizacion->update(['estado' => 'procesado', 'facturado' => 1]);
            }
            $this->history->createQuoteHistory($cotizacion);
            return $cotizacion;
        });
    }


    public function prepareQuotationHeader(array $data, GpCliente $client, GpPuntoVenta $pointSales, TipoDespacho $typeDispatch, GpVendedor $seller, GpUsuario $user): GpCotizacion
    {
        $id                         = data_get($data, 'cotizacion_id', null);
        $quotation                  = $id ? GpCotizacion::findOrNew($id) : new GpCotizacion();
        $requiredApprovalCommercial = $this->determineApprovalStatus($quotation, $data);
        $motivoLimpio               = $this->sanitizeString($requiredApprovalCommercial['motivo'] ?? '');

        $this->builder->formPayloadHeaderQuotation($quotation, $user,$client, $typeDispatch, $seller, $pointSales, $data,  $requiredApprovalCommercial, $motivoLimpio);
        $quotation->save();
        return $quotation;
    }
    public function determineApprovalStatus(GpCotizacion $quotation, array $data): array
    {
        $requiereAprobacion = $this->approval->approveQuotation($data);
        if ($quotation->exists) {
            $dispatchType = TipoDespacho::find(data_get($data, 'tipo_despacho_id'));
            if (FreightApproval::requiresApprovalForType($dispatchType) && !FreightApproval::hasValidApproval($data, $quotation)) {
                $this->resetToPending($requiereAprobacion);
            } else {
                $this->mantenerAprobacionSiSigueValida($quotation, $requiereAprobacion);
            }
        } else {
            $requiereAprobacion['estado'] = ($requiereAprobacion['requiere'] === 'yes') ? 'espera' : 'aprobado';
            $requiereAprobacion['aprobado'] = 'no';
        }
        return $requiereAprobacion;
    }

    public function mantenerAprobacionSiSigueValida(GpCotizacion $cotizacion, array &$requiereAprobacionComercial)
    {
        $estabaAprobada = $cotizacion->requiere_aprobacion_comercial === 'yes' && $cotizacion->aprobado_comercial === 'yes';

        if (!$estabaAprobada) {
            // Si no estaba aprobada antes, no hay nada que mantener.
            // Se aplica el estado que diga la nueva validación.
            $this->resetToPending($requiereAprobacionComercial);
            return;
        }
        // 2. Extraer motivos para comparar
        $motivoNuevo = $this->sanitizeString($requiereAprobacionComercial['motivo'] ?? '');
        $motivoGuardado = $this->sanitizeString($cotizacion->motivo_aprobacion_comercial ?? '');

        // 3. Comparación Estricta
        // Si la nueva validación dice que NO requiere aprobación, genial (aunque raro si antes requería).
        if (($requiereAprobacionComercial['requiere'] ?? 'no') !== 'yes') {
            $requiereAprobacionComercial['estado'] = 'aprobado';
            $requiereAprobacionComercial['aprobado'] = 'yes';
            return; // Dejamos que pase como 'no requiere'
        }

        if ($motivoNuevo === $motivoGuardado) {
            // ... RESTAURAMOS el estado de aprobación de la BD
            $requiereAprobacionComercial['requiere'] = $cotizacion->requiere_aprobacion_comercial;
            $requiereAprobacionComercial['motivo'] = $cotizacion->motivo_aprobacion_comercial; // Redundante pero seguro
            $requiereAprobacionComercial['estado'] = $cotizacion->estado; // Ojo: Asegúrate que 'estado' sea el campo correcto (aprobado/espera)
            $requiereAprobacionComercial['aprobado'] = $cotizacion->aprobado_comercial; // 'yes'
        } else {
            // Si el motivo cambió (ej: pasaste de 20% a 25%), se pierde la aprobación
            $this->resetToPending($requiereAprobacionComercial);
        }
    }

    public function resetToPending(array &$data)
    {
        // Si requiere aprobación, pasa a 'espera', si no, 'aprobado' (u otro status default)
        $req = $data['requiere'] ?? 'no';
        if ($req === 'yes') {
            $data['estado'] = 'espera';
            $data['aprobado'] = 'no';
        } else {
            $data['estado'] = 'aprobado';
            $data['aprobado'] = 'yes';
        }
    }

    public function sanitizeString(string $text): string
    {
        if ($text === '' || mb_check_encoding($text, 'UTF-8')) {
            return $text;
        }

        // Convertir texto legado sin descartar caracteres con //IGNORE.
        return mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
    }

    /** proceso de creacion de detalle de cotizacion */
    protected function saveQuotationDetails(int $quoteId, string $lvlPrice, array $items)
    {
        foreach ($items as $it) {
            $detail = $this->builder->formPayloadDetailQuotation($quoteId, $it, $lvlPrice);
            $detail->save();
            $providers = data_get($it, 'proveedores', []);
            if (!empty($providers)) {
                $this->saveQuotationDetailProviders($quoteId, $detail->id, $providers);
            }
            $stocks = data_get($it, 'stock', []);
            if (!empty($stocks)) {
                $this->saveQuotationDetailStocks($quoteId, $detail->id, $stocks);
            }
        }
    }


    protected function saveQuotationDetailProviders(int $quoteId, int $detailId, array $providers)
    {
        foreach ($providers as $provider) {
            $providerModel = $this->builder->formPayloadProvidersDetailQuotation($quoteId, $detailId, $provider);
            $providerModel->save();
        }
    }
    protected function saveQuotationDetailStocks(int $quoteId, int $detailId, array $stocks)
    {
        foreach ($stocks as $st) {
            $stockModel = $this->builder->formPayloadStocksDetailQuotation($quoteId, $detailId, $st);
            $stockModel->save();
        }
    }

    protected function updateRegisterCopyQuotation(int $registerCopyId, int $cotizacionId)
    {

        $registerCopy = GpRegistroCopia::find($registerCopyId);
        if ($registerCopy) {
            $registerCopy->cotizacion_destino = $cotizacionId;
            $registerCopy->save();
        }
    }



    protected function syncImages(GpCotizacion $cotizacion, array $keepUrls, array $newFiles)
    {
        $dbImages = GpCotizacionImagen::where('cotizacion_id', $cotizacion->id)->get();
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
            $this->copyImagesFromUrls($cotizacion, $urlsParaCopiar);
        }

        if (!empty($newFiles)) {
            $this->storeImagesQuotation($cotizacion, $newFiles);
        }
    }

    protected function copyImagesFromUrls(GpCotizacion $cotizacion, array $urls)
    {
        $baseUrl = asset('storage') . '/';
        $disk = Storage::disk('public');
        foreach ($urls as $url) {
            $relativePath = str_replace($baseUrl, '', $url);
            if (Storage::disk('public')->exists($relativePath)) {
                $fileName = basename($relativePath);
                $newPath = "cotizaciones/operaciones_img/{$cotizacion->id}/{$fileName}";
                if (!$disk->exists($newPath)) {
                    $disk->copy($relativePath, $newPath);

                    GpCotizacionImagen::create([
                        'cotizacion_id'   => $cotizacion->id,
                        'ruta'            => $newPath,
                        'nombre_original' => $fileName,
                        'tipo_mime'       => $this->getMimeTypeSafe($disk, $newPath),
                        'tamano'          => $disk->size($newPath)
                    ]);
                }
            }
        }
    }

    private function getMimeTypeSafe($disk, $path)
    {
        try {
            return $disk->mimeType($path);
        } catch (\Exception $e) {
            // Fallback simple por extensión si el driver falla
            return mime_content_type(storage_path('app/public/' . $path)) ?: 'application/octet-stream';
        }
    }

    protected function storeImagesQuotation(GpCotizacion $cotizacion, array $files)
    {
        Log::info("STORE IMAGES: Iniciando proceso. Cantidad de archivos: " . count($files));
        foreach ($files as $file) {
            // Validamos que sea un archivo real antes de procesar
            if (!$file instanceof UploadedFile) {
                continue;
            }

            $uniqueName = \App\Supports\SafeUploadName::image($file);

            $path = $file->storeAs(
                "cotizaciones/operaciones_img/{$cotizacion->id}",
                $uniqueName,
                'public'
            );

            GpCotizacionImagen::create([
                'cotizacion_id'     => $cotizacion->id,
                'ruta'              => $path, // Guardamos ruta relativa
                'nombre_original'   => $file->getClientOriginalName(),
                'tipo_mime'         => $file->getMimeType(),
                'tamano'            => $file->getSize()
            ]);
        }
    }





    protected function syncPdfs(GpCotizacion $quote, array $keepUrls, array $newFiles)
    {
        $mediaItems = $quote->getMedia('documentos_pdf');

        // 1.- identificar que PDFS actuales deben conservarse
        $mediaToKeeps = $mediaItems->filter(function (Media $media) use ($keepUrls) {
            return in_array($media->getUrl(), $keepUrls) || in_array($media->getFullUrl(), $keepUrls, true);
        });

        //2.- eliminar del modelo lo que no este en keepUrls
        $quote->clearMediaCollectionExcept('documentos_pdf', $mediaToKeeps);

        //3 .- detectatr cuales url son externas a esta cotizacion y deben copiarse
        $registerUrls = $mediaItems
            ->flatMap(fn(Media $media) => [$media->getUrl(), $media->getFullUrl()])
            ->unique()
            ->values()
            ->all();

        $urlsToCopy = array_values(array_diff($keepUrls, $registerUrls));
        if (!empty($urlsToCopy)) {
            $this->copyPdfsFromUrls($quote, $urlsToCopy);
        }

        //4.- almacenar nuevos archivos
        if (!empty($newFiles)) {
            $this->storePdfsQuotation($quote, $newFiles);
        }
    }


    protected function copyPdfsFromUrls(GpCotizacion $quote, array $urls)
    {
        foreach ($urls as $url) {
            $sourceMedia = $this->findMediaByUrl($url, 'documentos_pdf');
            if (!$sourceMedia) {
                continue;
            }

            $alreadyExists = $quote
                ->getMedia('documentos_pdf')
                ->contains(function (Media $existing) use ($sourceMedia) {
                    return $existing->file_name === $sourceMedia->file_name && (int) $existing->size === (int) $sourceMedia->size;
                });

            if ($alreadyExists) {
                continue;
            }

            $sourceMedia->copy($quote, 'documentos_pdf', 'public');
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

    protected function storePdfsQuotation(GpCotizacion $quote, array $files)
    {

        foreach ($files as  $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }
            $originalName   = $file->getClientOriginalName();
            $uniqueName     = \App\Supports\SafeUploadName::pdf($file);

            $quote
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
