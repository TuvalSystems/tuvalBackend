<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Venta\Cotizaciones\StoreQuotationDraftPilotRequest;
use App\Services\Ventas\Cotizaciones\Borradores\QuotationDraftPilotService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly(false)]
#[IsDestructive(false)]
#[IsIdempotent]
class CreateQuotationDraftTool extends TuvalQuotationTool
{
    protected string $name = 'tuval_crear_borrador_cotizacion';

    protected string $description = 'Crea un borrador real en pruebas usando el cliente, pv_id y detalle confirmado. El usuario procede del token. Puede incluir varios candidatos por línea. Reintentar con el mismo request_id UUID y el mismo payload evita duplicados. No inventar cantidades o precios.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'request_id' => $schema->string()->description('UUID de esta operación. Conservarlo al reintentar.')->required(),
            'confirmado' => $schema->boolean()->description('true solo después de confirmar el detalle con el usuario.')->required(),
            'cliente_ruc' => $schema->string()->max(30)->description('RUC del cliente seleccionado.')->required(),
            'pv_id' => $schema->integer()->min(1)->description('ID seleccionado en el frontend; debe estar autorizado.')->required(),
            'detalle' => $schema->array()->min(1)->max(20)->items($schema->object([
                'texto' => $schema->string()->max(200)->description('Descripción del artículo, incluidas medidas y características.')->required(),
                'cantidad' => $schema->number()->nullable()->description('Cantidad positiva hasta 1000000, máximo dos decimales. null u omitida asume 1; confirmar esa suposición con el usuario.'),
            ])->withoutAdditionalProperties())->required(),
            'maxCandidatos' => $schema->integer()->min(1)->max(6)->description('Máximo de candidatos por línea; por defecto 6.'),
        ];
    }

    public function handle(Request $request, QuotationDraftPilotService $pilot): Response|ResponseFactory
    {
        return $this->respond(fn (): array => $pilot->create(
            StoreQuotationDraftPilotRequest::validateInput($request->all()),
            $request->user()
        ));
    }
}
