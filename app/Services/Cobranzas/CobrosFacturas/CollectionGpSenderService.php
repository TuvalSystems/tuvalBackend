<?php

namespace App\Services\Cobranzas\CobrosFacturas;

use App\Enums\Cobranzas\CobrosFacturas\CollectTypeEnum;
use App\Models\Cobranzas\GpCobro;
use App\Models\Cobranzas\GpCobrosDeposito;
use App\Models\Cobranzas\GpCobrosDepositosFacturas;
use App\Models\Cobranzas\GpCobrosEfectivoFactura;
use App\Models\Cobranzas\GpCobrosEfectivoMonto;
use App\Models\GpCaja;
use App\Models\GpCliente;
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
            'efectivo' => $this->sendCashCollectionToGp($userCollection, $collection, $customer, $box),
            'depositos' => $this->sendDepositCollectionToGp($userCollection, $collection, $customer, $box),
            'tarjeta'   => $this->sendCardCollectionToGp($userCollection, $collection, $customer, $box),
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

        $response   = $this->sendCashCollectionToApi($headerPayload, $invoiceDatailPayload);
        $message    = $this->extractResponseMessage($response);
        $sent       = $this->isSuccessfulGpResponse($response, $message);

        $cashPayment->update([
            'codigo_ws'     => $this->extractResponseStatus($response, $sent),
            'mensaje_ws'    => $this->limitForColumn($message, 300),
            'enviado_gp'    => $sent,
        ]);

        if (!$sent) {
            $userCollection->notify((new UserNotification(
                'Error al ingresar cobro efectivo',
                $this->formatCollectionNotificationMessage($collection, 'efectivo', $message)
            )));

            return [
                'enviado_gp' => false,
                'mensaje' => $message,
            ];
        }

        $userCollection->notify((new UserNotification(
            'Registro de cobro efectivo',
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
            $response   = $this->sendCashCollectionToApi($headerPayloadDeposit, $invoiceDatailPayload);
            $message    = $this->extractResponseMessage($response);
            $sent       = $this->isSuccessfulGpResponse($response, $message);
            $deposit->update([
                'codigo_ws'     => $this->extractResponseStatus($response, $sent),
                'mensaje_ws'    => $this->limitForColumn($message, 300),
                'enviado_gp'    => $sent,
            ]);
            if (!$sent) {
                $userCollection->notify((new UserNotification(
                    'Error al ingresar cobro deposito',
                    $this->formatCollectionNotificationMessage($collection, 'deposito', $message)
                )));

                $alldeposits[] = [
                    'enviado_gp' => false,
                    'mensaje' => $message,
                ];

                continue;
            }

            $userCollection->notify((new UserNotification(
                'Registro de cobro deposito',
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

        $response   = $this->sendCashCollectionToApi($headerPayload, $invoiceDatailPayload);
        $message    = $this->extractResponseMessage($response);
        $sent       = $this->isSuccessfulGpResponse($response, $message);

        $cardAmount->update([
            'codigo_ws'     => $this->extractResponseStatus($response, $sent),
            'mensaje_ws'    => $this->limitForColumn($message, 300),
            'enviado_gp'    => $sent,
        ]);

        if (!$sent) {
            $userCollection->notify((new UserNotification(
                'Error al ingresar cobro tarjeta',
                $this->formatCollectionNotificationMessage($collection, 'tarjeta', $message)
            )));

            return [
                'enviado_gp' => false,
                'mensaje' => $message,
            ];
        }

        $userCollection->notify((new UserNotification(
            'Registro de cobro tarjeta',
            $this->formatCollectionNotificationMessage($collection, 'tarjeta', $message)
        )));

        return [
            'enviado_gp'    => true,
            'mensaje'       => $message,
        ];
    }






    private function sendCashCollectionToApi(array $headerPayload, array $invoiceDatailPayload)
    {
        return $this->api->registerCashCollectionInGp([
            'cabecera'  => $headerPayload,
            'detalle'   => $invoiceDatailPayload
        ]);
    }


    private function formatReceiptNumber(string $receiptNumber, string $type)
    {
        return  sprintf('%s-%s', $receiptNumber, $type);
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

    private function extractResponseStatus(mixed $response, bool $sent): string
    {
        $success = data_get($response, 'success');

        if (is_bool($success)) {
            return $this->limitForColumn($success ? 'success' : 'failed', 20);
        }

        $status = data_get($response, 'status');

        if ($status !== null && $status !== '') {
            return $this->limitForColumn((string) $status, 20);
        }

        return $this->limitForColumn($sent ? 'success' : 'failed', 20);
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
