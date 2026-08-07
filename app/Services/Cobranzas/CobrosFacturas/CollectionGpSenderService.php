<?php

namespace App\Services\Cobranzas\CobrosFacturas;

use App\Enums\Cobranzas\CobrosFacturas\CollectTypeEnum;
use App\Models\Cobranzas\GpCobro;
use App\Models\Cobranzas\GpCobrosDeposito;
use App\Models\Cobranzas\GpCobrosDepositosFacturas;
use App\Models\Cobranzas\GpCobrosEfectivoFactura;
use App\Models\Cobranzas\GpCobrosEfectivoMonto;
use App\Models\Cobranzas\GpCobrosRetenciones;
use App\Models\GpCaja;
use App\Models\GpCliente;
use App\Models\GpCobrosAplicacionMonto;
use App\Models\GpCobrosTarjetaFactura;
use App\Models\GpCobrosTarjetaMonto;
use App\Models\GpUsuario;
use App\Notifications\User\UserNotification;
use App\Services\Api\Cobros\ApiBillingService;
use App\Services\Builders\Cobranzas\CobrosFacturas\Contracts\CollectionRegistrationBuilderInterface;
use App\Services\Cobranzas\CobrosFacturas\Contracts\CollectionGpSenderServiceInterface;

class CollectionGpSenderService implements CollectionGpSenderServiceInterface
{
    private const SUCCESS_MESSAGES = [
        'Registro de cobro guardado correctamente.',
        'El número de recibo de cobro enviado ya existe para esta empresa.',
    ];

    public function __construct(
        protected readonly CollectionRegistrationBuilderInterface $collectBuilder,
        protected readonly ApiBillingService $api
    ) {}
    public function sendCollectionToGp(int $collectionId): array
    {
        $collection     = GpCobro::find($collectionId);
        $customer       = GpCliente::find($collection->cliente_id);
        $box            = GpCaja::find($collection->caja_id);
        $userCollection = GpUsuario::find($collection->user_id);

        return [
            'efectivo'      => $this->sendCashCollectionToGp($userCollection, $collection, $customer, $box),
            'depositos'     => $this->sendDepositCollectionToGp($userCollection, $collection, $customer, $box),
            'tarjeta'       => $this->sendCardCollectionToGp($userCollection, $collection, $customer, $box),
            'aplicaciones'  => $this->sendApplicationCollectionToGp($userCollection, $collection, $customer),
            'retencion'     => $this->sendRetentionCollectionToGp($userCollection, $collection, $customer, $box),
        ];
        // throw new \LogicException('CollectionGpSenderService::sendCollectionToGp esta pendiente de implementar.');
    }


    // envio de efectivo a sqlServer
    private function sendCashCollectionToGp(
        GpUsuario $userCollection,
        GpCobro $collection,
        GpCliente $customer,
        GpCaja $cashBox
    ) {
        $cashPayment  = GpCobrosEfectivoMonto::where('cobro_id', $collection->id)
            ->where('monto', '>', 0)
            ->where('enviado_gp', false)
            ->first();

        if (!$cashPayment) {
            return null;
        }
        $appliedInvoices        = GpCobrosEfectivoFactura::where('cobro_id', $collection->id)->get();
        $appliedAmount          = (float) round($appliedInvoices->sum('monto'), 2);
        $unappliedAmount        = (float) round($cashPayment->monto - $appliedAmount, 2);
        $receiptNumber          = $this->formatReceiptNumber(
            $collection->numero_recibo,
            CollectTypeEnum::EFECTIVO_E->value
        );
        $headerPayload  = $this->collectBuilder->payloadCollectionHeader(
            $collection,
            $receiptNumber,
            $customer->CLI_RUC,
            $cashBox->CAJA_CODIGO,
            $cashPayment->monto,
            $unappliedAmount,
        );

        $invoiceDatailPayload = $appliedInvoices
            ->map(function (GpCobrosEfectivoFactura $invoice) use ($receiptNumber, $customer) {
                return $this->collectBuilder->payloadCollectionDetailInvoices(
                    $receiptNumber,
                    $customer->CLI_RUC,
                    $invoice->id_factura,
                    $invoice->monto,
                    $invoice->observacion
                );
            })
            ->values()
            ->all();

        $response   = $this->sendCollectionToApi($headerPayload, $invoiceDatailPayload);
        $message    = $this->extractResponseMessage($response);
        $sent       = $this->isSuccessfulGpResponse($response, $message);

        $cashPayment->update([
            'mensaje_ws'    => $this->limitForColumn($message, 300),
            'enviado_gp'    => $sent,
        ]);

        if (!$sent) {
            $userCollection->notify((new UserNotification(
                'Cobro efectivo no enviado a GP',
                $this->formatCollectionNotificationMessage($collection, 'efectivo', $message)
            )));

            return [
                'enviado_gp' => false,
                'mensaje' => $message,
            ];
        }

        $userCollection->notify((new UserNotification(
            'Cobro efectivo enviado a GP',
            $this->formatCollectionNotificationMessage($collection, 'efectivo', $message)
        )));

        return [
            'enviado_gp'    => true,
            'mensaje'       => $message,
        ];
    }




    /** enviar  deposito a sqlServer*/
    private function sendDepositCollectionToGp(
        GpUsuario $userCollection,
        GpCobro $collection,
        GpCliente $customer,
        GpCaja $cashBox
    ) {

        $depositAmount = GpCobrosDeposito::with('facturas')->where('cobro_id', $collection->id)
            ->where('monto', '>', 0)
            ->where('enviado_gp', false)
            ->where('estado', 'revisado')->get();

        if ($depositAmount->isEmpty()) {
            return null;
        }
        $alldeposits = [];
        foreach ($depositAmount as $deposit) {
            $appliedInvoices    = $deposit->facturas;
            $appliedAmount      = (float) round($appliedInvoices->sum('monto'), 2);
            $unappliedAmount    = (float) round($deposit->monto - $appliedAmount, 2);
            $receiptNumber      = $this->formatReceiptNumber(
                $collection->numero_recibo,
                CollectTypeEnum::DEPOSITO->value
            );

            $headerPayloadDeposit = $this->collectBuilder->payloadCollectionDepositHeader(
                $collection,
                $deposit,
                $receiptNumber,
                $customer->CLI_RUC,
                $cashBox->CAJA_CODIGO,
                $unappliedAmount
            );
            $invoiceDatailPayload = $appliedInvoices
                ->map(function (GpCobrosDepositosFacturas $invoice) use ($receiptNumber, $customer) {
                    return $this->collectBuilder->payloadCollectionDetailInvoices(
                        $receiptNumber,
                        $customer->CLI_RUC,
                        $invoice->id_factura,
                        $invoice->monto,
                        $invoice->observacion
                    );
                })
                ->values()
                ->all();
            $response   = $this->sendCollectionToApi($headerPayloadDeposit, $invoiceDatailPayload);
            $message    = $this->extractResponseMessage($response);
            $sent       = $this->isSuccessfulGpResponse($response, $message);
            $deposit->update([
                'mensaje_ws'    => $this->limitForColumn($message, 300),
                'enviado_gp'    => $sent,
            ]);
            if (!$sent) {
                $userCollection->notify((new UserNotification(
                    'Deposito no enviado a GP',
                    $this->formatCollectionNotificationMessage($collection, 'deposito', $message)
                )));

                $alldeposits[] = [
                    'enviado_gp' => false,
                    'mensaje' => $message,
                ];

                continue;
            }

            $userCollection->notify((new UserNotification(
                'Deposito enviado a GP',
                $this->formatCollectionNotificationMessage($collection, 'deposito', $message)
            )));

            $alldeposits[] = [
                'enviado_gp' => $sent,
                'mensaje' => $message,
            ];
        }

        return $alldeposits;
    }

    /** enviar tarjeta a sqlServer */
    private function sendCardCollectionToGp(
        GpUsuario $userCollection,
        GpCobro $collection,
        GpCliente $customer,
        GpCaja $cashBox
    ) {
        $cardAmount = GpCobrosTarjetaMonto::where('cobro_id', $collection->id)
            ->where('monto', '>', 0)
            ->where('enviado_gp', false)
            ->first();
        if (!$cardAmount) {
            return null;
        }
        $appliedInvoices        = GpCobrosTarjetaFactura::where('cobro_id', $collection->id)->get();
        $appliedAmount          = (float) round($appliedInvoices->sum('monto'), 2);
        $unappliedAmount        = (float) round($cardAmount->monto - $appliedAmount, 2);
        $receiptNumber          = $this->formatReceiptNumber(
            $collection->numero_recibo,
            CollectTypeEnum::TARJETA->value
        );
        $headerPayload  = $this->collectBuilder->payloadCollectionCardHeader(
            $collection,
            $cardAmount,
            $receiptNumber,
            $customer->CLI_RUC,
            $cashBox->CAJA_CODIGO,
            $unappliedAmount,
        );
        $invoiceDatailPayload = $appliedInvoices
            ->map(function (GpCobrosTarjetaFactura $invoice) use ($receiptNumber, $customer) {
                return $this->collectBuilder->payloadCollectionDetailInvoices(
                    $receiptNumber,
                    $customer->CLI_RUC,
                    $invoice->id_factura,
                    $invoice->monto,
                    $invoice->observacion
                );
            })
            ->values()
            ->all();

        $response   = $this->sendCollectionToApi($headerPayload, $invoiceDatailPayload);
        $message    = $this->extractResponseMessage($response);
        $sent       = $this->isSuccessfulGpResponse($response, $message);

        $cardAmount->update([
            'mensaje_ws'    => $this->limitForColumn($message, 300),
            'enviado_gp'    => $sent,
        ]);

        if (!$sent) {
            $userCollection->notify((new UserNotification(
                'Cobro con tarjeta no enviado a GP',
                $this->formatCollectionNotificationMessage($collection, 'tarjeta', $message)
            )));

            return [
                'enviado_gp' => false,
                'mensaje' => $message,
            ];
        }

        $userCollection->notify((new UserNotification(
            'Cobro con tarjeta enviado a GP',
            $this->formatCollectionNotificationMessage($collection, 'tarjeta', $message)
        )));

        return [
            'enviado_gp'    => true,
            'mensaje'       => $message,
        ];
    }


    /**
     *  Envio de aplicaciones a sqlServer
     */
    private function sendApplicationCollectionToGp(
        GpUsuario $userCollection,
        GpCobro $collection,
        GpCliente $customer,
    ) {
        $applicationAmount = GpCobrosAplicacionMonto::with('facturasAplicadas')->where('cobro_id', $collection->id)
            ->where('monto', '>', 0)
            ->where('enviado_gp', false)
            ->get();
        if ($applicationAmount->isEmpty()) {
            return null;
        }

        $sentApplications = [];

        foreach ($applicationAmount as $application) {
            $appliedInvoices = $application->facturasAplicadas;
            $receiptNumber = trim((string) $application->cobro);

            if ($receiptNumber === '' || $appliedInvoices->isEmpty()) {
                $message = $receiptNumber === ''
                    ? 'La aplicacion no tiene numero de cobro para enviar a GP.'
                    : 'La aplicacion no tiene facturas aplicadas para enviar a GP.';

                $application->update([
                    'codigo_ws'  => $this->limitForColumn('failed', 20),
                    'mensaje_ws' => $this->limitForColumn($message, 300),
                    'enviado_gp' => false,
                ]);

                $userCollection->notify((new UserNotification(
                    'Aplicacion de cobro no enviada a GP',
                    $this->formatCollectionNotificationMessage($collection, 'aplicacion', $message)
                )));

                $sentApplications[] = [
                    'id'         => $application->id,
                    'cobro'      => $receiptNumber,
                    'enviado_gp' => false,
                    'mensaje'    => $message,
                ];

                continue;
            }

            $invoiceResults = [];

            foreach ($appliedInvoices as $invoice) {
                $payload = $this->collectBuilder->payloadCollectionDetailInvoices(
                    $receiptNumber,
                    $customer->CLI_RUC,
                    $invoice->id_factura,
                    $invoice->monto,
                    $invoice->observacion
                );

                $response = $this->sendApplicationCollectionToApi($payload);
                $message = $this->extractResponseMessage($response);
                $sent = $this->isSuccessfulGpResponse($response, $message);

                $invoiceResults[] = [
                    'factura'    => $invoice->id_factura,
                    'enviado_gp' => $sent,
                    'mensaje'    => $message,
                ];
            }

            $applicationSent = !in_array(false, array_column($invoiceResults, 'enviado_gp'), true);
            $message = $this->formatApplicationResultMessage($invoiceResults);

            $application->update([
                'codigo_ws'  => $this->limitForColumn($applicationSent ? 'success' : 'failed', 20),
                'mensaje_ws' => $this->limitForColumn($message, 300),
                'enviado_gp' => $applicationSent,
            ]);

            if (!$applicationSent) {
                $userCollection->notify((new UserNotification(
                    'Aplicacion de cobro no enviada a GP',
                    $this->formatCollectionNotificationMessage($collection, 'aplicacion', $message)
                )));

                $sentApplications[] = [
                    'id'         => $application->id,
                    'cobro'      => $receiptNumber,
                    'enviado_gp' => false,
                    'mensaje'    => $message,
                    'facturas'   => $invoiceResults,
                ];

                continue;
            }

            $userCollection->notify((new UserNotification(
                'Aplicacion de cobro enviada a GP',
                $this->formatCollectionNotificationMessage($collection, 'aplicacion', $message)
            )));

            $sentApplications[] = [
                'id'         => $application->id,
                'cobro'      => $receiptNumber,
                'enviado_gp' => true,
                'mensaje'    => $message,
                'facturas'   => $invoiceResults,
            ];
        }

        return $sentApplications;
    }


    /** envio de retenciones a sqlServer */
    private function sendRetentionCollectionToGp(
        GpUsuario $userCollection,
        GpCobro $collection,
        GpCliente $customer,
        GpCaja $cashBox
    ) {
        $retentions = GpCobrosRetenciones::where('cobro_id', $collection->id)->get();
        $retentionTypes = $this->retentionPayloadDefinitions();
        $sentRetentions = [];

        foreach ($retentions as $retention) {
            foreach ($retentionTypes as $retentionType) {
                $amount = (float) $retention->{$retentionType['amount_field']};

                if ($amount <= 0 || $retention->{$retentionType['sent_field']}) {
                    continue;
                }

                $payload = $this->buildRetentionPayload(
                    $collection,
                    $retention,
                    $customer,
                    $cashBox,
                    $retentionType,
                    $amount
                );

                $response = $this->sendRetencionCollectionToGp($payload['cabecera'], $payload['detalle']);
                $message = $this->extractResponseMessage($response);
                $sent = $this->isSuccessfulGpResponse($response, $message);

                $retention->update([
                    $retentionType['message_field'] => $this->limitForColumn($message, 300),
                    $retentionType['sent_field']    => $sent,
                ]);

                if (!$sent) {
                    $userCollection->notify((new UserNotification(
                        'Retencion ' . $retentionType['label'] . ' no enviada a GP',
                        $this->formatRetentionNotificationMessage($collection, $retention, $retentionType['label'], $message)
                    )));

                    $sentRetentions[] = [
                        'id'         => $retention->id,
                        'tipo'       => $retentionType['key'],
                        'factura'    => $retention->ret_factura,
                        'enviado_gp' => false,
                        'mensaje'    => $message,
                    ];

                    continue;
                }

                $userCollection->notify((new UserNotification(
                    'Retencion ' . $retentionType['label'] . ' enviada a GP',
                    $this->formatRetentionNotificationMessage($collection, $retention, $retentionType['label'], $message)
                )));

                $sentRetentions[] = [
                    'id'         => $retention->id,
                    'tipo'       => $retentionType['key'],
                    'factura'    => $retention->ret_factura,
                    'enviado_gp' => true,
                    'mensaje'    => $message,
                ];
            }
        }

        return empty($sentRetentions) ? null : $sentRetentions;
    }

    private function buildRetentionPayload(
        GpCobro $collection,
        GpCobrosRetenciones $retention,
        GpCliente $customer,
        GpCaja $cashBox,
        array $retentionType,
        float $amount
    ): array {
        $receiptNumber = $this->formatReceiptNumberRetention(
            $collection->numero_recibo,
            $retentionType['receipt_type'],
            $retention->id
        );

        return [
            'cabecera' => $this->collectBuilder->payloadCollectionRetentionHeader(
                $collection,
                $retention,
                $receiptNumber,
                $retentionType['collection_type'],
                $customer->CLI_RUC,
                $cashBox->CAJA_CODIGO,
                $amount,
                (float) $retention->{$retentionType['percentage_field']}
            ),
            'detalle'   => $this->collectBuilder->payloadCollectionDetailInvoices(
                $receiptNumber,
                $customer->CLI_RUC,
                $retention->ret_factura,
                $amount
            ),
        ];
    }

    private function retentionPayloadDefinitions(): array
    {
        return [
            [
                'key'              => 'iva',
                'label'            => 'IVA',
                'amount_field'     => 'ret_iva',
                'percentage_field' => 'ret_porcentaje_iva',
                'message_field'    => 'mensaje_ws_iva',
                'sent_field'       => 'enviado_iva_gp',
                'receipt_type'     => CollectTypeEnum::RETENTION_IVA->value,
                'collection_type'  => CollectTypeEnum::RETENCION_V->value,
            ],
            [
                'key'              => 'renta',
                'label'            => 'RENTA',
                'amount_field'     => 'ret_renta',
                'percentage_field' => 'ret_porcentaje_renta',
                'message_field'    => 'mensaje_ws_renta',
                'sent_field'       => 'enviado_renta_gp',
                'receipt_type'     => CollectTypeEnum::RETENTION_REN->value,
                'collection_type'  => CollectTypeEnum::RETENCION_F->value,
            ],
            [
                'key'              => 'iva_srv',
                'label'            => 'IVA SRV',
                'amount_field'     => 'ret_iva_srv',
                'percentage_field' => 'ret_porcentaje_iva_srv',
                'message_field'    => 'mensaje_ws_iva_srv',
                'sent_field'       => 'enviado_iva_srv_gp',
                'receipt_type'     => CollectTypeEnum::RETENTION_ISRV->value,
                'collection_type'  => CollectTypeEnum::RETENCION_V->value,
            ],
            [
                'key'              => 'renta_srv',
                'label'            => 'RENTA SRV',
                'amount_field'     => 'ret_renta_srv',
                'percentage_field' => 'ret_porcentaje_renta_srv',
                'message_field'    => 'mensaje_ws_renta_srv',
                'sent_field'       => 'enviado_renta_srv_gp',
                'receipt_type'     => CollectTypeEnum::RETENTION_RSRV->value,
                'collection_type'  => CollectTypeEnum::RETENCION_F->value,
            ],
        ];
    }

    private function sendCollectionToApi(array $headerPayload, array $invoiceDatailPayload)
    {
        return $this->api->registerCashCollectionInGp([
            'cabecera'  => $headerPayload,
            'detalle'   => $invoiceDatailPayload
        ]);
    }

    private function sendRetencionCollectionToGp(array $header, array $detail)
    {
        return $this->api->registerRetentionCollectionInGp([
            'cabecera'  => $header,
            'detalle'   => $detail
        ]);
    }


    private function sendApplicationCollectionToApi(array $applicationPayload)
    {
        return $this->api->registerApplicationCollectionInGp($applicationPayload);
    }

    private function formatReceiptNumber(string $receiptNumber, string $type)
    {
        return  sprintf('%s-%s', $receiptNumber, $type);
    }

    private function formatReceiptNumberRetention(
        string $receiptNumber,
        string $type,
        int $id
    ): string {
        return sprintf('%s-%s-%d', $receiptNumber, $type, $id);
    }

    private function extractResponseMessage(mixed $response): string
    {
        if (is_string($response)) {
            return $response;
        }

        $message = data_get($response, 'data')
            ?? data_get($response, 'message')
            ?? data_get($response, 'error')
            ?? 'Respuesta sin mensaje de GP';

        if (is_array($message)) {
            $message = data_get($message, 'mensaje')
                ?? data_get($message, 'message')
                ?? json_encode($message);
        }

        return (string) $message;
    }

    private function formatCollectionNotificationMessage(GpCobro $collection, string $type, string $message): string
    {
        return "Numero de cobro: {$collection->numero_recibo}. Tipo: {$type}. Mensaje GP: {$message}";
    }

    private function formatRetentionNotificationMessage(
        GpCobro $collection,
        GpCobrosRetenciones $retention,
        string $type,
        string $message
    ): string {
        return "Numero de cobro: {$collection->numero_recibo}. Tipo: retencion {$type}. Factura: {$retention->ret_factura}. Comprobante: {$retention->ret_comprobante}. Mensaje GP: {$message}";
    }

    private function formatApplicationResultMessage(array $invoiceResults): string
    {
        $messages = [];

        foreach ($invoiceResults as $result) {
            if (!$result['enviado_gp']) {
                $messages[] = "Factura {$result['factura']}: {$result['mensaje']}";
            }
        }

        if (!empty($messages)) {
            return implode(' | ', $messages);
        }

        foreach ($invoiceResults as $result) {
            $messages[] = $result['mensaje'];
        }

        return implode(' | ', array_unique($messages));
    }


    private function isSuccessfulGpResponse(mixed $response, string $message): bool
    {
        $success = data_get($response, 'success');
        if (is_bool($success)) {
            return $success;
        }

        $status = data_get($response, 'status');
        if (is_bool($status)) {
            return $status;
        }

        if (is_string($status)) {
            return in_array(strtolower(trim($status)), ['success', 'ok', 'true'], true);
        }

        $message = preg_replace('/^C.digo\s+\d+\s*:\s*/iu', '', $message) ?? $message;

        return in_array($message, self::SUCCESS_MESSAGES, true);
    }

    private function limitForColumn(string $value, int $length): string
    {
        return mb_substr($value, 0, $length);
    }
}
