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

        .pagenum {
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
            font-family: Arial, Helvetica, sans-serif;
            background-color: #CCC;
            background: #FFF;
            font-size: 11px;
            padding-top: 300px;
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

        .table td,
        table th {
            font-size: 10px;
        }

        .tablacarrito td {
            text-align: center;
        }

        p {
            margin: 0px;
            font-size: 10px;
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
            padding: 2px;
            vertical-align: top;
        }

        .table2>tbody>tr>td,
        .table2>tbody>tr>th,
        .table2>tfoot>tr>td,
        .table2>tfoot>tr>th,
        .table2>thead>tr>td,
        .table2>thead>tr>th {
            font-size: 9px;
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
            font-size: 9px;
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
            padding: 5px;
            margin-bottom: 10px;
        }

        .fondogris {
            background: #f6f6f6;
        }

        .text-right {
            text-align: right;
        }

        .text-left {
            text-align: left !important;
        }

        .tablacarrito td.text-right {
            text-align: right;
        }

        h1 {
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
                            @if($factura)
                                <h1>Factura No.: {{ $factura }}</h1>
                            @else
                                <h1>Pedido No.: {{ $pedido }}</h1>
                            @endif
                            <table class="table table-striped">
                                <tbody>
                                    <tr>
                                        <td>Almacén</td>
                                        <td>{{ $PTO_VTA_DESCRIPCION }}</td>
                                    </tr>
                                    <tr>
                                        <td>Forma de pago:</td>
                                        <td>{{ $COND_ID }}</td>
                                    </tr>
                                    <tr>
                                        <td>Fecha del documento:</td>
                                        @if($factura)
                                            <td>{{ $fecha_factura }}</td>
                                        @else
                                            <td>{{ $updated_at }}</td>
                                        @endif
                                    </tr>
                                    <tr>
                                        <td>Términos:</td>
                                        <td>{{ $formadepago }}</td>
                                    </tr>
                                    <tr>
                                        <td>Pedido:</td>
                                        <td>{{ $pedido }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <table class="table">
                            <tr>
                                <td>CONTRIBUYENTE ESPECIAN NRO. &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;176</td>
                            </tr>
                            <tr>
                                <td>OBLIGADO A LLEVAR CONTABILIDAD &nbsp;&nbsp;&nbsp;&nbsp; SI</td>
                            </tr>
                        </table>
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
    <div class="container" style="margin-bottom: 20px;">
        <h4>Detalle: </h4>
        <table class="table table-striped tablacarrito">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>3L</th>
                    <th>DESCRIPCIÓN </th>
                    <th>UNIDAD </th>
                    <th class="text-right">CANT </th>
                    <th class="text-right nowrap">P. UNIT</th>
                    <th class="text-right">DSCTO</th>
                    <th class="text-right">DSCTO TOTAL</th>
                    <th class="text-right">TOTAL</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($detalle as $key => $value)
                    <tr>
                        <td class="nowrap">
                            @if($value['ART_TIPO_COMPRA'] == 'SERV')
                                +
                            @endif
                            @if($value['ART_IMP_VTA'] == 5)
                                *
                            @endif
                            {{$value['ART_CODIGO']}}
                        </td>
                        <td>{{$value['ART_CODIGO_TRES_LETRAS']}}</td>
                        <td class="text-left">{{$value['ART_DESCRIPCION']}}
                            @if($value['CHECK_ALTERNATIVO'] === 1)
                                <span class="text-danger" style="color: red;">(Alternativo)</span>
                            @endif
                        </td>
                        <td>{{$value['ART_PLAN_UMEDIDA']}}</td>
                        <td class="text-right">{{$value['ART_CANTIDAD']}}</td>
                        <td class="text-right">${{$value['ART_VALOR']}}</td>
                        <td class="text-right">{{$value['descuento']}}%</td>
                        <td class="text-right">
                            ${{ number_format($value['ART_DESCUENTO'] * $value['ART_CANTIDAD'], 4, '.', '')}}</td>
                        <td class="text-right">
                            ${{ number_format(($value['ART_VALOR'] - $value['ART_DESCUENTO']) * $value['ART_CANTIDAD'], 2, '.', '')}}
                        </td>
                    </tr>
                    <tr>
                        <td></td>
                        <td></td>
                        <td class="text-center">
                            @foreach($value['imagenes'] as $imagen)
                                <img src="{{$imagen}}" style="max-width: 150px;margin-top: 20px;" alt="">
                            @endforeach
                        </td>
                        <td colspan="6"></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="footer">
        <table border="0" width="100%">
            <tr>
                <td style="vertical-align: top">
                    <table border="0">
                        <tr>
                            <td colspàn="2"><b>Información Adicional</b></td>
                        </tr>
                        <tr>
                            <td>Vendedor:</td>
                            <td>{{$vendedor['VEND_NOMBRES'] . ' ' . $vendedor['VEND_APELLIDOS']}}</td>
                        </tr>
                        <tr>
                            <td>Forma de pago:</td>
                            <td>{{$COND_ID}}</td>
                        </tr>
                        @if($cotizacion_id != '')
                            <tr>
                                <td>Cotización:</td>
                                <td>{{$cotizacion}}</td>
                            </tr>
                        @endif
                        <tr>
                            <td>Usuario:</td>
                            <td>{{$USERNAME}}</td>
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
                        @if($orden_de_compra != '')
                            <tr>
                                <td>Orden de compra:</td>
                                <td>{{ $orden_de_compra }}</td>
                            </tr>
                        @endif
                        <tr>
                            <td>Forma de pago SRI:</td>
                            <td>{{ $formadepagoSRI }}</td>
                        </tr>
                    </table>
                </td>
                <td style="vertical-align: top">
                    <table class="table2" align="right" style="margin-right: 10px;">
                        @foreach($tipoimpuesto as $key => $tipo)
                            <tr>
                                <td><b>SUBTOTAL {{ (int) $key}}%: </b></td>
                                <td class="text-right">${{ number_format($tipo, 2, '.', '')}}</td>
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
                                <td><b>IMPUESTOS {{ (int) $key}}%: </b></td>
                                <td class="text-right">${{ number_format($impuesto, 2, '.', '')}}</td>
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
            <p>&nbsp;</p>
            <p>&nbsp;</p>
            <p>&nbsp;</p>

            <p>Observaciones:</p>
            <div class="bordered">
                <p>{{ $nota_cliente }}</p>
            </div>
            <p>Nota: No se aceptan reclamos, ni devoluciones una vez recibido la mercadería.</p>
            <ol>
                <li>Nota: Los items marcados con (*) tienen el 5% de IVA.</li>
                <li>Nota: Favor entregar comprobante de retención dentro de los 5 días caso contrario cliente asumirá
                    valor total de Factura.</li>
                <li>Nota: La mercadería amparada en esta factura viajan por cuenta y riesgo del comprador.</li>
                <li>Nota: Los items marcados con (+) son items de servicio. Por favor retener el 2.75%.</li>
            </ol>
            <div class="bordered" style="font-size: 7px;">
                *DECLARO HABER RECIBIDO A ENTERA SATISFACCIÓN Y SIN LUGAR A RECLAMO POSTERIOR TODA LA MERCADERÍA Y/O
                SERVICIO DETALLADO
                EN ESTA FACTURA, LA MISMA QUE COMO COMPRADOR O DEPENDIENTE AUTORIZADO DEL COMPRADOR RECONOZCO
                EXPRESAMENTE,
                SUJETÁNDOME YO, O MI EMPLEADOR, SEGÚN EL CASO, A LAS CONDICIONES Y PLAZOS QUE CONSTA EN LA MISMA,
                ACEPTANDO DE IGUAL
                MANERA PAGAR EL MÁXIMO DEL INTERÉS CORRESPONDIENTE EN CASO DE RETRASO POR LA MORA, LA QUE PRINCIPIARÁ
                AUTOMÁTICAMENTE
                AL VENCERSE EL PLAZO ANTEDICHO, SIN NECESIDAD DE REQUERIMIENTO ALGUNO.
            </div>
            <div class="bordered" style="font-size: 10px;">
                Si desea ver su comprobante electrónico, visite nuestro portal web: https://tuval.seedbilling.ec<br>
                Nota: Para poder ingresar al portal por favor ingrese el ruc o cédula y su contraseña por defecto es el
                ruc o cédula
            </div>
        </div>
    </div>
</body>

</html>
