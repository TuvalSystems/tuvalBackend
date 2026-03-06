<?php
namespace App\Enums\Ventas;

enum OrderOrigenEnum : string
{
    case COTIZACION             = 'cotizacion';
    case RESERVA                = 'reserva';
    case FACTURA_EXISTENTE      = 'factura_existente';
    case DEVOLUCION             = 'devolucion';
    case FACTURA_DEMANDA        = 'factura_demanda';
}
