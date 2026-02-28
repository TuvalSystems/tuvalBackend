<!DOCTYPE html>
<html lang="es">

<head>
    <title>Guia Remisión</title>
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
    <!-- header -->
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
                            <h1>GUÍA DE REMISIÓN</h1>
                            <h1>No. {{$guia['numero_guia']}}</h1>
                            <table class="table table-striped">
                                <tbody>
                                    <tr>
                                        <td>Almacén</td>
                                        <td>{{ $descripcion }}</td>
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
            <table border="0" width="100%">
                <tr>
                    <td align="left" style="padding: 10px 0; ">

                        <p><b>Clave de acceso:</b> {{ $claveAcceso }}</p>
                    </td>
                    <td align="left" style="padding: 10px 0; width:50%">
                        <div style="text-align:justify;">
                            {!! DNS1D::getBarcodeHTML($claveAcceso, 'C128', 1, 50) !!}
                        </div>
                    </td>
                </tr>
            </table>
            <!-- datos transportista -->
            <div class="bordered">
                <table class="table2">
                    <tr>
                        <td><b>Id. Transportista:</b></td>
                        <td>{{ $guia['identificacion_transportista'] }}</td>
                        <td><b>Razón Social:</b></td>
                        <td>{{ $guia['razon_social_transportista'] }}</td>
                        <td><b>Placa:</b></td>
                        <td>{{ $guia['placa'] }}</td>
                    </tr>
                    <tr>
                        <td><b>Punto de Partida:</b></td>
                        <td>{{ $guia['bodega_origen'] }}</td>
                        <td><b>Fecha Inicio<br> Transporte:</b></td>
                        <td>{{ $guia['fecha_salida'] }}</td>
                        <td><b>Fecha Fin<br> Transporte:</b></td>
                        <td>{{ $guia['fecha_llegada'] }}</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>


    <!-- content -->
    <div class="container">
        <table border="0" width="100%" style="margin-bottom: 10px; ">
            <tr>
                <td style="padding: 10px 0; "><b>No. de Guía de Remisión</b></td>
                <td><b>Fecha de Emisión</b></td>
                <td><b>Punto de Partida</b></td>
                <td><b>No. Orden despacho</b></td>
                <td><b>No. del Comprobante</b></td>
            </tr>
            <tr>
                <td>{{ $guia['numero_guia'] }}</td>
                <td>{{ substr($guia['created_at'], 0, 10) }}</td>
                <td>{{ $guia['bodega_origen'] }}</td>
                <td>{{ $guia['orden'] }}</td>
                <td>{{ $guia['factura'] }}</td>
            </tr>
        </table>

        <!-- lista de cotizaciones -->
        <p style="padding-bottom: 10px;"><b>Cotizaciones:</b>
            @if(count($quoteList))
            @foreach($quoteList as $trans)
            {{$trans}},
            @endforeach
            @endif
        </p>
        <div class="bordered">
            <table class="table2">
                <tr>
                    <td><b>Motivo Traslado:</b></td>
                    <td>Transferencia Reserva</td>

                    <td><b>Destino (Punto de llegada):</b></td>
                    <td>{{ $guia['direccion_destinatario'] }}</td>
                </tr>
                <tr>
                    <td><b>Identificación (Destinatario):</b></td>
                    <td>{{ $guia['identificacion_destinatario'] }}</td>
                    <td><b>Razon Social:</b></td>
                    <td>{{ $guia['razon_social_destinatario'] }}
                        @if($guia['bodega_destino'] != '')
                        - {{$guia['bodega_destino']}}
                        @endif
                    </td>
                </tr>
            </table>
        </div>
        <!-- detalle de guia de remision -->
        <div class="bordered">
            <table width="100%">
                <tr>
                    <td width="50%">
                        <h4>Detalle: </h4>
                    </td>
                    <td class="text-right">
                        <h4 class="text-right">Peso total: {{ $pesoTotal }}kg</h4>
                    </td>
                </tr>
            </table>

            <table class="table table-striped tablacarrito">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Código</th>
                        <th>Cód.Let.Itm.</th>
                        <th class="text-right">Cant.</th>
                        <th>Unidad</th>
                        <th>Descripción</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($detalle as $key => $value)
                    <tr>
                        <td> {{ $key + 1 }}</td>
                        <td class="nowrap">
                            {{$value->art_code}}
                        </td>
                        <td>{{$value->art_cod_tres_letras}}</td>
                        <td class="text-right">{{$value->cantidad}}</td>
                        <td>{{$value->art_plan_umedida}}</td>
                        <td>{{$value->art_descripcion}}
                            @if($value->CHECK_ALTERNATIVO === 1)
                            <span class="text-danger" style="color: red;">(Alternativa)</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($guia['observacion'] != '')
        <strong style="font-size:10px;">Observación:</strong><br>
        <div class="bordered" style="font-size: 10px;">
             {{ $guia['observacion'] }}
        </div>
        @endif

        <!-- foter -->
        <div>
            <p class="text-center"><b>Documento sin validez tributaria, consultar documento electrónico en
                    www.ecdatos.com</b></p>
            <p>&nbsp;</p>

            <p>Según resolución NAC-DGERCGC14-00790 en un tiempo no mayor a 24 horas, el XML firmado electrónicamente
                deberá ser enviado al SRI para su validación</p>
        </div>

    </div>
</body>

</html>
