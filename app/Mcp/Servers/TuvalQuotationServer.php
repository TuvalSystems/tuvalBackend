<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\CreateQuotationDraftTool;
use App\Mcp\Tools\QuotationContextTool;
use Laravel\Mcp\Server;

class TuvalQuotationServer extends Server
{
    protected string $name = 'TUVAL Cotizaciones - Piloto';

    protected string $version = '1.0.0';

    protected string $instructions = <<<'TEXT'
        Herramientas de cotizaciones TUVAL para pruebas. La identidad procede del
        token autenticado, nunca de argumentos del modelo. Usa el pv_id seleccionado
        en el frontend y comprueba el contexto antes de crear un borrador.
        Extrae de la imagen solo descripciones y cantidades legibles. No inventes
        cantidades, códigos GP, precios, clientes ni puntos de venta. Ante dudas,
        solicita una aclaración. Muestra el detalle y pide confirmación antes de
        guardar. confirmado=true declara esa confirmación; no sustituye los permisos.
        El resolver actual puede incluir varios candidatos por línea; no garantiza
        una coincidencia única. Comunica ese comportamiento antes de confirmar.
        Genera un request_id UUID por operación y reutilízalo con los mismos argumentos
        al reintentar. No cambies el UUID para reintentar una respuesta incierta.
        Devuelve el número real, incluidos, omitidos y advertencias recibidos.
        Un resultado con id=null significa que no se creó un borrador.
    TEXT;

    protected array $tools = [
        QuotationContextTool::class,
        CreateQuotationDraftTool::class,
    ];
}
