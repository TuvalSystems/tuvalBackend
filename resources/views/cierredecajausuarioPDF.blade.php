<!DOCTYPE html>
<html>
<head>
    <title>Cierre de caja PDF</title>
    <style>
        body {
            font-family:Arial, Helvetica, sans-serif;
            background-color:#CCC;
            background:#FFF;
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
            text-align: left;
        }
        .table2 > tbody > tr > td, .table2 > tbody > tr > th, .table2 > tfoot > tr > td, .table2 > tfoot > tr > th, .table2 > thead > tr > td, .table2 > thead > tr > th {

            line-height: 1.42857;
            padding: 2px;
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
            padding: 10px;
            margin-bottom: 10px;
        }
        .fondogris{
            background: #f6f6f6;
        }
        .text-right, .text-end{
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
<div class="container">
    <table border="0" width="100%">
        <tr>
            <td width="50%" align="center" style="padding-right:20px;">
                <img src="images/tuval-logo.jpg" style="margin-bottom:10px;">
            </td>
            <td>
                <div class="bordered fondogris">
                    <h1>Consulta de Caja</h1>
                    <table class="table table-striped">
                        <tbody>
                        <tr>
                            <td>Caja</td>
                            <td>{{ $usuario['CAJ_USER_CAJA'] }}</td>
                        </tr>
                        <tr>
                            <td>Fecha</td>
                            <td>{{ $fecha }}</td>
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
    <div class="">

        @if(count($aplicacion_saldos)>0)
        <div class="bordered">
        <table class="table table-stripped table-bordered table-sm mt-4">
            <thead>
            <tr class="table-success">
                <th colspan="6">APLICACIÓN DE SALDOS (NOTAS DE CRÉDITO / ANTICIPOS)</th>
                <th class="text-end">Total: {{ format_money($aplicacion_saldos_total) }}</th>
            </tr>
            <tr class="table-active">
                <th>Recibo</th>
                <th>Cobro</th>
                <th>Tipo</th>
                <th class="text-end">Monto</th>
                <th>RUC</th>
                <th>Razón social</th>
                <th>Fecha/hora</th>
            </tr>
            </thead>
            <tbody>
            @foreach($aplicacion_saldos as $saldo)
            <tr>
                <td>{{ $saldo['numero_recibo'] }}</td>
                <td>{{ $saldo['cobro'] }}</td>
                <td>{{ $saldo['tipo'] }}</td>
                <td class="text-end">{{ format_money($saldo['monto']) }}</td>
                <td>{{ $saldo['CLI_RUC'] }}</td>
                <td>{{ $saldo['CLI_RAZON_SOCIAL'] }}</td>
                <td>{{ $saldo['fecha'] }}</td>
            </tr>
            @endforeach
            </tbody>
        </table>
        </div>
        @endif

        @if(count($depositos)>0)
        <div class="bordered">
        <table class="table table-stripped table-bordered table-sm mt-4">
            <thead>
            <tr class="table-success">
                <th colspan="7">Depósitos</th>
                <th class="text-end">Total: {{ format_money($depositos_total) }}</th>
            </tr>
            <tr class="table-active">
                <th>Recibo</th>
                <th>Comprobante</th>
                <th>Banco</th>
                <th>Fecha depósito</th>
                <th class="text-end">Monto</th>
                <th>RUC</th>
                <th>Razón social</th>
                <th>Fecha/hora</th>
            </tr>
            </thead>
            <tbody>
            @foreach($depositos as $deposito)
            <tr>
                <td>{{ $deposito['numero_recibo'] }}</td>
                <td>{{ $deposito['comprobante'] }}</td>
                <td>{{ $deposito['CB_DESCRIPCION'] }}</td>
                <td>{{ $deposito['fecha_deposito'] }}</td>
                <td class="text-end">{{  format_money($deposito['monto'] )}}</td>
                <td>{{ $deposito['CLI_RUC'] }}</td>
                <td>{{ $deposito['CLI_RAZON_SOCIAL'] }}</td>
                <td>{{ $deposito['fecha'] }}</td>
            </tr>
            @endforeach
            </tbody>
        </table>
        </div>
        @endif

        @if(count($cheques_aldia)>0)
        <div class="bordered">
        <table class="table table-stripped table-bordered table-sm mt-4">
            <thead>
            <tr class="table-success">
                <th colspan="9">Cheques al día</th>
                <th class="text-end">Total: {{ format_money($cheques_aldia_total) }}</th>
            </tr>
            <tr class="table-active">
                <th>Recibo</th>
                <th>#Cheque</th>
                <th>#Cuenta</th>
                <th>Banco</th>
                <th>F. Emisión</th>
                <th>F. Vencimiento</th>
                <th class="text-end">Monto</th>
                <th>RUC</th>
                <th>Razón social</th>
                <th>Fecha/hora</th>
            </tr>
            </thead>
            <tbody>
            @foreach($cheques_aldia as $cheque)
                <tr :key="index">
                    <td>{{  $cheque['numero_recibo'] }}</td>
                    <td>{{  $cheque['cheque'] }}</td>
                    <td>{{  $cheque['cuenta'] }}</td>
                    <td>{{  $cheque['CB_DESCRIPCION'] }}</td>
                    <td>{{  $cheque['fecha_emision'] }}</td>
                    <td>{{  $cheque['fecha_vencimiento'] }}</td>
                    <td class="text-end">{{  format_money($cheque['monto']) }}</td>
                    <td>{{ $cheque['CLI_RUC'] }}</td>
                    <td>{{ $cheque['CLI_RAZON_SOCIAL'] }}</td>
                    <td>{{ $cheque['fecha'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
        @endif

        @if(count($cheques_posfechados)>0)
        <div class="bordered">
        <table class="table table-stripped table-bordered table-sm mt-4">
            <thead>
                <tr class="table-success">
                    <th colspan="9">Cheques Posfechados</th>
                    <th class="text-end">Total: {{ format_money($cheques_posfechados_total) }}</th>
                </tr>
                <tr class="table-active">
                    <th>Recibo</th>
                    <th>#Cheque</th>
                    <th>#Cuenta</th>
                    <th>Banco</th>
                    <th>F. Emisión</th>
                    <th>F. Vencimiento</th>
                    <th class="text-end">Monto</th>
                    <th>RUC</th>
                    <th>Razón social</th>
                    <th>Fecha/hora</th>
                </tr>
            </thead>
            <tbody>
            @foreach($cheques_posfechados as $cheque)
                <tr>
                    <td>{{  $cheque['numero_recibo'] }}</td>
                    <td>{{  $cheque['cheque'] }}</td>
                    <td>{{  $cheque['cuenta'] }}</td>
                    <td>{{  $cheque['CB_DESCRIPCION'] }}</td>
                    <td>{{  $cheque['fecha_emision'] }}</td>
                    <td>{{  $cheque['fecha_vencimiento'] }}</td>
                    <td class="text-end">{{  format_money($cheque['monto']) }}</td>
                    <td>{{ $cheque['CLI_RUC'] }}</td>
                    <td>{{ $cheque['CLI_RAZON_SOCIAL'] }}</td>
                    <td>{{ $cheque['fecha'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
        @endif

        @if(count($efectivo)>0)
        <div class="bordered">
        <table class="table table-stripped table-bordered table-sm mt-4">
            <thead>
            <tr class="table-success">
                <th colspan="4">Efectivo</th>
                <th class="text-end">Total: {{ format_money($efectivo_total) }}</th>
            </tr>
            <tr class="table-active">
                <th>Recibo</th>
                <th class="text-end">Monto</th>
                <th>RUC</th>
                <th>Razón social</th>
                <th>Fecha/hora</th>
            </tr>
            </thead>
            <tbody>
            @foreach($efectivo as $efe)
            <tr>
                <td>{{  $efe['numero_recibo'] }}</td>
                <td class="text-end">{{  format_money($efe['monto']) }}</td>
                <td>{{ $efe['CLI_RUC'] }}</td>
                <td>{{ $efe['CLI_RAZON_SOCIAL'] }}</td>
                <td>{{ $efe['fecha'] }}</td>
            </tr>
            @endforeach
            </tbody>
        </table>
        </div>
        @endif

        @if(count($retenciones)>0)
        <div class="bordered">
        <table class="table table-stripped table-bordered table-sm mt-4">
            <thead>
            <tr class="table-success">
                <th colspan="11">Retenciones</th>
                <th class="text-end">Total: {{ format_money($retenciones_total) }}</th>
            </tr>
            <tr class="table-active">
                <th>Recibo</th>
                <th>Factura</th>
                <th>Comprobante</th>
                <th>F. Emisión</th>
                <th>F. Vencimiento</th>
                <th>Ret. IVA</th>
                <th>Ret. Renta</th>
                <th>Ret. IVA Srv.</th>
                <th>Ret. Renta Srv.</th>
                <th>RUC</th>
                <th>Razón social</th>
                <th>Fecha/hora</th>
            </tr>
            </thead>
            <tbody>
            @foreach($retenciones as $retencion)
            <tr>
                <td>{{  $retencion['numero_recibo'] }}</td>
                <td>{{  $retencion['ret_factura'] }}</td>
                <td>{{  $retencion['ret_comprobante'] }}</td>
                <td>{{  $retencion['ret_fecha_emision'] }}</td>
                <td>{{  $retencion['ret_fecha_vencimiento'] }}</td>
                <td>{{  $retencion['ret_porcentaje_iva'] }}% {{ format_money($retencion['ret_iva']) }}</td>
                <td>{{  $retencion['ret_porcentaje_renta'] }}% {{ format_money($retencion['ret_renta']) }}</td>
                <td>
                @if($retencion['ret_iva_srv'] > 0)
                  <span>
                    {{  $retencion['ret_porcentaje_iva_srv'] }}% {{ format_money($retencion['ret_iva_srv']) }}
                  </span>
                @endif
                </td>
                <td>
                @if($retencion['ret_renta_srv'] > 0)
                  <span>
                    {{  $retencion['ret_porcentaje_renta_srv'] }}% {{ format_money($retencion['ret_renta_srv']) }}
                  </span>
                @endif
                </td>
                <td>{{ $retencion['CLI_RUC'] }}</td>
                <td>{{ $retencion['CLI_RAZON_SOCIAL'] }}</td>
                <td>{{ $retencion['fecha'] }}</td>
            </tr>
            @endforeach
            </tbody>
        </table>
        </div>
        @endif
        @if(count($tarjeta)>0)
        <div class="bordered">
        <table class="table table-stripped table-bordered table-sm mt-4">
            <thead>
                <tr class="table-success">
                    <th colspan="6">Tarjeta de crédito</th>
                    <th class="text-end">Total: {{ format_money($tarjeta_total) }}</th>
                </tr>
                <tr class="table-active">
                    <th>Recibo</th>
                    <th>Comprobante</th>
                    <th>Banco</th>
                    <th class="text-end">Monto</th>
                    <th>RUC</th>
                    <th>Razón social</th>
                    <th>Fecha/hora</th>
                </tr>
            </thead>
            <tbody>
            @foreach($tarjeta as $tar)
                <tr >
                    <td>{{  $tar['numero_recibo'] }}</td>
                    <td>{{  $tar['comprobante'] }}</td>
                    <td>{{  $tar['banco'] }}</td>
                    <td class="text-end">{{  format_money($tar['monto']) }}</td>
                    <td>{{ $tar['CLI_RUC'] }}</td>
                    <td>{{ $tar['CLI_RAZON_SOCIAL'] }}</td>
                    <td>{{ $tar['fecha'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
        @endif
    </div>
</div>
</body>
</html>
