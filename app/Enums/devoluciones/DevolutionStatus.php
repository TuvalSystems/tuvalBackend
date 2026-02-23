<?php
namespace App\Enums\devoluciones;

enum DevolutionStatus: string
{
    case SOLICITADO     = 'solicitado';
    case ESPERA         = 'espera';
    case PROCESADO      = 'procesado';
    case APROBADO       = 'aprobado';
    case RECHAZADO      = 'rechazado';
    case RECIBIDO       = 'recibido';
    case ANULADA        = 'anulada';
    case NO_PROCESADO   = 'no_procesado';
    case ENVIADA        = 'enviada'; // PARA GP FACTURAS ANULADAS
    case CANCELADA      = 'cancelada';  // para despachos

}
