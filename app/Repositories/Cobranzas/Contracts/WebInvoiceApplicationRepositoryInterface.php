<?php

namespace App\Repositories\Cobranzas\Contracts;

interface WebInvoiceApplicationRepositoryInterface
{
    public function getOrderWithDevolution(string $factura, string $desde, string $hasta);
    public function getPendingWebInvoiceApplicationsExcludingDocuments(string $factura, array $documentos, string $desde, string $hasta, array $rcsPorExcluir = []);
    public function getPendingWebCheckPaymentsByInvoice(string $invoice, string $desde, string $hasta, array $rcsPorExcluir = []);

    public function getPendingWebDepositPaymentsByInvoice(string $invoice, string $desde, string $hasta, array $rcsPorExcluir = []);
    public function getPendingWebCashPaymentsByInvoice(string $invoice, string $desde, string $hasta, array $rcsPorExcluir = []);
    public function getPendingWebWithholdingPaymentsByInvoice(string $invoice, string $desde, string $hasta, array $rcsPorExcluir = []);
    public function getPendingWebCreditCardPaymentsByInvoice(string $invoice, string $desde, string $hasta, array $rcsPorExcluir = []);
}
