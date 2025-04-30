<!DOCTYPE html>
<html lang="en">

<head>
    <title>Hi</title>
    <style>
        .pagenum {
            position: fixed;
            bottom: -30px;
            right: 0px;
            height: 10px;
            right: 0px;
            font-size: 10px !important;
        }

        .pagenum:before {
            content: counter(page);
        }

        body {
            font-family: Arial, Arial, Helvetica, sans-serif;
            background-color: #CCC;
            background: #FFF;
            font-size: 11px;
            padding-top: 300px;

        }

        .header {
            position: fixed;
            top: -20px;
            left: 0px;
            right: 0px;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
        }

        table {
            border-collapse: collapse;
            border-spacing: 0;
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

        .bordered {
            border: rgb(19, 19, 19) 1px solid;
            -webkit-border-radius: 10px;
            -moz-border-radius: 10px;
            border-radius: 5px;
            padding: 5px;
            margin-bottom: 10px;
        }

        .table-striped>tbody>tr:nth-of-type(2n+1)>td {
            background-color: #f9f9f9;
        }

        .tablacarrito td {
            text-align: center;
        }

        .tablacarrito td.text-right {
            text-align: right;
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
            border: #CCC 1px solid;
        }
    </style>
</head>

<body>
    <span class="pagenum">Página </span>
    <div class="header">
        <div class="container">
            <table border="0" width="100%">
                <tr>
                    <!-- Columna Izquierda -->
                    <td width="50%" align="center" style="padding-right:10px;">
                        <div style="margin-bottom: 10px;">
                            <img src="{{ public_path('images/tuval-logo.jpg') }}" style="height: 60px;">
                        </div>
                        <div class="bordered" style="text-align: justify;">
                            <span style="font-size: 12px; font-weight: bold;">TUVAL S.A.</span>
                            <div><strong>R.U.C.:</strong> 0990281866001</div>
                            <div><strong>Dir. Matriz:</strong> GUAYAS / GUAYAQUIL / TARQUI / CEDROS SOLAR 5 Y
                                CALLE C</div>
                            <div><strong>Dir. Sucursal:</strong> MANABÍ / MANTA / AV 113 SN Y OLIVIA MIRANDA</div>
                            <div><strong>Telf:</strong> (04) 370-4060</div>
                            <div><strong>Contribuyente Especial Resolución Nro.:</strong> 176</div>
                            <div><strong>OBLIGADO A LLEVAR CONTABILIDAD:</strong> SI</div>
                        </div>
                    </td>

                    <!-- Columna Derecha -->
                    <td>
                        <div class="bordered" style="text-align: justify;">
                            <div style=" font-weight: bold; font-size: 12px; margin-bottom: 0.5em;">FACTURA</div>
                            <div><strong>No.:</strong> <span style="color: red;"> {{ $factura }}</span></div>
                            <div><strong>NÚMERO DE AUTORIZACIÓN</strong></div>
                            <div style="font-size: 10px;"> {{ $clave_acceso }}</div>
                            <div><strong>FECHA Y HORA DE AUTORIZACIÓN:</strong>{{ $fecha_autorizacion }}</div>
                            <div><strong>AMBIENTE:</strong> PRODUCCIÓN</div>
                            <div><strong>EMISIÓN:</strong> NORMAL</div>
                            <div style=" margin-top: 15px;"><strong>CLAVE DE ACCESO</strong></div>
                            <div style="text-align: center; ">
                                <div style="text-align:justify;">
                                    {!! DNS1D::getBarcodeHTML($clave_acceso, 'C128', 1, 50) !!}
                                </div>
                                <div style="font-size: 10px;">
                                    {{ $clave_acceso }}
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
            </table>
            <!-- Detalle del cliente -->
            <table border="0" width="100%">
                <tr>
                    <div class="bordered" style="text-align: justify;">
                         <div><strong>RAZÓN SOCIAL/NOMBRES :</strong>{{  $cliente['CLI_RAZON_SOCIAL'] }}</div>
                        <div><strong>RUC :</strong> {{ $cliente['CLI_RUC'] }} </div>
                        <div><strong>FECHA EMISIÓN :</strong>
                        {{ $fecha_factura }}
                        </div>
                        <div><strong>DIRECCIÓN :</strong> {{ $sucursal['CLI_SUC_DIRECCION'] }} </div>
                        <div><strong>EMAIL :</strong> {{ $cliente['CLI_CORREO_ELECT_SRI'] }} </div>

                    </div>
                </tr>
            </table>
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
                    <td class="text-left">{{$value['ART_DESCRIPCION']}}</td>
                    <td>{{$value['ART_PLAN_UMEDIDA']}}</td>
                    <td class="text-right">{{$value['ART_CANTIDAD']}}</td>
                    <td class="text-right">${{$value['ART_VALOR']}}</td>
                    <td class="text-right">{{$value['descuento']}}%</td>
                    <td class="text-right">${{ number_format($value['ART_DESCUENTO'] * $value['ART_CANTIDAD'], 4 , '.','')}}</td>
                    <td class="text-right">${{ number_format(($value['ART_VALOR'] - $value['ART_DESCUENTO']) * $value['ART_CANTIDAD'],2,'.','')}}</td>
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
                            <td>{{$vendedor['VEND_NOMBRES'].' '.$vendedor['VEND_APELLIDOS']}}</td>
                        </tr>
                        <tr>
                            <td>Forma de pago:</td>
                            <td>{{$COND_ID}}</td>
                        </tr>

                        <tr>
                            <td>Teléfono Cliente:</td>
                            <td>{{$cliente['CLI_CONTACTO']}}</td>
                        </tr>
                        <tr>
                            <td>Peso:</td>
                            <td>{{ $peso }}kg</td>
                        </tr>
                        <tr>
                            <td>Forma Retiro:</td>
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
                    <!-- formas de pago -->
                    <table class="table2" width="100%" style="margin-top: 10px;">
                        <thead>
                            <tr>
                                <th>Forma de pago</th>
                                <th>Valor</th>
                                <th>Plazo</th>
                                <th>Tiempo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="text-align: left;">{{ $formadepagoSRI }}</td>
                                <td style="text-align: right;">${{ $total }}</td>
                                <td style="text-align: right;">0</td>
                                <td style="text-align: center;">--</td>
                            </tr>
                        </tbody>
                    </table>
                </td>
                <td style="vertical-align: top">

                    <table class="table2 " align="right" style="margin-right: 10px;">
                        @foreach($tipoimpuesto as $key => $tipo)
                        <tr>
                            <td><b>SUBTOTAL {{ (int)$key}}%: </b></td>
                            <td style="text-align: right;">${{ number_format($tipo ,2,'.','')}}</td>
                        </tr>
                        @endforeach
                        <tr>
                            <td><b>SUBTOTAL 0%: </b></td>
                            <td style="text-align: right;">0.00</td>
                        </tr>
                        <tr>
                            <td><b>SUBTOTAL no Objeta IVA: </b></td>
                            <td style="text-align: right;">0.00</td>
                        </tr>
                        <tr>
                            <td><b>EXENTO de IVA: </b></td>
                            <td style="text-align: right;">0.00</td>
                        </tr>
                        <tr>
                            <td><b>SUBTOTAL SIN IMPUESTOS : </b></td>
                            <td style="text-align: right;">${{ $subtotalmenosdescuento }}</td>
                        </tr>
                        <tr>
                            <td><b>DESCUENTO: </b></td>
                            <td style="text-align: right;">${{ $descuento }}</td>
                        </tr>
                        <tr>
                            <td><b>ICE: </b></td>
                            <td style="text-align: right;">0.00</td>
                        </tr>
                        <tr>
                            <td><b>PROPINA: </b></td>
                            <td style="text-align: right;">0.00</td>
                        </tr>

                        @foreach($sumaimpuesto as $key => $impuesto)
                        <tr>
                            <td><b>IVA {{ (int)$key}}%: </b></td>
                            <td style="text-align: right;">${{ number_format($impuesto ,2,'.','')}}</td>
                        </tr>
                        @endforeach
                        <tr>
                            <td><b>TOTAL: </b></td>
                            <td style="text-align: right;">${{ $total }}</td>
                        </tr>
                    </table>
                </td>

            </tr>
        </table>

        <div>
            <!-- <p>Observaciones:</p>
            <div class="bordered">
                <p>{{ $nota_cliente }}</p>
            </div> -->
            <p>Nota: No se aceptan reclamos, ni devoluciones una vez recibido la mercadería.</p>
            <ol>
                <li>Nota: Los items marcados con (*) tienen el 5% de IVA.</li>
                <li>Nota: Favor entregar comprobante de retención dentro de los 5 días caso contrario cliente asumirá valor total de Factura.</li>
                <li>Nota: La mercadería amparada en esta factura viajan por cuenta y riesgo del comprador.</li>
                <li>Nota: Los items marcados con (+) son items de servicio. Por favor retener el 2.75%.</li>
            </ol>
            <div class="bordered" style="font-size: 7px;">
                *DECLARO HABER RECIBIDO A ENTERA SATISFACCIÓN Y SIN LUGAR A RECLAMO POSTERIOR TODA LA MERCADERÍA Y/O SERVICIO DETALLADO
                EN ESTA FACTURA, LA MISMA QUE COMO COMPRADOR O DEPENDIENTE AUTORIZADO DEL COMPRADOR RECONOZCO EXPRESAMENTE,
                SUJETÁNDOME YO, O MI EMPLEADOR, SEGÚN EL CASO, A LAS CONDICIONES Y PLAZOS QUE CONSTA EN LA MISMA, ACEPTANDO DE IGUAL
                MANERA PAGAR EL MÁXIMO DEL INTERÉS CORRESPONDIENTE EN CASO DE RETRASO POR LA MORA, LA QUE PRINCIPIARÁ AUTOMÁTICAMENTE
                AL VENCERSE EL PLAZO ANTEDICHO, SIN NECESIDAD DE REQUERIMIENTO ALGUNO.
            </div>
            <div class="bordered" style="font-size: 10px;">
                Si desea ver su comprobante electrónico, visite nuestro portal web: https://tuval.seedbilling.ec<br>
                Nota: Para poder ingresar al portal por favor ingrese el ruc o cédula y su contraseña por defecto es el ruc o cédula
            </div>
        </div>
    </div>
</body>

</html>
