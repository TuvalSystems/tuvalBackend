<?php

namespace App\Enums\Ventas\Vendedores;


enum SellerAttributeTypeEnum: string
{
    case STRING     = 'string';
    case BOOLEAN    = 'boolean';
    case INTEGER    = 'integer';
    case DECIMAL    = 'decimal';
    case DATE       = 'date';
    case OPCION     = 'opcion';
}
