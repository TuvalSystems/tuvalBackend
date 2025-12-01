<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Cotización Reservada</title>
</head>
<body style="font-family: Arial, sans-serif; color:#333; font-size:14px; line-height:1.6; background:#f9f9f9; padding:20px;">

  <h2 style="color:#2c3e50; margin:0 0 12px;">🗂️ Cotización Reservada</h2>

  <div style="background:#fff; padding:20px; border-radius:8px; box-shadow:0 0 5px #ccc; margin-bottom:30px;">
    <h3 style="color:#2980b9; margin:0 0 12px;">
      Cotización: {{ $cotizacion['cotizacion'] ?? 'N/D' }}
    </h3>

    <p style="margin:0 0 4px;"><strong>Cliente:</strong> {{ $cotizacion['CLI_RAZON_SOCIAL'] ?? 'N/D' }}</p>
    <p style="margin:0 0 4px;"><strong>RUC:</strong> {{ $cotizacion['CLI_RUC'] ?? 'N/D' }}</p>
    <p style="margin:0 0 4px;">
      <strong>Fecha cotización:</strong>
      {{ !empty($cotizacion['created_at']) ? \Carbon\Carbon::parse($cotizacion['created_at'])->format('d/m/Y') : 'N/A' }}
    </p>
    <p style="margin:0 0 4px;"><strong>Usuario:</strong> {{ $actor->USERNAME ?? $actor->USERID ?? 'N/A' }}</p>
    <p style="margin:0 0 12px;">
      <strong>Fecha solicitada de reserva:</strong>
      {{ !empty($cotizacion['reserva']['fe_solicitud_reserva']) ? \Carbon\Carbon::parse($cotizacion['reserva']['fe_solicitud_reserva'])->format('d/m/Y') : 'N/A' }}
    </p>

    <h4 style="margin:20px 0 8px;">🛒 Detalle de Cotización</h4>

    <table width="100%" cellpadding="8" cellspacing="0" style="border-collapse:collapse; font-size:13px;">
      <thead>
        <tr style="background:#f1f1f1;">
          <th style="border:1px solid #ccc;" align="left">Código</th>
          <th style="border:1px solid #ccc;" align="left">Descripción</th>
          <th style="border:1px solid #ccc;" align="left">CTL</th>
          <th style="border:1px solid #ccc;" align="left">U.Medida</th>
          <th style="border:1px solid #ccc;" align="right">Peso</th>
          <th style="border:1px solid #ccc;" align="left">Bodega</th>
          <!-- <th style="border:1px solid #ccc;" align="left">Tipo Compra</th> -->
          <th style="border:1px solid #ccc;" align="right">Cantidad</th>
        </tr>
      </thead>
      <tbody>
      @forelse ($detalles as $detalle)
        <tr>
          <td style="border:1px solid #ccc;">{{ $detalle['ART_CODIGO'] ?? '' }}</td>
          <td style="border:1px solid #ccc;">{{ $detalle['ART_DESCRIPCION'] ?? '' }}</td>
          <td style="border:1px solid #ccc;">{{ $detalle['ART_CODIGO_TRES_LETRAS'] ?? '' }}</td>
          <td style="border:1px solid #ccc;">{{ $detalle['ART_PLAN_UMEDIDA'] ?? '' }}</td>
          <td style="border:1px solid #ccc; text-align:right;">
            {{ isset($detalle['ART_PESO']) ? number_format((float)$detalle['ART_PESO'], 2) : '' }}
          </td>
          <td style="border:1px solid #ccc;">{{ $detalle['STO_ART_BODEGA'] ?? '' }}</td>
          <!-- <td style="border:1px solid #ccc;">{{ $detalle['ART_TIPO_COMPRA'] ?? '' }}</td> -->
          <td style="border:1px solid #ccc; text-align:right;">
            {{ isset($detalle['ART_CANTIDAD']) ? number_format((float)$detalle['ART_CANTIDAD'], 2) : '' }}
          </td>
        </tr>
      @empty
        <tr><td colspan="8" style="border:1px solid #ccc; text-align:center;">Sin items seleccionados.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>

  <p style="margin-top:40px;">Gracias por confiar en <strong>Sistemas</strong>.</p>
</body>
</html>
