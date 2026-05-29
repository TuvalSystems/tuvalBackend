<?php
namespace App\Enums\Ventas;

enum OrderOrigenEnum : string
{
    case COTIZACION             = 'cotizacion';
    case RESERVA                = 'reserva';
    case FACTURAR_EXISTENTE     = 'facturar_existente';
    case DEVOLUCION             = 'devolucion';
    case FACTURA_DEMANDA        = 'factura_demanda';
    case APP_MOVIL              = 'app_movil';
}
