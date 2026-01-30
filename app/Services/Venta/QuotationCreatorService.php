<?php

namespace App\Services\Venta;

use App\Models\GpCotizacion;
use App\Models\GpCotizacionDetalle;
use App\Models\GpCotizacionImagen;
use App\Models\GpPuntoVenta;
use App\Models\GpRegistroCopia;
use App\Repositories\Venta\Contracts\QuotationRepositoryInterface;
use App\Services\Aprobaciones\Contracts\ApprovalQuotationServiceInterface;
use App\Services\Builders\Venta\Contracts\QuotationBuilderInterface;
use App\Services\Venta\Contracts\QuotationCreatorServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class QuotationCreatorService implements QuotationCreatorServiceInterface
{

    public function __construct(
        protected readonly QuotationRepositoryInterface $repository,
        protected readonly QuotationBuilderInterface $builder,
        protected readonly ApprovalQuotationServiceInterface $approval
    ) {}


    public function createOrUpdate(array $data,  $user, array $existingImages = [], array $newImages = []): GpCotizacion
    {
        Log::info("STORE IMAGES: Iniciando proceso. Cantidad de archivos: " . count($newImages));

        return DB::transaction(function () use ($data, $user,  $existingImages, $newImages) {
            $quoteId = data_get($data, 'cotizacion_id', null);

            if (!empty($quoteId)) {
                GpCotizacionDetalle::where('cotizacion_id', $quoteId)
                    ->delete();
            }
            $cotizacion = $this->prepareQuotationHeader($data, $user);
            if (!empty($data['items'])) {
                $this->saveQuotationDetails($cotizacion->id, $data['items'] ?? []);
            }
            $registerCopyId = data_get($data, 'registro_copia', null);
            if (!empty($registerCopyId)) {
                $this->updateRegisterCopyQuotation($registerCopyId, $cotizacion->id);
            }
            if ($cotizacion->wasRecentlyCreated) {
                $cotizacion->refresh();
            }
            $this->syncImages($cotizacion, $existingImages, $newImages);

            $mode = data_get($data, 'mode', 'create');
            if ($mode == 'updated') {
                $cotizacion->update(['estado' => 'procesado', 'facturado' => 1]);
            }
            return $cotizacion;
        });
    }


    public function prepareQuotationHeader(array $data, $user): GpCotizacion
    {
        $id = data_get($data, 'cotizacion_id', null);
        $quotation = $id ? GpCotizacion::findOrNew($id) : new GpCotizacion();

        $requiredApprovalCommercial = $this->determineApprovalStatus($quotation, $data);
        $motivoLimpio = $this->sanitizeString($requiredApprovalCommercial['motivo'] ?? '');
        $puntoVenta = GpPuntoVenta::where('PTO_VTA_ID', $data['punto_venta_id'])->first();
        $despacho = data_get($data, 'tipo_entrega', 'retiro');
        $tipoDespacho = ($despacho == 'retiro') ? 'Retiro en bodega' : 'Despacho en ruta';
        $this->builder->formPayloadHeaderQuotation($quotation, $data, $user, $puntoVenta, $requiredApprovalCommercial, $motivoLimpio, $tipoDespacho);
        $quotation->save();
        return $quotation;
    }
    public function determineApprovalStatus(GpCotizacion $quotation, array $data): array
    {
        $requiereAprobacion = $this->approval->approveQuotation($data);
        if ($quotation->exists) {
            $this->mantenerAprobacionSiSigueValida($quotation, $requiereAprobacion);
        } else {
            $requiereAprobacion['estado'] = ($requiereAprobacion['requiere'] === 'yes') ? 'espera' : 'aprobado';
            $requiereAprobacion['aprobado'] = 'no';
        }
        return $requiereAprobacion;
    }

    public function mantenerAprobacionSiSigueValida($cotizacion, &$requiereAprobacionComercial)
    {
        $estabaAprobada = $cotizacion->requiere_aprobacion_comercial === 'yes' && $cotizacion->aprobado_comercial === 'yes';

        if (!$estabaAprobada) {
            // Si no estaba aprobada antes, no hay nada que mantener.
            // Se aplica el estado que diga la nueva validación.
            $this->resetToPending($requiereAprobacionComercial);
            return;
        }
        // 2. Extraer motivos para comparar
        $motivoNuevo = $requiereAprobacionComercial['motivo'] ?? '';
        $motivoGuardado = $cotizacion->motivo_aprobacion_comercial ?? '';

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

    public function resetToPending(&$data)
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
        if (empty($text))
            return '';

        $limpio = iconv('UTF-8', 'UTF-8//IGNORE', $text);

        if (!$limpio) {
            $encoding = mb_detect_encoding($text, mb_detect_order(), true);
            $limpio = mb_convert_encoding($text, 'UTF-8', $encoding ?: 'ISO-8859-1');
        }
        return $limpio;
    }


    /** proceso de creacion de detalle de cotizacion */
    protected function saveQuotationDetails(int $quoteId, array $items)
    {
        foreach ($items as $it) {
            $detail = $this->builder->formPayloadDetailQuotation($quoteId, $it);
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
            if (!$file instanceof \Illuminate\Http\UploadedFile) {
                continue;
            }

            $cleanName = str_replace(' ', '_', $file->getClientOriginalName());
            $uniqueName = time() . '_' . $cleanName;

            $path = $file->storeAs(
                "cotizaciones/operaciones_img/{$cotizacion->id}",
                $uniqueName,
                'public'
            );

            GpCotizacionImagen::create([
                'cotizacion_id' => $cotizacion->id,
                'ruta' => $path, // Guardamos ruta relativa
                'nombre_original' => $file->getClientOriginalName(),
                'tipo_mime' => $file->getMimeType(),
                'tamano' => $file->getSize()
            ]);
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
}
