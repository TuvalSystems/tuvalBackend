<?php

namespace App\Services\Venta;

use App\Models\GpCotizacion;
use App\Models\GpCotizacionDetalle;
use App\Models\GpCotizacionImagen;
use App\Models\GpPuntoVenta;
use App\Repositories\Venta\Contracts\QuotationRepositoryInterface;
use App\Services\Aprobaciones\Contracts\ApprovalServiceInterface;
use App\Services\Builders\Venta\Contracts\QuotationBuilderInterface;
use App\Services\Venta\Contracts\QuotationCreatorServiceInterface;
use Illuminate\Support\Facades\DB;

class QuotationCreatorService implements QuotationCreatorServiceInterface
{

    public function __construct(
        protected readonly QuotationRepositoryInterface $repository,
        protected readonly QuotationBuilderInterface $builder,
        protected readonly ApprovalServiceInterface $aproval,
    ) {
    }


    public function createOrUpdate(array $data, $user, array $files = []): GpCotizacion
    {

        return DB::transaction(function () use ($data, $files, $user) {
            $quoteId = data_get($data, 'cotizacion_id', null);

            if (!empty($quoteId)) {
                GpCotizacionDetalle::where('cotizacion_id', $quoteId)
                    ->delete();
            }
            $cotizacion = $this->prepareQuotationHeader($data, $user);
            if (!empty($data['items'])) {
                $this->saveQuotationDetails($cotizacion->id, $data['items'] ?? []);
            }
            if ($cotizacion->wasRecentlyCreated) {
                $cotizacion->refresh();
            }
            if (!empty($files)) {
                $this->storeImagesQuotation($cotizacion, $files);
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
        $this->builder->formPayloadHeaderQuotation($quotation, $data, $user, $puntoVenta, $requiredApprovalCommercial, $motivoLimpio);
        $quotation->save();
        return $quotation;

    }
    public function determineApprovalStatus(GpCotizacion $quotation, array $data): array
    {
        $requiereAprobacion = $this->aproval->checkoutApprovalCommercialQuotations($data);
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
    public function saveQuotationDetails(int $quoteId, array $items)
    {
        foreach ($items as $it) {
            $detail = $this->builder->formPayloadDetailQuotation($quoteId, $it);
            $detail->save();
            $providers = data_get($it, 'proveedores', []);
            if (!empty($providers)) {
                $this->saveQuotationDetailProviders($quoteId, $detail->id, $providers);
            }
            $stocks = data_get($it, 'stocks', []);
            if (!empty($stocks)) {
                $this->saveQuotationDetailStocks($quoteId, $detail->id, $stocks);
            }
        }
    }


    public function saveQuotationDetailProviders(int $quoteId, int $detailId, array $providers)
    {
        foreach ($providers as $provider) {
            $providerModel = $this->builder->formPayloadProvidersDetailQuotation($quoteId, $detailId, $provider);
            $providerModel->save();
        }
    }
    public function saveQuotationDetailStocks(int $quoteId, int $detailId, array $stocks)
    {
        foreach ($stocks as $st) {
            $stockModel = $this->builder->formPayloadStocksDetailQuotation($quoteId, $detailId, $st);
            $stockModel->save();
        }
    }


    public function storeImagesQuotation(GpCotizacion $cotizacion, array $files)
    {
        foreach ($files as $file) {
            $path = $file->storeAs(
                "cotizaciones/operaciones_img/{$cotizacion->id}",
                $file->getClientOriginalName(),
                'public'
            );

            GpCotizacionImagen::create([
                'cotizacion_id'   => $cotizacion->id,
                'ruta'            => $path,
                'nombre_original' => $file->getClientOriginalName(),
                'tipo_mime'       => $file->getMimeType(),
                'tamano'          => $file->getSize()
            ]);
        }
    }

}
