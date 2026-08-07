<?php

namespace App\Services\Builders\Cobranzas\CobrosFacturas\Contracts;

use App\Models\Cobranzas\GpCobro;
use App\Models\Cobranzas\GpCobrosDeposito;
use App\Models\Cobranzas\GpCobrosRetenciones;
use App\Models\GpCaja;
use App\Models\GpCobrosAplicacionMonto;
use App\Models\GpCobrosTarjetaMonto;

interface CollectionRegistrationBuilderInterface
{
    public function createHeaderCollection(array $collectionData, int $userBoxedId, GpCaja $boxed): GpCobro;
    //retenciones
    public function createCollectionRetentionDetail(array $data, int $collectionId): void;
    //efectivo
    public function createCollectionCashHeader(array $data, int $collectionId): void;
    public function createCollectionCashDetail(array $data, int $collectionId): void;
    //depositos
    public function createCollectionDepositHeader(array $data, int $collectionId, string $bankDescription): GpCobrosDeposito;
    public function createCollectionDepositDetail(array $data, int $collectionId, int $depositId): void;

    // cheque
    public function createCollectionCheckAmount(array $data, int $collectionId): void;
    public function createCollectionCheckHeader(array $data, int $collectionId, string $bankDescription, string $numCheck): void;
    public function createCollectionCheckDetail(array $data, int $collectionId): void;

    //tarjeta
    public function createCollectionCardsHeader(array $data, int $collectionId, string $bankDescription): void;
    public function createCollectionCardsInvoice(array $data, int $collectionId): void;

    //aplicaciones
    public function createCollectionApplicationHeader(array $data, int $collectionId): GpCobrosAplicacionMonto;
    public function createCollectionApplicationDetail(array $data, int $collectionId, int $applicationId): void;


    //payloads para enviar a gp
    // efectivo
    public function payloadCollectionHeader(
        GpCobro $collect,
        string $receiptNumber,
        string $ruc,
        string $boxedCode,
        float $cashPaymentAmount,
        float $unappliedAmount
    ): array;
    public function payloadCollectionDetailInvoices(string $receiptNumber, string $ruc, string $numberInvoice, float $applyAmount, ?string $obs = ''): array;


    public function payloadCollectionDepositHeader(
        GpCobro $collection,
        GpCobrosDeposito $deposit,
        string $receiptNumber,
        string $ruc,
        string $boxedCode,
        float $unappliedAmount,
    ): array;

    public function payloadCollectionCardHeader(
        GpCobro $collection,
        GpCobrosTarjetaMonto $card,
        string $receiptNumber,
        string $ruc,
        string $boxedCode,
        float $unappliedAmount,
    ): array;

       public function payloadCollectionRetentionHeader(
        GpCobro $collection,
        GpCobrosRetenciones $retention,
        string $receiptNumber,
        string $typeCollection,
        string $ruc,
        string $boxedCode,
        float $amount,
        float $porcentilAmount
    ) : array;
}
