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
            padding-top: 130px;
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
                        </table>
                    </div>
                </td>
                <td>
                    <div class="bordered fondogris">
                        <h1>ORDEN DE RECIBO L</h1>
                        <h1>Documento. {{$ORDEN_COMPRA_DOCUMENTO}}</h1>
                        <table class="table table-striped">
                            <tbody>
                            <tr>
                                <td>Proveedor</td>
                                <td>{{ $PROV_RAZON_SOCIAL }}</td>
                            </tr>

                            <tr>
                                <td>Pedido</td>
                                <td>{{ $ORDEN_COMPRA_DOCU_ORIGEN }}</td>
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
        <table class="table table-striped tablacarrito">
            <thead>
            <tr>
                <ht>No.</ht>
                <th>Código</th>
                <th class="text-right">Cant.</th>
                <th>Descripción</th>
                <th>Bodega</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($detalle as $key => $value)
                <tr>
                    <td>{{$key + 1}}</td>
                    <td class="nowrap">
                        {{$value['ORDEN_COMPRA_DETALLE_ARTICULO']}}
                    </td>
                    <td class="text-right">{{$value['seleccionado']}}</td>
                    <td>{{$value['ORDEN_COMPRA_DETALLE_DESCRIPCION_ARTICULO']}}</td>
                    <td>{{$value['ORDEN_COMPRA_DETALLE_BODEGA']}}</td>

                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
