<?php

namespace App\Services\Funciones\Contracts;

use App\Models\GpPedido;

interface InvoiceServiceInterface
{
    // Define los métodos que debe implementar el servicio

    public function checkSendInvoice(GpPedido $pedido, $trasladado = false, $checkDemanda = true);

    public function generateDisptachOrder($pedido);

    public function sendInvoiceGp($pedido);

     public function checkTrasladado(GpPedido $pedido);

     public function respuestaFacturaVenta($order);
      public function checkTraslado(GpPedido $pedido);
}
