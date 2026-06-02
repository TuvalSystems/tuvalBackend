<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Cotización Borrador - Trampas de Vapor</title>
</head>

<body style="margin: 0; padding: 0;  font-family: Arial, Helvetica, sans-serif; color: #333333; font-size: 14px; line-height: 1.5;">

    @php
        $fechaCreacion = $cotizacion->created_at
            ? \Carbon\Carbon::parse($cotizacion->created_at)->format('d/m/Y')
            : 'N/A';
    @endphp

    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f6f8; padding: 24px 0;">
        <tr>
            <td align="center">

                <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 980px; background-color: #ffffff; border-radius: 10px; overflow: hidden; border: 1px solid #e1e5ea;">

                    {{-- Header --}}
                    <tr>
                        <td style="background-color: #1f4e79; padding: 22px 28px; color: #ffffff;">
                            <h2 style="margin: 0; font-size: 22px; font-weight: 700;">
                                Cotización Borrador
                            </h2>

                            <p style="margin: 6px 0 0 0; font-size: 14px; color: #dceaf7;">
                                Generada desde App Móvil - Módulo de Trampas de Vapor
                            </p>
                        </td>
                    </tr>

                    {{-- Aviso --}}
                    <tr>
                        <td style="padding: 22px 28px 10px 28px;">
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #fff8e5; border: 1px solid #f2d27c; border-radius: 8px;">
                                <tr>
                                    <td style="padding: 14px 16px;">
                                        <strong style="color: #8a6100;">Importante:</strong>
                                        <span style="color: #5f4b16;">
                                            Esta cotización fue generada automáticamente como borrador desde la App Móvil,
                                            a partir del proceso de dimensionamiento de trampas de vapor.
                                            Debe ser revisada antes de ser enviada al cliente o convertida en una cotización final.
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Información principal --}}
                    <tr>
                        <td style="padding: 12px 28px 20px 28px;">

                            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse;">
                                <tr>
                                    <td style="vertical-align: top; width: 60%; padding-right: 18px;">
                                        <h3 style="margin: 0 0 12px 0; color: #1f4e79; font-size: 18px;">
                                            Cotización: {{ $cotizacion->cotizacion }}
                                        </h3>

                                        <p style="margin: 0; color: #333333;">
                                            <strong>Cliente:</strong> {{ $cotizacion->CLI_RAZON_SOCIAL }}<br>
                                            <strong>RUC:</strong> {{ $cotizacion->CLI_RUC }}<br>
                                            <strong>Fecha de creación:</strong> {{ $fechaCreacion }}<br>
                                            <strong>Usuario:</strong> {{ $cotizacion->usuario->USERNAME ?? 'N/A' }}
                                        </p>
                                    </td>

                                    <td style="vertical-align: top; width: 40%; text-align: right;">
                                        <span style="display: inline-block; background-color: #eaf3ff; color: #1f4e79; border: 1px solid #b7d7f5; border-radius: 20px; padding: 6px 12px; font-size: 12px; font-weight: 700;">
                                            BORRADOR
                                        </span>

                                        <br><br>

                                        <span style="display: inline-block; background-color: #eef8f1; color: #2f7d44; border: 1px solid #b8dfc2; border-radius: 20px; padding: 6px 12px; font-size: 12px; font-weight: 700;">
                                            APP MÓVIL
                                        </span>

                                        <br><br>

                                        <span style="display: inline-block; background-color: #f1f3f5; color: #495057; border: 1px solid #d7dce1; border-radius: 20px; padding: 6px 12px; font-size: 12px; font-weight: 700;">
                                            TRAMPAS DE VAPOR
                                        </span>
                                    </td>
                                </tr>
                            </table>

                        </td>
                    </tr>

                    {{-- Detalle --}}
                    <tr>
                        <td style="padding: 0 28px 24px 28px;">
                            <h4 style="margin: 10px 0 12px 0; color: #2c3e50; font-size: 16px;">
                                Detalle de la cotización
                            </h4>

                            <table width="100%" cellpadding="7" cellspacing="0" style="border-collapse: collapse; font-size: 12px;">
                                <thead>
                                    <tr style="background-color: #eef2f6;">
                                        <th style="border: 1px solid #d5dbe1; text-align: left;">Código</th>
                                        <th style="border: 1px solid #d5dbe1; text-align: left;">Descripción</th>
                                        <th style="border: 1px solid #d5dbe1; text-align: left;">Código 3 Letras</th>
                                        <th style="border: 1px solid #d5dbe1; text-align: right;">Stock</th>
                                        <th style="border: 1px solid #d5dbe1; text-align: right;">Cantidad</th>
                                        <th style="border: 1px solid #d5dbe1; text-align: right;">% Desc.</th>
                                        <th style="border: 1px solid #d5dbe1; text-align: right;">Valor</th>
                                        <th style="border: 1px solid #d5dbe1; text-align: right;">IVA</th>
                                        <th style="border: 1px solid #d5dbe1; text-align: right;">Valor Desc.</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    @forelse ($cotizacion->detallecotizacion as $detalle)
                                        <tr>
                                            <td style="border: 1px solid #d5dbe1;">
                                                {{ $detalle->ART_CODIGO }}
                                            </td>

                                            <td style="border: 1px solid #d5dbe1;">
                                                {{ $detalle->ART_DESCRIPCION }}
                                            </td>

                                            <td style="border: 1px solid #d5dbe1;">
                                                {{ $detalle->ART_CODIGO_TRES_LETRAS }}
                                            </td>

                                            <td style="border: 1px solid #d5dbe1; text-align: right;">
                                                {{ number_format((float) $detalle->ART_STOCK, 2) }}
                                            </td>

                                            <td style="border: 1px solid #d5dbe1; text-align: right;">
                                                {{ number_format((float) $detalle->ART_CANTIDAD, 2) }}
                                            </td>

                                            <td style="border: 1px solid #d5dbe1; text-align: right;">
                                                {{ number_format((float) $detalle->descuento, 2) }}%
                                            </td>

                                            <td style="border: 1px solid #d5dbe1; text-align: right;">
                                                ${{ number_format((float) $detalle->ART_VALOR, 2) }}
                                            </td>

                                            <td style="border: 1px solid #d5dbe1; text-align: right;">
                                                ${{ number_format((float) $detalle->ART_IVA, 2) }}
                                            </td>

                                            <td style="border: 1px solid #d5dbe1; text-align: right;">
                                                ${{ number_format((float) $detalle->ART_DESCUENTO, 2) }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="9" style="border: 1px solid #d5dbe1; text-align: center; padding: 14px; color: #777777;">
                                                No existen ítems registrados en el detalle de la cotización.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </td>
                    </tr>

                    {{-- Totales --}}
                    <tr>
                        <td style="padding: 0 28px 28px 28px;">

                            <table cellpadding="0" cellspacing="0" style="width: 340px; margin-left: auto; font-size: 13px; border-collapse: collapse;">
                                <tr>
                                    <td style="padding: 7px 10px; text-align: right; border-bottom: 1px solid #e5e9ed;">
                                        <strong>Subtotal:</strong>
                                    </td>
                                    <td style="padding: 7px 10px; text-align: right; border-bottom: 1px solid #e5e9ed;">
                                        ${{ number_format((float) $cotizacion->subtotal, 2) }}
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding: 7px 10px; text-align: right; border-bottom: 1px solid #e5e9ed;">
                                        <strong>Descuento:</strong>
                                    </td>
                                    <td style="padding: 7px 10px; text-align: right; border-bottom: 1px solid #e5e9ed;">
                                        ${{ number_format((float) $cotizacion->descuento, 2) }}
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding: 7px 10px; text-align: right; border-bottom: 1px solid #e5e9ed;">
                                        <strong>Subtotal - Descuento:</strong>
                                    </td>
                                    <td style="padding: 7px 10px; text-align: right; border-bottom: 1px solid #e5e9ed;">
                                        ${{ number_format((float) $cotizacion->subtotalmenosdescuento, 2) }}
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding: 7px 10px; text-align: right; border-bottom: 1px solid #e5e9ed;">
                                        <strong>IVA:</strong>
                                    </td>
                                    <td style="padding: 7px 10px; text-align: right; border-bottom: 1px solid #e5e9ed;">
                                        ${{ number_format((float) $cotizacion->iva, 2) }}
                                    </td>
                                </tr>

                                <tr style="background-color: #1f4e79; color: #ffffff;">
                                    <td style="padding: 10px; text-align: right;">
                                        <strong>Total Cotización:</strong>
                                    </td>
                                    <td style="padding: 10px; text-align: right;">
                                        <strong>${{ number_format((float) $cotizacion->total, 2) }}</strong>
                                    </td>
                                </tr>
                            </table>

                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="background-color: #f1f3f5; padding: 16px 28px; color: #6c757d; font-size: 12px; border-top: 1px solid #e1e5ea;">
                            Este correo fue generado automáticamente por el sistema desde la App Móvil.
                            La cotización se encuentra en estado borrador y debe ser validada internamente antes de continuar con el proceso comercial.
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>

</html>
