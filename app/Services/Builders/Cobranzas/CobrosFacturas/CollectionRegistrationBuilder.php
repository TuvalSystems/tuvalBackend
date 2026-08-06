<?php

namespace App\Services\Builders\Cobranzas\CobrosFacturas;

use App\Enums\Cobranzas\CobrosFacturas\CollectionStatusEnum;
use App\Enums\Cobranzas\CobrosFacturas\CollectTypeEnum;
use App\Models\Cobranzas\GpCobro;
use App\Models\Cobranzas\GpCobrosCheques;
use App\Models\Cobranzas\GpCobrosChequesFacturas;
use App\Models\Cobranzas\GpCobrosChequesMonto;
use App\Models\Cobranzas\GpCobrosDeposito;
use App\Models\Cobranzas\GpCobrosDepositosFacturas;
use App\Models\Cobranzas\GpCobrosEfectivoFactura;
use App\Models\Cobranzas\GpCobrosEfectivoMonto;
use App\Models\Cobranzas\GpCobrosRetenciones;
use App\Models\GpCaja;
use App\Models\GpCobrosAplicacionFactura;
use App\Models\GpCobrosAplicacionMonto;
use App\Models\GpCobrosTarjetaFactura;
use App\Models\GpCobrosTarjetaMonto;
use App\Services\Builders\Cobranzas\CobrosFacturas\Contracts\CollectionRegistrationBuilderInterface;
use Carbon\Carbon;

class CollectionRegistrationBuilder implements CollectionRegistrationBuilderInterface
{


    public function createHeaderCollection(array $collectionData, int $userBoxedId, GpCaja $boxed): GpCobro
    {
        $collection = new GpCobro();
        $collection->fill([
            'user_id'       => $userBoxedId,
            'pv_id'         => data_get($collectionData, 'pv_id'),
            'cliente_id'    => data_get($collectionData, 'cliente_id'),
            'caja_id'       => $boxed->id,
            'caja'          => $boxed->cajaCodigo,
            'fecha'         => Carbon::now()->toDateTimeString(),
            'estado'        => CollectionStatusEnum::INICIADO->value,
            'pedido'        => data_get($collectionData, 'pedido'),
            'referencia'    => data_get($collectionData, 'referencia'),
        ]);
        $collection->save();
        return $collection->refresh();
    }


    //retenciones
    public function createCollectionRetentionDetail(array $data, int $collectionId): void
    {
        $retention = new GpCobrosRetenciones();
        $retention->fill([
            'cobro_id'                  => $collectionId,
            'ret_factura'               => data_get($data, 'documento'),
            'ret_fecha_emision'         => data_get($data, 'fechaEmision'),
            'ret_fecha_vencimiento'     => data_get($data, 'fechaVencimiento'),
            'ret_subtotal_factura'      => data_get($data, 'subtotal'),
            'ret_iva_factura'           => data_get($data, 'ivaFactura'),
            'ret_total_factura'         => data_get($data, 'montoOriginal'),
            'ret_saldo_factura'         => data_get($data, 'saldoFactura'),
            'ret_iva'                   => data_get($data, 'iva'),
            'ret_porcentaje_iva'        => data_get($data, 'porcentajeIva'),
            'ret_renta'                 => data_get($data, 'renta'),
            'ret_porcentaje_renta'      => data_get($data, 'porcentajeRenta'),

            'ret_iva_srv'               => data_get($data, 'ivaSrv'),
            'ret_porcentaje_iva_srv'      => data_get($data, 'porcentajeIvaSrv'),
            'ret_renta_srv'             => data_get($data, 'rentaSrv'),
            'ret_porcentaje_renta_srv'  => data_get($data, 'porcentajeRentaSrv'),

            'ret_emision'               => data_get($data, 'retEmision'),
            'ret_caducidad'             => data_get($data, 'retCaducidad'),
            'ret_comprobante'           => data_get($data, 'comprobante'),
            'ret_autorizacion'          => data_get($data, 'autorizacion'),
            'total'                     => data_get($data, 'total'),
        ]);
        $retention->save();
    }



    //efectivo
    public function createCollectionCashHeader(array $data, int $collectionId): void
    {
        $cashHeader = new GpCobrosEfectivoMonto();
        $cashHeader->fill([
            'cobro_id' => $collectionId,
            'monto'    => data_get($data, 'montoACobrar', 0),
        ]);
        $cashHeader->save();
    }

    public function createCollectionCashDetail(array $data, int $collectionId): void
    {
        $cashInvoice = new GpCobrosEfectivoFactura();
        $cashInvoice->fill([
            'cobro_id'       => $collectionId,
            'id_factura'     => data_get($data, 'documento'),
            'monto'          => data_get($data, 'aplicarMontoEfectivo', 0),
            'observacion'    => data_get($data, 'observacionEfectivo', ''),
        ]);
        $cashInvoice->save();
    }



    //depositos
    public function createCollectionDepositHeader(array $data, int $collectionId, string $bankDescription): GpCobrosDeposito
    {
        $depositHeader = new GpCobrosDeposito();
        $depositHeader->fill([
            'cobro_id'      => $collectionId,
            'banco_id'     => data_get($data, 'banco_id'),
            'estado'        => CollectionStatusEnum::INICIADO->value,
            'tipo'          => data_get($data, 'tipo'),
            'banco'         => $bankDescription,
            'comprobante'   => data_get($data, 'comprobante'),
            'fecha'         => data_get($data, 'fechaDeposito'),
            'monto'         => data_get($data, 'montoRecibido'),
        ]);
        $depositHeader->save();
        return $depositHeader;
    }

    public function createCollectionDepositDetail(array $data, int $collectionId, int $depositId): void
    {
        $depositInvoice = new GpCobrosDepositosFacturas();
        $depositInvoice->fill([
            'cobro_id'      => $collectionId,
            'deposito_id'   => $depositId,
            'id_factura'    => data_get($data, 'documento'),
            'monto'         => data_get($data, 'aplicarMontoDeposito', 0),
            'observacion'   => data_get($data, 'observacionDeposito', ''),
        ]);
        $depositInvoice->save();
    }



    //cheque
    public function createCollectionCheckAmount(array $data, int $collectionId): void
    {
        $checkAmount = new GpCobrosChequesMonto();
        $checkAmount->fill([
            'cobro_id'  => $collectionId,
            'monto'     => data_get($data, 'montoRecibido', 0),
            'saldo'     => data_get($data, 'montoNoAplicado', 0),
        ]);

        $checkAmount->save();
    }


    public function createCollectionCheckHeader(array $data, int $collectionId, string $bankDescription, string $numCheck): void
    {
        $checkHeader = new GpCobrosCheques();
        $checkHeader->fill([
            'cobro_id'          => $collectionId,
            'banco_id'          => data_get($data, 'banco_id'),
            'banco'             => $bankDescription,
            'cuenta'            => data_get($data, 'cuenta'),
            'cheque'            => $numCheck,
            'monto'             => data_get($data, 'montoRecibido', 0),
            'fecha_emision'     => data_get($data, 'fechaEmision'),
            'fecha_vencimiento' => data_get($data, 'fechaVencimiento'),
            'comentario'        => data_get($data, 'comentario', ''),
        ]);
        $checkHeader->save();
    }


    public function createCollectionCheckDetail(array $data, int $collectionId): void
    {
        $checkDetail = new GpCobrosChequesFacturas();
        $checkDetail->fill([
            'cobro_id'                  => $collectionId,
            'id_factura'                => data_get($data, 'documento'),
            'monto'                     => data_get($data, 'aplicarMontoCheque', 0),
            'observacion'               => data_get($data, 'observacionCheque', ''),
            'ECD_DET_FECHA_VENCIMIENTO' => data_get($data, 'fechaVencimiento'),
        ]);
        $checkDetail->save();
    }


    //tarjetas
    public function createCollectionCardsHeader(array $data, int $collectionId, string $bankDescription): void
    {
        $cardHeader = new GpCobrosTarjetaMonto();
        $cardHeader->fill([
            'cobro_id'      => $collectionId,
            'banco_id'      => data_get($data, 'banco_id'),
            'banco'         => $bankDescription,
            'monto'         => data_get($data, 'montoRecibido', 0),
            'fecha'         => data_get($data, 'fechaTarjeta'),
            'comprobante'   => data_get($data, 'comprobante'),
            'comentario'    => data_get($data, 'comentario'),
        ]);
        $cardHeader->save();
    }


    public function createCollectionCardsInvoice(array $data, int $collectionId): void
    {
        $cardInvoice = new GpCobrosTarjetaFactura();
        $cardInvoice->fill([
            'cobro_id'      => $collectionId,
            'id_factura'    => data_get($data, 'documento'),
            'monto'         => data_get($data, 'aplicarMontoTarjeta', 0),
            'observacion'   => data_get($data, 'observacionTarjeta', ''),
        ]);
        $cardInvoice->save();
    }

    //aplicaciones

    public function createCollectionApplicationHeader(array $data, int $collectionId): GpCobrosAplicacionMonto
    {
        $apply = new GpCobrosAplicacionMonto();
        $apply->fill([
            'cobro_id'      => $collectionId,
            'cobro'         => data_get($data, 'documento'),
            'monto'         => data_get($data, 'montoDisponible', 0),
            'tipo'          => data_get($data, 'tipo'),
            'comentario'    => data_get($data, 'comentario', ''),
        ]);
        $apply->save();
        return $apply->fresh();
    }


    public function createCollectionApplicationDetail(array $data, int $collectionId, int $applicationId): void
    {
        $applyDetail = new GpCobrosAplicacionFactura();
        $applyDetail->fill([
            'cobro_id'          => $collectionId,
            'aplicacion_id'     => $applicationId,
            'id_factura'        => data_get($data, 'documento'),
            'monto'             => data_get($data, 'aplicarMontoAplicacion', 0),
            'observacion'       => data_get($data, 'observacionAplicacion', ''),
        ]);
        $applyDetail->save();
    }




    /** funciones para envio de metodos de cobros a gp */


    public function payloadCollectionHeader(
        GpCobro $collection,
        string $receiptNumber,
        string $ruc,
        string $boxedCode,
        float $cashPaymentAmount,
        float $unappliedAmount,
    ): array {
        return [
            'numeroRecibo'          => $receiptNumber, //$collect->numero_recibo .'-' .CollectTypeEnum::EFECTIVO_E->value,
            'ruc'                   => $ruc,
            'fechaCobro'            => Carbon::parse($collection->fecha)->format('Y-m-d H:i:s'),
            'tipoCobro'             => CollectTypeEnum::EFECTIVO->value,
            'observacion'           => $collection->referencia,
            'solicitudDescuento'    => $collection->pedido, //no se llena pero la pongo por cualquier motivo
            'idVendedor'            => $boxedCode,
            'montoCobro'            => (float) $cashPaymentAmount,
            'saldoPendiente'        => (float) $unappliedAmount,
            'bancoTuval'            => $boxedCode,
            'fechaDeposito'         => $collection->fecha,
            'numeroPapeleta'        => '',
        ];
    }

    //puede ser general para los metodos de pago que lo implementen
    public function payloadCollectionDetailInvoices(
        string $receiptNumber,
        string $ruc,
        string $numberInvoice,
        float $applyAmount,
        ?string $obs
    ): array {
        return [
            'recibo'        => $receiptNumber,
            'idcliente'     => $ruc,
            'factura'       => $numberInvoice,
            'monto'         => $applyAmount,
            'observacion'   => $obs
        ];
    }


    // payload para envio de deposito a gp
    public function payloadCollectionDepositHeader(
        GpCobro $collection,
        GpCobrosDeposito $deposit,
        string $receiptNumber,
        string $ruc,
        string $boxedCode,
        float $unappliedAmount,
    ): array {
        return [
            'numeroRecibo'          => $receiptNumber,
            'ruc'                   => $ruc,
            'fechaCobro'            => Carbon::parse($collection->fecha)->format('Y-m-d H:i:s'),
            'tipoCobro'             => $deposit->tipo,
            'observacion'           => $collection->referencia,
            'solicitudDescuento'    => $collection->pedido, //no se llena pero la pongo por cualquier motivo
            'idVendedor'            => $boxedCode,
            'montoCobro'            => (float) $deposit->monto,
            'saldoPendiente'        => (float) $unappliedAmount,
            'bancoTuval'            => $deposit->banco,
            'fechaDeposito'         => $deposit->fecha . ' 00:00:00',
            'numeroPapeleta'        => $deposit->comprobante,
        ];
    }

    //payload para envio de tarjeta
    public function payloadCollectionCardHeader(
        GpCobro $collection,
        GpCobrosTarjetaMonto $card,
        string $receiptNumber,
        string $ruc,
        string $boxedCode,
        float $unappliedAmount,
    ): array {
        return [
            'numeroRecibo'          => $receiptNumber,
            'ruc'                   => $ruc,
            'fechaCobro'            => Carbon::parse($collection->fecha)->format('Y-m-d H:i:s'),
            'tipoCobro'             => CollectTypeEnum::TARJETA->value,
            'observacion'           => $card->comentario,
            'solicitudDescuento'    => $collection->pedido, //no se llena pero la pongo por cualquier motivo
            'idVendedor'            => $boxedCode,
            'montoCobro'            => (float) $card->monto,
            'saldoPendiente'        => (float) $unappliedAmount,
            'bancoTuval'            => $card->banco,
            'fechaDeposito'         => Carbon::parse($collection->fecha)->format('Y-m-d H:i:s'),
            'numeroPapeleta'        => $card->comprobante,
        ];
    }
}
