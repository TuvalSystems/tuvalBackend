<?php

namespace App\Enums\Cobranzas\CobrosFacturas;

enum CollectTypeEnum : string
{
    //efectivo
    case EFECTIVO = 'EF';
    case EFECTIVO_E = 'EFE';

    //retenciones
    case RETENCION_V = 'RV';
    case RETENCION_F = 'RF';

    //cheque
    case CHEQUE = 'CHE';

    //deposito
    case DEPOSITO = 'D';

    //tarjeta
    case TARJETA = 'TC';

}
