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
            background:#FFF;
            font-size:11px;
            padding-top: 380px;
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
                                <td>Dir. Matriz:</td>
                                <td>GUAYAS / GUAYAQUIL / TARQUI / CEDROS SOLAR 5 Y CALLE C</td>
                            </tr>
                            <tr>
                                <td>Dir Sucursal:</td>
                                <td>{{ $direccion }}</td>
                            </tr>
                            <tr>
                                <td>Tel Sucursal:</td>
                                <td>{{ $telefono }}</td>
                            </tr>

                        </table>
                    </div>
                </td>
                <td>
                    <div class="bordered fondogris">
                        <h1>NOTA DE CRÉDITO</h1>
                        <h1>No. {{$devolucion['DOC_GENERADO']}}</h1>
                        <table class="table table-striped">
                            <tbody>
                                <tr>
                                    <td>Fecha:</td>
                                    <td>{{ $devolucion['fecha_NC'] }}</td>
                                </tr>
                                <tr>
                                    <td>Almacén</td>
                                    <td>{{ $PTO_VTA_DESCRIPCION }}</td>
                                </tr>

                                <tr>
                                    <td>Factura No.:</td>
                                    <td>{{ $pedido['factura'] }}</td>
                                </tr>

                                <tr>
                                    <td>Pedido No.:</td>
                                    <td>{{ $pedido['pedido'] }}</td>
                                </tr>
                                <tr>
                                    <td>Devolución No.:</td>
                                    <td>{{ $devolucion['devolucion'] }}</td>
                                </tr>
                                <tr>
                                    <td>OBLIGADO A LLEVAR CONTABILIDAD:</td>
                                    <td>SI</td>
                                </tr>
                                <tr>
                                    <td>CONTRIBUYENTE ESPECIAL NRO.</td>
                                    <td>176</td>
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
                <tr>
                    <td><b>Comprobante que se modifica:</b></td>
                    <td>{{ $pedido['factura'] }}</td>
                    <td><b>Fecha Emisión (Comprobante a modificar):</b></td>
                    <td>{{ $pedido['fecha_factura'] }}</td>
                    <td><b>Razón de Modificación:</b></td>
                    <td>DEVOLUCION EN VENTA</td>
                </tr>
            </table>
        </div>
    </div>
</div>
<div class="container">

    <table class="table table-striped tablacarrito" style="margin-bottom: 20px;">
        <thead>
        <tr>
            <th>Código</th>
            <th>Unidad</th>
            <th class="text-right">Cant.</th>
            <th>Descripción</th>
            <th class="text-right">Descuento</th>
            <th class="text-right">Precio Unitario</th>
            <th class="text-right">Precio Total</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($detalle as $key => $value)
            <tr>
                <td class="nowrap">
                    @if($value->ART_IMP_VTA == 5)
                        *
                    @endif
                    {{$value->ART_CODIGO}}
                </td>
                <td>{{$value->ART_PLAN_UMEDIDA}}</td>
                <td class="text-right">{{$value->devuelto}}</td>
                <td>{{$value->ART_DESCRIPCION}}</td>
                <td class="text-right">${{ number_format($value->ART_DESCUENTO, 2, '.','')}}</td>
                <td class="text-right">${{ $value->ART_VALOR }}</td>
                <td class="text-right">${{ number_format(($value->ART_VALOR - $value->ART_DESCUENTO) * $value->devuelto,2,'.','') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <table border="0" width="100%">
        <tr>
            <td style="vertical-align: top">
                <table border="0">
                    <tr>
                        <td colspàn="2"><b>Información Adicional</b></td>
                    </tr>
                    <tr>
                        <td>Vendedor:</td>
                        <td >{{$vendedor['VEND_NOMBRES'].' '.$vendedor['VEND_APELLIDOS']}}</td>
                    </tr>
                    <tr>
                        <td>Usuario:</td>
                        <td>{{$pedido['USERNAME']}}</td>
                    </tr>
                </table>
            </td>
            <td style="vertical-align: top">
                <table class="table2" align="right" style="margin-right: 10px;">
                    @foreach($tipoimpuesto as $key => $tipo)
                        <tr>
                            <td><b>SUBTOTAL {{ (int)$key}}%: </b></td>
                            <td class="text-right">${{ number_format($tipo ,2,'.','')}}</td>
                        </tr>
                    @endforeach
                    <tr>
                        <td><b>DESCUENTO: </b></td>
                        <td class="text-right">${{ $descuento }}</td>
                    </tr>
                    <tr>
                        <td><b>SUBTOTAL - DESCUENTO: </b></td>
                        <td class="text-right">${{ $subtotalmenosdescuento }}</td>
                    </tr>
                    @foreach($sumaimpuesto as $key => $impuesto)
                        <tr>
                            <td><b>IMPUESTOS {{ (int)$key}}%: </b></td>
                            <td class="text-right">${{ number_format($impuesto ,2,'.','')}}</td>
                        </tr>
                    @endforeach
                    <tr>
                        <td><b>TOTAL: </b></td>
                        <td class="text-right">${{ $total }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>


    <div>
        <p>Nota: Los items marcados con (*) tienen el 5% de IVA.</p>
        <p>&nbsp;</p>
        <p class="text-center"><b>Si desea ver su comprobante electrónico, visite nuestro portal web: https://tuval.seedbilling.ec</b></p>
        <p>&nbsp;</p>
        <p>Nota: Para poder ingresar al portal por favor ingrese el ruc o cédula y su contraseña por defecto es el ruc o cédula</p>
    </div>
</div>
</body>
</html>
