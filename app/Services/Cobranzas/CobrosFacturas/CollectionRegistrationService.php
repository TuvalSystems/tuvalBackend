<?php

namespace App\Services\Cobranzas\CobrosFacturas;

use App\Enums\Cobranzas\CobrosFacturas\CollectionStatusEnum;
use App\Jobs\Cobranzas\CobrosFacturas\SendCollectionToGpJob;
use App\Models\Cobranzas\Cobros\GpCobro;
use App\Models\Cobranzas\Cajas\GpCaja;
use App\Models\Cobranzas\Bancos\GpCajaBancos;
use App\Models\Admin\PuntosVenta\GpPuntoVenta;
use App\Models\Admin\PuntosVenta\GpRelacionPuntoVentaCaja;
use App\Models\Cobranzas\Cajas\GpRelacionUsuarioCaja;
use App\Models\Admin\Usuarios\GpUsuario;
use App\Services\Builders\Cobranzas\CobrosFacturas\Contracts\CollectionRegistrationBuilderInterface;
use App\Services\Cobranzas\CobrosFacturas\Contracts\CollectionRegistrationServiceInterface;

use Illuminate\Support\Facades\DB;

class CollectionRegistrationService implements CollectionRegistrationServiceInterface
{
    public function __construct(
        protected readonly CollectionRegistrationBuilderInterface $collectBuilder
    ) {}

    public function registerCollection(array $collectionData, GpUsuario $user): GpCobro
    {
        $boxId          = data_get($collectionData, 'caja_id');
        $pvId           = data_get($collectionData, 'pv_id');
        $pvCode         = data_get($collectionData, 'pvCodigo');
        $clientId       = data_get($collectionData, 'cliente_id');
        $boxed          = GpCaja::select(['id', 'CAJA_CODIGO as cajaCodigo'])->where('id', $boxId)->where('estado', 1)->first();
        $pvDescription  = GpPuntoVenta::where('id', $pvId)->value('PTO_VTA_DESCRIPCION');
        if (!$boxed) {
            throw new \Exception("La caja con ID {$boxId} no existe o no está activa.");
        }

        $existingRelation = GpRelacionPuntoVentaCaja::where('caja_id', $boxId)
            ->where('pv_id', $pvId)
            ->where('estado', 1)
            ->exists();

        if (!$existingRelation) {
            throw new \RuntimeException("La caja {$boxed->CAJA_CODIGO}no está asociada al punto de venta con ID {$pvCode}.");
        }
        $userBoxed = GpRelacionUsuarioCaja::where('caja_id', $boxId)->where('user_id', $user->id)->where('estado', 1)->first();
        if (!$userBoxed) {
            throw new \RuntimeException("El usuario {$user->USERID} no tiene acceso a la caja {$boxed->cajaCodigo} en la {$pvDescription}.");
        }

        $collectionRegister = DB::transaction(function () use ($collectionData, $user, $boxed, $userBoxed, $clientId) {
            $headerCollection = $this->collectBuilder->createHeaderCollection($collectionData, $userBoxed->user_id, $boxed);

            // Crear detalles de retención
            $this->createCollectionRetentionDetail(data_get($collectionData, 'retenciones'), $headerCollection->id);

            // Crear detalles de efectivo
            $this->createCollectionCashDetail(data_get($collectionData, 'efectivo'),  $headerCollection->id);

            // Crear detalles de depósitos
            $this->createCollectionDepositDetail(data_get($collectionData, 'depositos'),  $headerCollection->id);

            //crear detalle de cheque
            $this->createCollectionCheckDetail(data_get($collectionData, 'cheque'),  $headerCollection->id, $clientId);

            //crear detalle de tarjetas
            $this->createCollectionCardsDetail(data_get($collectionData, 'tarjeta'),  $headerCollection->id);

            //crear detalle de aplicaciones
            $this->createCollectionApplicationsDetail(data_get($collectionData, 'aplicaciones'),  $headerCollection->id);

            return $headerCollection;
        });

        SendCollectionToGpJob::dispatch($collectionRegister->id);

        return $collectionRegister;
    }

    private function createCollectionRetentionDetail(?array $retentionData, int $collectionId): void
    {
        foreach ($retentionData ?? [] as $retentionDetail) {
            if (!is_array($retentionDetail)) {
                continue;
            }

            $this->assertNoNegativeAmounts(
                $retentionDetail,
                ['total', 'iva', 'renta', 'ivaSrv', 'rentaSrv'],
                'retencion'
            );

            if (!$this->hasRetentionData($retentionDetail)) {
                continue;
            }

            $this->collectBuilder->createCollectionRetentionDetail($retentionDetail,  $collectionId);
        }
    }

    private function createCollectionCashDetail(?array $cashCollection, int $collectionId): void
    {
        $this->assertNoNegativePaymentAmounts(
            $cashCollection,
            'efectivo',
            ['montoACobrar'],
            ['aplicarMontoEfectivo']
        );

        if (!$this->hasSinglePaymentData($cashCollection, 'montoACobrar', 'aplicarMontoEfectivo')) {
            return;
        }

        $this->collectBuilder->createCollectionCashHeader($cashCollection, $collectionId);
        foreach (data_get($cashCollection, 'facturas', []) as $cashDetail) {
            if (!$this->hasInvoiceDetailData($cashDetail, 'aplicarMontoEfectivo')) {
                continue;
            }

            $this->collectBuilder->createCollectionCashDetail($cashDetail, $collectionId);
        }
    }


    private function createCollectionDepositDetail(?array $depositCollection, int $collectionId): void
    {
        foreach ($depositCollection ?? [] as $deposit) {
            if (!is_array($deposit)) {
                continue;
            }

            $this->assertNoNegativePaymentAmounts(
                $deposit,
                'deposito',
                ['montoRecibido'],
                ['aplicarMontoDeposito']
            );

            if (!$this->hasSinglePaymentData($deposit, 'montoRecibido', 'aplicarMontoDeposito')) {
                continue;
            }

            $backString = (string) GpCajaBancos::where('id', data_get($deposit, 'banco_id'))->value('CB_ID');
            $depositHeader = $this->collectBuilder->createCollectionDepositHeader($deposit,  $collectionId, $backString);

            foreach (data_get($deposit, 'facturas', []) as $depositDetail) {
                if (!$this->hasInvoiceDetailData($depositDetail, 'aplicarMontoDeposito')) {
                    continue;
                }

                $this->collectBuilder->createCollectionDepositDetail($depositDetail, $collectionId, $depositHeader->id);
            }
        }
    }



    private function createCollectionCheckDetail(?array $checkCollection, int $collectionId, int $clientId): void
    {
        $this->assertNoNegativePaymentAmounts(
            $checkCollection,
            'cheque',
            ['montoRecibido', 'montoNoAplicado'],
            ['aplicarMontoCheque']
        );

        if (!$this->hasSinglePaymentData($checkCollection, 'montoRecibido', 'aplicarMontoCheque')) {
            return;
        }

        $numCheckOrigin = data_get($checkCollection, 'cheque');
        if (!filled($numCheckOrigin)) {
            throw new \InvalidArgumentException('El numero de cheque es requerido cuando se registra un pago con cheque.');
        }

        $backString = (string) GpCajaBancos::where('id', data_get($checkCollection, 'banco_id'))->value('CB_ID');
        $numCheck   = $this->validateNumberCheck($clientId, (string) $numCheckOrigin);
        $this->collectBuilder->createCollectionCheckAmount($checkCollection, $collectionId);
        $this->collectBuilder->createCollectionCheckHeader($checkCollection, $collectionId, $backString, $numCheck);
        foreach (data_get($checkCollection, 'facturas', []) as $checkDetail) {
            if (!$this->hasInvoiceDetailData($checkDetail, 'aplicarMontoCheque')) {
                continue;
            }

            $this->collectBuilder->createCollectionCheckDetail($checkDetail, $collectionId);
        }
    }


    private function validateNumberCheck(
        int $clientId,
        string $numCheckOrigin
    ): string {
        $numCheckFinal = $numCheckOrigin;

        $numCheckCount = GpCobro::where('cliente_id', $clientId)
            ->whereHas('cheques', function ($query) use ($numCheckOrigin) {
                $query->where('cheque', 'like', '%' . $numCheckOrigin);
            })
            ->count();

        if ($numCheckCount > 0) {
            do {
                $largeDynamic = strlen($numCheckOrigin) + $numCheckCount;

                $numCheckFinal = str_pad(
                    $numCheckOrigin,
                    $largeDynamic,
                    '0',
                    STR_PAD_LEFT
                );

                $exists = GpCobro::where('cliente_id', $clientId)
                    ->whereHas('cheques', function ($query) use ($numCheckFinal) {
                        $query->where('cheque', $numCheckFinal);
                    })
                    ->exists();

                $numCheckCount++;
            } while ($exists);
        }

        return $numCheckFinal;
    }



    private function createCollectionCardsDetail(?array $cardsCollection, int $collectionId): void
    {
        $this->assertNoNegativePaymentAmounts(
            $cardsCollection,
            'tarjeta',
            ['montoRecibido'],
            ['aplicarMontoTarjeta']
        );

        if (!$this->hasSinglePaymentData($cardsCollection, 'montoRecibido', 'aplicarMontoTarjeta')) {
            return;
        }

        $bankDescription = (string) GpCajaBancos::where('id', data_get($cardsCollection, 'banco_id'))->value('CB_ID');
        $this->collectBuilder->createCollectionCardsHeader($cardsCollection, $collectionId, $bankDescription);
        foreach (data_get($cardsCollection, 'facturas', []) as $cardDetail) {
            if (!$this->hasInvoiceDetailData($cardDetail, 'aplicarMontoTarjeta')) {
                continue;
            }

            $this->collectBuilder->createCollectionCardsInvoice($cardDetail, $collectionId);
        }
    }

    private function createCollectionApplicationsDetail(?array $applicationsCollection, int $collectionId): void
    {
        foreach ($applicationsCollection ?? [] as $application) {
            if (!is_array($application)) {
                continue;
            }

            $this->assertNoNegativePaymentAmounts(
                $application,
                'aplicacion',
                ['montoDisponible'],
                ['aplicarMontoAplicacion']
            );

            if (!$this->hasSinglePaymentData($application, 'montoDisponible', 'aplicarMontoAplicacion')) {
                continue;
            }

            $applicationHeader = $this->collectBuilder->createCollectionApplicationHeader($application, $collectionId);
            foreach (data_get($application, 'facturas', []) as $applicationDetail) {
                if (!$this->hasInvoiceDetailData($applicationDetail, 'aplicarMontoAplicacion')) {
                    continue;
                }

                $this->collectBuilder->createCollectionApplicationDetail($applicationDetail, $collectionId, $applicationHeader->id);
            }
        }
    }

    private function hasSinglePaymentData(?array $paymentData, string $amountKey, string $invoiceAmountKey): bool
    {
        if (!is_array($paymentData) || empty($paymentData)) {
            return false;
        }

        return $this->hasPositiveAmount($paymentData, $amountKey)
            || collect(data_get($paymentData, 'facturas', []))
            ->contains(fn($invoice) => $this->hasInvoiceDetailData($invoice, $invoiceAmountKey));
    }

    private function hasRetentionData(array $retentionData): bool
    {
        return filled(data_get($retentionData, 'documento'))
            && filled(data_get($retentionData, 'comprobante'))
            && (
                $this->hasPositiveAmount($retentionData, 'total')
                || $this->hasPositiveAmount($retentionData, 'iva')
                || $this->hasPositiveAmount($retentionData, 'renta')
                || $this->hasPositiveAmount($retentionData, 'ivaSrv')
                || $this->hasPositiveAmount($retentionData, 'rentaSrv')
            );
    }

    private function hasInvoiceDetailData(mixed $invoiceDetail, string $amountKey): bool
    {
        return is_array($invoiceDetail)
            && filled(data_get($invoiceDetail, 'documento'))
            && $this->hasPositiveAmount($invoiceDetail, $amountKey);
    }

    private function hasPositiveAmount(array $data, string $key): bool
    {
        return (float) data_get($data, $key, 0) > 0;
    }

    private function assertNoNegativePaymentAmounts(
        ?array $paymentData,
        string $paymentLabel,
        array $amountKeys,
        array $invoiceAmountKeys
    ): void {
        if (!is_array($paymentData) || empty($paymentData)) {
            return;
        }

        $this->assertNoNegativeAmounts($paymentData, $amountKeys, $paymentLabel);

        foreach (data_get($paymentData, 'facturas', []) as $invoice) {
            if (!is_array($invoice)) {
                continue;
            }

            $this->assertNoNegativeAmounts($invoice, $invoiceAmountKeys, "{$paymentLabel}.facturas");
        }
    }

    private function assertNoNegativeAmounts(array $data, array $amountKeys, string $label): void
    {
        foreach ($amountKeys as $amountKey) {
            $amount = data_get($data, $amountKey);

            if ($amount !== null && (float) $amount < 0) {
                throw new \InvalidArgumentException("El monto {$amountKey} de {$label} no puede ser negativo.");
            }
        }
    }





    public function reSendCollectionPendings(): void
    {
        GpCobro::query()
            ->whereIn('estado', [
                CollectionStatusEnum::INICIADO->value,
                CollectionStatusEnum::REVISADO->value,
            ])
            ->chunkById(100, function ($collections) {
                foreach ($collections as $collect) {
                    SendCollectionToGpJob::dispatch($collect->id);
                }
            });
    }
}
