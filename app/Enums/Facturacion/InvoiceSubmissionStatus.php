<?php

namespace App\Enums\Facturacion;

enum InvoiceSubmissionStatus: string
{
    case UNCERTAIN = 'incierto';
    case CONFIRMED = 'confirmado';
}
