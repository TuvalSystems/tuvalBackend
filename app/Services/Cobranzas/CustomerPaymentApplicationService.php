<?php

namespace App\Services\Cobranzas;

use App\Models\GpPedido;
use App\Services\Api\Cobros\ApiBillingService;
use App\Services\Cobranzas\Contracts\CustomerPaymentApplicationServiceInterface;

class CustomerPaymentApplicationService implements CustomerPaymentApplicationServiceInterface
{

    //serivicio dedicado para la verificacion de aplicación de pagos antes de registrar un cobro
    public function __construct(protected ApiBillingService $api) {}



    public function validateIdentifyPaymentInvoices(array $payload)
    {
        $facturas = $this->filterInvoicesAndClients($payload);
        $params = $this->getOrdersWithInvoice($facturas);
        // $payments = $this->getPaymentApplyGp($params);
        return $params;
    }

    protected function filterInvoicesAndClients(array $data)
    {
        $facturas = [];

        foreach (data_get($data, 'ingreso_efectivo.facturas', []) as $factura) {
            $facturas[] = data_get($factura, 'ECD_DET_NUM_DOC');
        }

        foreach (data_get($data, 'ingreso_depositos', []) as $deposito) {
            foreach (data_get($deposito, 'facturas', []) as $factura) {
                $facturas[] = data_get($factura, 'ECD_DET_NUM_DOC');
            }
        }

        foreach (data_get($data, 'ingreso_cheques.facturas', []) as $factura) {
            $facturas[] = data_get($factura, 'ECD_DET_NUM_DOC');
        }

        foreach (data_get($data, 'ingreso_tarjeta.facturas', []) as $factura) {
            $facturas[] = data_get($factura, 'ECD_DET_NUM_DOC');
        }

        foreach (data_get($data, 'ingreso_aplicaciones', []) as $aplicacion) {
            foreach (data_get($aplicacion, 'facturas', []) as $factura) {
                $facturas[] = data_get($factura, 'ECD_DET_NUM_DOC');
            }
        }

        foreach (data_get($data, 'retenciones', []) as $retencion) {
            $facturas[] = data_get($retencion, 'ret_factura');
        }

        return collect($facturas)
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }

    protected function getOrdersWithInvoice(array $invoices)
    {
        return GpPedido::whereIn('factura', $invoices)->get(['id', 'pedido', 'factura', 'CLI_RUC'])->map(function ($ord) {
            return [
                'factura' => $ord->factura,
                'ruc'    => $ord->CLI_RUC
            ];
        })->values()->all();
    }


    protected function getPaymentApplyGp(array $params){
        $responseApi  =[];
        foreach ($params as $param) {
            $responseApi[] = data_get($this->api->getListCustomerPaymentApply($param), 'data', []);
        }

        return $responseApi;
    }
}
