<!DOCTYPE html>
<html>
<head>
    <title>Cierre de caja PDF</title>
    <style>
        body {
            font-family:Arial, Helvetica, sans-serif;
            background-color:#CCC;
            background:#FFF;
            font-size:12px;
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
            font-size: 12px;
        }
        .tablacarrito td{
            text-align:center;
        }
        p{
            margin: 0px;
            font-size:12px;
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
            font-size:11px;
            line-height: 1.42857;
            vertical-align: top;
        }
        .table3 > thead > tr > th, .table3 > tbody > tr > td, .table3 > tbody > tr > th{
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
        .bordered2{
            border: #999999 1px solid;
            -webkit-border-radius: 10px;
            -moz-border-radius: 10px;
            border-radius: 10px;
            padding: 10px;
            margin-bottom: 10px;
            margin: 0 auto;
            width: 50%;
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
        .tablacarrito td.text-right, .tablacarrito th.text-right{
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
                    <h1>CIERRE DE CAJA</h1>
                    <table class="table table-striped">
                        <tbody>
                        <tr>
                            <td>Almacén</td>
                            <td>{{ $usuario['PTO_VTA_DESCRIPCION'] }}</td>
                        </tr>
                        <tr>
                            <td>Caja</td>
                            <td>{{ $usuario['CAJ_USER_CAJA'] }}</td>
                        </tr>
                        <tr>
                            <td>Fecha</td>
                            <td>{{ $cierreDeCaja['fecha'] }}</td>

                        <tr>
                            <td>Apertura</td>
                            <td>{{ $cierreDeCaja['fecha_apertura'] }}</td>
                        </tr>
                        <tr>
                            <td>Cierre</td>
                            <td>{{ $cierreDeCaja['fecha_cierre'] }}</td>
                        </tr>
                        <tr>
                            <td>ID Usuario:</td>
                            <td>{{ $usuario['USERID'] }}</td>
                        </tr>
                        <tr>
                            <td>Nombre Usuario:</td>
                            <td>{{ $usuario['USERNAME'] }}</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </td>
        </tr>
    </table>
    <div class="bordered2">
        <table class="table tablacarrito table3">
            <thead>
            <tr>
                <th>Forma de pago</th>
                <th class="text-right">Valor cobrado</th>
            </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Retenciones IVA</td>
                    <td class="text-right">${{$retenciones['ret_iva']}}</td>
                </tr>
                <tr>
                    <td>Retenciones Renta</td>
                    <td class="text-right">${{$retenciones['ret_renta']}}</td>
                </tr>
                <tr>
                    <td>Efectivo</td>
                    <td class="text-right">${{$efectivo}}</td>
                </tr>
                <tr>
                    <td>Cheques al día</td>
                    <td class="text-right">${{$cheques}}</td>
                </tr>
                <tr>
                    <td>Cheques posfechados</td>
                    <td class="text-right">${{$cheques_p}}</td>
                </tr>
                <tr>
                    <td>Depósitos/Transferencias</td>
                    <td class="text-right">${{$depositos}}</td>
                </tr>
                <tr>
                    <td>Tarjetas</td>
                    <td class="text-right">${{$tarjetas}}</td>
                </tr>
                <tr>
                    <th>Total</th>
                    <th class="text-right">${{$total}}</th>
                </tr>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
