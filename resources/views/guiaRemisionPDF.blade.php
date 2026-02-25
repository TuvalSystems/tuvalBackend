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
                            <h1>No. {{$guia['GUI_REM_GUIA_REMISION']}}</h1>
                            <table class="table table-striped">
                                <tbody>
                                    <tr>
                                        <td>Almacén</td>
                                        <td>{{ $PTO_VTA_DESCRIPCION }}</td>
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

                        <p><b>Clave de acceso:</b> {{ $guia['GUI_REM_CODIGO'] }}</p>
                    </td>
                    <td align="left" style="padding: 10px 0; width:50%">
                        @if(isset($guia['GUI_REM_CODIGO']) && $guia['GUI_REM_CODIGO'] != '')
                        @php
                        $generatorPNG = new Picqer\Barcode\BarcodeGeneratorPNG();
                        @endphp
                        <img style="width: 100%;height: 50px;"
                            src="data:image/png;base64,{{ base64_encode($generatorPNG->getBarcode($guia['GUI_REM_CODIGO'], $generatorPNG::TYPE_CODE_128)) }}">
                        @endif
                    </td>
                </tr>
            </table>
            <div class="bordered">
                <table class="table2">
                    <tr>
                        <td><b>Id. Transportista:</b></td>
                        <td>{{ $guia['GUI_REM_IDENTIFICACION_TRANSPORTISTA'] }}</td>
                        <td><b>Razón Social:</b></td>
                        <td>{{ $guia['GUI_REM_RAZON_SOCIAL_TRANSPORTISTA'] }}</td>
                        <td><b>Placa:</b></td>
                        <td>{{ $guia['GUI_REM_PLACA'] }}</td>
                    </tr>
                    <tr>
                        <td><b>Punto de Partida:</b></td>
                        <td>{{ $guia['bodega_origen'] }}</td>
                        <td><b>Fecha Inicio<br> Transporte:</b></td>
                        <td>{{ $guia['GUI_REM_FECHA_INICIO'] }}</td>
                        <td><b>Fecha Fin<br> Transporte:</b></td>
                        <td>{{ $guia['GUI_REM_FECHA_FIN'] }}</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
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
                <td>{{ $guia['GUI_REM_GUIA_REMISION'] }}</td>
                <td>{{ substr($guia['created_at'], 0, 10) }}</td>
                <td>{{ $guia['bodega_origen'] }}</td>
                <td>{{ $guia['orden'] }}</td>
                <td>{{ $guia['factura'] }}</td>
            </tr>
        </table>
        <p style="padding-bottom: 10px;"><b>Pedido:</b>
            @if(count($transferencias))
            @foreach($transferencias as $trans)
            {{$trans}},
            @endforeach
            @else
            {{ $guia['pedido'] }}
            @endif
            @if($guia['orden_de_compra'] && $guia['tipo'] == 'despacho')
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <b>Orden de Compra:</b> {{ $guia['orden_de_compra'] }}
            @endif
        </p>

        <div class="bordered">
            <table class="table2">
                <tr>
                    <td><b>Motivo Traslado:</b></td>
                    <td>
                        @if($guia['tipo'] == 'despacho')
                        VENTA
                        @else
                        TRASLADO
                        @endif
                    </td>
                    <td><b>Destino (Punto de llegada):</b></td>
                    <td>{{ $guia['GUI_REM_DIRECCION_DESTINATARIO'] }}</td>

                </tr>
                <tr>
                    <td><b>Identificación (Destinatario):</b></td>
                    <td>{{ $guia['GUI_REM_IDENTIFICACION_DESTINATARIO'] }}</td>
                    <td><b>Razon Social:</b></td>
                    <td>{{ $guia['GUI_REM_RAZON_SOCIAL_DESTINATARIO'] }}
                        @if($guia['bodega_destino'] != '')
                        - {{$guia['bodega_destino']}}
                        @endif
                    </td>
                </tr>
            </table>
        </div>

        <div class="bordered">
            <table width="100%">
                <tr>
                    <td width="50%">
                        <h4>Detalle: </h4>
                    </td>
                    <td class="text-right">
                        <h4 class="text-right">Peso total: {{ $peso }}kg</h4>
                    </td>
                </tr>
            </table>

            <table class="table table-striped tablacarrito">
                <thead>
                    <tr>
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
                        <td class="nowrap">
                            {{$value->GUI_REM_CODI_INTERNO}}
                        </td>
                        <td>{{$value->GUI_REM_CODI_ADICIONAL}}</td>
                        <td class="text-right">{{$value->GUI_REM_CANTIDAD}}</td>
                        <td>{{$value->GUI_REM_UNIDAD_MEDIDA}}</td>
                        <td>{{$value->GUI_REM_DESCRIPCION}}
                            @if($value->CHECK_ALTERNATIVO === 1)
                            <span class="text-danger" style="color: red;">(Alternativa)</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($guia['tipo'] == 'despacho')
        <table width="100%" style="margin-top: 30px; margin-bottom: 30px;">
            <tr>
                <td width="40%" align="left">
                    <table width="100%" cellpadding="0" cellspacing="0">
                        <tr>
                            <td style="text-align: left; padding-bottom: 40px; font-size: 11px;">
                                Recibí Conforme:
                            </td>
                        </tr>
                        <tr>
                            <td style="border-top: 1px solid #000000; padding-top: 5px; text-align: left; font-size: 11px;">
                                C.I.:
                            </td>
                        </tr>
                    </table>
                </td>

                <td width="60%"></td>
            </tr>
        </table>
        @endif


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
