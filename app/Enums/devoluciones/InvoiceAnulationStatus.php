<?php
namespace App\Enums\devoluciones;

enum InvoiceAnulationStatus: string
{

    case ENVIADA = 'enviada';
    case PENDIENTE = 'pendiente';

}
