<!DOCTYPE html>
<html>
<head>
    <title>PDF</title>
    <style>
        body {
            font-family:Arial, Helvetica, sans-serif;
            background-color:#CCC;
            background:#FFF;
            font-size:11px;
        }
        .nowrap{
            white-space: nowrap;
        }

        table {
            border-collapse: collapse;
            border-spacing: 0;
        }
        .table, thead, tbody {
            max-width: 100%;
            width: 100%;
        }
        table td,table th{
            font-size: 11px;
        }
        .tablacarrito td{
            text-align:center;
        }
        p{
            margin: 0px;
            font-size:11px;
        }
        .table-striped > tbody > tr:nth-of-type(2n+1) > td {
            background-color: #f9f9f9;
        }
        .table > tbody > tr > td, .table > tbody > tr > th, .table > tfoot > tr > td, .table > tfoot > tr > th, .table > thead > tr > td, .table > thead > tr > th {
            border-top: 1px solid #ddd;
            line-height: 1.42857;
            padding: 4px;
            vertical-align: top;
        }
        .table2 > tbody > tr > td, .table2 > tbody > tr > th, .table2 > tfoot > tr > td, .table2 > tfoot > tr > th, .table2 > thead > tr > td, .table2 > thead > tr > th {

            line-height: 1.42857;
            padding: 4px;
            vertical-align: top;
        }
        .table3 > tbody > tr > td, .table2 > tbody > tr > th, .table2 > tfoot > tr > td, .table2 > tfoot > tr > th, .table2 > thead > tr > td, .table2 > thead > tr > th {
            font-size:10px;
            line-height: 1.42857;
            vertical-align: top;
        }
        .table3 > thead > tr > th{
            text-align: left;
        }
        .container{
            max-width: 1000px;
            margin: 0 auto;
        }
        .bordered{
            border: #999999 1px solid;
            -webkit-border-radius: 10px;
            -moz-border-radius: 10px;
            border-radius: 10px;
            padding: 10px;
            margin-bottom: 10px;
        }
        .fondogris{
            background: #f6f6f6;
        }
        .text-right{
            text-align: right !important;
        }
        .text-left{
            text-align: left!important;
        }
        .text-center{
            text-align: center!important;
        }
        .tablacarrito td.text-right{
            text-align: right;
        }
        h1{
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
            </td>
            <td>
                <div class="bordered fondogris">
                    <h1>Cobro No.: {{ $numero_recibo }}</h1>
                    <table class="table table-striped">
                        <tbody>
                            <tr>
                                <td>Almacén</td>
                                <td>{{ $puntodeventa }}</td>
                            </tr>
                            <tr>
                                <td>Fecha</td>
                                <td>{{ $fecha }}</td>
                            </tr>
                            <tr>
                                <td>Usuario:</td>
                                <td>{{ $usuario }}</td>
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
                <td>{{ $cliente['CLI_RAZON_SOCIAL'] }}</td>
                <td><b>Dirección:</b></td>
                <td colspan="3">{{ $cliente['CLI_DIRECCION'] }}</td>
            </tr>
            <tr>
                <td><b>Identificación:</b></td>
                <td>{{ $cliente['CLI_RUC'] }}</td>
                <td><b>Teléfono:</b></td>
                <td>{{ $cliente['CLI_TELEFONO_1'] }}</td>
                <td><b>Ciudad:</b></td>
                <td>{{ $cliente['CLI_CIUDAD'] }}</td>
            </tr>
        </table>
    </div>
    @if(isset($retenciones))
    <div class="bordered">
        <h4>Retenciones: </h4>
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
                    <td>{{$value['ret_factura']}}</td>
                    <td>{{$value['ret_comprobante']}}</td>
                    <td>{{$value['ret_emision']}}</td>
                    <td>{{$value['ret_caducidad']}}</td>
                    <td>
                        @if($value['ret_iva'] > 0)
                        {{$value['ret_porcentaje_iva']}}%   ${{$value['ret_iva']}}
                        @endif
                    </td>
                    <td>
                        @if($value['ret_renta'] > 0)
                            {{$value['ret_porcentaje_renta']}}%   ${{$value['ret_renta']}}
                        @endif
                    </td>
                    <td>
                        @if($value['ret_iva_srv'] > 0)
                            {{$value['ret_porcentaje_iva_srv']}}%   ${{$value['ret_iva_srv']}}
                        @endif
                    </td>
                    <td>
                        @if($value['ret_renta_srv'] > 0)
                        {{$value['ret_porcentaje_renta_srv']}}%   ${{$value['ret_renta_srv']}}
                        @endif
                    </td>
                    <td class="text-right">${{$value['total']}}</td>
                </tr>
                @endforeach

            </tbody>
        </table>
    </div>
    @endif
    @if(isset($efectivo))
    <div class="bordered">
        <h4>Efectivo: </h4>
        <table class="table tablacarrito table3">
            <thead>
            <tr>
                <th class="text-right">Total Efectivo</th>
                <th class="text-center">Doc. aplicado</th>
                <th class="text-right">Aplicación</th>
            </tr>
            </thead>
            <tbody>
                <tr>
                    <th class="text-right">${{$efectivo['monto']}}</th>
                    <th></th>
                    <th></th>
                </tr>

            @foreach ($efectivo_facturas as $key => $value)
                <tr>
                    <th></th>
                    <td>{{$value['id_factura']}}</td>
                    <th class="text-right">${{$value['monto']}}</th>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @endif
    @if(isset($depositos) && count($depositos) > 0)
    <div class="bordered">
        <h4>Depósitos: </h4>
        <table class="table tablacarrito table3">
            <thead>
            <tr>
                <th class="text-center">F. de pago</th>
                <th class="text-center">Número</th>
                <th class="text-center">Fecha </th>
                <th class="text-center">Banco </th>
                <th class="text-right">Valor</th>
                <th class="text-center">Doc. aplicado</th>
                <th class="text-right">Aplicación</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($depositos as $key => $value)
                <tr>
                    <td>
                        @if($value['tipo'] == 'DP')
                            Depósito
                        @elseif($value['tipo'] == 'DA')
                            Depósito Anterior
                        @else
                            Transferencia
                        @endif

                    </td>
                    <td>{{$value['comprobante']}}</td>

                    <td>{{$value['fecha']}}</td>
                    <td>{{$value['CB_DESCRIPCION']}}</td>


                    <td class="text-right">${{$value['monto']}}</td>
                    <td></td>
                    <td></td>
                </tr>
                @foreach ($value['facturas'] as $keyf => $fac)
                    <tr>
                        <td></td>
                        <td></td>

                        <th></th>
                        <th></th>
                        <th></th>
                        <td>{{$fac['id_factura']}}</td>

                        <td class="text-right">${{$fac['monto']}}</td>
                    </tr>
                @endforeach
            @endforeach
            </tbody>
        </table>
    </div>
    @endif
    @if(count($cheques) > 0)
    <div class="bordered">
        <h4>Cheques: </h4>
        <table class="table tablacarrito table3">
            <thead>
                <tr>

                    <th class="text-center"># Cheque</th>
                    <th class="text-center"># Cuenta</th>
                    <th class="text-center">Banco </th>
                    <th class="text-center">Fecha </th>
                    <th class="text-center">F. Venc. </th>
                    <th class="text-right">Valor</th>
                    <th class="text-center">Doc. aplicado</th>
                    <th class="text-right">Aplicación</th>
                </tr>
            </thead>
            <tbody>
            @foreach ($cheques as $key => $value)
                <tr>

                    <td>{{$value['cheque']}}</td>
                    <td>{{$value['cuenta']}}</td>
                    <td>{{$value['CB_DESCRIPCION']}}</td>
                    <td>{{$value['fecha_emision']}}</td>
                    <td>{{$value['fecha_vencimiento']}}</td>

                    <td class="text-right">${{$value['monto']}}</td>
                    <td></td>
                    <td></td>
                </tr>
            @endforeach
            @foreach ($cheques_facturas as $key => $value)
                <tr>
                    <td></td>
                    <td></td>

                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td>{{$value['id_factura']}}</td>
                    <td class="text-right">${{$value['monto']}}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @endif
    @if(isset($tarjeta))
    <div class="bordered">
        <h4>Tarjeta: </h4>
        <table class="table tablacarrito table3">
            <thead>
                <tr>

                    <th>Emisor</th>
                    <th>Aprobación</th>
                    <th>Lote ref. </th>
                    <th>Fecha </th>
                    <th class="text-right">Valor</th>
                    <th>Doc. aplicado</th>
                    <th class="text-right">Aplicación</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{$tarjeta['CB_DESCRIPCION']}}</td>
                    <td>{{$tarjeta['comprobante']}}</td>
                    <td>{{$tarjeta['comentario']}}</td>
                    <td>{{$tarjeta['fecha']}}</td>
                    <td class="text-right">${{$tarjeta['monto']}}</td>
                    <td></td>
                    <th></th>
                </tr>

                @foreach ($tarjeta_facturas as $key => $value)
                    <tr>
                        <td></td>
                        <td></td>

                        <th></th>
                        <th></th>

                        <th></th>
                        <td>{{$value['id_factura']}}</td>
                        <th class="text-right">${{$value['monto']}}</th>
                    </tr>
                @endforeach

            </tbody>
        </table>
    </div>
    @endif

    @if(isset($aplicaciones) && count($aplicaciones)>0)
    <div class="bordered">
        <h4>Aplicación de saldos: </h4>
        <table class="table tablacarrito table3">
            <thead>
            <tr>
                <th class="text-center">Número</th>
                <th class="text-right">Valor</th>
                <th class="text-center">Doc. aplicado</th>
                <th class="text-right">Aplicación</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($aplicaciones as $key => $value)
                <tr>
                    <td>{{$value['cobro']}}</td>
                    <td class="text-right">${{$value['monto']}}</td>

                    <td></td>
                    <td></td>
                </tr>
                @foreach ($value['facturas'] as $keyf => $fac)
                    <tr>
                        <td></td>
                        <td></td>

                        <td>{{$fac['id_factura']}}</td>
                        <td class="text-right">${{$fac['monto']}}</td>
                    </tr>
                @endforeach

            @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @if(isset($pedido) && $pedido != '')
    <div class="bordered">

        <table class="table tablacarrito table3">
            <thead>
            <tr>
                <th>Pago a pedido</th>
                <th class="text-right">{{$pedido}}</th>
            </tr>
            </thead>
        </table>
    </div>
    @endif

    <div class="bordered">
        <div style="overflow: hidden;">
            <table class="table2" align="right">
                <tr>
                    <td><b>TOTAL COBRO: </b></td>
                    <td class="text-right"><b>${{ $total_cobro }}</b></td>
                </tr>
                <tr>
                    <td><b>TOTAL APLICADO: </b></td>
                    <td class="text-right"><b>${{ $total_aplicado }}</b></td>
                </tr>
                <tr>
                    <td><b>SALDO A FAVOR: </b></td>
                    <td class="text-right"><b>${{ $saldo }}</b></td>
                </tr>
            </table>
        </div>
    </div>
</div>
</body>
</html>
