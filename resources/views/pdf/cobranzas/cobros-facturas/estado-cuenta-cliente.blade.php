<!DOCTYPE html>
<html>

<head>
    <title>Hi</title>
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
            text-align: left;
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
            text-align: left;
        }

        .table3>tbody>tr>td,
        .table2>tbody>tr>th,
        .table2>tfoot>tr>td,
        .table2>tfoot>tr>th,
        .table2>thead>tr>td,
        .table2>thead>tr>th {
            font-size: 11px;
            line-height: 1.42857;
            vertical-align: top;
            text-align: left;
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

        .tablacarrito td.text-right {
            text-align: right;
        }

        h1 {
            font-size: 12px;
        }
    </style>
</head>

<body>
    <div class="container">
        <table border="0" width="100%">
            <tr>
                <td width="50%" align="center" style="padding-right:20px;">
                    <img src="images/tuval-logo.jpg" style="margin-bottom:10px;">
                    <div class="">
                        <table class="table">
                            <tr>
                                <td colspan="2"><b>TUVAL S.A.</b></td>
                            </tr>
                            <tr>
                                <td>R.U.C.:</td>
                                <td>0990281866001</td>
                            </tr>
                            <tr>
                                <td>Dirección:</td>
                                <td>GUAYAS / GUAYAQUIL / TARQUI / CEDROS SOLAR 5 Y CALLE C</td>
                            </tr>
                            <tr>
                                <td>Email:</td>
                                <td>cobranzas@tuvalsa.com</td>
                            </tr>
                            <tr>
                                <td>Teléfono:</td>
                                <td>3704060</td>
                            </tr>
                        </table>
                    </div>
                </td>
                <td>
                    <div class="bordered fondogris">

                        <h1>ESTADO DE CUENTA DE CLIENTES</h1>
                        <table class="table2">
                            <tr>
                                <td><b>Cliente:</b></td>
                                <td>{{ $cliente->CLI_RAZON_SOCIAL }}</td>
                            </tr>
                            <tr>
                                <td><b>Dirección:</b></td>
                                <td>{{ $cliente->CLI_DIRECCION }}</td>
                            </tr>
                            <tr>
                                <td><b>E-mail:</b></td>
                                <td>
                                    @php
                                        $correosSri = array_filter(array_map('trim', explode(';', $cliente->CLI_CORREO_ELECT_SRI ?? '')));
                                    @endphp
                                    @foreach ($correosSri as $correoSri)
                                        {{ $correoSri }}@if (!$loop->last)<br>@endif
                                    @endforeach
                                </td>
                            </tr>
                            <tr>
                                <td><b>Identificación:</b></td>
                                <td>{{ $cliente->CLI_RUC }}</td>
                            </tr>
                            <tr>
                                <td><b>Teléfono:</b></td>
                                <td>{{ $cliente->CLI_TELEFONO_1 }}</td>
                            </tr>
                            <tr>
                                <td><b>Ciudad:</b></td>
                                <td>{{ $cliente->CLI_CIUDAD }}</td>
                            </tr>
                            <tr>
                                <td><b>Fecha:</b></td>
                                <td>{{ date('Y-m-d') }}</td>
                            </tr>
                        </table>
                    </div>
                </td>
            </tr>
        </table>
        <div class="">
            <table class="table bordered tablacarrito">
                <thead>
                    <tr>
                        <th class="text-right">Actual</th>
                        <th class="text-right">0 - 30 Días</th>
                        <th class="text-right">31 - 60 Días</th>
                        <th class="text-right">61 - 90 Días</th>
                        <th class="text-right">91 y más</th>
                        <th class="text-right">Total Pend</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="text-right">
                            ${{ number_format($resumen['actual'], 2, '.', ',') }}
                        </td>
                        <td class="text-right">
                            ${{ number_format($resumen['dias030'], 2, '.', ',') }}
                        </td>
                        <td class="text-right">
                            ${{ number_format($resumen['dias3160'], 2, '.', ',') }}
                        </td>
                        <td class="text-right">
                            ${{ number_format($resumen['dias6190'], 2, '.', ',') }}
                        </td>
                        <td class="text-right">
                            ${{ number_format($resumen['dias91Mas'], 2, '.', ',') }}
                        </td>
                        <td class="text-right">
                            ${{ number_format($resumen['total'], 2, '.', ',') }}
                        </td>
                    </tr>
                </tbody>
            </table>
            <table class="table bordered tablacarrito">
                <thead>
                    <tr>
                        <th>Tipo Doc</th>
                        <th>Nro Doc</th>
                        <th>Fecha doc</th>
                        <th>Fecha Venc</th>
                        <th>Cond Pago</th>
                        <th class="text-right">Valor Doc</th>
                        <th class="text-right">Abono</th>
                        <th class="text-right">Saldo Pend</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($detalle as $value)
                    <tr>
                        <td>{{ $value->tipo }}</td>
                        <td>{{ $value->documento }}</td>
                        <td>{{ $value->fechaEmision }}</td>
                        <td>{{ $value->fechaVencimiento }}</td>
                        <td>{{ $value->condicionPago }}</td>

                        <td class="text-right">
                            ${{ number_format($value->montoOriginal, 2, '.', ',') }}
                        </td>

                        <td class="text-right">
                            ${{ number_format($value->abono, 2, '.', ',') }}
                        </td>

                        <td class="text-right">
                            ${{ number_format($value->saldoPendiente, 2, '.', ',') }}
                        </td>

                        <td>{{ $value->observacion }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            <table class="table bordered tablacarrito">
                <thead>
                    <tr>
                        <th class="text-right">Actual</th>
                        <th class="text-right">0 - 30 Días</th>
                        <th class="text-right">31 - 60 Días</th>
                        <th class="text-right">61 - 90 Días</th>
                        <th class="text-right">91 y más</th>
                        <th class="text-right">Total Pend</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="text-right">
                            ${{ number_format($resumen['actual'], 2, '.', ',') }}
                        </td>
                        <td class="text-right">
                            ${{ number_format($resumen['dias030'], 2, '.', ',') }}
                        </td>
                        <td class="text-right">
                            ${{ number_format($resumen['dias3160'], 2, '.', ',') }}
                        </td>
                        <td class="text-right">
                            ${{ number_format($resumen['dias6190'], 2, '.', ',') }}
                        </td>
                        <td class="text-right">
                            ${{ number_format($resumen['dias91Mas'], 2, '.', ',') }}
                        </td>
                        <td class="text-right">
                            ${{ number_format($resumen['total'], 2, '.', ',') }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</body>

</html>
