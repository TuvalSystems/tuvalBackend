<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Orden de Almacenamiento</title>
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
            padding-top: 160px;
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

    <!-- Footer con numeración -->
    <span class="pagenum">Página </span>

    <!-- Header -->
    <div class="header">
        <div class="container">
            <table border="0" width="100%">
                <tr>
                    <td width="50%" align="center" style="padding-right:20px;">
                        @php
                            $logo = public_path('images/tuval-logo.jpg');
                        @endphp
                        @if (is_file($logo))
                            <img src="{{ $logo }}" alt="TUVAL" style="max-height:60px; margin-bottom:6px;">
                        @endif
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
                            <h1>ORDEN DE ALMACENAMIENTO</h1>
                            <h1>Guía: <span>{{ $numero_guia ?? '-' }}</span></h1>
                            <table class="table table-striped">
                                <tbody>
                                    <tr>
                                        <td>Bodega</td>
                                        <td>{{ $destino }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    </div>
    <!-- Contenido -->
    <div class="container">
        <div class="card" style="margin-top:8px; background:#fff;">
             <table class="table table-striped tablacarrito">
                <thead>
                    <tr>
                        <th style="width:34px;">No.</th>
                        <th style="width:120px;">Código</th>
                        <th style="width:92px;">CTL</th>
                        <th class="text-right" style="width:70px;">Cant.</th>
                        <th style="width:74px;">Unidad</th>
                        <th>Descripción</th>
                        <th style="width:120px;">Cotización</th>

                    </tr>
                </thead>
                <tbody>
                    @foreach (($detail ?? []) as $i => $row)
                        <tr>
                            <td class="">{{ $i + 1 }}</td>

                            <td class=" nowrap">
                                {{ data_get($row, 'art_code', '-') }}
                            </td>

                            <td>
                                {{ data_get($row, 'art_cod_tres_letras', '-') }}
                            </td>

                            <td class="text-right">
                                {{ (float) data_get($row, 'cantidad', 0) }}
                            </td>

                            <td>

                                {{ data_get($row, 'art_plan_umedida', '-') }}
                            </td>

                            <td>{{ data_get($row, 'art_descripcion', '-') }}</td>

                            <td>
                                {{-- Si viene como string (JOIN), úsalo; si viene como objeto (with), intenta acceder a
                                ->cotizacion --}}
                                {{ data_get($row, 'co_cotizacion', '-') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</body>

</html>
