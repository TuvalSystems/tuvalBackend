<?php

namespace App\Services\Devoluciones;

use App\Enums\devoluciones\InvoiceAnulationStatus;
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





    /**
     *  proceso de envio de documentos no anulados del dia anterior
     *
     */
    public function handleCancellationRetries()
    {
        $docs = $this->getYesterdayCancellations()->map(function ($item) {
            $item->can_resend = $this->isWithinCancellationDeadline($item->gpPedido);
            return $item;
        });

        $docsCanSend = $docs->where('can_resend', 1);
        $docsCantSend = $docs->where('can_resend', 0);



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
            $email = 'sistemas_desarrollo@tuvalsa.com';
            // Mail::to($email)->send(new FacturasNoAnuladasMail($invoices));
            Mail::to($email)->queue(new FacturasNoAnuladasMail($invoices));
        }
    }


    /**
     *  proceso de enviar anulaciones del dia que dieron problemas
     */

    public function handleSendCancellationsWithError()
    {
        $docs = $this->getCancellationsOnDay()->map(function ($item) {
            $item->can_resend = $this->isWithinCancellationDeadline($item->gpPedido);
            return $item;
        })->values();
        $docsCanSend = $docs->where('can_resend', 1);
        $this->resendCancellationWithError($docsCanSend);
    }

    protected function getCancellationsOnDay(): Collection
    {
        $startDate  = Carbon::now()->startOfDay();
        $endDate    = Carbon::now()->endOfDay();
        $msg = 'COMPROBANTE ENVIADO PARA SER ANULADO';
        return GpFacturaAnulada::with([
            'gpPedido:id,pedido,factura,fecha_factura,PTO_VTA_ID,CLI_RUC,CLI_RAZON_SOCIAL',
            'gpPedido.puntoVenta:PTO_VTA_ID,PTO_VTA_DIAS_MAXIMO_PARA_ANULACION',
            'gpDevolucion:id,devolucion,DOC_GENERADO,fecha_NC'
        ])->where(function ($q) use ($msg) {
            $q->where('resp_seedbilling', '!=', $msg)
                ->orWhereNull('resp_seedbilling')
                ->orWhere('resp_seedbilling', '');
        })->whereBetween('created_at', [
            $startDate,
            $endDate
        ])->get();
    }

    protected function resendCancellationWithError(Collection $docs)
    {

        $docs->map(function ($item) {
            $codeAccess = trim((string) $item->claveacceso);
            if ($codeAccess === '') {
                Log::warning('No se puede reenviar anulación porque no tiene clave de acceso', [
                    'factura_anulada_id' => $item->id,
                    'pedido' => $item->pedido ?? null,
                    'factura' => $item->factura ?? null,
                ]);
                return;
            }

            $resp       = $this->consultApiSeedBilling($codeAccess);
            $status     = strtoupper(trim((string) data_get($resp, 'status', '')));
            $canResend  = $status !== 'ANU';
            if (!$canResend) {
                return;
            }

            $resp =  $this->apiResponseSeedBelling($codeAccess);
            $message = data_get($resp, 'respuesta', null);
            GpFacturaAnulada::where('id', $item->id)->update(
                [
                    'estado' => InvoiceAnulationStatus::ENVIADA,
                    'resp_seedbilling' => $message
                ]
            );
        });
    }
}
