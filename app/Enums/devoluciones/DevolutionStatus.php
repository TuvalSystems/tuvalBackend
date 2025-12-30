<?php
namespace App\Enums\devoluciones;

enum DevolutionStatus: string
{
   case PENDIENTE = 'pendiente';
    case ANULADA = 'anulada';
    case NO_PROCESADO = 'no_procesado';
    case ENVIADA = 'enviada';
    case CANCELADA = 'cancelada';  // para despachos


    // PARA REFACTURA
    case ESPERA = 'espera';
}
