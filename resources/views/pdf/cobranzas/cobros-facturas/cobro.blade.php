<!DOCTYPE html>
<html>

<head>
    <title>PDF</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            background-color: #CCC;
            background: #FFF;
            font-size: 11px;
        }

        .nowrap {
            white-space: nowrap;
        }

        table {
            border-collapse: collapse;
            border-spacing: 0;
        }

        .table,
        thead,
        tbody {
            max-width: 100%;
            width: 100%;
        }

        table td,
        table th {
            font-size: 11px;
        }

        .tablacarrito td {
            text-align: center;
        }

        p {
            margin: 0px;
            font-size: 11px;
        }

        .table-striped>tbody>tr:nth-of-type(2n+1)>td {
            background-color: #f9f9f9;
        }

        .table>tbody>tr>td,
        .table>tbody>tr>th,
        .table>tfoot>tr>td,
        .table>tfoot>tr>th,
        .table>thead>tr>td,
        .table>thead>tr>th {
            border-top: 1px solid #ddd;
            line-height: 1.42857;
            padding: 4px;
            vertical-align: top;
        }

        .table2>tbody>tr>td,
        .table2>tbody>tr>th,
        .table2>tfoot>tr>td,
        .table2>tfoot>tr>th,
        .table2>thead>tr>td,
        .table2>thead>tr>th {
            line-height: 1.42857;
            padding: 4px;
            vertical-align: top;
        }

        .table3>tbody>tr>td,
        .table2>tbody>tr>th,
        .table2>tfoot>tr>td,
        .table2>tfoot>tr>th,
        .table2>thead>tr>td,
        .table2>thead>tr>th {
            font-size: 10px;
            line-height: 1.42857;
            vertical-align: top;
        }

        .table3>thead>tr>th {
            text-align: left;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .bordered {
            border: #999999 1px solid;
            -webkit-border-radius: 10px;
            -moz-border-radius: 10px;
            border-radius: 10px;
            padding: 10px;
            margin-bottom: 10px;
        }

        .fondogris {
            background: #f6f6f6;
        }

        .text-right {
            text-align: right !important;
        }

        .text-left {
            text-align: left !important;
        }

        .text-center {
            text-align: center !important;
        }

        .tablacarrito td.text-right {
            text-align: right;
        }

        h1 {
            font-size: 12px;
        }

        h4 {
            margin: 0 0 8px 0;
        }
    </style>
</head>

<body>
    @php
        $cliente = $cobro->cliente;
        $puntoVenta = $cobro->puntoVenta;
        $usuarioCobro = $usuario ?? $cobro->usuarioCobro;

        $retenciones = collect(data_get($retencion, 'retenciones', []));
        $efectivoFacturas = collect(data_get($efectivo, 'facturas', []));
        $depositosItems = collect(data_get($depositos, 'depositos', []));
        $chequesItems = collect(data_get($cheque, 'cheque', []));
        $chequesFacturas = collect(data_get($cheque, 'facturas', []));
        $tarjetaMonto = data_get($tarjeta, 'tarjeta');
        $tarjetaFacturas = collect(data_get($tarjeta, 'facturas', []));
        $aplicacionesItems = collect(data_get($aplicaciones, 'aplicaciones', []));

        $formatDate = function ($value) {
            if ($value instanceof \Carbon\CarbonInterface) {
                return $value->format('Y-m-d');
            }

            return $value ?: '';
        };

        $depositType = function ($type) {
            return match ($type) {
                'DP' => 'Deposito',
                'DA' => 'Deposito anterior',
                'TR' => 'Transferencia',
                default => $type ?: 'Deposito',
            };
        };
    @endphp

    <div class="container">
        <table border="0" width="100%">
            <tr>
                <td width="50%" align="center" style="padding-right:20px;">
                    <img src="images/tuval-logo.jpg" style="margin-bottom:10px;">
                </td>
                <td>
                    <div class="bordered fondogris">
                        <h1>Cobro No.: {{ $cobro->numero_recibo }}</h1>
                        <table class="table table-striped">
                            <tbody>
                                <tr>
                                    <td>Almacen</td>
                                    <td>{{ data_get($puntoVenta, 'PTO_VTA_DESCRIPCION') }}</td>
                                </tr>
                                <tr>
                                    <td>Fecha</td>
                                    <td>{{ $formatDate($cobro->fecha) }}</td>
                                </tr>
                                <tr>
                                    <td>Usuario:</td>
                                    <td>{{ data_get($usuarioCobro, 'USERNAME') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </td>
            </tr>
        </table>

        <div class="bordered">
            <table class="table2">
                <tr>
                    <td><b>Cliente:</b></td>
                    <td>{{ data_get($cliente, 'CLI_RAZON_SOCIAL') }}</td>
                    <td><b>Direccion:</b></td>
                    <td colspan="3">{{ data_get($cliente, 'CLI_DIRECCION') }}</td>
                </tr>
                <tr>
                    <td><b>Identificacion:</b></td>
                    <td>{{ data_get($cliente, 'CLI_RUC') }}</td>
                    <td><b>Telefono:</b></td>
                    <td>{{ data_get($cliente, 'CLI_TELEFONO_1') }}</td>
                    <td><b>Ciudad:</b></td>
                    <td>{{ data_get($cliente, 'CLI_CIUDAD') }}</td>
                </tr>
            </table>
        </div>

        @if($retenciones->isNotEmpty())
        <div class="bordered">
            <h4>Retenciones:</h4>
            <table class="table tablacarrito table3">
                <thead>
                    <tr>
                        <th>Ret. Factura</th>
                        <th>Comprobante</th>
                        <th>F. emision</th>
                        <th>F. vencimiento</th>
                        <th>Ret IVA</th>
                        <th>Ret RENTA</th>
                        <th>Ret IVA SRV</th>
                        <th>Ret RENTA SRV</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($retenciones as $value)
                    <tr>
                        <td>{{ data_get($value, 'ret_factura') }}</td>
                        <td>{{ data_get($value, 'ret_comprobante') }}</td>
                        <td>{{ $formatDate(data_get($value, 'ret_fecha_emision')) }}</td>
                        <td>{{ $formatDate(data_get($value, 'ret_fecha_vencimiento')) }}</td>
                        <td>
                            @if((float) data_get($value, 'ret_iva', 0) > 0)
                            {{ data_get($value, 'ret_porcentaje_iva') }}% {{ format_money(data_get($value, 'ret_iva')) }}
                            @endif
                        </td>
                        <td>
                            @if((float) data_get($value, 'ret_renta', 0) > 0)
                            {{ data_get($value, 'ret_porcentaje_renta') }}% {{ format_money(data_get($value, 'ret_renta')) }}
                            @endif
                        </td>
                        <td>
                            @if((float) data_get($value, 'ret_iva_srv', 0) > 0)
                            {{ data_get($value, 'ret_porcentaje_iva_srv') }}% {{ format_money(data_get($value, 'ret_iva_srv')) }}
                            @endif
                        </td>
                        <td>
                            @if((float) data_get($value, 'ret_renta_srv', 0) > 0)
                            {{ data_get($value, 'ret_porcentaje_renta_srv') }}% {{ format_money(data_get($value, 'ret_renta_srv')) }}
                            @endif
                        </td>
                        <td class="text-right">{{ format_money(data_get($value, 'total')) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        @if($efectivoFacturas->isNotEmpty() || (float) data_get($efectivo, 'totalCobro', 0) > 0)
        <div class="bordered">
            <h4>Efectivo:</h4>
            <table class="table tablacarrito table3">
                <thead>
                    <tr>
                        <th class="text-right">Total Efectivo</th>
                        <th class="text-center">Doc. aplicado</th>
                        <th class="text-right">Aplicacion</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <th class="text-right">{{ format_money(data_get($efectivo, 'totalCobro')) }}</th>
                        <th></th>
                        <th></th>
                    </tr>

                    @foreach($efectivoFacturas as $value)
                    <tr>
                        <th></th>
                        <td>{{ data_get($value, 'id_factura') }}</td>
                        <th class="text-right">{{ format_money(data_get($value, 'monto')) }}</th>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        @if($depositosItems->isNotEmpty())
        <div class="bordered">
            <h4>Depositos:</h4>
            <table class="table tablacarrito table3">
                <thead>
                    <tr>
                        <th class="text-center">F. de pago</th>
                        <th class="text-center">Numero</th>
                        <th class="text-center">Fecha</th>
                        <th class="text-center">Banco</th>
                        <th class="text-right">Valor</th>
                        <th class="text-center">Doc. aplicado</th>
                        <th class="text-right">Aplicacion</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($depositosItems as $value)
                    <tr>
                        <td>{{ $depositType(data_get($value, 'tipo')) }}</td>
                        <td>{{ data_get($value, 'comprobante') }}</td>
                        <td>{{ $formatDate(data_get($value, 'fecha')) }}</td>
                        <td>{{ data_get($value, 'bancoDeposito.CB_DESCRIPCION') ?: data_get($value, 'banco_codigo') }}</td>
                        <td class="text-right">{{ format_money(data_get($value, 'monto')) }}</td>
                        <td></td>
                        <td></td>
                    </tr>
                    @foreach(collect(data_get($value, 'facturas', [])) as $fac)
                    <tr>
                        <td></td>
                        <td></td>
                        <th></th>
                        <th></th>
                        <th></th>
                        <td>{{ data_get($fac, 'id_factura') }}</td>
                        <td class="text-right">{{ format_money(data_get($fac, 'monto')) }}</td>
                    </tr>
                    @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        @if($chequesItems->isNotEmpty())
        <div class="bordered">
            <h4>Cheques:</h4>
            <table class="table tablacarrito table3">
                <thead>
                    <tr>
                        <th class="text-center"># Cheque</th>
                        <th class="text-center"># Cuenta</th>
                        <th class="text-center">Banco</th>
                        <th class="text-center">Fecha</th>
                        <th class="text-center">F. Venc.</th>
                        <th class="text-right">Valor</th>
                        <th class="text-center">Doc. aplicado</th>
                        <th class="text-right">Aplicacion</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($chequesItems as $value)
                    <tr>
                        <td>{{ data_get($value, 'cheque') }}</td>
                        <td>{{ data_get($value, 'cuenta') }}</td>
                        <td>{{ data_get($value, 'banco.CB_DESCRIPCION') ?: data_get($value, 'banco_codigo') }}</td>
                        <td>{{ $formatDate(data_get($value, 'fecha_emision')) }}</td>
                        <td>{{ $formatDate(data_get($value, 'fecha_vencimiento')) }}</td>
                        <td class="text-right">{{ format_money(data_get($value, 'monto')) }}</td>
                        <td></td>
                        <td></td>
                    </tr>
                    @endforeach
                    @foreach($chequesFacturas as $value)
                    <tr>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td>{{ data_get($value, 'id_factura') }}</td>
                        <td class="text-right">{{ format_money(data_get($value, 'monto')) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        @if($tarjetaMonto)
        <div class="bordered">
            <h4>Tarjeta:</h4>
            <table class="table tablacarrito table3">
                <thead>
                    <tr>
                        <th>Emisor</th>
                        <th>Aprobacion</th>
                        <th>Lote ref.</th>
                        <th>Fecha</th>
                        <th class="text-right">Valor</th>
                        <th>Doc. aplicado</th>
                        <th class="text-right">Aplicacion</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>{{ data_get($tarjetaMonto, 'banco_descripcion') ?: data_get($tarjetaMonto, 'banco') }}</td>
                        <td>{{ data_get($tarjetaMonto, 'comprobante') }}</td>
                        <td>{{ data_get($tarjetaMonto, 'comentario') }}</td>
                        <td>{{ $formatDate(data_get($tarjetaMonto, 'fecha')) }}</td>
                        <td class="text-right">{{ format_money(data_get($tarjetaMonto, 'monto')) }}</td>
                        <td></td>
                        <th></th>
                    </tr>

                    @foreach($tarjetaFacturas as $value)
                    <tr>
                        <td></td>
                        <td></td>
                        <th></th>
                        <th></th>
                        <th></th>
                        <td>{{ data_get($value, 'id_factura') }}</td>
                        <th class="text-right">{{ format_money(data_get($value, 'monto')) }}</th>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        @if($aplicacionesItems->isNotEmpty())
        <div class="bordered">
            <h4>Aplicacion de saldos:</h4>
            <table class="table tablacarrito table3">
                <thead>
                    <tr>
                        <th class="text-center">Numero</th>
                        <th class="text-right">Valor</th>
                        <th class="text-center">Doc. aplicado</th>
                        <th class="text-right">Aplicacion</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($aplicacionesItems as $value)
                    <tr>
                        <td>{{ data_get($value, 'cobro') }}</td>
                        <td class="text-right">{{ format_money(data_get($value, 'monto')) }}</td>
                        <td></td>
                        <td></td>
                    </tr>
                    @foreach(collect(data_get($value, 'facturasAplicadas', [])) as $fac)
                    <tr>
                        <td></td>
                        <td></td>
                        <td>{{ data_get($fac, 'id_factura') }}</td>
                        <td class="text-right">{{ format_money(data_get($fac, 'monto')) }}</td>
                    </tr>
                    @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <div class="bordered">
            <div style="overflow: hidden;">
                <table class="table2" align="right">
                    <tr>
                        <td><b>TOTAL COBRO: </b></td>
                        <td class="text-right"><b>{{ format_money($totalCobro) }}</b></td>
                    </tr>
                    <tr>
                        <td><b>TOTAL APLICADO: </b></td>
                        <td class="text-right"><b>{{ format_money($totalAplicado) }}</b></td>
                    </tr>
                    <tr>
                        <td><b>SALDO A FAVOR: </b></td>
                        <td class="text-right"><b>{{ format_money($saldo) }}</b></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</body>

</html>
