<?php

namespace App\Services\Devoluciones;

use App\Mail\Devoluciones\FacturasNoAnuladasMail;
use App\Models\devoluciones\GpFacturaAnulada;
use App\Models\GpPedido;
use App\Services\Api\ApiSeedBillingService;
use App\Services\Devoluciones\Contracts\DocumentCancellationRetryServiceInterface;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class DocumentCancellationRetryService implements DocumentCancellationRetryServiceInterface
{
    public function __construct(protected readonly ApiSeedBillingService $api) {}






    public function handleCancellationRetries()
    {
        $docs = $this->getYesterdayCancellations()->map(function ($item) {
            $item->can_resend = $this->isWithinCancellationDeadline($item->gpPedido);
            return $item;
        });

        $docsCanSend = $docs->where('can_resend', 1);
        $docsCantSend = $docs->where('can_resend', 0);

        Log::info('Logica de envio a api SeedBilling', [
            'datos' => $docsCanSend
        ]);
        Log::info('Logica de no enviados ', [
            'datos' => $docsCantSend
        ]);

        $this->retryValidCancellations($docsCanSend);
        $this->reportUnprocessableCancellations($docsCantSend);
    }


    protected function getYesterdayCancellations(): Collection
    {
        $startDate = Carbon::yesterday()->startOfDay();
        $endDate = Carbon::yesterday()->endOfDay();
        return  GpFacturaAnulada::with([
            'gpPedido:id,pedido,factura,fecha_factura,PTO_VTA_ID,CLI_RUC,CLI_RAZON_SOCIAL',
            'gpPedido.puntoVenta:PTO_VTA_ID,PTO_VTA_DIAS_MAXIMO_PARA_ANULACION',
            'gpDevolucion:id,devolucion,DOC_GENERADO,fecha_NC'
        ])->whereBetween('created_at', [
            $startDate,
            $endDate
        ])->get();
    }

    protected function isWithinCancellationDeadline(GpPedido $pedido)
    {

        $fechaFactura = data_get($pedido, 'fecha_factura', null);
        $ptoVtaId = data_get($pedido, 'PTO_VTA_ID', null);

        if (!$fechaFactura || !$ptoVtaId) {
            return false;
        }

        $ptoVta = $pedido->puntoVenta;
        if (!$ptoVta || !$ptoVta->PTO_VTA_DIAS_MAXIMO_PARA_ANULACION) {
            return false;
        }
        $diaLimite = $ptoVta->PTO_VTA_DIAS_MAXIMO_PARA_ANULACION;

        $fechaLimite = Carbon::parse($fechaFactura)
            ->addMonth()
            ->startOfMonth()
            ->day($diaLimite)
            ->endOfDay();
        return now()->lte($fechaLimite);
    }

    protected function retryValidCancellations(Collection $docs)
    {
        $docs->map(function ($item) {
            $codeAccess = $item->claveacceso;
            $resp = $this->consultApiSeedBilling($codeAccess);
            $canResend = trim(data_get($resp, 'status')) !== 'ANU';
            if ($canResend) {
                $this->apiResponseSeedBelling($codeAccess);
            }
        });
    }

    protected function consultApiSeedBilling(string $codeAccess)
    {
        return $this->api->checkDocumentCancellationStatus([
            'claveAcceso' => $codeAccess
        ]);
    }

    public function apiResponseSeedBelling(string $accesCode)
    {
        return $this->api->cancelDocument([
            'claveAcceso' => $accesCode,
        ]);
    }

    protected function reportUnprocessableCancellations(Collection $docs)
    {
        if ($docs->isEmpty()) {
            return;
        }
        $invoices = $docs->map(function ($itm) {
            return [
                'ruc'                   => $itm->gpPedido->CLI_RUC,
                'razon_social'          => $itm->gpPedido->CLI_RAZON_SOCIAL,
                'pedido'                => $itm->gpPedido->pedido,
                'factura'               => $itm->gpPedido->factura,
                'clave_acceso'          => $itm->claveacceso,
                'fecha_factura'         => $itm->gpPedido->fecha_factura,
                'dias_max_anulacion'    => $itm->gpPedido->puntoVenta->PTO_VTA_DIAS_MAXIMO_PARA_ANULACION,
                'devolucion'            => $itm->gpDevolucion->devolucion,
            ];
        })->values();

        Log::info('documentos formateados', [
            'formateados' => $invoices
        ]);
        if ($invoices->isNotEmpty()) {
            $email = 'sistemas6@tuvalsa.com';
            Mail::to($email)->send(new FacturasNoAnuladasMail($invoices));
            // Mail::to($email)->queue(new FacturasNoAnuladasMail($invoices));
        }
    }
}
