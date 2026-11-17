<?php

namespace App\Services\Ventas\Cotizaciones;

use App\Models\Admin\PuntosVenta\GpPuntoVenta;
use App\Models\Ventas\Clientes\GpProspecto;
use App\Models\Ventas\CondicionesPago\GpDescuentoFormaPago;
use App\Models\Ventas\Cotizaciones\GpCotizacion;
use App\Models\Ventas\Cotizaciones\GpCotizacionDetalle;
use App\Services\Ventas\Cotizaciones\Contracts\QuotationServiceInterface;
use Barryvdh\DomPDF\Facade\Pdf;

class QuotationDocumentService
{
    public function __construct(private readonly QuotationServiceInterface $quotations) {}

    public function printQuotation(int $id, bool $withImages = false): array
    {
        $data = $this->quotations->printQuotationPdf($id, $withImages);
        return $this->render($withImages ? 'cotizacionImagenesPDF' : 'cotizacionPDF', $data);
    }

    public function printProspectQuotation(int $id): array
    {
        $cotizacionModel = GpCotizacion::findOrFail($id);
        $cotizacion = $cotizacionModel->setAppends([])->attributesToArray();

        $detalle = GpCotizacionDetalle::where('cotizacion_id', $cotizacion['id'])->get()->toarray();
        $peso = 0;
        foreach ($detalle as $art) {
            $peso += $art['ART_PESO'] * $art['ART_CANTIDAD'];
        }
        $cotizacion['peso'] = $peso;
        $cotizacion['detalle'] = $detalle;
        $formadepago = GpDescuentoFormaPago::where('DESC_FORMA_PAGO_CODIGO', $cotizacion['DESC_FORMA_PAGO_CODIGO'])->firstOrFail()->toArray();
        $cotizacion['formadepago'] = $formadepago['DESC_FORMA_PAGO_TIPO_CREDITO'];

        $cliente = GpProspecto::where('id', $cotizacion['prospecto_id'])->firstOrFail()->toArray();

        $cotizacion['cliente'] = $cliente;
        $puntodeventa = GpPuntoVenta::where('PTO_VTA_ID', $cotizacion['PTO_VTA_ID'])->firstOrFail()->toArray();

        if ($puntodeventa['PTO_VTA_DIA_VALIDEZ_COTIZACION'] == 0) {
            $cotizacion['validez'] = '0 días';
        } else {

            $cotizacion['validez'] = $puntodeventa['PTO_VTA_DIA_VALIDEZ_COTIZACION'] . ' días hábiles';
        }

        $cotizacion['expira'] = date('Y-m-d', strtotime($puntodeventa['PTO_VTA_DIA_VALIDEZ_COTIZACION'] . ' day', strtotime($cotizacion['updated_at'])));

        $cotizacion['direccion'] = $puntodeventa['PTO_VTA_DIRECCION_1'] . ' ' . $puntodeventa['PTO_VTA_DIRECCION_2'];

        return $this->render('cotizacionProspectoPDF', $cotizacion);
    }

    private function render(string $view, array $data): array
    {
        $binary = Pdf::loadView($view, $data)->output();
        // Registrar impresión únicamente después de generar el archivo.
        $quote = GpCotizacion::findOrFail($data['id']);
        $quote->impreso = 'yes';
        $quote->fecha_impreso ??= now();
        $quote->save();

        return [
            'binary' => $binary,
            'filename' => 'cotizacion-' . $data['cotizacion'] . '.pdf',
            'content_type' => 'application/pdf',
        ];
    }
}
