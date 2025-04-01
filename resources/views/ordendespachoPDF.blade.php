<!DOCTYPE html>
<html>
<head>
    <title>Hi</title>
    <style>
        .header {
            position: fixed;
            top: -20px;
            left: 0px;
            right: 0px;
        }
        .pagenum{
            position: fixed;
            bottom: -30px;
            right: 0px;
            height: 10px;
            font-size: 10px !important;
        }
        .footer {
            /*position: absolute;*/
            /*bottom: -0px;*/
            /*left: 0px;*/
            /*right: 0px;*/

            /*font-size: 20px !important;*/
        }
        .pagenum:after {
            content: counter(page);
        }
        body {
            font-family:Arial, Helvetica, sans-serif;
            background-color:#CCC;
            background:#FFF;
            font-size:11px;
            padding-top: 220px;
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
        .table td,table th{
            font-size: 10px;
        }
        .tablacarrito td{
            text-align:center;
        }
        p{
            margin: 0px;
            font-size:10px;
        }
        .table-striped > tbody > tr:nth-of-type(2n+1) > td {
            background-color: #f9f9f9;
        }
        .table > tbody > tr > td, .table > tbody > tr > th, .table > tfoot > tr > td, .table > tfoot > tr > th, .table > thead > tr > td, .table > thead > tr > th {
            border-top: 1px solid #ddd;
            line-height: 1.42857;
            padding: 2px;
            vertical-align: top;
        }
        .table2 > tbody > tr > td, .table2 > tbody > tr > th, .table2 > tfoot > tr > td, .table2 > tfoot > tr > th, .table2 > thead > tr > td, .table2 > thead > tr > th {
            font-size:9px;
            line-height: 1.42857;
            padding: 4px;
            vertical-align: top;
        }
        .table3 > tbody > tr > td, .table2 > tbody > tr > th, .table2 > tfoot > tr > td, .table2 > tfoot > tr > th, .table2 > thead > tr > td, .table2 > thead > tr > th {
            font-size:9px;
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
            padding: 5px;
            margin-bottom: 10px;
        }
        .fondogris{
            background: #f6f6f6;
        }
        .text-right{
            text-align: right;
        }
        .text-left{
            text-align: left!important;
        }
        .tablacarrito td.text-right{
            text-align: right;
        }
        h1{
            font-size: 11px;
        }
    </style>
</head>
<body>
<span class="pagenum">Página </span>
<div class="header">
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
                                <td>{{ $direccion }}</td>
                            </tr>
                        </table>
                    </div>
                </td>
                <td>
                    <div class="bordered fondogris">
                        <h1>ORDEN DE DESPACHO: {{$orden_despacho['orden']}}</h1>
                        <table class="table table-striped">
                            <tbody>
                            <tr>
                                <td>Almacén</td>
                                <td>{{ $PTO_VTA_DESCRIPCION }}</td>
                            </tr>
                            @if($factura)
                                <tr>
                                    <td>Factura No.:</td>
                                    <td>{{ $factura }}</td>
                                </tr>
                            @endif
                            <tr>
                                <td>Pedido No.:</td>
                                <td>{{ $pedido }}</td>
                            </tr>

                            <tr>
                                <td>Fecha del documento:</td>
                                <td>{{ $orden_despacho['created_at'] }}</td>
                            </tr>
                            <tr>
                                <td>Usuario:</td>
                                <td>{{ $USERNAME }}</td>
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
                    <td>{{ $sucursal['CLI_SUC_DIRECCION'] }}</td>
                    <td><b>E-mail:</b></td>
                    <td>{{ $cliente['CLI_CORREO_ELECT_SRI'] }}</td>
                </tr>
                <tr>
                    <td><b>Identificación:</b></td>
                    <td>{{ $cliente['CLI_RUC'] }}</td>
                    <td><b>Teléfono:</b></td>
                    <td>{{ $sucursal['CLI_SUC_TELEFONO'] }}</td>
                    <td><b>Ciudad:</b></td>
                    <td>{{ $sucursal['CLI_SUC_CIUDAD'] }}</td>
                </tr>
            </table>
        </div>
    </div>
</div>
<div class="container">
    <div class="bordered">
        <h4>Detalle: </h4>
        <table class="table table-striped tablacarrito">
            <thead>
            <tr>
                <th>Código</th>
                <th>3L</th>
                <th>DESCRIPCIÓN </th>
                <th class="text-right">CANT </th>
                <th>Bodega </th>
                <th>Compartimiento </th>
            </tr>
            </thead>
            <tbody>
            @foreach ($detalle as $key => $value)
                <tr>
                    <td class="nowrap">
                        @if($value['ART_TIPO_COMPRA'] == 'SERV')
                            *
                        @endif
                        {{$value['ART_CODIGO']}}
                    </td>
                    <td>{{$value['ART_CODIGO_TRES_LETRAS']}}</td>
                    <td class="text-left">{{$value['ART_DESCRIPCION']}}</td>
                    <td class="text-right">{{$value['ART_CANTIDAD']}}</td>
                    <td>{{ $BOD_BODEGA}}</td>
                    <td>
                        @if(isset($value['compartimiento']))
                        {{ $value['compartimiento']}}
                        @endif
                    </td>

                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <table border="0" width="100%">
        <tr>
            <td style="vertical-align: top">
                <table border="0">
                    <tr>
                        <td colspàn="2"><b>Información Adicional</b></td>
                    </tr>
                    <tr>
                        <td>Peso:</td>
                        <td>{{ $peso }}kg</td>
                    </tr>
                    <tr>
                        <td>Despacho:</td>
                        <td>{{ $despacho }}</td>
                    </tr>
                    <tr>
                        <td>
                            @if($despacho == 'Retiro en bodega')
                                Bodega Retiro:
                            @else
                                Bodega:
                            @endif
                        </td>
                        <td>{{ $BOD_BODEGA }}</td>
                    </tr>
                </table>
            </td>

        </tr>
    </table>
</div>
</body>
</html>
