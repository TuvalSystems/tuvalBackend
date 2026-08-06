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
            // 'efectivo' => $this->sendCashCollectionToGp($userCollection, $collection, $customer, $box),
            'depositos' => $this->sendDepositCollectionToGp($userCollection, $collection, $customer, $box),
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
            'codigo_ws'     => $this->extractResponseCode($response, $sent),
            'mensaje_ws'    => $this->limitForColumn($message, 300),
            'enviado_gp'    => $sent,
        ]);

        if (!$sent) {
            $userCollection->notify((new UserNotification(
                'Error al ingresar un cobro de efectivo',
                $message . " al enviar el efectivo de  {$collection->numero_recibo}"
            )));

            return [
                'enviado_gp' => false,
                'mensaje' => $message,
            ];
        }

        $userCollection->notify((new UserNotification(
            'Registro de Efectivo',
            $message
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
            $depPayload = [
                'cabecera' =>  $headerPayloadDeposit,
                'facturas' =>  $invoiceDatailPayload,
            ];
            $alldeposits[] = $depPayload;
        }

        return $alldeposits;
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

    private function extractResponseCode(mixed $response, bool $sent): string
    {
        $code = data_get($response, 'codigo')
            ?? data_get($response, 'code')
            ?? data_get($response, 'status')
            ?? ($sent ? '1' : '0');

        if (is_bool($code)) {
            $code = $code ? '1' : '0';
        }

        return $this->limitForColumn((string) $code, 20);
    }

    private function isSuccessfulGpResponse(mixed $response, string $message): bool
    {
        if (in_array($message, self::SUCCESS_MESSAGES, true)) {
            return true;
        }

        $success = data_get($response, 'success');
        if ($success !== null) {
            return $success === true;
        }

        return data_get($response, 'status') === true;
    }

    private function limitForColumn(string $value, int $length): string
    {
        return mb_substr($value, 0, $length);
    }
}
