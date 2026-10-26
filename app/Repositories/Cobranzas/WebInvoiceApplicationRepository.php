<?php

namespace App\Repositories\Cobranzas;

use App\Models\Cobranzas\Cheques\GpCobrosChequesFacturas;
use App\Models\Cobranzas\Depositos\GpCobrosDepositosFacturas;
use App\Models\Cobranzas\Efectivo\GpCobrosEfectivoFactura;
use App\Models\Cobranzas\Retenciones\GpCobrosRetenciones;
use App\Models\Cobranzas\Aplicaciones\GpCobrosAplicacionFactura;
use App\Models\Cobranzas\Aplicaciones\GpCobrosTarjetaFactura;
use App\Models\Ventas\Pedidos\GpPedido;
use App\Repositories\Cobranzas\Contracts\WebInvoiceApplicationRepositoryInterface;

class WebInvoiceApplicationRepository implements WebInvoiceApplicationRepositoryInterface
{


    public function getOrderWithDevolution(string $factura, string $desde, string $hasta)
    {
        return  GpPedido::where('factura', $factura)
            ->with([
                'devolucionesBloqueantes' => function ($q)  use ($desde, $hasta) {
                    $q->select([
                        'gp_devoluciones.id',
                        'gp_devoluciones.devolucion',
                        'gp_devoluciones.DOC_GENERADO',
                        'gp_devoluciones.USERID',
                        'gp_devoluciones.pedido_id',
                        'gp_devoluciones.estado',
                        'gp_devoluciones.total',
                        'gp_devoluciones.created_at',
                    ])
                        ->whereBetween('gp_devoluciones.created_at', [$desde, $hasta]);
                }
            ])->first();
    }


    //aplicacion de facturas
    public function getPendingWebInvoiceApplicationsExcludingDocuments(string $invoice, array $documents, string $desde, string $hasta, array $rcsPorExcluir = [])
    {
        return GpCobrosAplicacionFactura::with([
            'cobroPendienteWeb:id,numero_recibo',
            'aplicacionMonto'
        ])
            ->pendientesWeb($desde, $hasta, $rcsPorExcluir)
            ->where('id_factura', $invoice)
            ->where('estado_aplicado', 1)
            ->whereHas('aplicacionMonto', function ($q) use ($documents) {
                if (!empty($documents)) {
                    $q->whereNotIn('cobro', $documents);
                }
            })
            ->get([
                'id',
                'cobro_id',
                'aplicacion_id',
                'id_factura',
                'monto',
                'created_at'
            ]);
    }


    //cheques facturas pendientes
    public function getPendingWebCheckPaymentsByInvoice(string $invoice, string $desde, string $hasta, array $rcsPorExcluir = [])
    {
        return GpCobrosChequesFacturas::with( 'cobroPendienteWeb:id,numero_recibo')
            ->pendientesWeb($desde, $hasta, $rcsPorExcluir)
            ->where('id_factura', $invoice)
            ->where('estado_aplicado', 1)
            ->get([
                'id',
                'cobro_id',
                'id_factura',
                'monto',
                'ECD_DET_FECHA_VENCIMIENTO',
                'created_at'
            ]);
    }


    //despositos facturas pendientes
    public function getPendingWebDepositPaymentsByInvoice(string $invoice, string $desde, string $hasta, array $rcsPorExcluir = [])
    {
        return GpCobrosDepositosFacturas::with( 'cobroPendienteWeb:id,numero_recibo',)
            ->pendientesWeb($desde, $hasta, $rcsPorExcluir)
            ->where('estado_aplicado', 1)
            ->where('id_factura', $invoice)
            ->get(['id', 'cobro_id', 'deposito_id', 'id_factura', 'monto', 'created_at']);
    }


    //efectivo de facturas pendientes
    public function getPendingWebCashPaymentsByInvoice(string $invoice, string $desde, string $hasta, array $rcsPorExcluir = [])
    {
        return GpCobrosEfectivoFactura::with( 'cobroPendienteWeb:id,numero_recibo',)
            ->pendientesWeb($desde, $hasta, $rcsPorExcluir)
            ->where('id_factura', $invoice)
            ->where('estado_aplicado', 1)
            ->get(['id', 'cobro_id', 'id_factura', 'monto', 'created_at']);
    }


    //retenciones de facturas pendientes
    public function getPendingWebWithholdingPaymentsByInvoice(string $invoice, string $desde, string $hasta, array $rcsPorExcluir = [])
    {
        return GpCobrosRetenciones::with( 'cobroPendienteWeb:id,numero_recibo',)
            ->pendientesWeb($desde, $hasta, $rcsPorExcluir)
            ->where('ret_factura', $invoice)
            ->where('estado_aplicado', 1)
            ->get(['id', 'cobro_id', 'ret_factura', 'total', 'created_at']);
    }

    //tarjetas de facturas pendientes
    public function getPendingWebCreditCardPaymentsByInvoice(string $invoice, string $desde, string $hasta, array $rcsPorExcluir = [])
    {
        return GpCobrosTarjetaFactura::with( 'cobroPendienteWeb:id,numero_recibo',)
            ->pendientesWeb($desde, $hasta, $rcsPorExcluir)
            ->where('id_factura', $invoice)
            ->where('estado_aplicado', 1)
            ->get(['id', 'cobro_id', 'id_factura', 'monto', 'created_at']);
    }
}
