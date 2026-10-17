<?php

namespace App\Services\Devoluciones;

use App\Enums\devoluciones\InvoiceAnulationStatus;
use App\Enums\Admin\Sync\StatusSync;
use App\Models\Admin\SyncLog;
use App\Mail\Devoluciones\FacturasNoAnuladasMail;
use App\Models\devoluciones\GpFacturaAnulada;
use App\Models\GpPedido;
use App\Services\Api\ApiSeedBillingService;
use App\Services\Devoluciones\Contracts\DocumentCancellationRetryServiceInterface;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
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
        $endDate = Carbon::today();
        $startDate = $endDate->copy()->subDay();
        return  GpFacturaAnulada::with([
            'gpPedido:id,pedido,factura,fecha_factura,PTO_VTA_ID,CLI_RUC,CLI_RAZON_SOCIAL',
            'gpPedido.puntoVenta:PTO_VTA_ID,PTO_VTA_DIAS_MAXIMO_PARA_ANULACION',
            'gpDevolucion:id,devolucion,DOC_GENERADO,fecha_NC'
        ])->where('created_at', '>=', $startDate)
            ->where('created_at', '<', $endDate)->get();
    }

    protected function isWithinCancellationDeadline(?GpPedido $pedido)
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
            ->startOfMonth()
            ->addMonth()
            ->day($diaLimite)
            ->endOfDay();
        return now()->lte($fechaLimite);
    }

    protected function retryValidCancellations(Collection $docs)
    {
        $this->resendCancellationWithError($docs, 'handleCancellationRetries');
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
            $this->logCancellation($itm, 'No se puede reenviar: plazo vencido o datos del pedido/punto de venta incompletos.', method: 'handleCancellationRetries');
            return [
                'ruc'                   => data_get($itm, 'gpPedido.CLI_RUC'),
                'razon_social'          => data_get($itm, 'gpPedido.CLI_RAZON_SOCIAL'),
                'pedido'                => data_get($itm, 'gpPedido.pedido'),
                'factura'               => data_get($itm, 'gpPedido.factura'),
                'clave_acceso'          => $itm->claveacceso,
                'fecha_factura'         => data_get($itm, 'gpPedido.fecha_factura'),
                'dias_max_anulacion'    => data_get($itm, 'gpPedido.puntoVenta.PTO_VTA_DIAS_MAXIMO_PARA_ANULACION'),
                'devolucion'            => data_get($itm, 'gpDevolucion.devolucion'),
            ];
        })->values();

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
        $this->resendCancellationWithError($this->getCancellationsOnDay());
    }

    protected function getCancellationsOnDay(): Collection
    {
        $startDate = Carbon::today();
        $endDate = $startDate->copy()->addDay();
        $completedResponses = ['COMPROBANTE ENVIADO PARA SER ANULADO', 'ANU'];
        return GpFacturaAnulada::with([
            'gpPedido:id,pedido,factura,fecha_factura,PTO_VTA_ID,CLI_RUC,CLI_RAZON_SOCIAL',
            'gpPedido.puntoVenta:PTO_VTA_ID,PTO_VTA_DIAS_MAXIMO_PARA_ANULACION',
            'gpDevolucion:id,devolucion,DOC_GENERADO,fecha_NC'
        ])->where(function ($q) use ($completedResponses) {
            $q->whereNotIn('resp_seedbilling', $completedResponses)
                ->orWhereNull('resp_seedbilling')
                ->orWhere('resp_seedbilling', '');
        })->where('created_at', '>=', $startDate)
            ->where('created_at', '<', $endDate)->get();
    }

    protected function resendCancellationWithError(Collection $docs, string $method = 'handleSendCancellationsWithError')
    {

        $docs->each(function ($item) use ($method) {
            $codeAccess = trim((string) $item->claveacceso);
            if ($codeAccess === '') {
                $this->logCancellation($item, 'No se puede reenviar: falta la clave de acceso.', method: $method);
                return;
            }

            if (!$this->isWithinCancellationDeadline($item->gpPedido)) {
                $this->logCancellation($item, 'No se puede reenviar: plazo vencido o datos del pedido/punto de venta incompletos.', method: $method);
                return;
            }

            try {
                $resp       = $this->consultApiSeedBilling($codeAccess);
                if (!data_get($resp, 'success', false)) {
                    $this->logCancellation($item, 'Falló la consulta a Seedbilling; no se reenvía.', $resp, StatusSync::FAILED, $method);
                    return;
                }
                $estado     = strtoupper(trim((string) data_get($resp, 'data.estado', '')));
                if ($estado === '') {
                    $this->logCancellation($item, 'Consulta sin estado del comprobante; no se reenvía.', $resp, method: $method);
                    return;
                }
                $canResend  = $estado !== 'ANU';
                if (!$canResend) {
                    $item->update([
                        'estado' => InvoiceAnulationStatus::ENVIADA,
                        'resp_seedbilling' => 'ANU',
                    ]);
                    $this->logCancellation($item, 'Seedbilling confirma que el comprobante ya está anulado.', $resp, StatusSync::SUCCESS, $method);
                    return;
                }

                $resp =  $this->apiResponseSeedBelling($codeAccess);
                $message = data_get($resp, 'respuesta', null);
                $item->update(
                    [
                        'estado' => data_get($resp, 'success', false)
                            ? InvoiceAnulationStatus::ENVIADA : InvoiceAnulationStatus::PENDIENTE,
                        'resp_seedbilling' => $message ?: data_get($resp, 'message', 'Sin respuesta de Seedbilling'),
                    ]
                );
                $this->logCancellation(
                    $item,
                    data_get($resp, 'success', false) ? 'Solicitud de anulación aceptada por Seedbilling.' : 'Falló el envío de la anulación a Seedbilling.',
                    $resp,
                    data_get($resp, 'success', false) ? StatusSync::SUCCESS : StatusSync::FAILED,
                    $method,
                );
            } catch (\Throwable $e) {
                $this->logCancellation($item, $e->getMessage(), null, StatusSync::FAILED, $method);
            }
        });
    }

    protected function logCancellation(GpFacturaAnulada $item, string $message, ?array $response = null, StatusSync $status = StatusSync::WARNING, string $method = 'handleSendCancellationsWithError'): void
    {
        SyncLog::logErrorSync(
            module: 'devoluciones',
            action: 'retry_seedbilling_cancellation',
            method: $method,
            message: $message,
            externalId: $item->claveacceso,
            localId: $item->id,
            payload: ['pedido_id' => $item->pedido_id, 'devolucion_id' => $item->devolucion_id, 'request' => ['claveAcceso' => trim((string) $item->claveacceso)], 'response' => $response],
            status: $status,
        );
    }
}
