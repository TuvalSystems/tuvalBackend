{{-- resources/views/pdf/steam-traps/sizing.blade.php --}}

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Dimensionamiento de Trampa de Vapor</title>

    <style>
        @page {
            margin: 22px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 11px;
            color: #1f2937;
            background: #ffffff;
        }

        .page {
            width: 100%;
        }

        .header {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 12px;
        }

        .header tr {
            height: 76px;
        }

        .header td {
            vertical-align: middle;
        }

        .brand {
            width: 30%;
            padding: 0 !important;
            background: none !important;
            line-height: 0;
            font-size: 0;
            text-align: center;
        }

        .brand-logo {
            display: block;
            width: 100%;
            height: 76px;
            margin: 0;
            padding: 0;
            border: 0;
        }


        .title {
            width: 43%;
            height: 76px;
            background: #fff7ed;
            color: #111827;
            font-size: 14px;
            font-weight: bold;
            text-align: center;
            line-height: 1.15;
            padding: 0 8px;
            border-top: 1px solid #fed7aa;
            border-bottom: 1px solid #fed7aa;
        }

        .meta {
            width: 27%;
            height: 76px;
            background: #111827;
            color: #ffffff;
            text-align: center;
            padding: 0 8px;
            font-size: 8.5px;
            line-height: 1.2;
        }

        .meta strong {
            display: block;
            font-size: 8.5px;
            color: #d1d5db;
        }

        .meta span {
            display: block;
            font-weight: bold;
            margin-bottom: 3px;
        }

        @page {
            margin: 14px 18px;
        }

        body {
            font-size: 9.5px;
            line-height: 1.18;
        }




        .columns {
            width: 100%;
            clear: both;
        }

        .col-left {
            float: left;
            width: 49%;
        }

        .col-right {
            float: right;
            width: 49%;
        }

        .clearfix {
            clear: both;
        }

        .section {
            margin-bottom: 9px;
            page-break-inside: avoid;
        }

        .section-title {
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: .35px;
            color: #111827;
            border-bottom: 1.5px solid #f97316;
            padding-bottom: 3px;
            margin-bottom: 5px;
        }

        .data-table td,
        .result-table td,
        .model-table td,
        .sp-table td {
            padding: 3px 5px;
            border-bottom: 1px solid #e5e7eb;
            line-height: 1.1;
        }

        .note {
            border: 1px solid #fed7aa;
            background: #fff7ed;
            padding: 6px 8px;
            line-height: 1.25;
            margin-top: 7px;
        }

        .recommendation {
            border: 1px solid #bbf7d0;
            background: #f0fdf4;
            padding: 8px;
            text-align: center;
            margin-bottom: 7px;
        }

        .recommendation-label {
            font-size: 8.5px;
            font-weight: bold;
            color: #15803d;
            text-transform: uppercase;
            letter-spacing: .35px;
            margin-bottom: 3px;
        }

        .recommendation-value {
            font-size: 14px;
            font-weight: bold;
            color: #166534;
            line-height: 1.15;
        }

        .comparison-row {
            margin-bottom: 7px;
        }

        .bar-track {
            width: 100%;
            height: 8px;
            background: #f3f4f6;
            border-radius: 20px;
            overflow: hidden;
        }

        .bar-required,
        .bar-selected {
            height: 8px;
        }

        .bar-label {
            font-weight: bold;
            color: #374151;
            margin-top: 2px;
            font-size: 9px;
        }

        .bar-value {
            text-align: right;
            font-weight: bold;
            width: 85px;
            font-size: 9px;
        }

        .accessories-table th {
            background: #f9fafb;
            color: #374151;
            font-size: 8.5px;
            text-align: left;
            padding: 4px 5px;
            border-bottom: 1px solid #d1d5db;
        }

        .accessories-table td {
            padding: 4px 5px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 9px;
        }

        .footer {
            clear: both;
            margin-top: 8px;
            padding-top: 5px;
            border-top: 1px solid #e5e7eb;
            font-size: 8px;
            color: #6b7280;
            text-align: center;
        }

        .section {
            margin-bottom: 15px;
        }

        .section-title {
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: .4px;
            color: #111827;
            border-bottom: 2px solid #f97316;
            padding-bottom: 6px;
            margin-bottom: 10px;
        }

        .section-title.dark {
            border-bottom-color: #111827;
        }

        .data-table,
        .result-table,
        .model-table,
        .accessories-table,
        .sp-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table td,
        .result-table td,
        .model-table td,
        .sp-table td {
            padding: 5px 7px;
            border-bottom: 1px solid #e5e7eb;
        }

        .key {
            width: 50%;
            color: #4b5563;
            font-weight: bold;
        }

        .value {
            width: 50%;
            color: #111827;
        }

        .note {
            border: 1px solid #fed7aa;
            background: #fff7ed;
            padding: 10px;
            line-height: 1.4;
            margin-top: 12px;
        }

        .recommendation {
            border: 1px solid #bbf7d0;
            background: #f0fdf4;
            padding: 14px;
            text-align: center;
            margin-bottom: 14px;
        }

        .recommendation-label {
            font-size: 10px;
            font-weight: bold;
            color: #15803d;
            text-transform: uppercase;
            letter-spacing: .4px;
            margin-bottom: 6px;
        }

        .recommendation-value {
            font-size: 18px;
            font-weight: bold;
            color: #166534;
            line-height: 1.25;
        }

        .highlight {
            color: #f97316;
            font-weight: bold;
        }

        .success {
            color: #15803d;
            font-weight: bold;
        }

        .muted {
            color: #6b7280;
        }

        .comparison-row {
            margin-bottom: 14px;
        }

        .comparison-table {
            width: 100%;
            border-collapse: collapse;
        }

        .comparison-table td {
            vertical-align: middle;
            padding: 3px 0;
        }

        .bar-label {
            font-weight: bold;
            color: #374151;
            margin-top: 4px;
        }

        .bar-value {
            text-align: right;
            font-weight: bold;
            width: 115px;
        }

        .bar-track {
            width: 100%;
            height: 13px;
            background: #f3f4f6;
            border-radius: 20px;
            overflow: hidden;
        }

        .bar-required {
            height: 13px;
            background: #f97316;
        }

        .bar-selected {
            height: 13px;
            background: #16a34a;
        }

        .accessories-table th {
            background: #f9fafb;
            color: #374151;
            font-size: 10px;
            text-align: left;
            padding: 6px 8px;
            border-bottom: 1px solid #d1d5db;
        }

        .accessories-table td {
            padding: 7px 8px;
            border-bottom: 1px solid #e5e7eb;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .footer {
            margin-top: 18px;
            padding-top: 8px;
            border-top: 1px solid #e5e7eb;
            font-size: 9px;
            color: #6b7280;
            text-align: center;
        }

        .item-image-box {
            width: 100%;
            text-align: center;
            margin: 6px 0 10px 0;
            padding: 6px;
            border: 1px solid #e5e7eb;
            background: #ffffff;
        }

        .item-image {
            max-width: 135px;
            max-height: 95px;
        }

        .item-image-placeholder {
            border: 1px dashed #d1d5db;
            color: #9ca3af;
            font-size: 8.5px;
            padding: 16px 6px;
            text-align: center;
        }

        .item-images-small {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
        }

        .item-images-small td {
            width: 33.33%;
            text-align: center;
            padding: 2px;
            border: 0;
        }

        .item-thumb {
            max-width: 55px;
            max-height: 42px;
        }

        .full-width-section {
            clear: both;
            width: 100%;
            margin-top: 5px;
            page-break-inside: avoid;
        }

        .full-width-section .section-title {
            font-size: 10px;
            margin-bottom: 6px;
            padding-bottom: 4px;
        }

        .accessories-table.full {
            width: 100%;
            border-collapse: collapse;
        }

        .accessories-table.full th {
            background: #f9fafb;
            color: #374151;
            font-size: 9px;
            text-align: left;
            padding: 5px 6px;
            border-bottom: 1px solid #d1d5db;
        }

        .accessories-table.full td {
            padding: 5px 6px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 9px;
        }

        .accessories-table.full .col-order {
            width: 8%;
            text-align: center;
        }

        .accessories-table.full .col-origin {
            width: 18%;
        }

        .accessories-table.full .col-item {
            width: 18%;
        }


        .accessories-table.full .col-descripcion {
            width: 70%;
        }

        .accessories-table.full .col-qty {
            width: 8%;
            text-align: right;
        }

        .footer {
            position: fixed;
            left: 18px;
            right: 18px;
            bottom: 8px;
            padding-top: 5px;
            border-top: 1px solid #e5e7eb;
            font-size: 8px;
            color: #6b7280;
            text-align: center;
        }

        .footer-legend {
            display: block;
            margin-bottom: 2px;
            color: #374151;
            font-weight: bold;
        }

        body {
            margin-bottom: 28px;
        }
    </style>
</head>

<body>
    @php
    $requestData = $requestData ?? [];
    $spParams = $spParams ?? data_get($requestData, 'spParams', []);
    $selectedItem = $selectedItem ?? [];
    $originItems = $originItems ?? [];

    $fmt = function ($value, $decimals = 2, $default = '-') {
    if ($value === null || $value === '') {
    return $default;
    }

    return number_format((float) $value, $decimals, '.', ',');
    };

    $fmtInt = function ($value, $default = '-') {
    if ($value === null || $value === '') {
    return $default;
    }

    return number_format((float) $value, 0, '.', ',');
    };

    $fecha = now()->format('d/m/Y');
    $codigo = data_get($selectedItem, 'txItem', 'TRP-0000');

    $customerId = data_get($requestData, 'customer_id');
    $customerRuc = data_get($requestData, 'customer_ruc');

    $process = data_get($requestData, 'process');
    $trapType = data_get($requestData, 'trapType');
    $calculationMethod = data_get($requestData, 'calculationMethod');

    $workingPressure = data_get($requestData, 'workingPressurePsig');
    $returnPressure = data_get($requestData, 'returnPressurePsig');
    $pressureDifferential = data_get($requestData, 'pressureDifferentialPsig');

    $fluid = data_get($requestData, 'fluid');
    $volume = data_get($requestData, 'volumeM3');
    $density = data_get($requestData, 'densityKgM3');
    $cp = data_get($requestData, 'cpKjKgC');
    $initialTemperature = data_get($requestData, 'initialTemperatureC');
    $finalTemperature = data_get($requestData, 'finalTemperatureC');
    $heatingTime = data_get($requestData, 'heatingTimeMin');
    $requiredHeat = data_get($requestData, 'requiredHeatKj');
    $hfg = data_get($requestData, 'hfgKjKg');

    $estimatedLoadKg = data_get($selectedItem, 'ndCargaKg', data_get($requestData, 'estimatedLoadKg'));
    $safetyFactor = data_get($selectedItem, 'ndFacseg', data_get($requestData, 'safetyFactor'));
    $loadWithSafetyKg = data_get($selectedItem, 'ndCargaCalcKg', data_get($requestData, 'loadWithSafetyFactorKg'));

    $requiredLoadLbh = data_get($selectedItem, 'ndCargaLbh');
    $selectedCapacityLbh = data_get($selectedItem, 'ndCaplbh');
    $coverageFactor = data_get($selectedItem, 'ndFacCob');

    $selectedCapacityKg = is_numeric($selectedCapacityLbh)
    ? ((float) $selectedCapacityLbh * 0.45359237)
    : null;

    $requiredBarPercent = 35;
    $selectedBarPercent = 100;

    if (is_numeric($loadWithSafetyKg) && is_numeric($selectedCapacityKg) && (float) $selectedCapacityKg > 0) {
    $requiredBarPercent = min(100, max(8, (((float) $loadWithSafetyKg / (float) $selectedCapacityKg) * 100)));
    }

    $model = data_get($selectedItem, 'txVentec', '-');
    $itemCode = data_get($selectedItem, 'txItem', '-');
    $trapCategory = data_get($selectedItem, 'txTiptra', '-');
    $pmo = data_get($selectedItem, 'nePmoPsig', '-');
    $pipe = data_get($selectedItem, 'txPipe', '-');
    $pipeDecimal = data_get($selectedItem, 'ndPipe', '-');
    $spMessage = data_get($selectedItem, 'txMensaje', '');


    $requiredBarPercent = max(0, min(100, (float) $requiredBarPercent));
    $requiredBarWidth = number_format($requiredBarPercent, 2, '.', '');
    @endphp

    <div class="page">

        <table class="header">
            <tr>
                <td class="brand">
                    <img
                        src="{{ public_path('images/tuval-logo.jpg') }}"
                        alt="Tuval Logo" class="brand-logo">
                </td>

                <td class="title">
                    DIMENSIONAMIENTO<br>
                    TRAMPA DE VAPOR
                </td>

                <td class="meta">
                    <strong>Fecha</strong>
                    <span>{{ $fecha }}</span>

                    <strong>Código ítem</strong>
                    <span>{{ $codigo }}</span>
                </td>
            </tr>
        </table>

        <div class="columns">

            <div class="col-left">
                {{-- Cliente --}}

                <div class="section">
                    <div class="section-title">Cliente</div>

                    <table class="data-table">
                        <tr>
                            <td class="key">RUC</td>
                            <td class="value">{{ $customerRuc ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="key">Razón Social</td>
                            <td class="value">{{ $cliente['CLI_RAZON_SOCIAL'] ?? '-' }}</td>
                        </tr>
                    </table>
                </div>

                {{-- Datos del proceso --}}
                <div class="section">
                    <div class="section-title">Datos del proceso</div>

                    <table class="data-table">
                        <tr>
                            <td class="key">Aplicación</td>
                            <td class="value">{{ $process ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="key">Método de cálculo</td>
                            <td class="value">{{ $calculationMethod ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="key">Fluido</td>
                            <td class="value">{{ $fluid ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="key">Presión trabajo</td>
                            <td class="value">{{ $fmt($workingPressure, 2) }} psig</td>
                        </tr>
                        <tr>
                            <td class="key">Presión retorno</td>
                            <td class="value">{{ $fmt($returnPressure, 2) }} psig</td>
                        </tr>
                        <tr>
                            <td class="key">Presión diferencial</td>
                            <td class="value">{{ $fmt($pressureDifferential, 2) }} psig</td>
                        </tr>
                        <tr>
                            <td class="key">Volumen</td>
                            <td class="value">{{ $fmt($volume, 3) }} m³</td>
                        </tr>
                        <tr>
                            <td class="key">Densidad</td>
                            <td class="value">{{ $fmt($density, 0) }} kg/m³</td>
                        </tr>
                        <tr>
                            <td class="key">Cp</td>
                            <td class="value">{{ $fmt($cp, 2) }} kJ/kg·°C</td>
                        </tr>
                        <tr>
                            <td class="key">Temp. inicial</td>
                            <td class="value">{{ $fmt($initialTemperature, 2) }} °C</td>
                        </tr>
                        <tr>
                            <td class="key">Temp. final</td>
                            <td class="value">{{ $fmt($finalTemperature, 2) }} °C</td>
                        </tr>
                        <tr>
                            <td class="key">Tiempo</td>
                            <td class="value">{{ $fmt($heatingTime, 2) }} min</td>
                        </tr>
                        <tr>
                            <td class="key">Calor requerido</td>
                            <td class="value">{{ $fmt($requiredHeat, 2) }} kJ</td>
                        </tr>
                        <tr>
                            <td class="key">hfg</td>
                            <td class="value">{{ $fmt($hfg, 2) }} kJ/kg</td>
                        </tr>
                    </table>
                </div>

                {{-- Comparación --}}
                <div class="section">
                    <div class="section-title">Comparación de capacidad</div>

                    <div class="comparison-row">
                        <table class="comparison-table">
                            <tr>
                                <td>
                                    <div class="bar-track">
                                        <div class="bar-required" {!! 'style="width: ' . $requiredBarWidth . '%;"' !!}></div>
                                    </div>
                                </td>
                                <td class="bar-value">
                                    {{ $fmt($loadWithSafetyKg, 2) }} kg/h
                                </td>
                            </tr>
                        </table>
                        <div class="bar-label">
                            Carga requerida con FS
                            @if($requiredLoadLbh !== null)
                            <span class="muted">({{ $fmt($requiredLoadLbh, 2) }} lb/h)</span>
                            @endif
                        </div>
                    </div>

                    <div class="comparison-row">
                        <table class="comparison-table">
                            <tr>
                                <td>
                                    <div class="bar-track">
                                        <div class="bar-selected" style="width: 100%;"></div>
                                    </div>
                                </td>
                                <td class="bar-value">
                                    {{ $fmt($selectedCapacityKg, 2) }} kg/h
                                </td>
                            </tr>
                        </table>
                        <div class="bar-label">
                            Capacidad seleccionada
                            @if($selectedCapacityLbh !== null)
                            <span class="muted">({{ $fmt($selectedCapacityLbh, 2) }} lb/h)</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-right">
                {{-- Resultados --}}
                <div class="section">
                    <div class="section-title">Resultados obtenidos</div>

                    <div class="recommendation">
                        <div class="recommendation-label">Tipo recomendado</div>
                        <div class="recommendation-value">
                            {{ strtoupper($trapType ?? '-') }}
                        </div>
                    </div>
                    <div class="item-image-box">
                        @if(!empty($mainItemImage) && file_exists($mainItemImage))
                        <img src="{{ $mainItemImage }}" class="item-image" alt="Imagen del ítem {{ $itemCode }}">
                        @else
                        <div class="item-image-placeholder">
                            Imagen no disponible<br>
                            Ítem: {{ $itemCode ?? '-' }}
                        </div>
                        @endif
                    </div>

                    <table class="result-table">
                        <tr>
                            <td class="key">Consumo de Vapor</td>
                            <td class="value">{{ $fmt($estimatedLoadKg, 2) }} kg/h</td>
                        </tr>
                        <tr>
                            <td class="key">Factor seguridad</td>
                            <td class="value">{{ $fmt($safetyFactor, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="key">Capacidad Sugerida</td>
                            <td class="value highlight">{{ $fmt($loadWithSafetyKg, 2) }} kg/h</td>
                        </tr>
                        <tr>
                            <td class="key">Capacidad calculada</td>
                            <td class="value">{{ $fmt($requiredLoadLbh, 2) }} lb/h</td>
                        </tr>
                        <tr>
                            <td class="key">Capacidad sel.</td>
                            <td class="value">{{ $fmt($selectedCapacityLbh, 2) }} lb/h</td>
                        </tr>
                    </table>
                </div>

                {{-- Modelo --}}
                <div class="section">
                    <div class="section-title">Modelo recomendado</div>

                    <table class="model-table">
                        <tr>
                            <td class="key">Marca</td>
                            <td class="value">{{ $articulo['ART_MARCA'] }}</td>
                        </tr>
                        <tr>
                            <td class="key">Modelo técnico</td>
                            <td class="value">{{ $model }}</td>
                        </tr>
                        <tr>
                            <td class="key">SKU</td>
                            <td class="value">{{ $itemCode }}</td>
                        </tr>
                        <tr>
                            <td class="key">Descripción</td>
                            <td class="value">{{ $articulo['ART_DESCRIPCION'] }}</td>
                        </tr>
                        <tr>
                            <td class="key">Tipo SP</td>
                            <td class="value">{{ $trapCategory }}</td>
                        </tr>
                        <tr>
                            <td class="key">Conexión</td>
                            <td class="value">{{ $pipe }}</td>
                        </tr>
                        <!-- <tr>
                            <td class="key">Diámetro</td>
                            <td class="value">{{ $pipeDecimal }}</td>
                        </tr> -->
                        <tr>
                            <td class="key">Presión dif. tabla</td>
                            <td class="value">{{ $fmt(data_get($selectedItem, 'ndPredifTabPsig'), 2) }} psig</td>
                        </tr>
                    </table>
                </div>



                {{-- Accesorios --}}

            </div>

            <div class="clearfix"></div>
            <div class="full-width-section">
                <div class="section-title dark">
                    Ítems sugeridos / accesorios
                </div>

                <table class="accessories-table full">
                    <thead>
                        <tr>
                            <th class="col-item">Artículo</th>
                            <th class="col-descripcion">Descripción</th>
                            <th class="col-item">Cod. Zafiro</th>
                            <th class="col-item">Cod.3L </th>
                            <th class="col-item"> Marca</th>
                            <th class="col-qty">Cantidad</th>

                        </tr>
                    </thead>
                    <tbody>
                        @forelse($originItems as $originItem)
                        <tr>
                            <td class="col-item">
                                {{ data_get($originItem, 'ART_CODIGO', '-') }}
                            </td>
                            <td class="col-descripcion">
                                {{ data_get($originItem, 'ART_DESCRIPCION', '-') }}
                            </td>
                            <td class="col-item">
                                {{ data_get($originItem, 'ART_CODIGO_ZAFIRO', '-') }}
                            </td>
                            <td class="col-item">
                                <strong>{{ data_get($originItem, 'ART_CODIGO_TRES_LETRAS', '-') }}</strong>
                            </td>
                            <td class="col-item">
                                {{ data_get($originItem, 'ART_MARCA', '-') }}
                            </td>
                            <td class="col-qty">
                                {{ data_get($originItem, 'neCan', '-') }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center muted">
                                No existen ítems sugeridos asociados.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="footer">
            <span class="footer-legend">
                Leyenda: los ítems sugeridos corresponden a accesorios y componentes asociados al ítem seleccionado por el procedimiento técnico.
            </span>
            Documento generado automáticamente por TUVAL · Selector de trampas de vapor
        </div>

    </div>

</body>

</html>
