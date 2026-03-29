<?php

namespace App\Enums\Ventas;


enum PurchaseOrdersL: string
{
    case INICIADO   = 'iniciado';
    case ENVIADO    = 'enviado';
    case GENERADO   = 'generado';
}
