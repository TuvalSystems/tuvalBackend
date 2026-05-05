<!DOCTYPE html>
<html lang="en">

<head>
    <title>Picking</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>

</head>
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
        font-size: 12px;
        padding-top: 220px;
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
        font-size: 12px;
    }

    .tablacarrito td {
        text-align: center;
    }

    p {
        margin: 0px;
        font-size: 11px;
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
        line-height: 1.6;
        padding: 6px;
        vertical-align: top;
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
                                        <td>{{$destino}}</td>
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
            <table class="table table-sm table-striped tablacarrito">
                <thead>
                    <tr>
                        <th>#</th>
                        <th class="nowrap">Compartimiento / Existencia </th>
                        <th>Cotizacion</th>
                        <th>CTL </th>
                        <th>Descripción</th>
                        <th>UDM</th>
                        <th class="text-right"> Cantidad</th>
                        <th class="text-right"> Peso</th>

                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $key => $value)
                    <tr>
                        <td>{{ $key +1 }}</td>
                        <td>
                            <table style="width:100%">
                                <tbody>
                                    <tr>
                                        <td class="text-left"><b>{{$value['compartimiento']}}</b></td>
                                        <td class="text-left">{{$value['existencia']}}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                        <td>{{$value['cotizacion']}}</td>
                        <td><b>{{$value['art_cod_tres_letras']}}</b></td>
                        <td>{{$value['art_descripcion']}}</td>
                        <td>{{$value['art_plan_umedida']}}</td>
                        <td class="text-right"><b>{{$value['seleccionado']}}</b></td>
                        <td class="text-right">{{ number_format($value['peso_kg'], 2) }}Kg</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="text-right"><strong>Peso total: {{ number_format(($pesototal / 1000), 2, '.', '') }} Tn</strong></p>
        </div>
    </div>
</body>

</html>
