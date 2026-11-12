<?php

namespace App\Mcp\Tools;

use App\Services\Ventas\Cotizaciones\Borradores\QuotationDraftPilotService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class QuotationContextTool extends TuvalQuotationTool
{
    protected string $name = 'tuval_contexto_cotizacion';

    protected string $description = 'Comprueba el usuario autenticado y el punto de venta seleccionado en el frontend. Usar antes de crear un borrador; no guarda datos.';

    public function schema(JsonSchema $schema): array
    {
        return ['pv_id' => $schema->integer()->min(1)
            ->description('ID del punto de venta seleccionado en el frontend; no inferirlo.')->required()];
    }

    public function handle(Request $request, QuotationDraftPilotService $pilot): Response|ResponseFactory
    {
        return $this->respond(function () use ($request, $pilot): array {
            $data = Validator::make(['input' => $request->all()], [
                'input' => ['required', 'array:pv_id'],
                'input.pv_id' => ['required', 'integer', 'min:1'],
            ])->validate()['input'];

            return $pilot->context((int) $data['pv_id'], $request->user());
        });
    }
}
