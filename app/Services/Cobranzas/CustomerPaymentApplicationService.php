<?php

namespace App\Services\Cobranzas;

use App\Repositories\Cobranzas\Contracts\WebInvoiceApplicationRepositoryInterface;
use App\Services\Api\Cobros\ApiBillingService;
use App\Services\Cobranzas\Contracts\CustomerPaymentApplicationServiceInterface;
use Carbon\Carbon;
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
            $facturaOrigen          = trim(data_get($ret, 'ret_factura'));
            $factura                = $this->normalizeInvoiceNumber($facturaOrigen);

            if (!$this->isValidWebInvoiceNumber($factura)) {
                $ret = $this->markSkippedInvoiceValidation($ret, $facturaOrigen, $factura);
                continue;
            }

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
            $devsInfo               = $this->formatInfoDevolutions($this->getDevolutions($order->devolucionesBloqueantes, $devPaymentDocs));

            $validation = $this->buildInvoiceValidation(
                $valorIngresado,
                $saldoFactura,
                $sumPayments,
                $sumPendingWeb,
                $sumDev,
            );

            $ret = array_merge($ret, $validation, [
                'devoluciones_info' => $devsInfo
            ]);
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
            $facturaOrigen          = trim(data_get($ing, 'ECD_DET_NUM_DOC', ''));
            $factura                = $this->normalizeInvoiceNumber($facturaOrigen);
            if (!$this->isValidWebInvoiceNumber($factura)) {
                $ing = $this->markSkippedInvoiceValidation($ing, $facturaOrigen, $factura);
                continue;
            }
            $valorIngresado         = $this->money(data_get($ing, 'aplicar_monto_efectivo', 0));
            $saldoFactura           = $this->money(data_get($ing, 'ECD_DET_MONTO_ORIGINAL', 0));
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
            $devsInfo               = $this->formatInfoDevolutions($this->getDevolutions($order->devolucionesBloqueantes, $devPaymentDocs));
            $validation = $this->buildInvoiceValidation(
                $valorIngresado,
                $saldoFactura,
                $sumPayments,
                $sumPendingWeb,
                $sumDev
            );

            $ing = array_merge($ing, $validation, [
                'devoluciones_info' => $devsInfo
            ]);
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
            $facturaOrigen          = trim(data_get($ing, 'ECD_DET_NUM_DOC', ''));
            $factura                = $this->normalizeInvoiceNumber($facturaOrigen);
            if (!$this->isValidWebInvoiceNumber($factura)) {
                $ing = $this->markSkippedInvoiceValidation($ing, $facturaOrigen, $factura);
                continue;
            }
            $valorIngresado         = $this->money(data_get($ing, 'aplicar_monto_tarjeta', 0));
            $saldoFactura           = $this->money(data_get($ing, 'ECD_DET_MONTO_ORIGINAL', 0));
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
            $devsInfo               = $this->formatInfoDevolutions($this->getDevolutions($order->devolucionesBloqueantes, $devPaymentDocs));

            $validation = $this->buildInvoiceValidation(
                $valorIngresado,
                $saldoFactura,
                $sumPayments,
                $sumPendingWeb,
                $sumDev
            );

            $ing = array_merge($ing, $validation, [
                'devoluciones_info' => $devsInfo
            ]);
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
                $facturaOrigen          = trim(data_get($ing, 'ECD_DET_NUM_DOC', ''));
                $factura                = $this->normalizeInvoiceNumber($facturaOrigen);

                if (!$this->isValidWebInvoiceNumber($factura)) {
                    $ing = $this->markSkippedInvoiceValidation($ing, $facturaOrigen, $factura);
                    continue;
                }
                $valorIngresado         = $this->money(data_get($ing, 'aplicar_monto_deposito', 0));
                $saldoFactura           = $this->money(data_get($ing, 'ECD_DET_MONTO_ORIGINAL', 0));
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
                $devsInfo               = $this->formatInfoDevolutions($this->getDevolutions($order->devolucionesBloqueantes, $devPaymentDocs));

                $validation = $this->buildInvoiceValidation(
                    $valorIngresado,
                    $saldoFactura,
                    $sumPayments,
                    $sumPendingWeb,
                    $sumDev,
                );

                $ing = array_merge($ing, $validation, [
                    'devoluciones_info' => $devsInfo
                ]);
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
            $applyDoc = trim((string) data_get($typeDeposits, 'detalle.CN_DOCUMENTO', '')); //documento de cobro para aplicar
            $dataInvoices   = data_get($typeDeposits, 'facturas', []);

            if (empty($dataInvoices)) {
                continue;
            }

            foreach ($dataInvoices as &$ing) {
                $facturaOrigen          = trim(data_get($ing, 'ECD_DET_NUM_DOC', ''));
                $factura                = $this->normalizeInvoiceNumber($facturaOrigen);

                if (!$this->isValidWebInvoiceNumber($factura)) {
                    $ing = $this->markSkippedInvoiceValidation($ing, $facturaOrigen, $factura);
                    continue;
                }

                $valorIngresado         = $this->money(data_get($ing, 'aplicar_monto_aplicacion', 0));
                $saldoFactura           = $this->money(data_get($ing, 'ECD_DET_MONTO_ORIGINAL', 0));
                $order                  = $this->applyRepository->getOrderWithDevolution($factura, $desde, $hasta);

                $payments               = collect($this->getPaymentApplyGp($order->factura, $order->CLI_RUC));
                $sumPayments            = $this->money($payments->sum('collectionAmount'));
                //filtros de documentos
                $devPaymentDocs         = $this->filterGroupPayment($payments);
                $othersPaymentDocs      = $this->filterGroupDocumentsPayments($payments);
                $groupDocuments         = collect($othersPaymentDocs)
                    ->merge($devPaymentDocs)
                    ->map(fn($doc) => trim((string)$doc))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                //agrupacion de numeros de documentos a devoluciones para omitir cuando es aplicacion de la misma devolucion para evitar excedente
                $groupDevs               = collect($devPaymentDocs)
                    ->push($applyDoc)
                    ->map(fn($doc) => trim((string)$doc))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                //funcion para validacion de documentos contabilizados que no son de web
                $rcPayments             = $this->filterGroupReceiptNumbers($payments);

                //validacion de aplicacion doble del mismo rc a la misma factura
                $hasDoubleApply         = $this->validateDoubleApply($applyDoc, $groupDocuments);
                //sumatoria de lo pendiente
                $sumPendingWeb          = $this->sumPendingWebNotAccountedByInvoice($factura, $groupDocuments, $desde, $hasta, $rcPayments,  'ingreso_aplicaciones');
                //sumatoria  de devoluciones pendeintes de procesar
                $sumDev                 = $this->money(collect($this->getDevolutions($order->devolucionesBloqueantes, $groupDevs))->sum('total'));
                $devsInfo               = $this->formatInfoDevolutions($this->getDevolutions($order->devolucionesBloqueantes, $groupDevs));

                $validation = $this->buildInvoiceValidation(
                    $valorIngresado,
                    $saldoFactura,
                    $sumPayments,
                    $sumPendingWeb,
                    $sumDev,
                );
                if ($hasDoubleApply) {
                    $validation = $this->applyDoubleApplyValidation($validation, $applyDoc);
                }

                $ing = array_merge($ing, $validation, [
                    'devoluciones_info' => $devsInfo
                ]);
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
            $facturaOrigen      = trim(data_get($ing, 'ECD_DET_NUM_DOC', ''));
            $factura            = $this->normalizeInvoiceNumber($facturaOrigen);
            if (!$this->isValidWebInvoiceNumber($factura)) {
                $ing = $this->markSkippedInvoiceValidation($ing, $facturaOrigen, $factura);
                continue;
            }
            $valorIngresado     = $this->money(data_get($ing, 'aplicar_monto_cheque', 0));
            $saldoFactura       = $this->money(data_get($ing, 'ECD_DET_MONTO_ORIGINAL', 0));
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
            $devsInfo               = $this->formatInfoDevolutions($this->getDevolutions($order->devolucionesBloqueantes, $devPaymentDocs));

            $validation = $this->buildInvoiceValidation(
                $valorIngresado,
                $saldoFactura,
                $sumPayments,
                $sumPendingWeb,
                $sumDev,
            );

            $ing = array_merge($ing, $validation, [
                'devoluciones_info' => $devsInfo
            ]);
        }
        unset($ing);

        $data['facturas'] = $dataInvoices;

        return $data;
    }

    protected function formatInfoDevolutions(SupportCollection $devs): array
    {
        return collect($devs)->map(function ($dev) {
            $userName   = data_get($dev, 'USERID', 'N/A');
            $cod        = data_get($dev, 'devolucion', 'N/A');
            $doc        = data_get($dev, 'DOC_GENERADO', 'N/A');
            $fecha      = Carbon::parse(data_get($dev, 'created_at'))->format('Y-m-d');
            $total      = $this->money(data_get($dev, 'total', 0));
            return "{$userName} - {$cod} - {$doc} - {$fecha} - $" . number_format($total, 2, '.', '');
        })
            ->filter()
            ->values()
            ->all();
    }

    protected function buildInvoiceValidation(
        int|float|string|null $valorIngresado,
        int|float|string|null $montoOriginalFactura,
        int|float|string|null $sumPayments,
        int|float|string|null $sumPendingWeb,
        int|float|string|null $sumDev
    ): array {
        $valorIngresado       = $this->money($valorIngresado);
        $montoOriginalFactura = $this->money($montoOriginalFactura);
        $sumPayments          = $this->money($sumPayments);
        $sumPendingWeb        = $this->money($sumPendingWeb);
        $sumDev               = $this->money($sumDev);

        $totalNoContabilizado = $this->money($sumPendingWeb + $sumDev);

        $totalApply = $this->money(
            $sumPayments + $totalNoContabilizado
        );

        $saldoBase = $montoOriginalFactura;

        $saldoDisponible = $this->money(
            max($saldoBase - $totalApply, 0)
        );

        $priceRecommend = $this->money(
            min($valorIngresado, $saldoDisponible)
        );

        $excessValue = $this->money(
            max($valorIngresado - $saldoDisponible, 0)
        );

        $hasExcess = $excessValue > 0;

        return [
            'hasExcess'                => $hasExcess,
            'hasDoubleApply'           => false,

            'totalApply'               => $totalApply,
            'sumPayments'              => $sumPayments,
            'sumPendingWeb'            => $sumPendingWeb,
            'sumDevolutions'           => $sumDev,

            'saldoFactura'             => $montoOriginalFactura,
            'saldoBase'                => $saldoBase,
            'saldoDisponible'          => $saldoDisponible,
            'totalPendingNotAccounted' => $totalNoContabilizado,

            'excessValue'              => $excessValue,
            'priceRecommend'           => $priceRecommend,

            'validationType'           => $hasExcess ? 'excess' : null,
            'validationMessage'        => $hasExcess
                ? 'El valor ingresado supera el saldo disponible de la factura.'
                : null,
            'doubleApplyDoc'           => null,
        ];
    }




    protected function hasAnyExcess(array $payload): bool
    {
        return $this->hasAnyInvoiceValidationFlag($payload, 'hasExcess')
            || $this->hasAnyInvoiceValidationFlag($payload, 'hasDoubleApply');
    }

    protected function hasAnyInvoiceValidationFlag(array $payload, string $flag): bool
    {
        if (collect(data_get($payload, 'retenciones', []))->contains($flag, true)) {
            return true;
        }

        if (collect(data_get($payload, 'ingreso_efectivo.facturas', []))->contains($flag, true)) {
            return true;
        }

        if (collect(data_get($payload, 'ingreso_tarjeta.facturas', []))->contains($flag, true)) {
            return true;
        }

        if (collect(data_get($payload, 'ingreso_cheques.facturas', []))->contains($flag, true)) {
            return true;
        }

        foreach (data_get($payload, 'ingreso_depositos', []) as $deposito) {
            if (collect(data_get($deposito, 'facturas', []))->contains($flag, true)) {
                return true;
            }
        }

        foreach (data_get($payload, 'ingreso_aplicaciones', []) as $aplicacion) {
            if (collect(data_get($aplicacion, 'facturas', []))->contains($flag, true)) {
                return true;
            }
        }

        return false;
    }


    protected function filterGroupPayment(SupportCollection $payments): array
    {
        return $this->filterGroupCollectionIds($payments, true);
    }

    protected function filterGroupDocumentsPayments(SupportCollection $payments): array
    {
        return $this->filterGroupCollectionIds($payments, false);
    }

    protected function filterGroupCollectionIds(SupportCollection $payments, bool $onlyTypePaymentEight): array
    {
        return $payments
            ->filter(function ($payment) use ($onlyTypePaymentEight) {
                $typePayment = (int) data_get($payment, 'typePayment');

                return $onlyTypePaymentEight
                    ? $typePayment === 8
                    : $typePayment !== 8;
            })
            ->pluck('collectionId')
            ->map(fn($doc) => trim((string)$doc))
            ->filter(fn($doc) => !empty($doc))
            ->unique()
            ->values()
            ->all();
    }


    protected function filterGroupReceiptNumbers(SupportCollection $payments): array
    {
        return $payments
            ->filter(fn($payment) => (int) data_get($payment, 'typePayment') !== 8)
            ->pluck('numeroRecibo')
            ->map(fn($doc) => trim((string) $this->formatReceiptNumber($doc)))
            ->filter(fn($doc) => !empty($doc))
            ->unique()
            ->values()
            ->all();
    }

    protected function formatReceiptNumber(?string $rc): ?string
    {
        if (empty($rc)) {
            return null;
        }
        $rc = strtoupper(trim($rc));
        if (preg_match('/^(RC-)(\d{1,8})/', $rc, $matches)) {
            return $matches[1] . str_pad($matches[2], 8, '0', STR_PAD_LEFT);
        }

        return $rc;
    }

    protected function getDevolutions(Collection $devoluciones, array $devPaymentDocs)
    {
        return collect($devoluciones ?? [])
            ->whereNotIn('DOC_GENERADO', $devPaymentDocs)
            ->values();
    }


    protected function sumPendingWebNotAccountedByInvoice(string  $invoice, array $documents,  string $desde, string $hasta, array $rcPayments = [],  ?string $type = null)
    {

        $rcsPorExcluir = $type === 'ingreso_aplicaciones' ? $rcPayments : [];
        $applications   = $this->applyRepository->getPendingWebInvoiceApplicationsExcludingDocuments($invoice, $documents, $desde, $hasta, $rcsPorExcluir);
        $checks         = $this->applyRepository->getPendingWebCheckPaymentsByInvoice($invoice, $desde, $hasta, $rcsPorExcluir);
        $deposits       = $this->applyRepository->getPendingWebDepositPaymentsByInvoice($invoice, $desde, $hasta, $rcsPorExcluir);
        $cash           = $this->applyRepository->getPendingWebCashPaymentsByInvoice($invoice, $desde, $hasta, $rcsPorExcluir);
        $withholdings   = $this->applyRepository->getPendingWebWithholdingPaymentsByInvoice($invoice, $desde, $hasta, $rcsPorExcluir);
        $creditCards    = $this->applyRepository->getPendingWebCreditCardPaymentsByInvoice($invoice, $desde, $hasta, $rcsPorExcluir);

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


    protected function normalizeInvoiceNumber(?string $invoice): string
    {
        $invoice = trim((string) $invoice);
        return preg_replace('/\.\d+$/', '', $invoice);
    }


    protected function isValidWebInvoiceNumber(?string $invoice): bool
    {
        $invoice = $this->normalizeInvoiceNumber($invoice);

        return preg_match('/^\d{3}-\d{3}-\d{9}$/', $invoice) === 1;
    }

    protected function markSkippedInvoiceValidation(
        array $item,
        string $facturaOrigen,
        string $facturaNormalizada
    ): array {
        return array_merge($item, [
            'hasExcess'                  => false,
            'hasDoubleApply'             => false,

            'totalApply'                 => 0,
            'sumPayments'                => 0,
            'sumPendingWeb'              => 0,
            'sumDevolutions'             => 0,
            'totalPendingNotAccounted'   => 0,
            'excessValue'                => 0,
            'priceRecommend'             => 0,

            'devoluciones_info'          => [],
            'factura_original'           => $facturaOrigen,
            'factura_busqueda'           => $facturaNormalizada,

            'validationType'             => null,
            'validationMessage'          => null,
            'doubleApplyDoc'             => null,

            'validation_skipped'         => true,
            'validation_skipped_reason'  => 'Documento omitido porque no corresponde al formato de factura web.',
        ]);
    }


    protected function validateDoubleApply(?string $applyDoc, array $othersDocuments): bool
    {
        $applyDoc = mb_strtoupper(trim((string) $applyDoc));

        if ($applyDoc === '') {
            return false;
        }

        return collect($othersDocuments)
            ->map(fn($doc) => mb_strtoupper(trim((string) $doc)))
            ->contains($applyDoc);
    }

    protected function applyDoubleApplyValidation(array $validation, ?string $applyDoc): array
    {
        $applyDoc = trim((string) $applyDoc);
        return array_merge($validation, [
            'hasDoubleApply'            => true,
            'doubleApplyDoc'            => $applyDoc,
            'priceRecommend'            => 0,
            'validationType'            => 'double_apply',
            'validationMessage' => $applyDoc !== ''
                ? "El documento de cobro {$applyDoc} ya fue aplicado a esta factura. No se permite aplicarlo nuevamente."
                : 'El documento de cobro ya fue aplicado a esta factura. No se permite aplicarlo nuevamente.',
        ]);
    }
}
