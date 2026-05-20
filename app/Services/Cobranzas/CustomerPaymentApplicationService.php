<?php

namespace App\Services\Cobranzas;

use App\Repositories\Cobranzas\Contracts\WebInvoiceApplicationRepositoryInterface;
use App\Services\Api\Cobros\ApiBillingService;
use App\Services\Cobranzas\Contracts\CustomerPaymentApplicationServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class CustomerPaymentApplicationService implements CustomerPaymentApplicationServiceInterface
{

    //serivicio dedicado para la verificacion de aplicación de pagos antes de registrar un cobro
    public function __construct(protected ApiBillingService $api, protected readonly WebInvoiceApplicationRepositoryInterface $applyRepository) {}



    public function validateIdentifyPaymentInvoices(array $payload)
    {


        $fechaCobro = now();
        $desde                              = $fechaCobro->copy()->startOfDay()->toDateTimeString();
        $hasta                              = $fechaCobro->copy()->endOfDay()->toDateTimeString();
        $payload['retenciones']             = $this->processRetenciones(data_get($payload, 'retenciones', []),  $desde, $hasta);
        $payload['ingreso_efectivo']        = $this->processIngresoEfectivo(data_get($payload, 'ingreso_efectivo', []), $desde, $hasta);
        $payload['ingreso_tarjeta']         = $this->processIngresoTarjeta(data_get($payload, 'ingreso_tarjeta', []), $desde, $hasta);
        $payload['ingreso_depositos']       = $this->processIngresoDeposito(data_get($payload, 'ingreso_depositos', []), $desde, $hasta);
        $payload['ingreso_aplicaciones']    = $this->processIngresoAplicaciones(data_get($payload, 'ingreso_aplicaciones', []), $desde, $hasta);
        $payload['ingreso_cheques']         = $this->processIngresoCheques(data_get($payload, 'ingreso_cheques', []), $desde, $hasta);
        $payload['hasGlobalExcess']         = $this->hasAnyExcess($payload);
        return $payload;
    }

    protected function processRetenciones(array $retenciones, string $desde, string $hasta)
    {
        foreach ($retenciones as &$ret) {
            $factura                = trim(data_get($ret, 'ret_factura'));
            $valorIngresado         = $this->money(data_get($ret, 'total', 0));
            $saldoFactura           = $this->money(data_get($ret, 'ret_saldo_factura', 0));
            $order                  = $this->applyRepository->getOrderWithDevolution($factura, $desde, $hasta);

            $payments               = collect($this->getPaymentApplyGp($order->factura, $order->CLI_RUC));
            $sumPayments            = $this->money($payments->sum('collectionAmount'));

            //filtros de documentos
            $devPaymentDocs         = $this->filterGroupPayment($payments);
            $othersPaymentDocs      = $this->filterGroupDocumentsPayments($payments);

            //sumatoria de lo pendiente
            $sumPendingWeb          = $this->sumPendingWebNotAccountedByInvoice($factura, $othersPaymentDocs, $desde, $hasta);
            //sumatoria  de devoluciones pendeintes de procesar
            $sumDev                 = $this->money(collect($this->getDevolutions($order->devolucionesBloqueantes, $devPaymentDocs))->sum('total'));



            $validation = $this->buildInvoiceValidation(
                $valorIngresado,
                $saldoFactura,
                $sumPayments,
                $sumPendingWeb,
                $sumDev,
                false
            );

            $ret = array_merge($ret, $validation);
        }
        unset($ret);
        return $retenciones;
    }


    protected function processIngresoEfectivo(array $data, string $desde, string $hasta)
    {
        $dataInvoices = data_get($data, 'facturas', []);

        if (empty($dataInvoices)) {
            return $data;
        }

        foreach ($dataInvoices as &$ing) {
            $factura            = trim(data_get($ing, 'ECD_DET_NUM_DOC', ''));
            $valorIngresado     = $this->money(data_get($ing, 'aplicar_monto_efectivo', 0));
            $saldoFactura       = $this->money(data_get($ing, 'ECD_DET_SALDO_CLIENTE', 0));
            $order              = $this->applyRepository->getOrderWithDevolution($factura, $desde, $hasta);

            $payments               = collect($this->getPaymentApplyGp($order->factura, $order->CLI_RUC));
            $sumPayments            = $this->money($payments->sum('collectionAmount'));
            //filtros de documentos
            $devPaymentDocs         = $this->filterGroupPayment($payments);
            $othersPaymentDocs      = $this->filterGroupDocumentsPayments($payments);

            //sumatoria de lo pendiente
            $sumPendingWeb          = $this->sumPendingWebNotAccountedByInvoice($factura, $othersPaymentDocs, $desde, $hasta);
            //sumatoria  de devoluciones pendeintes de procesar
            $sumDev                 = $this->money(collect($this->getDevolutions($order->devolucionesBloqueantes, $devPaymentDocs))->sum('total'));

            $validation = $this->buildInvoiceValidation(
                $valorIngresado,
                $saldoFactura,
                $sumPayments,
                $sumPendingWeb,
                $sumDev,
                true
            );

            $ing = array_merge($ing, $validation);
        }
        unset($ing);

        $data['facturas'] = $dataInvoices;

        return $data;
    }
    protected function processIngresoTarjeta(array $data, string $desde, string $hasta)
    {
        $dataInvoices = data_get($data, 'facturas', []);

        if (empty($dataInvoices)) {
            return $data;
        }

        foreach ($dataInvoices as &$ing) {
            $factura            = trim(data_get($ing, 'ECD_DET_NUM_DOC', ''));
            $valorIngresado     = $this->money(data_get($ing, 'aplicar_monto_tarjeta', 0));
            $saldoFactura       = $this->money(data_get($ing, 'ECD_DET_SALDO_CLIENTE', 0));
            $order              = $this->applyRepository->getOrderWithDevolution($factura, $desde, $hasta);

            $payments               = collect($this->getPaymentApplyGp($order->factura, $order->CLI_RUC));
            $sumPayments            = $this->money($payments->sum('collectionAmount'));
            //filtros de documentos
            $devPaymentDocs         = $this->filterGroupPayment($payments);
            $othersPaymentDocs      = $this->filterGroupDocumentsPayments($payments);

            //sumatoria de lo pendiente
            $sumPendingWeb          = $this->sumPendingWebNotAccountedByInvoice($factura, $othersPaymentDocs, $desde, $hasta);
            //sumatoria  de devoluciones pendeintes de procesar
            $sumDev                 = $this->money(collect($this->getDevolutions($order->devolucionesBloqueantes, $devPaymentDocs))->sum('total'));

            $validation = $this->buildInvoiceValidation(
                $valorIngresado,
                $saldoFactura,
                $sumPayments,
                $sumPendingWeb,
                $sumDev,
                true
            );

            $ing = array_merge($ing, $validation);
        }
        unset($ing);

        $data['facturas'] = $dataInvoices;

        return $data;
    }
    protected function processIngresoDeposito(array $data, string $desde, string $hasta)
    {
        foreach ($data as  &$typeDeposits) {

            $dataInvoices = data_get($typeDeposits, 'facturas', []);

            if (empty($dataInvoices)) {
                continue;
            }

            foreach ($dataInvoices as &$ing) {
                $factura            = trim(data_get($ing, 'ECD_DET_NUM_DOC', ''));
                $valorIngresado     = $this->money(data_get($ing, 'aplicar_monto_deposito', 0));
                $saldoFactura       = $this->money(data_get($ing, 'ECD_DET_SALDO_CLIENTE', 0));
                $order              = $this->applyRepository->getOrderWithDevolution($factura, $desde, $hasta);

                $payments               = collect($this->getPaymentApplyGp($order->factura, $order->CLI_RUC));
                $sumPayments            = $this->money($payments->sum('collectionAmount'));
                //filtros de documentos
                $devPaymentDocs         = $this->filterGroupPayment($payments);
                $othersPaymentDocs      = $this->filterGroupDocumentsPayments($payments);

                //sumatoria de lo pendiente
                $sumPendingWeb          = $this->sumPendingWebNotAccountedByInvoice($factura, $othersPaymentDocs, $desde, $hasta);
                //sumatoria  de devoluciones pendeintes de procesar
                $sumDev                 = $this->money(collect($this->getDevolutions($order->devolucionesBloqueantes, $devPaymentDocs))->sum('total'));
                $validation = $this->buildInvoiceValidation(
                    $valorIngresado,
                    $saldoFactura,
                    $sumPayments,
                    $sumPendingWeb,
                    $sumDev,
                    true
                );

                $ing = array_merge($ing, $validation);
            }
            unset($ing);

            $typeDeposits['facturas'] = $dataInvoices;
        }
        unset($typeDeposits);

        return $data;
    }

    protected function processIngresoAplicaciones(array $data, string $desde, string $hasta)
    {
        foreach ($data as  &$typeDeposits) {

            $dataInvoices = data_get($typeDeposits, 'facturas', []);

            if (empty($dataInvoices)) {
                continue;
            }

            foreach ($dataInvoices as &$ing) {
                $factura            = trim(data_get($ing, 'ECD_DET_NUM_DOC', ''));
                $valorIngresado     = $this->money(data_get($ing, 'aplicar_monto_aplicacion', 0));
                $saldoFactura       = $this->money(data_get($ing, 'ECD_DET_SALDO_CLIENTE', 0));
                $order              = $this->applyRepository->getOrderWithDevolution($factura, $desde, $hasta);

                $payments               = collect($this->getPaymentApplyGp($order->factura, $order->CLI_RUC));
                $sumPayments            = $this->money($payments->sum('collectionAmount'));
                //filtros de documentos
                $devPaymentDocs         = $this->filterGroupPayment($payments);
                $othersPaymentDocs      = $this->filterGroupDocumentsPayments($payments);

                //sumatoria de lo pendiente
                $sumPendingWeb          = $this->sumPendingWebNotAccountedByInvoice($factura, $othersPaymentDocs, $desde, $hasta);
                //sumatoria  de devoluciones pendeintes de procesar
                $sumDev                 = $this->money(collect($this->getDevolutions($order->devolucionesBloqueantes, $devPaymentDocs))->sum('total'));
                $validation = $this->buildInvoiceValidation(
                    $valorIngresado,
                    $saldoFactura,
                    $sumPayments,
                    $sumPendingWeb,
                    $sumDev,
                    true
                );

                $ing = array_merge($ing, $validation);
            }
            unset($ing);

            $typeDeposits['facturas'] = $dataInvoices;
        }
        unset($typeDeposits);

        return $data;
    }

    protected function processIngresoCheques(array $data, string $desde, string $hasta)
    {
        $dataInvoices = data_get($data, 'facturas', []);

        if (empty($dataInvoices)) {
            return $data;
        }

        foreach ($dataInvoices as &$ing) {
            $factura            = trim(data_get($ing, 'ECD_DET_NUM_DOC', ''));
            $valorIngresado     = $this->money(data_get($ing, 'aplicar_monto_cheque', 0));
            $saldoFactura       = $this->money(data_get($ing, 'ECD_DET_SALDO_CLIENTE', 0));
            $order              = $this->applyRepository->getOrderWithDevolution($factura, $desde, $hasta);

            $payments               = collect($this->getPaymentApplyGp($order->factura, $order->CLI_RUC));
            $sumPayments            = $this->money($payments->sum('collectionAmount'));
            //filtros de documentos
            $devPaymentDocs         = $this->filterGroupPayment($payments);
            $othersPaymentDocs      = $this->filterGroupDocumentsPayments($payments);

            //sumatoria de lo pendiente
            $sumPendingWeb          = $this->sumPendingWebNotAccountedByInvoice($factura, $othersPaymentDocs, $desde, $hasta);
            //sumatoria  de devoluciones pendeintes de procesar
            $sumDev                 = $this->money(collect($this->getDevolutions($order->devolucionesBloqueantes, $devPaymentDocs))->sum('total'));
            $validation = $this->buildInvoiceValidation(
                $valorIngresado,
                $saldoFactura,
                $sumPayments,
                $sumPendingWeb,
                $sumDev,
                true
            );

            $ing = array_merge($ing, $validation);
        }
        unset($ing);

        $data['facturas'] = $dataInvoices;

        return $data;
    }



    protected function buildInvoiceValidation(
        int|float|string|null $valorIngresado,
        int|float|string|null $saldoFactura,
        int|float|string|null $sumPayments,
        int|float|string|null $sumPendingWeb,
        int|float|string|null $sumDev,
        bool $saldoIncluyeAplicacionActual = true
    ): array {
        $valorIngresado = $this->money($valorIngresado);
        $saldoFactura   = $this->money($saldoFactura);
        $sumPayments    = $this->money($sumPayments);
        $sumPendingWeb  = $this->money($sumPendingWeb);
        $sumDev         = $this->money($sumDev);

        $totalNoContabilizado = $this->money($sumPendingWeb + $sumDev);

        /*
     * Cuando viene de ECD_DET_SALDO_CLIENTE, normalmente ya viene
     * después de aplicar el monto actual.
     */
        $saldoBase = $saldoIncluyeAplicacionActual
            ? $this->money($saldoFactura + $valorIngresado)
            : $saldoFactura;

        $saldoDisponible = $this->money(
            max($saldoBase - $totalNoContabilizado, 0)
        );

        $totalApply = $this->money(
            $sumPayments + $totalNoContabilizado
        );

        $priceRecommend = $this->money(
            min($valorIngresado, $saldoDisponible)
        );

        $excessValue = $this->money(
            max($valorIngresado - $saldoDisponible, 0)
        );

        return [
            'hasExcess'                => $excessValue > 0,
            'totalApply'               => $totalApply,
            'sumPayments'              => $sumPayments,
            'sumPendingWeb'            => $sumPendingWeb,
            'sumDevolutions'           => $sumDev,
            'saldoFactura'             => $saldoFactura,
            'saldoBase'                => $saldoBase,
            'saldoDisponible'          => $saldoDisponible,
            'totalPendingNotAccounted' => $totalNoContabilizado,
            'excessValue'              => $excessValue,
            'priceRecommend'           => $priceRecommend,
        ];
    }


    protected function hasAnyExcess(array $payload): bool
    {
        if (collect(data_get($payload, 'retenciones', []))->contains('hasExcess', true)) {
            return true;
        }

        if (collect(data_get($payload, 'ingreso_efectivo.facturas', []))->contains('hasExcess', true)) {
            return true;
        }

        if (collect(data_get($payload, 'ingreso_tarjeta.facturas', []))->contains('hasExcess', true)) {
            return true;
        }

        if (collect(data_get($payload, 'ingreso_cheques.facturas', []))->contains('hasExcess', true)) {
            return true;
        }

        foreach (data_get($payload, 'ingreso_depositos', []) as $deposito) {
            if (collect(data_get($deposito, 'facturas', []))->contains('hasExcess', true)) {
                return true;
            }
        }

        foreach (data_get($payload, 'ingreso_aplicaciones', []) as $aplicacion) {
            if (collect(data_get($aplicacion, 'facturas', []))->contains('hasExcess', true)) {
                return true;
            }
        }

        return false;
    }


    protected function filterGroupPayment(SupportCollection $payments)
    {
        return $payments
            ->where('typePayment', 8)
            ->pluck('collectionId')
            ->filter()
            ->map(fn($doc) => trim((string) $doc))
            ->unique()
            ->values()
            ->all();
    }

    protected function filterGroupDocumentsPayments(SupportCollection $payments)
    {
        return $payments
            ->where('typePayment', '!=', 8)
            ->pluck('collectionId')
            ->filter()
            ->map(fn($doc) => trim((string)$doc))
            ->unique()
            ->values()
            ->all();
    }

    protected function getDevolutions(Collection $devoluciones, array $devPaymentDocs)
    {
        return collect($devoluciones ?? [])
            ->whereNotIn('DOC_GENERADO', $devPaymentDocs)
            ->values();
    }


    protected function sumPendingWebNotAccountedByInvoice(string  $invoice, array $documents,  string $desde, string $hasta)
    {
        $applications   = $this->applyRepository->getPendingWebInvoiceApplicationsExcludingDocuments($invoice, $documents, $desde, $hasta);
        $checks         = $this->applyRepository->getPendingWebCheckPaymentsByInvoice($invoice, $desde, $hasta);
        $deposits       = $this->applyRepository->getPendingWebDepositPaymentsByInvoice($invoice, $desde, $hasta);
        $cash           = $this->applyRepository->getPendingWebCashPaymentsByInvoice($invoice, $desde, $hasta);
        $withholdings   = $this->applyRepository->getPendingWebWithholdingPaymentsByInvoice($invoice, $desde, $hasta);
        $creditCards    = $this->applyRepository->getPendingWebCreditCardPaymentsByInvoice($invoice, $desde, $hasta);

        $sumApplications    = $this->money($applications->sum('monto'));
        $sumChecks          = $this->money($checks->sum('monto'));
        $sumDeposits        = $this->money($deposits->sum('monto'));
        $sumCash            = $this->money($cash->sum('monto'));
        $sumWithholdings    = $this->money($withholdings->sum('total'));
        $sumCreditCards     = $this->money($creditCards->sum('monto'));

        return $this->money($sumApplications + $sumChecks + $sumDeposits + $sumCash + $sumWithholdings + $sumCreditCards);
    }

    protected function money(int|float|string|null $value, int $decimals = 2): float
    {
        if ($value === null || trim((string) $value) === '') {
            return 0.00;
        }
        return round((float) $value, $decimals);
    }

    protected function getPaymentApplyGp(string $documento, string $ruc)
    {
        $resp = $this->api->getListCustomerPaymentApply([
            'documento' => $documento,
            'ruc'       => $ruc
        ]);
        return data_get($resp, 'data', []);
    }
}
