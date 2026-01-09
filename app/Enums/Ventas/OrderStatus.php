<?php
namespace App\Enums\Ventas;

enum OrderStatus : string
{
    case ESPERA = 'espera';
    case PROCESADO = 'procesado';
    case FACTURADO = 'facturado';
    case ELIMINADO = 'eliminado';
    case RECHAZADO = 'rechazado';
    case TRASLADO = 'traslado';
    case ORDEN_DE_COMPRA = 'orden_de_compra';
    case ENVIADO = 'enviando';
    case ANULADA = 'anulada';
}
