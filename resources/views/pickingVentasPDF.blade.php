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
            font-size:12px;
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
            font-size: 12px;
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
            line-height: 1.6;
            padding: 6px;
            vertical-align: top;
        }
        .table2 > tbody > tr > td, .table2 > tbody > tr > th, .table2 > tfoot > tr > td, .table2 > tfoot > tr > th, .table2 > thead > tr > td, .table2 > thead > tr > th {
            font-size:11px;
            line-height: 1.8;
            padding: 6px;
            vertical-align: top;
        }
        .table3 > tbody > tr > td, .table2 > tbody > tr > th, .table2 > tfoot > tr > td, .table2 > tfoot > tr > th, .table2 > thead > tr > td, .table2 > thead > tr > th {
            font-size:11px;
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
                    <p class="text-center">DOCUMENTO SIN VALIDEZ TRIBUTARIA</p>
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
                                <td>{{$direccion}}</td>
                            </tr>
                        </table>
                    </div>
                </td>
                <td>
                    <div class="bordered fondogris">
                        <h1>Picking</h1>
                        <table class="table table-striped">
                            <tbody>
                            <tr>
                                <td>Destino: </td>
                                <td>{{$reporte[0]['bodega_destino']}}</td>
                            </tr>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </td>
            </tr>
        </table>
    </div>
</div>
<div class="container">
    <div class="bordered">
        <h4>Detalle: </h4>
        <table class="table table-striped tablacarrito">
            <thead>
            <tr>
                <th class="nowrap">Compartimiento / Existencia </th>
                @if(isset($reporte[0]['pedido']))
                <th>Pedido</th>
                @endif
                <th>CTL </th>
                <th>Descripción</th>

                <th>UDM </th>
                <th class="text-right"> Cantidad</th>
                <th class="text-right"> Peso</th>

            </tr>
            </thead>
            <tbody>
            @foreach ($reporte as $key => $value)
                <tr>
                    <td>
                        <table style="width:100%">
                            <tbody>
                            @foreach ($value['compartimento'] as $compartimiento)
                                <tr>
                                    <td class="text-left"><b>{{$compartimiento['STO_ART_COMPART_COMPARTIMIENTO']}}</b></td>
                                    <td class="text-left">{{$compartimiento['STO_ART_COMPART_STOCK_DISPONIBLE']}}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </td>
                    @if(isset($value['pedido']))
                    <td>{{$value['pedido']}}</td>
                    @endif
                    <td><b>{{$value['ART_CODIGO_TRES_LETRAS']}}</b></td>
                    <td>{{$value['ART_DESCRIPCION']}}</td>
                    <td>{{$value['ART_PLAN_UMEDIDA']}}</td>
                    <td class="text-right"><b>{{$value['seleccionado']}}</b></td>
                    <td class="text-right">{{$value['ART_PESO'] * $value['seleccionado']}}Kg</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <p class="text-right"><strong>Peso total: {{ number_format($peso_total / 1000, 2, '.', '') }} Tn</strong></p>
    </div>
</div>
</body>
</html>
