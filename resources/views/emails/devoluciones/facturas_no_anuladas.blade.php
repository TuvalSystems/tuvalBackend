<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Facturas no Anuladas</title>
</head>

<body style="margin: 10px; padding: 2px; font-family: Arial, Helvetica, sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="padding:10px 0">
        <tr>
            <td style="padding: 0; margin: 0;">
                <table width="100%" cellpadding="0" cellspacing="0"
                    style="max-width: none;
                background-color: #ffffff;
                border-collapse: collapse;
                border: 1px solid #d9e2ec;">
                    {{-- Encabezado --}}
                    <tr>
                        <td style="background-color: #1f4e79; padding: 18px 24px;">
                            <h2 style="margin:0; color: #ffffff; font-size: 20px; font-weight: 700;">
                                Facturas no Anuladas
                            </h2>
                            <p style="margin: 6px 0 0 0; color: #d9e2ec; font-size: 13px;">
                                Documentos que no pudieron ser anulados automáticamente
                            </p>
                        </td>
                    </tr>
                    {{-- Resumen --}}
                    <tr>
                        <td style="padding: 18px 24px 8px 24px;">
                            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse;">
                                <tr>
                                    <td style="background-color: #fff4e5; border-left: 4px solid #f59e0b; padding: 12px 14px;">
                                        <p style="margin: 0; font-size: 14px; color: #7c4a03;">
                                            Se encontraron
                                            <strong>{{ $facturas->count() }}</strong>
                                            factura(s) que superan los días permitidos para anulación o no cumplen con las condiciones requeridas.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- tabla -->
                    <tr>
                        <td style="padding: 14px 24px 24px 24px;">
                            <table width="100%" cellpadding="7" cellspacing="0" style="border-collapse: collapse; font-size: 12px;">
                                <thead>
                                    <tr style="background-color: #eef2f6;">
                                        <th style="border: 1px solid #d5dbe1; text-align: left; text-transform: uppercase; color: #334e68;">Pedido</th>
                                        <th style="border: 1px solid #d5dbe1; text-align: left; text-transform: uppercase; color: #334e68;">RUC</th>
                                        <th style="border: 1px solid #d5dbe1; text-align: left; text-transform: uppercase; color: #334e68;">Razón Social</th>
                                        <th style="border: 1px solid #d5dbe1; text-align: left; text-transform: uppercase; color: #334e68;">Factura</th>
                                        <th style="border: 1px solid #d5dbe1; text-align: left; text-transform: uppercase; color: #334e68;">Clave Acceso</th>
                                        <th style="border: 1px solid #d5dbe1; text-align: left; text-transform: uppercase; color: #334e68;">Fecha Factura</th>
                                        <th style="border: 1px solid #d5dbe1; text-align: left; text-transform: uppercase; color: #334e68;">Devolución</th>
                                        <th style="border: 1px solid #d5dbe1; text-align: center; text-transform: uppercase; color: #334e68;">Días Máx.</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    @forelse ($facturas as $fac)
                                    <tr>
                                        <td style="border: 1px solid #d5dbe1; color: #243b53; white-space: nowrap;">
                                            {{ $fac['pedido'] ?? '' }}
                                        </td>
                                        <td style="border: 1px solid #d5dbe1; color: #243b53; white-space: nowrap;">
                                            {{ $fac['ruc'] ?? '' }}
                                        </td>

                                        <td style="border: 1px solid #d5dbe1; color: #243b53;">
                                            {{ $fac['razon_social'] ?? '' }}
                                        </td>


                                        <td style="border: 1px solid #d5dbe1; color: #243b53; white-space: nowrap;">
                                            <strong>{{ $fac['factura'] ?? '' }}</strong>
                                        </td>

                                        <td style="border: 1px solid #d5dbe1; color: #486581; word-break: break-all;">
                                            {{ $fac['clave_acceso'] ?? '' }}
                                        </td>

                                        <td style="border: 1px solid #d5dbe1; color: #243b53; white-space: nowrap;">
                                            {{ $fac['fecha_factura'] ?? '' }}
                                        </td>

                                        <td style="border: 1px solid #d5dbe1; color: #243b53; white-space: nowrap;">
                                            {{ $fac['devolucion'] ?? '' }}
                                        </td>

                                        <td style="border: 1px solid #d5dbe1; color: #9a3412; text-align: center; font-weight: bold;">
                                            {{ $fac['dias_max_anulacion'] ?? '' }}
                                        </td>

                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="8" style="border: 1px solid #d5dbe1; padding: 14px; text-align: center; color: #627d98;">
                                            No existen facturas pendientes de notificación.
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>

                        </td>
                    </tr>

                    {{-- Pie --}}
                    <tr>
                        <td style="background-color: #f8fafc; border-top: 1px solid #d9e2ec; padding: 12px 24px;">
                            <p style="margin: 0; font-size: 11px; color: #627d98;">
                                Correo generado automáticamente por el sistema. No responder a este mensaje.
                            </p>
                            <p style="margin: 4px 0 0 0; font-size: 11px; color: #829ab1;">
                                Fecha de generación: {{ now()->format('d/m/Y H:i') }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>
