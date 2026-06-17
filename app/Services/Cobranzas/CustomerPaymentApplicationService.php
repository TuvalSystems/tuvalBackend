<?php

namespace App\Services\Cobranzas;

use App\Models\Cobranzas\CobroValidacionFactura;
use App\Models\GpCliente;
use App\Models\GpUsuario;
use App\Repositories\Cobranzas\Contracts\WebInvoiceApplicationRepositoryInterface;
use App\Services\Api\Cobros\ApiBillingService;
use App\Services\Cobranzas\Contracts\CustomerPaymentApplicationServiceInterface;
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
        $fechaCobro = now();
        $desde                              = $fechaCobro->copy()->startOfDay()->toDateTimeString();
        $hasta                              = $fechaCobro->copy()->endOfDay()->toDateTimeString();
        $payload['retenciones']             = $this->processRetenciones(data_get($payload, 'retenciones', []),  $desde, $hasta, $cliente);
        $payload['ingreso_efectivo']        = $this->processIngresoEfectivo(data_get($payload, 'ingreso_efectivo', []), $desde, $hasta, $cliente);
        $payload['ingreso_tarjeta']         = $this->processIngresoTarjeta(data_get($payload, 'ingreso_tarjeta', []), $desde, $hasta, $cliente);
        $payload['ingreso_depositos']       = $this->processIngresoDeposito(data_get($payload, 'ingreso_depositos', []), $desde, $hasta, $cliente);
        $payload['ingreso_aplicaciones']    = $this->processIngresoAplicaciones(data_get($payload, 'ingreso_aplicaciones', []), $desde, $hasta, $cliente);
        $payload['ingreso_cheques']         = $this->processIngresoCheques(data_get($payload, 'ingreso_cheques', []), $desde, $hasta, $cliente);
        $payload['hasGlobalExcess']         = $this->hasAnyExcess($payload);
        $this->storeFromValidationResult($payload, $user);

        return $this->cleanInternalHistory($payload);
    }

    protected function processRetenciones(array $retenciones, string $desde, string $hasta, GpCliente $cliente)
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

            $payments               = collect($this->getPaymentApplyGp($facturaOrigen, $cliente->CLI_RUC));
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

            //sumatoria de lo pendiente
            $pendingWebResult       = $this->getPendingWebNotAccountedByInvoice($factura, $groupDocuments, $desde, $hasta);
            $sumPendingWeb          = $pendingWebResult['total'];
            $pendingWebDetails      = $pendingWebResult['details'];
            //sumatoria  de devoluciones pendeintes de procesar
            $devolutions            = collect($this->getDevolutions($order->devolucionesBloqueantes ?? new Collection(), $devPaymentDocs));
            $sumDev                 = $this->money($devolutions->sum('total'));
            $devsInfo               = $this->formatInfoDevolutions($devolutions);
            $validation = $this->buildInvoiceValidation(
                $valorIngresado,
                $saldoFactura,
                $sumPayments,
                $sumPendingWeb,
                $sumDev,
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
            $facturaOrigen          = trim(data_get($ing, 'ECD_DET_NUM_DOC', ''));
            $factura                = $this->normalizeInvoiceNumber($facturaOrigen);
            if (!$this->isValidWebInvoiceNumber($factura)) {
                $ing = $this->markSkippedInvoiceValidation($ing, $facturaOrigen, $factura);
                continue;
            }
            $valorIngresado         = $this->money(data_get($ing, 'aplicar_monto_efectivo', 0));
            $saldoFactura           = $this->money(data_get($ing, 'ECD_DET_MONTO_ORIGINAL', 0));
            $order                  = $this->applyRepository->getOrderWithDevolution($factura, $desde, $hasta);

            $payments               = collect($this->getPaymentApplyGp($facturaOrigen, $cliente->CLI_RUC));
            $sumPayments            = $this->money($payments->sum('valorCobro'));
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
            //sumatoria de lo pendiente
            $pendingWebResult       = $this->getPendingWebNotAccountedByInvoice($factura, $groupDocuments, $desde, $hasta);
            $sumPendingWeb          = $pendingWebResult['total'];
            $pendingWebDetails      = $pendingWebResult['details'];
            //sumatoria  de devoluciones pendeintes de procesar
            $devolutions            = collect($this->getDevolutions($order->devolucionesBloqueantes ?? new Collection(), $devPaymentDocs));
            $sumDev                 = $this->money($devolutions->sum('total'));
            $devsInfo               = $this->formatInfoDevolutions($devolutions);
            $validation             = $this->buildInvoiceValidation(
                $valorIngresado,
                $saldoFactura,
                $sumPayments,
                $sumPendingWeb,
                $sumDev
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
            $facturaOrigen          = trim(data_get($ing, 'ECD_DET_NUM_DOC', ''));
            $factura                = $this->normalizeInvoiceNumber($facturaOrigen);
            if (!$this->isValidWebInvoiceNumber($factura)) {
                $ing = $this->markSkippedInvoiceValidation($ing, $facturaOrigen, $factura);
                continue;
            }
            $valorIngresado         = $this->money(data_get($ing, 'aplicar_monto_tarjeta', 0));
            $saldoFactura           = $this->money(data_get($ing, 'ECD_DET_MONTO_ORIGINAL', 0));
            $order                  = $this->applyRepository->getOrderWithDevolution($factura, $desde, $hasta);

            $payments               = collect($this->getPaymentApplyGp($facturaOrigen, $cliente->CLI_RUC));
            $sumPayments            = $this->money($payments->sum('valorCobro'));
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
            //sumatoria de lo pendiente
            $pendingWebResult       = $this->getPendingWebNotAccountedByInvoice($factura, $groupDocuments, $desde, $hasta);
            $sumPendingWeb          = $pendingWebResult['total'];
            $pendingWebDetails      = $pendingWebResult['details'];
            //sumatoria  de devoluciones pendeintes de procesar
            $devolutions            = collect($this->getDevolutions($order->devolucionesBloqueantes ?? new Collection(), $devPaymentDocs));
            $sumDev                 = $this->money($devolutions->sum('total'));
            $devsInfo               = $this->formatInfoDevolutions($devolutions);
            $validation             = $this->buildInvoiceValidation(
                $valorIngresado,
                $saldoFactura,
                $sumPayments,
                $sumPendingWeb,
                $sumDev
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
                $facturaOrigen          = trim(data_get($ing, 'ECD_DET_NUM_DOC', ''));
                $factura                = $this->normalizeInvoiceNumber($facturaOrigen);

                if (!$this->isValidWebInvoiceNumber($factura)) {
                    $ing = $this->markSkippedInvoiceValidation($ing, $facturaOrigen, $factura);
                    continue;
                }
                $valorIngresado         = $this->money(data_get($ing, 'aplicar_monto_deposito', 0));
                $saldoFactura           = $this->money(data_get($ing, 'ECD_DET_MONTO_ORIGINAL', 0));
                $order                  = $this->applyRepository->getOrderWithDevolution($factura, $desde, $hasta);

                $payments               = collect($this->getPaymentApplyGp($facturaOrigen, $cliente->CLI_RUC));
                $sumPayments            = $this->money($payments->sum('valorCobro'));
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

                //sumatoria de lo pendiente
                $pendingWebResult       = $this->getPendingWebNotAccountedByInvoice($factura, $groupDocuments, $desde, $hasta);
                $sumPendingWeb          = $pendingWebResult['total'];
                $pendingWebDetails      = $pendingWebResult['details'];
                //sumatoria  de devoluciones pendeintes de procesar
                $devolutions            = collect($this->getDevolutions($order->devolucionesBloqueantes ?? new Collection(), $devPaymentDocs));
                $sumDev                 = $this->money($devolutions->sum('total'));
                $devsInfo               = $this->formatInfoDevolutions($devolutions);
                $validation = $this->buildInvoiceValidation(
                    $valorIngresado,
                    $saldoFactura,
                    $sumPayments,
                    $sumPendingWeb,
                    $sumDev,
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

                $payments               = collect($this->getPaymentApplyGp($facturaOrigen, $cliente->CLI_RUC));
                $sumPayments            = $this->money($payments->sum('valorCobro'));
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
                $pendingWebResult       = $this->getPendingWebNotAccountedByInvoice($factura, $groupDocuments, $desde, $hasta,  $rcPayments,  'ingreso_aplicaciones');
                $sumPendingWeb          = $pendingWebResult['total'];
                $pendingWebDetails      = $pendingWebResult['details'];
                //sumatoria  de devoluciones pendeintes de procesar
                $devolutions            = collect($this->getDevolutions($order->devolucionesBloqueantes ?? new Collection(), $groupDevs));
                $sumDev                 = $this->money($devolutions->sum('total'));
                $devsInfo               = $this->formatInfoDevolutions($devolutions);



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

    protected function processIngresoCheques(array $data, string $desde, string $hasta, GpCliente $cliente)
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

            $payments               = collect($this->getPaymentApplyGp($facturaOrigen, $cliente->CLI_RUC));
            $sumPayments            = $this->money($payments->sum('valorCobro'));
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

            //sumatoria de lo pendiente
            $pendingWebResult       = $this->getPendingWebNotAccountedByInvoice($factura, $groupDocuments, $desde, $hasta);
            $sumPendingWeb          = $pendingWebResult['total'];
            $pendingWebDetails      = $pendingWebResult['details'];
            //sumatoria  de devoluciones pendeintes de procesar
            $devolutions            = collect($this->getDevolutions($order->devolucionesBloqueantes ?? new Collection(), $devPaymentDocs));
            $sumDev                 = $this->money($devolutions->sum('total'));
            $devsInfo               = $this->formatInfoDevolutions($devolutions);
            $validation = $this->buildInvoiceValidation(
                $valorIngresado,
                $saldoFactura,
                $sumPayments,
                $sumPendingWeb,
                $sumDev,
            );

            $ing = array_merge($ing, $validation, [
                'devoluciones_info' => $devsInfo,
                '_validacion_historial' => $this->buildValidationHistory(
                    tipoIngreso: 'cheques',
                    cliente: $cliente,
                    facturaOrigen: $facturaOrigen,
                    factura: $factura,
                    item: $ing,
                    valorIngresado: $valorIngresado,
                    validation: $validation,
                    payments: $payments,
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


    protected function getPendingWebNotAccountedByInvoice(string $invoice, array $documents,  string $desde, string $hasta, array $rcPayments = [],  ?string $type = null): array
    {

        $rcsPorExcluir = $type === 'ingreso_aplicaciones' ? $rcPayments : [];
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
            'total'   => $this->money($details->sum('monto')),
            'details' => $details,
        ];
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




    protected function buildValidationHistory(
        string $tipoIngreso,
        GpCliente $cliente,
        string $facturaOrigen,
        string $factura,
        array $item,
        float $valorIngresado,
        array $validation,
        SupportCollection $payments,
        SupportCollection $pendingWebDetails,
        SupportCollection $devolutions
    ): array {
        return [
            'cabecera' => [
                'cliente_id'                   => $cliente->id ?? $cliente->cliente_id ?? null,
                'cliente_ruc'                  => $cliente->CLI_RUC ?? null,

                'tipo_ingreso'                 => $tipoIngreso,

                'factura'                      => $facturaOrigen,
                'fecha_vencimiento'            => data_get($item, 'ECD_DET_FECHA_VENCIMIENTO'),

                'monto_original_factura'       => $this->money(data_get($item, 'ECD_DET_MONTO_ORIGINAL', 0)),
                'saldo_cliente'                => $this->money(data_get($item, 'ECD_DET_SALDO_CLIENTE', 0)),
                'valor_a_aplicar'              => $this->money($valorIngresado),

                'sum_payments_gp'              => $this->money(data_get($validation, 'sumPayments', 0)),
                'sum_pending_web'              => $this->money(data_get($validation, 'sumPendingWeb', 0)),
                'sum_devolutions'              => $this->money(data_get($validation, 'sumDevolutions', 0)),
                'total_apply'                  => $this->money(data_get($validation, 'totalApply', 0)),

                'saldo_factura'                => $this->money(data_get($validation, 'saldoFactura', 0)),
                'saldo_base'                   => $this->money(data_get($validation, 'saldoBase', 0)),
                'saldo_disponible'             => $this->money(data_get($validation, 'saldoDisponible', 0)),
                'total_pending_not_accounted'  => $this->money(data_get($validation, 'totalPendingNotAccounted', 0)),
                'excess_value'                 => $this->money(data_get($validation, 'excessValue', 0)),
                'price_recommend'              => $this->money(data_get($validation, 'priceRecommend', 0)),

                'has_excess'                   => (bool) data_get($validation, 'hasExcess', false),
                'has_double_apply'             => (bool) data_get($validation, 'hasDoubleApply', false),

                'validation_type'              => data_get($validation, 'validationType'),
                'validation_message'           => data_get($validation, 'validationMessage'),
                'double_apply_doc'             => data_get($validation, 'doubleApplyDoc'),
            ],

            'detalles' => collect()
                ->merge($this->mapGpPaymentDetails($payments))
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
                'tipo_pago'         => $this->mapTypePayment(data_get($payment, 'typePayment')),
                'codigo_tipo_pago'  => data_get($payment, 'typePayment'),
                'documento'         => data_get($payment, 'collectionId'),
                'numero_recibo'     => $this->formatReceiptNumber(data_get($payment, 'numeroRecibo')),
                'estado'            => null,
                'monto'             => $this->money(data_get($payment, 'collectionAmount', 0)),
                'fecha_documento'   => data_get($payment, 'collectionDocDate'),
                'fecha_aplicacion'  => data_get($payment, 'collectionApplyDate'),
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
                'monto'             => $this->money(data_get($item, $amountField, 0)),
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
                'monto'             => $this->money(data_get($dev, 'total', 0)),

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

        foreach (data_get($payload, 'retenciones', []) as $item) {
            if ($history = data_get($item, '_validacion_historial')) {
                $histories[] = $history;
            }
        }

        foreach (data_get($payload, 'ingreso_efectivo.facturas', []) as $item) {
            if ($history = data_get($item, '_validacion_historial')) {
                $histories[] = $history;
            }
        }

        foreach (data_get($payload, 'ingreso_tarjeta.facturas', []) as $item) {
            if ($history = data_get($item, '_validacion_historial')) {
                $histories[] = $history;
            }
        }

        foreach (data_get($payload, 'ingreso_cheques.facturas', []) as $item) {
            if ($history = data_get($item, '_validacion_historial')) {
                $histories[] = $history;
            }
        }

        foreach (data_get($payload, 'ingreso_depositos', []) as $deposito) {
            foreach (data_get($deposito, 'facturas', []) as $item) {
                if ($history = data_get($item, '_validacion_historial')) {
                    $histories[] = $history;
                }
            }
        }

        foreach (data_get($payload, 'ingreso_aplicaciones', []) as $aplicacion) {
            foreach (data_get($aplicacion, 'facturas', []) as $item) {
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

        foreach (['ingreso_efectivo', 'ingreso_tarjeta', 'ingreso_cheques'] as $key) {
            foreach ($payload[$key]['facturas'] ?? [] as $index => $factura) {
                unset($payload[$key]['facturas'][$index]['_validacion_historial']);
            }
        }

        foreach (['ingreso_depositos', 'ingreso_aplicaciones'] as $key) {
            foreach ($payload[$key] ?? [] as $indexGrupo => $grupo) {
                foreach ($grupo['facturas'] ?? [] as $indexFactura => $factura) {
                    unset($payload[$key][$indexGrupo]['facturas'][$indexFactura]['_validacion_historial']);
                }
            }
        }

        return $payload;
    }
}
