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
            background-color:#CCC;
            background:#FFF;
            font-size:11px;
            padding-top: 270px;
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
                            </table>
                        </div>
                    </td>
                    <td>
                        <div class="bordered fondogris">
                            <h1>COTIZACIÓN No.: {{ $cotizacion }}</h1>
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
                                        <td>{{ $created_at }}</td>
                                    </tr>
                                    <tr>
                                        <td>Términos:</td>
                                        <td>{{ $formadepago }}</td>
                                    </tr>
                                    <tr>
                                        <td>Usuario:</td>
                                        <td>{{ $USERID }}</td>
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
                        <td colspan="3">{{ $sucursal['CLI_SUC_DIRECCION'] }}</td>
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
    <div class="container">
        <div class="">
            <h4>Detalle: </h4>
            <table class="table table-striped tablacarrito">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th class="nowrap">CÓDIGO 3L</th>
                        <th>DESCRIPCIÓN </th>
                        <th class="text-right">CANT </th>
                        <th>UNIDAD </th>
                        <th class="text-right">P. UNIT</th>
                        <th class="text-right">DSCTO</th>
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
                        <td class="text-right">{{$value['ART_CANTIDAD']}}</td>
                        <td>{{$value['ART_PLAN_UMEDIDA']}}</td>
                        <td class="text-right">${{$value['ART_VALOR']}}</td>
                        <td class="text-right">{{$value['descuento']}}%</td>
                        <td class="text-right">${{ number_format(($value['ART_VALOR'] - $value['ART_DESCUENTO']) * $value['ART_CANTIDAD'],2,'.','')}}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            <div style="overflow: hidden;">
                <table class="table2" align="right">
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
            </div>
        </div>
    </div>
    <div class="footer">
        <table border="0" width="100%">
            <tr>
                <td width="40%">
                    <p><b>Pagos mediante Depósitos en Cheque o Efectivo:</b></p>

                    <p>Cta. Corriente Bco. Pichincha 3119240004</p>
                    <p>Cta. Ahorros Bco. Guayaquil 15740162</p>
                    <p>Cta. Ahorros Bco. Pacifico 1058578234</p>
                    <p>Nota: Si los depósitos son realizados en otras cuentas, <br>deben esperar 72 horas para el despacho.</p>
                </td>
                <td width="40%">

                    <p><b>Pagos mediante Transferencias Bancarias</b></p>
                    <p>Cta. Corriente Bco. Internacional 1650604193</p>
                    <p>Cta. Corriente Bco. Produbanco 02005305957</p>
                    <p>Cta. Corriente Bco. Pichincha 3119240004</p>
                    <p>Cta. Ahorros Bco. Guayaquil 15740162</p>
                    <p>Cta. Ahorros Bco. Bolivariano 0003839337</p>
                </td>
                <td width="20%" style="vertical-align: top; text-align: right">
                    <table border="0" width="100%">
                        <tr>
                            <td colspan="2" class="text-right"><b>Información Adicional</b></td>
                        </tr>
                        <tr>
                            <td class="text-right">Vendedor:</td>
                            <td class="text-right" style="width: 50px;">{{$VEND_NOMBRES.' '.$VEND_APELLIDOS}}</td>
                        </tr>
                        <tr>
                            <td class="text-right">Peso:</td>
                            <td class="text-right">{{ $peso }}kg</td>
                        </tr>
                        @if($orden_de_compra != '')
                            <tr>
                                <td class="text-right">Orden de compra:</td>
                                <td class="text-right nowrap">{{ $orden_de_compra }}</td>
                            </tr>
                        @endif
                    </table>
                </td>
            </tr>
        </table>
        <div>
            <p>&nbsp;</p>
            <p>Esta cotización no incluye los cambios que pudieran darse al Impuesto al Valor Agregado (IVA) ni a los tributos al comercio exterior. Disponibilidad
de producto sujeto a confirmación. Esta cotización se ajusta el artículo 148 del Código de Comercio.</p>
            <p>&nbsp;</p>
            <p>Toda compra mayor a $500 debe realizarse a través del sistema financiero. Ley de Eficiencia Económica y Generación de Empleo. Art.25</p>
            <table class="table2" style="width: 100%;">
                <tr>
                    <td class="text-right nowrap" ><b>Validez de la cotización: {{$validez}}</b></td>
                    <td class="text-right nowrap"><b>Vence: {{$expira}}</b></td>
                </tr>
            </table>
            <p>Observaciones:</p>
            <div class="bordered">
                <p>{{ $nota_cliente }}</p>
            </div>
        </div>
        <img src="images/ventas/cotizaciones/CotizacionLineaProductos.jpg" style="max-width: 100%;margin-top: 20px;" alt="">
        <h1 style="display: none">LINEA DE PRODUCTOS</h1>
        <table class="table3" style="width: 100%; display: none">
            <thead>
                <tr>
                    <th width="10%">TUBERIA</th>
                    <th width="10%">VALVULASth</th>
                    <th width="10%">PLANCHAS</th>
                    <th width="10%">PERFILES</th>
                    <th width="10%">FERRETERIA</th>
                    <th width="10%">ACCESORIOS</th>
                    <th width="10%">BRIDAS</th>
                    <th width="15%">AISLAM. VAPOR TERMICOS Y REFRACT.</th>
                    <th width="10%">VAPOR</th>

                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>NEGRA</td>
                    <td>COMPUERTA</td>
                    <td>NEGRA</td>
                    <td>CORREAS</td>
                    <td>SOLDADURA</td>
                    <td>CONEXIONES</td>
                    <td>DESLIZABLES</td>
                    <td>FIBROCERAMICA</td>
                    <td>REDUC.DE PRESION</td>
                </tr>
                <tr>
                    <td>INOXIDABLE</td>
                    <td>GLOBO</td>
                    <td>INOXIDABLE</td>
                    <td>CANALES</td>
                    <td>PINTURA</td>
                    <td>LINEA SANITARIA</td>
                    <td>CON CUELLO</td>
                    <td>PAPEL CERAMICO</td>
                    <td>SEPARADORES DE</td>
                </tr>
                <tr>
                    <td>GALVANIZADA</td>
                    <td>CHEQUE</td>
                    <td>GALVANIZADA</td>
                    <td>ANGULOS</td>
                    <td>PENETRANTES</td>
                    <td>ACC.SOLDABLES</td>
                    <td>CIEGAS</td>
                    <td>RECUBRIMIENTO</td>
                    <td>HUMEDAD</td>
                </tr>
                <tr>
                    <td>DECORATIVA</td>
                    <td>DIAFRAGMA</td>
                    <td>EXPANDIDA</td>
                    <td>IPE</td>
                    <td>PERNOS</td>
                    <td>ACC.ROSCABLES</td>
                    <td>ROSCADAS</td>
                    <td>CANUELAS CON Y SIN</td>
                    <td>VALVULAS DE</td>
                </tr>
                <tr>
                    <td></td>
                    <td>MARIPOSA</td>
                    <td>NAVAL</td>
                    <td>HEB</td>
                    <td>MALLAS</td>
                    <td>ACC.TIPO CLAMP</td>
                    <td></td>
                    <td>SISTEMAS DE ANCLAJE</td>
                    <td>CONTROL</td>
                </tr>
                <tr>
                    <td></td>
                    <td>BOLA</td>
                    <td></td>
                    <td>IPN</td>
                    <td>DISCOS DE CORTE</td>
                    <td></td>
                    <td></td>
                    <td>ROLLOS DE LANA</td>
                    <td></td>
                </tr>
                <tr>
                    <td></td>
                    <td>SANITARIAS</td>
                    <td></td>
                    <td>UPN</td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
                <tr>
                    <td></td>
                    <td>SOLENOIDES</td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
            </tbody>
        </table>
    </div>
</body>
</html>
