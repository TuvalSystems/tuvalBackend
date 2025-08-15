<!DOCTYPE html>
<html>
<head>
    <title>Hi</title>
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
            font-size:13px;
        }
        ul{
            margin-left: 0px;
            padding-left: 10px;
        }
        ul  li{

            font-size:13px;
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
            text-align: right;
        }
        .text-left{
            text-align: left!important;
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
                            <td>Teléfono:</td>
                            <td>3704060</td>
                        </tr>
                    </table>
                </div>
            </td>
            <td>
                <div class="bordered fondogris">
                    <h1>ORDEN DE COMPRA No.: {{ $ORDEN_COMPRA_DOCUMENTO }}</h1>
                    <table class="table table-striped">
                        <tbody>
                        <tr>
                            <td>Bodega</td>
                            <td>{{ $ORDEN_COMPRA_BODEGA }}</td>
                        </tr>
                        <tr>
                            <td>Fecha del documento:</td>
                            <td>{{ $ORDEN_COMPRA_FECHA_DOCUMENTO }}</td>
                        </tr>
                        <tr>
                            <td>Usuario:</td>
                            <td>{{ $USERID }}</td>
                        </tr>
                        <tr>
                            <td>Referencia:</td>
                            <td>{{ $ORDEN_COMPRA_DOCU_ORIGEN }}</td>
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
                <td><b>Proveedor:</b></td>
                <td>{{ $PROV_RAZON_SOCIAL }}</td>
                <td><b>Destino:</b></td>
                <td>{{ $ORDEN_COMPRA_DIRECCION }}</td>
            </tr>
            <tr>
                <td><b>RUC:</b></td>
                <td>{{ $ORDEN_COMPRA_ID_PROVEEDOR }}</td>
                <td>Comentario:</td>
                <td>{{ $ORDEN_COMPRA_NOTA_DOCU }}</td>
            </tr>
            <tr>
                <td><b>Teléfono:</b></td>
                <td>{{ $PROV_TELEFONO_1 }}</td>
                <td></td>
                <td></td>
            </tr>
            <tr>
                <td><b>Forma de Pago:</b></td>
                <td>{{ $PROV_CONDICION_PAGO }}</td>
                <td></td>
                <td></td>
            </tr>
        </table>
    </div>
    <div class="bordered">
        <h4>Detalle: </h4>
        <table class="table table-striped tablacarrito">
            <thead>
            <tr>
                <th>Código</th>
                <th>DESCRIPCIÓN </th>
                <th>U de M </th>
                <th class="text-right">PESO </th>
                <th class="text-right">CANT </th>
                <th class="text-right">P. UNIT</th>
                <th class="text-right">TOTAL</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($detalle as $key => $value)
                <tr>
                    <td>{{$value->ORDEN_COMPRA_DETALLE_ARTICULO}}</td>
                    <td>{{$value->ORDEN_COMPRA_DETALLE_DESCRIPCION_ARTICULO}}</td>
                    <td>{{$value->ORDEN_COMPRA_DETALLE_UMEDIDA}}</td>
                    <td class="text-right"> {{ number_format((float)($value->ART_PESO ?? 0), 2, ',', '.') }} Kg
                    </td>
                    <td class="text-right">{{ number_format($value->ORDEN_COMPRA_DETALLE_CANTIDAD,2,'.', ',')}}</td>
                    <td class="text-right">${{$value->ORDEN_COMPRA_DETALLE_COSTO_UNITARIO}}</td>
                    <td class="text-right">{{ number_format($value->ORDEN_COMPRA_DETALLE_TOTAL,2,'.', ',')}}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <div style="overflow: hidden;">
            <table class="table2" align="right">
                <tr>
                    <td><b>SUBTOTAL: </b></td>
                    <td class="text-right">${{ $subtotal }}</td>
                </tr>
                <tr>
                    <td><b>IMPUESTOS: </b></td>
                    <td class="text-right">${{ $iva }}</td>
                </tr>
                <tr>
                    <td><b>TOTAL: </b></td>
                    <td class="text-right">${{ $total }}</td>
                </tr>
            </table>
        </div>
    </div>
    <ul>
        <li>Recepción de facturas hasta el 25 de cada mes</li>
        <li>La factura debe contener el número de Orden de Compra</li>
        <li>La factura debe ser emitida/entregada máximo 24 horas después de la fecha de emisión. </li>
    </ul>
</div>
</body>
</html>
