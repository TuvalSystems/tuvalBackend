<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Recordatorio Cotizaciones No Vendidas</title>
</head>

<body
    style="font-family: Arial, sans-serif; color: #333; font-size: 14px; line-height: 1.6; background-color: #f9f9f9; padding: 20px;">
    <h2 style="color: #2c3e50;">📋 Recordatorio de Cotizaciones Pendientes</h2>

    <p>Estimado/a, le recordamos que tiene cotizaciones sin concretar. A continuación se detalla la información:</p>

    @foreach ($cotizaciones as $cotizacion)
        <div
            style="background-color: #ffffff; padding: 20px; border-radius: 8px; box-shadow: 0 0 5px #ccc; margin-bottom: 30px;">
            <h3 style="color: #2980b9;">Cotización: {{ $cotizacion->cotizacion }}</h3>

            <p>
                <strong>Cliente:</strong> {{ $cotizacion->CLI_RAZON_SOCIAL }}<br>
                <strong>RUC:</strong> {{ $cotizacion->CLI_RUC }}<br>
                <strong>Fecha de creación:</strong>
                {{ \Carbon\Carbon::parse($cotizacion->created_at)->format('d/m/Y') }}<br>
                <strong>Motivo No Venta:</strong> {{ $cotizacion->motivosNoVenta->descripcion ?? 'N/A' }}<br>
                <strong>Usuario:</strong> {{ $cotizacion->usuario->USERNAME ?? 'N/A' }} <br>
                <strong>Fecha de Recordatorio:</strong>
                {{  $cotizacion->fecha_recordatorio ? \Carbon\Carbon::parse($cotizacion->fecha_recordatorio)->format('d/m/Y') : 'N/A' }}<br>
            </p>

            <h4 style="margin-top: 20px;">🛒 Detalle de Cotización</h4>

            <table width="100%" cellpadding="8" cellspacing="0" style="border-collapse: collapse; font-size: 13px;">
                <thead>
                    <tr style="background-color: #f1f1f1;">
                        <th style="border: 1px solid #ccc;">Código</th>
                        <th style="border: 1px solid #ccc;">Descripción</th>
                        <th style="border: 1px solid #ccc;">Código 3 Letras</th>
                        <th style="border: 1px solid #ccc;">Stock</th>
                        <th style="border: 1px solid #ccc;">Cantidad</th>
                        <th style="border: 1px solid #ccc;">Descuento</th>
                        <!-- <th style="border: 1px solid #ccc;">Valor Original</th> -->
                        <th style="border: 1px solid #ccc;">Valor</th>
                        <th style="border: 1px solid #ccc;">IVA</th>
                        <th style="border: 1px solid #ccc;">Descuento</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($cotizacion->detallecotizacion as $detalle)
                        <tr>
                            <td style="border: 1px solid #ccc;">{{ $detalle->ART_CODIGO }}</td>
                            <td style="border: 1px solid #ccc;">{{ $detalle->ART_DESCRIPCION }}</td>
                            <td style="border: 1px solid #ccc;">{{ $detalle->ART_CODIGO_TRES_LETRAS }}</td>
                            <td style="border: 1px solid #ccc;text-align: right;">{{ $detalle->ART_STOCK }}</td>
                            <td style="border: 1px solid #ccc;text-align: right;">{{ $detalle->ART_CANTIDAD }}</td>
                            <td style="border: 1px solid #ccc;text-align: right;">{{ $detalle->descuento }}%</td>
                            <!-- <td style="border: 1px solid #ccc;">${{ number_format($detalle->ART_VALOR_ORIGINAL, 2) }}</td> -->
                            <td style="border: 1px solid #ccc;text-align: right;">${{ number_format($detalle->ART_VALOR, 2) }}
                            </td>
                            <td style="border: 1px solid #ccc;text-align: right;">${{ number_format($detalle->ART_IVA, 2) }}
                            </td>
                            <td style="border: 1px solid #ccc;text-align: right;">
                                ${{  number_format($detalle->ART_DESCUENTO, 2) }}</td>

                        </tr>
                    @endforeach

                </tbody>
            </table>
            <table style="width: 300px; margin-left: auto; font-size: 13px; margin-top: 10px; border-collapse: collapse;">
                <tr>
                    <td style="padding: 4px 8px; text-align: right;"><strong>Subtotal:</strong></td>
                    <td style="padding: 4px 8px; text-align: right;">${{ number_format($cotizacion->subtotal, 2) }}</td>
                </tr>
                <tr>
                    <td style="padding: 4px 8px; text-align: right;"><strong>Descuento:</strong></td>
                    <td style="padding: 4px 8px; text-align: right;">${{ number_format($cotizacion->descuento, 2) }}</td>
                </tr>
                <tr>
                    <td style="padding: 4px 8px; text-align: right;"><strong>Subtotal - Descuento:</strong></td>
                    <td style="padding: 4px 8px; text-align: right;">
                        ${{ number_format($cotizacion->subtotalmenosdescuento, 2) }}</td>
                </tr>
                <tr>
                    <td style="padding: 4px 8px; text-align: right;"><strong>IVA:</strong></td>
                    <td style="padding: 4px 8px; text-align: right;">${{ number_format($cotizacion->iva, 2) }}</td>
                </tr>
                <tr style="background-color: #f1f1f1;">
                    <td style="padding: 6px 8px; text-align: right;"><strong>Total Cotización:</strong></td>
                    <td style="padding: 6px 8px; text-align: right;">
                        <strong>${{ number_format($cotizacion->total, 2) }}</strong>
                    </td>
                </tr>
            </table>
        </div>
    @endforeach
    <p style="margin-top: 40px;">Gracias por confiar en <strong>Sistemas</strong>.</p>
</body>

</html>
