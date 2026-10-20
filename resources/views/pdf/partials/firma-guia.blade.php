@if(!empty($firmaGuia) || ($mostrarSinFirma ?? false))
<div style="page-break-inside: avoid; margin-top: 18px; margin-bottom: 18px; width: 260px;">
    <p style="font-size: 11px; margin-bottom: 4px;"><strong>Recibí conforme</strong></p>
    @if(!empty($firmaGuia))
        <img src="{{ $firmaGuia['imagen'] }}" alt="Firma de recepción"
            style="width: 230px; height: 92px; display: block;">
    @else
        <div style="height: 65px;"></div>
    @endif
    <div style="border-top: 1px solid #333; padding-top: 5px; font-size: 10px;">
        @if(!empty($firmaGuia['firmante']))
            <p style="margin: 2px 0;">{{ $firmaGuia['firmante'] }}</p>
        @else
            <p style="margin: 2px 0;">Firma de recepción</p>
        @endif
        @if(!empty($firmaGuia['usuario']))
            <p style="margin: 2px 0; font-size: 9px;">Registrada por: {{ $firmaGuia['usuario'] }}</p>
        @endif
        @if(!empty($firmaGuia['fecha']))
            <p style="margin: 2px 0; font-size: 9px;">Fecha: {{ $firmaGuia['fecha'] }} (Ecuador)</p>
        @endif
    </div>
</div>
@endif
