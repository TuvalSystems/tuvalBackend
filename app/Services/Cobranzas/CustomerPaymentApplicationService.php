<?php

namespace App\Services\Cobranzas;

use App\Models\Cobranzas\CobroValidacionFactura;
use App\Models\GpCliente;
use App\Models\GpUsuario;
use App\Repositories\Cobranzas\Contracts\WebInvoiceApplicationRepositoryInterface;
use App\Services\Api\Cobros\ApiBillingService;
use App\Services\Cobranzas\Contracts\CustomerPaymentApplicationServiceInterface;
use App\Supports\Money;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustomerPaymentApplicationService implements CustomerPaymentApplicationServiceInterface
{

    //serivicio dedicado para la verificacion de aplicación de pagos antes de registrar un cobro
    public function __construct(protected ApiBillingService $api, protected readonly WebInvoiceApplicationRepositoryInterface $applyRepository) {}



    public function validateIdentifyPaymentInvoices(array $payload, GpUsuario $user)
    {

        $cliente                            = GpCliente::find(data_get($payload, 'cliente_id'));
        if (!$cliente) {
            throw new \InvalidArgumentException('Cliente no encontrado para validar el cobro.');
        }

        $fechaCobro = now();
        $desde                              = $fechaCobro->copy()->startOfDay()->toDateTimeString();
        $hasta                              = $fechaCobro->copy()->endOfDay()->toDateTimeString();

        $payload['retenciones']  = $this->processRetenciones($this->arrayValue(data_get($payload, 'retenciones', [])),  $desde, $hasta, $cliente);
        $payload['efectivo']     = $this->processSinglePayment(data_get($payload, 'efectivo'), 'efectivo', $desde, $hasta, $cliente);
        $payload['tarjeta']      = $this->processSinglePayment(data_get($payload, 'tarjeta'), 'tarjeta', $desde, $hasta, $cliente);
        $payload['cheque']       = $this->processSinglePayment(data_get($payload, 'cheque'), 'cheque', $desde, $hasta, $cliente);
        $payload['depositos']    = $this->processIngresoDeposito($this->arrayValue(data_get($payload, 'depositos', [])), $desde, $hasta, $cliente);
        $payload['aplicaciones'] = $this->processIngresoAplicaciones($this->arrayValue(data_get($payload, 'aplicaciones', [])), $desde, $hasta, $cliente);

        $payload['hasGlobalExcess']         = $this->hasAnyExcess($payload);
        $this->storeFromValidationResult($payload, $user);

        return $this->cleanInternalHistory($payload);
    }

    protected function arrayValue(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    protected function processSinglePayment(
        mixed $payment,
        string $type,
        string $desde,
        string $hasta,
        GpCliente $cliente
    ): ?array {
        if (!is_array($payment) || empty($payment)) {
            return null;
        }

        return match ($type) {
            'efectivo' => $this->processIngresoEfectivo($payment, $desde, $hasta, $cliente),
            'tarjeta'  => $this->processIngresoTarjeta($payment, $desde, $hasta, $cliente),
            'cheque'   => $this->processIngresoCheque($payment, $desde, $hasta, $cliente),
            default    => $payment,
        };
    }

    protected function processRetenciones(array $retenciones, string $desde, string $hasta, GpCliente $cliente)
    {
        foreach ($retenciones as &$ret) {
            $facturaOrigen          = $this->getInvoiceDocument($ret);
            $factura                = $this->normalizeInvoiceNumber($facturaOrigen);

            if (!$this->isValidWebInvoiceNumber($factura)) {
                $ret = $this->markSkippedInvoiceValidation($ret, $facturaOrigen, $factura);
                continue;
            }

            $valorIngresado         = Money::normalize(data_get($ret, 'total', 0));
            $saldoFactura           = $this->getInvoiceBalanceForValidation($ret);
            $order                  = $this->applyRepository->getOrderWithDevolution($factura, $desde, $hasta);

            $payments               = collect($this->getPaymentApplyGp($facturaOrigen, $cliente->CLI_RUC));
            $checksPendings         = collect($this->getChecksPendingPayment($facturaOrigen, $cliente->CLI_RUC));
            $sumCheckPending        = $this->sumCheckPaymentPending($checksPendings);
            $sumPayments            = Money::normalize($this->sumPaymentAmount($payments) + $sumCheckPending);

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

            //funcion para validacion de documentos contabilizados que no son de web
            $rcCheckPendings        = $this->filterGroupReciptNumbersCheckPayment($checksPendings);
            $rcPayments             = array_values(array_unique(array_merge(
                $this->filterGroupReceiptNumbers($payments),
                $rcCheckPendings
            )));


            //sumatoria de lo pendiente
            $pendingWebResult       = $this->getPendingWebNotAccountedByInvoice($factura, $groupDocuments, $desde, $hasta, $rcPayments);
            $sumPendingWeb          = data_get($pendingWebResult, 'total');
            $pendingWebDetails      = data_get($pendingWebResult, 'details');
            //sumatoria  de devoluciones pendeintes de procesar
            $devolutions            = collect($this->getDevolutions($order->devolucionesBloqueantes ?? new Collection(), $devPaymentDocs));
            $sumDev                 = Money::normalize($devolutions->sum('total'));
            $devsInfo               = $this->formatInfoDevolutions($devolutions);
            $validation = $this->buildInvoiceValidation(
                $valorIngresado,
                $saldoFactura,
                $sumPayments,
                $sumPendingWeb,
                $sumDev,
                $this->getInvoiceCurrentBalanceForValidation($ret)
            );

            $ret = array_merge($ret, $validation, [
                'devoluciones_info' => $devsInfo,
                '_validacion_historial' => $this->buildValidationHistory(
                    tipoIngreso: 'retencion',
                    cliente: $cliente,
                    facturaOrigen: $facturaOrigen,
                    factura: $factura,
                    item: $ret,
                    valorIngresado: $valorIngresado,
                    validation: $validation,
                    payments: $payments,
                    checksPendings: $checksPendings,
                    pendingWebDetails: $pendingWebDetails,
                    devolutions: $devolutions
                ),
            ]);
        }
        unset($ret);
        return $retenciones;
    }


    protected function processIngresoEfectivo(array $data, string $desde, string $hasta, GpCliente $cliente)
    {
        $dataInvoices = data_get($data, 'facturas', []);

        if (empty($dataInvoices)) {
            return $data;
        }

        foreach ($dataInvoices as &$ing) {
            $facturaOrigen          = $this->getInvoiceDocument($ing);
            $factura                = $this->normalizeInvoiceNumber($facturaOrigen);
            if (!$this->isValidWebInvoiceNumber($factura)) {
                $ing = $this->markSkippedInvoiceValidation($ing, $facturaOrigen, $factura);
                continue;
            }
            $valorIngresado         = $this->getAppliedAmount($ing, ['aplicarMontoEfectivo']);
            $saldoFactura           = $this->getInvoiceBalanceForValidation($ing);
            $order                  = $this->applyRepository->getOrderWithDevolution($factura, $desde, $hasta);

            $payments               = collect($this->getPaymentApplyGp($facturaOrigen, $cliente->CLI_RUC));
            $checksPendings         = collect($this->getChecksPendingPayment($facturaOrigen, $cliente->CLI_RUC));
            $sumCheckPending        = $this->sumCheckPaymentPending($checksPendings);
            $sumPayments            = Money::normalize($this->sumPaymentAmount($payments) + $sumCheckPending);
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
            //funcion para validacion de documentos contabilizados que no son de web
            $rcCheckPendings        = $this->filterGroupReciptNumbersCheckPayment($checksPendings);
            $rcPayments             = array_values(array_unique(array_merge(
                $this->filterGroupReceiptNumbers($payments),
                $rcCheckPendings
            )));


            //sumatoria de lo pendiente
            $pendingWebResult       = $this->getPendingWebNotAccountedByInvoice($factura, $groupDocuments, $desde, $hasta, $rcPayments);
            $sumPendingWeb          = data_get($pendingWebResult, 'total');
            $pendingWebDetails      = data_get($pendingWebResult, 'details');
            //sumatoria  de devoluciones pendeintes de procesar
            $devolutions            = collect($this->getDevolutions($order->devolucionesBloqueantes ?? new Collection(), $devPaymentDocs));
            $sumDev                 = Money::normalize($devolutions->sum('total'));
            $devsInfo               = $this->formatInfoDevolutions($devolutions);
            $validation             = $this->buildInvoiceValidation(
                $valorIngresado,
                $saldoFactura,
                $sumPayments,
                $sumPendingWeb,
                $sumDev,
                $this->getInvoiceCurrentBalanceForValidation($ing)
            );

            $ing = array_merge($ing, $validation, [
                'devoluciones_info' => $devsInfo,
                '_validacion_historial' => $this->buildValidationHistory(
                    tipoIngreso: 'efectivo',
                    cliente: $cliente,
                    facturaOrigen: $facturaOrigen,
                    factura: $factura,
                    item: $ing,
                    valorIngresado: $valorIngresado,
                    validation: $validation,
                    payments: $payments,
                    checksPendings: $checksPendings,
                    pendingWebDetails: $pendingWebDetails,
                    devolutions: $devolutions
                ),
            ]);
        }
        unset($ing);

        $data['facturas'] = $dataInvoices;

        return $data;
    }
    protected function processIngresoTarjeta(array $data, string $desde, string $hasta, GpCliente $cliente)
    {
        $dataInvoices = data_get($data, 'facturas', []);

        if (empty($dataInvoices)) {
            return $data;
        }

        foreach ($dataInvoices as &$ing) {
            $facturaOrigen          = $this->getInvoiceDocument($ing);
            $factura                = $this->normalizeInvoiceNumber($facturaOrigen);
            if (!$this->isValidWebInvoiceNumber($factura)) {
                $ing = $this->markSkippedInvoiceValidation($ing, $facturaOrigen, $factura);
                continue;
            }
            $valorIngresado         = $this->getAppliedAmount($ing, ['aplicarMontoTarjeta']);
            $saldoFactura           = $this->getInvoiceBalanceForValidation($ing);
            $order                  = $this->applyRepository->getOrderWithDevolution($factura, $desde, $hasta);

            $payments               = collect($this->getPaymentApplyGp($facturaOrigen, $cliente->CLI_RUC));
            $checksPendings         = collect($this->getChecksPendingPayment($facturaOrigen, $cliente->CLI_RUC));
            $sumCheckPending        = $this->sumCheckPaymentPending($checksPendings);
            $sumPayments            = Money::normalize($this->sumPaymentAmount($payments) + $sumCheckPending);
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
            //funcion para validacion de documentos contabilizados que no son de web
            $rcCheckPendings        = $this->filterGroupReciptNumbersCheckPayment($checksPendings);
            $rcPayments             = array_values(array_unique(array_merge(
                $this->filterGroupReceiptNumbers($payments),
                $rcCheckPendings
            )));
            //sumatoria de lo pendiente
            $pendingWebResult       = $this->getPendingWebNotAccountedByInvoice($factura, $groupDocuments, $desde, $hasta, $rcPayments);
            $sumPendingWeb          = data_get($pendingWebResult, 'total');
            $pendingWebDetails      = data_get($pendingWebResult, 'details');
            //sumatoria  de devoluciones pendeintes de procesar
            $devolutions            = collect($this->getDevolutions($order->devolucionesBloqueantes ?? new Collection(), $devPaymentDocs));
            $sumDev                 = Money::normalize($devolutions->sum('total'));
            $devsInfo               = $this->formatInfoDevolutions($devolutions);
            $validation             = $this->buildInvoiceValidation(
                $valorIngresado,
                $saldoFactura,
                $sumPayments,
                $sumPendingWeb,
                $sumDev,
                $this->getInvoiceCurrentBalanceForValidation($ing)
            );

            $ing = array_merge($ing, $validation, [
                'devoluciones_info' => $devsInfo,
                '_validacion_historial' => $this->buildValidationHistory(
                    tipoIngreso: 'tarjeta',
                    cliente: $cliente,
                    facturaOrigen: $facturaOrigen,
                    factura: $factura,
                    item: $ing,
                    valorIngresado: $valorIngresado,
                    validation: $validation,
                    payments: $payments,
                    checksPendings: $checksPendings,
                    pendingWebDetails: $pendingWebDetails,
                    devolutions: $devolutions
                ),
            ]);
        }
        unset($ing);

        $data['facturas'] = $dataInvoices;

        return $data;
    }
    protected function processIngresoDeposito(array $data, string $desde, string $hasta, GpCliente $cliente)
    {
        foreach ($data as  &$typeDeposits) {

            $dataInvoices = data_get($typeDeposits, 'facturas', []);

            if (empty($dataInvoices)) {
                continue;
            }

            foreach ($dataInvoices as &$ing) {
                $facturaOrigen          = $this->getInvoiceDocument($ing);
                $factura                = $this->normalizeInvoiceNumber($facturaOrigen);

                if (!$this->isValidWebInvoiceNumber($factura)) {
                    $ing = $this->markSkippedInvoiceValidation($ing, $facturaOrigen, $factura);
                    continue;
                }
                $valorIngresado         = $this->getAppliedAmount($ing, ['aplicarMontoDeposito']);
                $saldoFactura           = $this->getInvoiceBalanceForValidation($ing);
                $order                  = $this->applyRepository->getOrderWithDevolution($factura, $desde, $hasta);

                $payments               = collect($this->getPaymentApplyGp($facturaOrigen, $cliente->CLI_RUC));
                $checksPendings         = collect($this->getChecksPendingPayment($facturaOrigen, $cliente->CLI_RUC));
                $sumCheckPending        = $this->sumCheckPaymentPending($checksPendings);
                $sumPayments            = Money::normalize($this->sumPaymentAmount($payments) + $sumCheckPending);
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
                //funcion para validacion de documentos contabilizados que no son de web
                $rcCheckPendings        = $this->filterGroupReciptNumbersCheckPayment($checksPendings);
                $rcPayments             = array_values(array_unique(array_merge(
                    $this->filterGroupReceiptNumbers($payments),
                    $rcCheckPendings
                )));

                //sumatoria de lo pendiente
                $pendingWebResult       = $this->getPendingWebNotAccountedByInvoice($factura, $groupDocuments, $desde, $hasta, $rcPayments);
                $sumPendingWeb          = data_get($pendingWebResult, 'total');
                $pendingWebDetails      = data_get($pendingWebResult, 'details');
                //sumatoria  de devoluciones pendeintes de procesar
                $devolutions            = collect($this->getDevolutions($order->devolucionesBloqueantes ?? new Collection(), $devPaymentDocs));
                $sumDev                 = Money::normalize($devolutions->sum('total'));
                $devsInfo               = $this->formatInfoDevolutions($devolutions);
                $validation = $this->buildInvoiceValidation(
                    $valorIngresado,
                    $saldoFactura,
                    $sumPayments,
                    $sumPendingWeb,
                    $sumDev,
                    $this->getInvoiceCurrentBalanceForValidation($ing)
                );

                $ing = array_merge($ing, $validation, [
                    'devoluciones_info' => $devsInfo,
                    '_validacion_historial' => $this->buildValidationHistory(
                        tipoIngreso: 'deposito',
                        cliente: $cliente,
                        facturaOrigen: $facturaOrigen,
                        factura: $factura,
                        item: $ing,
                        valorIngresado: $valorIngresado,
                        validation: $validation,
                        payments: $payments,
                        checksPendings: $checksPendings,
                        pendingWebDetails: $pendingWebDetails,
                        devolutions: $devolutions
                    ),
                ]);
            }
            unset($ing);

            $typeDeposits['facturas'] = $dataInvoices;
        }
        unset($typeDeposits);

        return $data;
    }

    protected function processIngresoAplicaciones(array $data, string $desde, string $hasta, GpCliente $cliente)
    {
        foreach ($data as  &$typeDeposits) {
            $applyDoc = $this->getApplicationDocument($typeDeposits); //documento de cobro para aplicar
            $dataInvoices   = data_get($typeDeposits, 'facturas', []);


            if (empty($dataInvoices)) {
                continue;
            }
            $totalAmountToApply         = Money::normalize(
                collect($dataInvoices)->sum(
                    fn($invoice) => $this->getAppliedAmount($invoice, ['aplicarMontoAplicacion'])
                )
            );
            $pendingApplicationDocument             = $this->getApplyDocumentsPendings($applyDoc, $cliente->CLI_RUC);
            $gpApplicationBalance       = Money::normalize(data_get($pendingApplicationDocument, 'saldoCliente', 0));
            if ($totalAmountToApply > $gpApplicationBalance) {
                throw new \RuntimeException(
                    'El monto total a aplicar supera el saldo disponible en GP. ' .
                        'Documento: ' . $applyDoc . ', ' .
                        'Total a aplicar: ' . number_format($totalAmountToApply, 2) . ', ' .
                        'Saldo GP: ' . number_format($gpApplicationBalance, 2)
                );
            }

            foreach ($dataInvoices as &$ing) {
                $facturaOrigen          = $this->getInvoiceDocument($ing);
                $factura                = $this->normalizeInvoiceNumber($facturaOrigen);

                if (!$this->isValidWebInvoiceNumber($factura)) {
                    $ing = $this->markSkippedInvoiceValidation($ing, $facturaOrigen, $factura);
                    continue;
                }

                $valorIngresado         = $this->getAppliedAmount($ing, ['aplicarMontoAplicacion']);
                $saldoFactura           = $this->getInvoiceBalanceForValidation($ing);
                $order                  = $this->applyRepository->getOrderWithDevolution($factura, $desde, $hasta);

                $payments               = collect($this->getPaymentApplyGp($facturaOrigen, $cliente->CLI_RUC));
                $checksPendings         = collect($this->getChecksPendingPayment($facturaOrigen, $cliente->CLI_RUC));
                $sumCheckPending        = $this->sumCheckPaymentPending($checksPendings);
                $sumPayments            = Money::normalize($this->sumPaymentAmount($payments) + $sumCheckPending);
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
                $rcCheckPendings        = $this->filterGroupReciptNumbersCheckPayment($checksPendings);
                $rcPayments             = array_values(array_unique(array_merge(
                    $this->filterGroupReceiptNumbers($payments),
                    $rcCheckPendings
                )));

                //validacion de aplicacion doble del mismo rc a la misma factura
                $hasDoubleApply         = $this->validateDoubleApply($applyDoc, $groupDocuments);

                //sumatoria de lo pendiente
                $pendingWebResult       = $this->getPendingWebNotAccountedByInvoice($factura, $groupDocuments, $desde, $hasta,  $rcPayments);
                $sumPendingWeb          = data_get($pendingWebResult, 'total');
                $pendingWebDetails      = data_get($pendingWebResult, 'details');
                //sumatoria  de devoluciones pendeintes de procesar
                $devolutions            = collect($this->getDevolutions($order->devolucionesBloqueantes ?? new Collection(), $groupDevs));
                $sumDev                 = Money::normalize($devolutions->sum('total'));
                $devsInfo               = $this->formatInfoDevolutions($devolutions);



                $validation = $this->buildInvoiceValidation(
                    $valorIngresado,
                    $saldoFactura,
                    $sumPayments,
                    $sumPendingWeb,
                    $sumDev,
                    $this->getInvoiceCurrentBalanceForValidation($ing)
                );
                if ($hasDoubleApply) {
                    $validation = $this->applyDoubleApplyValidation($validation, $applyDoc);
                }

                $ing = array_merge($ing, $validation, [
                    'devoluciones_info' => $devsInfo,
                    '_validacion_historial' => $this->buildValidationHistory(
                        tipoIngreso: 'aplicaciones',
                        cliente: $cliente,
                        facturaOrigen: $facturaOrigen,
                        factura: $factura,
                        item: $ing,
                        valorIngresado: $valorIngresado,
                        validation: $validation,
                        payments: $payments,
                        checksPendings: $checksPendings,
                        pendingWebDetails: $pendingWebDetails,
                        devolutions: $devolutions
                    ),
                ]);
            }
            unset($ing);

            $typeDeposits['facturas'] = $dataInvoices;
        }
        unset($typeDeposits);

        return $data;
    }

    protected function processIngresoCheque(array $data, string $desde, string $hasta, GpCliente $cliente)
    {
        $dataInvoices = data_get($data, 'facturas', []);

        if (empty($dataInvoices)) {
            return $data;
        }

        foreach ($dataInvoices as &$ing) {
            $facturaOrigen      = $this->getInvoiceDocument($ing);
            $factura            = $this->normalizeInvoiceNumber($facturaOrigen);
            if (!$this->isValidWebInvoiceNumber($factura)) {
                $ing = $this->markSkippedInvoiceValidation($ing, $facturaOrigen, $factura);
                continue;
            }
            $valorIngresado     = $this->getAppliedAmount($ing, ['aplicarMontoCheque']);
            $saldoFactura       = $this->getInvoiceBalanceForValidation($ing);
            $order              = $this->applyRepository->getOrderWithDevolution($factura, $desde, $hasta);

            $payments               = collect($this->getPaymentApplyGp($facturaOrigen, $cliente->CLI_RUC));
            $checksPendings         = collect($this->getChecksPendingPayment($facturaOrigen, $cliente->CLI_RUC));
            $sumCheckPending        = $this->sumCheckPaymentPending($checksPendings);
            $sumPayments            = Money::normalize($this->sumPaymentAmount($payments) + $sumCheckPending);
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

            //funcion para validacion de documentos contabilizados que no son de web
            $rcCheckPendings            = $this->filterGroupReciptNumbersCheckPayment($checksPendings);
            $rcPayments                 = array_values(array_unique(array_merge(
                $this->filterGroupReceiptNumbers($payments),
                $rcCheckPendings
            )));



            //sumatoria de lo pendiente
            $pendingWebResult       = $this->getPendingWebNotAccountedByInvoice($factura, $groupDocuments, $desde, $hasta, $rcPayments);
            $sumPendingWeb          = data_get($pendingWebResult, 'total');
            $pendingWebDetails      = data_get($pendingWebResult, 'details');
            //sumatoria  de devoluciones pendeintes de procesar
            $devolutions            = collect($this->getDevolutions($order->devolucionesBloqueantes ?? new Collection(), $devPaymentDocs));
            $sumDev                 = Money::normalize($devolutions->sum('total'));
            $devsInfo               = $this->formatInfoDevolutions($devolutions);
            $validation = $this->buildInvoiceValidation(
                $valorIngresado,
                $saldoFactura,
                $sumPayments,
                $sumPendingWeb,
                $sumDev,
                $this->getInvoiceCurrentBalanceForValidation($ing)
            );

            $ing = array_merge($ing, $validation, [
                'devoluciones_info' => $devsInfo,
                '_validacion_historial' => $this->buildValidationHistory(
                    tipoIngreso: 'cheque',
                    cliente: $cliente,
                    facturaOrigen: $facturaOrigen,
                    factura: $factura,
                    item: $ing,
                    valorIngresado: $valorIngresado,
                    validation: $validation,
                    payments: $payments,
                    checksPendings: $checksPendings,
                    pendingWebDetails: $pendingWebDetails,
                    devolutions: $devolutions
                ),
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
            $total      = Money::normalize(data_get($dev, 'total', 0));
            return "{$userName} - {$cod} - {$doc} - {$fecha} - $" . number_format($total, 2, '.', '');
        })
            ->filter()
            ->values()
            ->all();
    }

    protected function getInvoiceDocument(array $item): string
    {
        return trim((string) data_get($item, 'documento', ''));
    }

    protected function getApplicationDocument(array $item): string
    {
        return trim((string) data_get($item, 'documento', ''));
    }

    protected function getAppliedAmount(array $item, array $fields): float
    {
        foreach ($fields as $field) {
            $value = data_get($item, $field);

            if ($value !== null && trim((string) $value) !== '') {
                return Money::normalize($value);
            }
        }

        return 0.00;
    }

    protected function sumPaymentAmount(SupportCollection $payments): float
    {
        return Money::normalize(
            $payments->sum(fn($payment) => Money::normalize(
                data_get($payment, 'valorCobro', data_get($payment, 'collectionAmount', 0))
            ))
        );
    }

    protected function sumCheckPaymentPending(SupportCollection $checkPayment): float
    {
        return Money::normalize(
            $checkPayment->sum(fn($payment) => Money::normalize(
                data_get($payment, 'montoAplicado', 0)
            ))
        );
    }

    protected function getInvoiceBalanceForValidation(array $item): float
    {
        return Money::normalize(
            data_get($item, 'montoOriginal')
                ?? data_get($item, 'saldoFactura')
                ?? data_get($item, 'saldoCliente')
                ?? 0
        );
    }

    protected function getInvoiceOriginalAmount(array $item): float
    {
        return Money::normalize(
            data_get($item, 'montoOriginal')
                ?? data_get($item, 'saldoFactura')
                ?? data_get($item, 'saldoCliente')
                ?? 0
        );
    }

    protected function getInvoiceCustomerBalance(array $item): float
    {
        return Money::normalize(
            data_get($item, 'saldoCliente')
                ?? data_get($item, 'saldoFactura')
                ?? 0
        );
    }

    protected function getInvoiceCurrentBalanceForValidation(array $item): ?float
    {
        $value = data_get($item, 'saldoCliente')
            ?? data_get($item, 'saldoFactura');

        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return Money::normalize($value);
    }

    protected function getInvoiceDueDate(array $item): mixed
    {
        return data_get($item, 'fechaVencimiento');
    }

    protected function buildInvoiceValidation(
        int|float|string|null $valorIngresado,
        int|float|string|null $montoOriginalFactura,
        int|float|string|null $sumPayments,
        int|float|string|null $sumPendingWeb,
        int|float|string|null $sumDev,
        int|float|string|null $saldoActualFactura = null
    ): array {
        $valorIngresado       = Money::normalize($valorIngresado);
        $montoOriginalFactura = Money::normalize($montoOriginalFactura);
        $sumPayments          = Money::normalize($sumPayments);
        $sumPendingWeb        = Money::normalize($sumPendingWeb);
        $sumDev               = Money::normalize($sumDev);
        $saldoActualFactura   = $saldoActualFactura === null ? null : Money::normalize($saldoActualFactura);

        $totalNoContabilizado = Money::normalize($sumPendingWeb + $sumDev);

        $totalApply = Money::normalize(
            $sumPayments + $totalNoContabilizado
        );

        $saldoBase = $montoOriginalFactura;

        $saldoDisponibleGp = Money::normalize(
            max($saldoBase - $totalApply, 0)
        );

        $saldoDisponible = $saldoDisponibleGp;

        if ($saldoActualFactura !== null) {
            $saldoDisponibleDraft = Money::normalize(
                max($saldoActualFactura - $totalNoContabilizado, 0)
            );
            $saldoDisponible = Money::normalize(
                min($saldoDisponibleGp, $saldoDisponibleDraft)
            );
        }

        $priceRecommend = Money::normalize(
            min($valorIngresado, $saldoDisponible)
        );

        $excessValue = Money::normalize(
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

        if (collect(data_get($payload, 'efectivo.facturas', []))->contains($flag, true)) {
            return true;
        }

        if (collect(data_get($payload, 'tarjeta.facturas', []))->contains($flag, true)) {
            return true;
        }

        if (collect(data_get($payload, 'cheque.facturas', []))->contains($flag, true)) {
            return true;
        }

        foreach ($this->arrayValue(data_get($payload, 'depositos', [])) as $deposito) {
            if (collect(data_get($deposito, 'facturas', []))->contains($flag, true)) {
                return true;
            }
        }

        foreach ($this->arrayValue(data_get($payload, 'aplicaciones', [])) as $aplicacion) {
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
                $typePayment = (int) data_get($payment, 'tipoPago');

                return $onlyTypePaymentEight
                    ? $typePayment === 8
                    : $typePayment !== 8;
            })
            ->pluck('documentoCobro')
            ->map(fn($doc) => trim((string)$doc))
            ->filter(fn($doc) => !empty($doc))
            ->unique()
            ->values()
            ->all();
    }


    protected function filterGroupReceiptNumbers(SupportCollection $payments): array
    {
        return $payments
            ->filter(fn($payment) => (int) data_get($payment, 'tipoPago') !== 8)
            ->pluck('numeroRecibo')
            ->map(fn($doc) => trim((string) $this->formatReceiptNumber($doc)))
            ->filter(fn($doc) => !empty($doc))
            ->unique()
            ->values()
            ->all();
    }

    protected function filterGroupReciptNumbersCheckPayment(SupportCollection $checkPaymentsPendings): array
    {
        return $checkPaymentsPendings
            ->filter(fn($payment) => (string) data_get($payment, 'documentoGp', '') === '')
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


    protected function getPendingWebNotAccountedByInvoice(string $invoice, array $documents,  string $desde, string $hasta, array $rcsPorExcluir = []): array
    {

        $applications   = $this->applyRepository->getPendingWebInvoiceApplicationsExcludingDocuments($invoice, $documents, $desde, $hasta, $rcsPorExcluir);
        $checks         = $this->applyRepository->getPendingWebCheckPaymentsByInvoice($invoice, $desde, $hasta, $rcsPorExcluir);
        $deposits       = $this->applyRepository->getPendingWebDepositPaymentsByInvoice($invoice, $desde, $hasta, $rcsPorExcluir);
        $cash           = $this->applyRepository->getPendingWebCashPaymentsByInvoice($invoice, $desde, $hasta, $rcsPorExcluir);
        $withholdings   = $this->applyRepository->getPendingWebWithholdingPaymentsByInvoice($invoice, $desde, $hasta, $rcsPorExcluir);
        $creditCards    = $this->applyRepository->getPendingWebCreditCardPaymentsByInvoice($invoice, $desde, $hasta, $rcsPorExcluir);
        $details = collect()
            ->merge($this->mapPendingWebDetails($applications, 'aplicacion', 'monto', 'id_factura'))
            ->merge($this->mapPendingWebDetails($checks, 'cheque', 'monto', 'id_factura'))
            ->merge($this->mapPendingWebDetails($deposits, 'deposito', 'monto', 'id_factura'))
            ->merge($this->mapPendingWebDetails($cash, 'efectivo', 'monto', 'id_factura'))
            ->merge($this->mapPendingWebDetails($withholdings, 'retencion', 'total', 'ret_factura'))
            ->merge($this->mapPendingWebDetails($creditCards, 'tarjeta', 'monto', 'id_factura'))
            ->values();

        return [
            'total'   => Money::normalize($details->sum('monto')),
            'details' => $details,
        ];
    }

    protected function getPaymentApplyGp(string $documento, string $ruc)
    {
        $resp = $this->api->getListCustomerPaymentApply([
            'documento' => $documento,
            'ruc'       => $ruc
        ]);
        return data_get($resp, 'data', []);
    }

    protected function getChecksPendingPayment(string $document, string $ruc)
    {
        $resp = $this->api->getListCustomerCheckPendingPayment([
            'documento' => $document,
            'ruc'       => $ruc
        ]);
        return data_get($resp, 'data');
    }


    protected function normalizeInvoiceNumber(?string $invoice): string
    {
        $invoice = trim((string) $invoice);
        return preg_replace('/\.\d+$/', '', $invoice);
    }


    protected function isValidWebInvoiceNumber(?string $invoice): bool
    {
        $invoice = strtoupper($this->normalizeInvoiceNumber($invoice));

        if ($invoice === '') {
            return false;
        }

        return preg_match('/^\d{3}-\d{3}-\d{6,9}$/', $invoice) === 1
            || preg_match('/^\d{3}-\d{6,9}$/', $invoice) === 1
            || preg_match('/^FAC-\d{3}-\d{3}-\d{6,9}$/', $invoice) === 1
            || preg_match('/^FAC-\d{3}-\d{6,9}$/', $invoice) === 1;
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




    protected function buildValidationHistory(
        string $tipoIngreso,
        GpCliente $cliente,
        string $facturaOrigen,
        string $factura,
        array $item,
        float $valorIngresado,
        array $validation,
        SupportCollection $payments,
        SupportCollection $checksPendings,
        SupportCollection $pendingWebDetails,
        SupportCollection $devolutions
    ): array {
        return [
            'cabecera' => [
                'cliente_id'                   => $cliente->id ?? $cliente->cliente_id ?? null,
                'cliente_ruc'                  => $cliente->CLI_RUC ?? null,

                'tipo_ingreso'                 => $tipoIngreso,

                'factura'                      => $facturaOrigen,
                'fecha_vencimiento'            => $this->getInvoiceDueDate($item),

                'monto_original_factura'       => $this->getInvoiceOriginalAmount($item),
                'saldo_cliente'                => $this->getInvoiceCustomerBalance($item),
                'valor_a_aplicar'              => Money::normalize($valorIngresado),

                'sum_payments_gp'              => Money::normalize(data_get($validation, 'sumPayments', 0)),
                'sum_pending_web'              => Money::normalize(data_get($validation, 'sumPendingWeb', 0)),
                'sum_devolutions'              => Money::normalize(data_get($validation, 'sumDevolutions', 0)),
                'total_apply'                  => Money::normalize(data_get($validation, 'totalApply', 0)),

                'saldo_factura'                => Money::normalize(data_get($validation, 'saldoFactura', 0)),
                'saldo_base'                   => Money::normalize(data_get($validation, 'saldoBase', 0)),
                'saldo_disponible'             => Money::normalize(data_get($validation, 'saldoDisponible', 0)),
                'total_pending_not_accounted'  => Money::normalize(data_get($validation, 'totalPendingNotAccounted', 0)),
                'excess_value'                 => Money::normalize(data_get($validation, 'excessValue', 0)),
                'price_recommend'              => Money::normalize(data_get($validation, 'priceRecommend', 0)),

                'has_excess'                   => (bool) data_get($validation, 'hasExcess', false),
                'has_double_apply'             => (bool) data_get($validation, 'hasDoubleApply', false),

                'validation_type'              => data_get($validation, 'validationType'),
                'validation_message'           => data_get($validation, 'validationMessage'),
                'double_apply_doc'             => data_get($validation, 'doubleApplyDoc'),
            ],

            'detalles' => collect()
                ->merge($this->mapGpPaymentDetails($payments))
                ->merge($checksPendings->map(function ($payment) {
                    return [
                        'tipo_origen'      => 'GP_APLICADO',
                        'tipo_pago'        => 'cheque',
                        'codigo_tipo_pago' => null,
                        'documento'        => data_get($payment, 'documentoGp'),
                        'numero_recibo'    => $this->formatReceiptNumber(
                            data_get($payment, 'numeroRecibo')
                        ),
                        'estado'           => data_get($payment, 'estado'),
                        'monto'            => Money::normalize(
                            data_get($payment, 'montoAplicado', 0)
                        ),
                        'fecha_documento'  => null,
                        'fecha_aplicacion' => null,
                    ];
                }))
                ->merge($pendingWebDetails)
                ->merge($this->mapDevolutionDetails($devolutions))
                ->values()
                ->all(),
        ];
    }

    protected function mapGpPaymentDetails(SupportCollection $payments): SupportCollection
    {
        return $payments->map(function ($payment) {
            return [
                'tipo_origen'       => 'GP_APLICADO',
                'tipo_pago'         => $this->mapTypePayment(data_get($payment, 'tipoPago')),
                'codigo_tipo_pago'  => data_get($payment, 'tipoPago'),
                'documento'         => data_get($payment, 'documentoCobro'),
                'numero_recibo'     => $this->formatReceiptNumber(data_get($payment, 'numeroRecibo')),
                'estado'            => null,
                'monto'             => Money::normalize(data_get($payment, 'valorCobro', data_get($payment, 'collectionAmount', 0))),
                'fecha_documento'   => data_get($payment, 'documentoFecha'),
                'fecha_aplicacion'  => data_get($payment, 'fechaAplicacion'),
            ];
        })->values();
    }

    protected function mapPendingWebDetails(
        SupportCollection $items,
        string $tipoPago,
        string $amountField = 'monto',
        string $invoiceField = 'id_factura'
    ): SupportCollection {
        return $items->map(function ($item) use ($tipoPago, $amountField, $invoiceField) {
            $factura = data_get($item, $invoiceField)
                ?? data_get($item, 'id_factura')
                ?? data_get($item, 'ret_factura');

            $numeroRecibo = data_get($item, 'cobroPendienteWeb.numero_recibo')
                ?? data_get($item, 'numeroRecibo')
                ?? data_get($item, 'CN_DOCUMENTO');

            return [
                'tipo_origen'       => 'WEB_PENDIENTE',
                'tipo_pago'         => $tipoPago,
                'codigo_tipo_pago'  => null,
                'documento'         => $factura,
                'numero_recibo'     => $this->formatReceiptNumber($numeroRecibo),
                'monto'             => Money::normalize(data_get($item, $amountField, 0)),
                'fecha_documento'   => data_get($item, 'created_at')
                    ? Carbon::parse(data_get($item, 'created_at'))->toDateString()
                    : null,
                'fecha_aplicacion'  => null,
            ];
        })->values();
    }

    protected function mapDevolutionDetails(SupportCollection $devolutions): SupportCollection
    {
        return $devolutions->map(function ($dev) {
            return [
                'tipo_origen'       => 'DEVOLUCION',
                'tipo_pago'         => 'devolucion',
                'codigo_tipo_pago'  => null,

                'documento'         => data_get($dev, 'DOC_GENERADO'),
                'numero_recibo'     => data_get($dev, 'devolucion'),
                'estado'            => data_get($dev, 'estado'),
                'monto'             => Money::normalize(data_get($dev, 'total', 0)),

                'fecha_documento'   => data_get($dev, 'created_at')
                    ? Carbon::parse(data_get($dev, 'created_at'))->toDateString()
                    : null,

                'fecha_aplicacion'  => null,
            ];
        })->values();
    }

    protected function mapTypePayment(?int $typePayment): string
    {
        return match ((int) $typePayment) {
            1       => 'factura',
            2       => 'pago_programado',
            3       => 'nota_debito',
            4       => 'cargo_financiero',
            5       => 'reparacion_servicio',
            6       => 'garantia',
            7       => 'nota_credito',
            8       => 'devolucion',
            9       => 'pago',
            default => 'desconocido',
        };
    }

    protected function getApplyDocumentsPendings(string $document, string $ruc): array
    {
        $resp = $this->api->getPendingApplicationDocument([
            'documento' => $document,
            'ruc'       => $ruc
        ]);

        $data = data_get($resp, 'data');
        return is_array($data) ? $data : [];
    }




    // funcion para guardar el historial

    protected function storeFromValidationResult(array $payload, GpUsuario $user): void
    {
        $grupoValidacion = (string) Str::uuid();

        $histories = $this->extractHistories($payload);

        if (empty($histories)) {
            return;
        }

        DB::transaction(function () use ($histories, $grupoValidacion, $user) {
            foreach ($histories as $history) {
                $cabeceraData = data_get($history, 'cabecera', []);
                $detallesData = data_get($history, 'detalles', []);

                $cabeceraData['grupo_validacion'] = $grupoValidacion;
                $cabeceraData['user_id'] = $user?->id;

                $cabecera = CobroValidacionFactura::create($cabeceraData);

                if (!empty($detallesData)) {
                    $cabecera->detalles()->createMany($detallesData);
                }
            }
        });
    }

    protected function extractHistories(array $payload): array
    {
        $histories = [];

        foreach ($this->arrayValue(data_get($payload, 'retenciones', [])) as $item) {
            if ($history = data_get($item, '_validacion_historial')) {
                $histories[] = $history;
            }
        }

        foreach ($this->arrayValue(data_get($payload, 'efectivo.facturas', [])) as $item) {
            if ($history = data_get($item, '_validacion_historial')) {
                $histories[] = $history;
            }
        }

        foreach ($this->arrayValue(data_get($payload, 'tarjeta.facturas', [])) as $item) {
            if ($history = data_get($item, '_validacion_historial')) {
                $histories[] = $history;
            }
        }

        foreach ($this->arrayValue(data_get($payload, 'cheque.facturas', [])) as $item) {
            if ($history = data_get($item, '_validacion_historial')) {
                $histories[] = $history;
            }
        }

        foreach ($this->arrayValue(data_get($payload, 'depositos', [])) as $deposito) {
            foreach ($this->arrayValue(data_get($deposito, 'facturas', [])) as $item) {
                if ($history = data_get($item, '_validacion_historial')) {
                    $histories[] = $history;
                }
            }
        }

        foreach ($this->arrayValue(data_get($payload, 'aplicaciones', [])) as $aplicacion) {
            foreach ($this->arrayValue(data_get($aplicacion, 'facturas', [])) as $item) {
                if ($history = data_get($item, '_validacion_historial')) {
                    $histories[] = $history;
                }
            }
        }

        return $histories;
    }

    protected function cleanInternalHistory(array $payload): array
    {
        foreach (['retenciones'] as $key) {
            foreach ($payload[$key] ?? [] as $index => $item) {
                unset($payload[$key][$index]['_validacion_historial']);
            }
        }

        foreach (['efectivo', 'tarjeta', 'cheque'] as $key) {
            foreach ($payload[$key]['facturas'] ?? [] as $index => $factura) {
                unset($payload[$key]['facturas'][$index]['_validacion_historial']);
            }
        }

        foreach (['depositos', 'aplicaciones'] as $key) {
            foreach ($payload[$key] ?? [] as $indexGrupo => $grupo) {
                foreach ($grupo['facturas'] ?? [] as $indexFactura => $factura) {
                    unset($payload[$key][$indexGrupo]['facturas'][$indexFactura]['_validacion_historial']);
                }
            }
        }

        return $payload;
    }


    /**
     * funcion dedicada para actualizar los saldos pendintes de documentos de aplicaciones
     */

    public function updatePendingApplicationDocumentBalances(int $clientId, array $documents)
    {
        $client = GpCliente::find($clientId);
        $pendingApplicationDocuments  = collect($this->getPendingApplicationDocumentsFromGp($client->CLI_RUC))
            ->keyBy(
                function ($document) {
                    return trim((string) data_get($document, 'documento', ''));
                }
            );

        return collect($documents)
            ->map(function ($document) use ($pendingApplicationDocuments) {
                $documentNumber = trim((string) data_get($document, 'CN_DOCUMENTO', ''));

                $pendingApplicationDocument = $pendingApplicationDocuments->get($documentNumber);
                $document['CN_SALDO_PENDIENTE'] = (float) data_get($pendingApplicationDocument, 'saldoCliente', 0);
                return $document;
            })
            ->values()
            ->all();
    }


    protected function getPendingApplicationDocumentsFromGp(string $ruc): array
    {
        return data_get($this->api->getUnappliedCollections([
            'ruc' => $ruc
        ]), 'data', []);
    }
}
