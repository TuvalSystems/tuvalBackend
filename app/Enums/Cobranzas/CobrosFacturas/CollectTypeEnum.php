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

    case RETENTION_IVA = 'I';
    case RETENTION_REN = 'R';
    case RETENTION_ISRV = 'ISRV';
    case RETENTION_RSRV = 'RSRV';


    //cheque
    case CHEQUE = 'CHE';
    CASE CHEQUE_TIPO = 'CH';
    case CHEQUE_ORIGEN_CUENTA = 'P';
    //deposito
    case DEPOSITO = 'D';

    //tarjeta
    case TARJETA = 'TC';

}
