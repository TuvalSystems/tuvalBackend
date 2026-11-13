<?php

namespace App\Services\Sistemas\InteligenciaArtificial;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ProveedorIaService
{
    public function __construct(private readonly ConsumoIaService $consumo) {}

    public function responder(array $payload, int $userId, string $operacion): array
    {
        $key = config('services.openai.key');
        abort_unless(is_string($key) && trim($key) !== '', 503, 'El servicio de IA no está configurado.');
        $config = $this->consumo->configuracion();
        abort_unless($config->habilitado, 429, 'El uso de IA está deshabilitado en Sistemas.');
        $payload['store'] = false;
        $payload['max_output_tokens'] = min($payload['max_output_tokens'] ?? 6000, $config->max_salida);
        $http = fn () => Http::withToken($key)->acceptJson()->asJson()->withoutRedirecting()
            ->connectTimeout(10)->timeout(max(1, min(60, (int) config('services.openai.timeout', 60))));
        // Count the complete input (including images/schema/tools) before reserving the output ceiling.
        // This endpoint counts input; it does not run a second extraction/generation.
        try {
            $count = $http()->post('https://api.openai.com/v1/responses/input_tokens',
                Arr::only($payload, ['model', 'input', 'instructions', 'tools', 'text', 'tool_choice', 'parallel_tool_calls']));
        } catch (ConnectionException) {
            throw new HttpException(504, 'No se pudo comprobar el cupo de entrada. No se inició la generación.');
        }
        $entrada = $count->json('input_tokens');
        abort_unless($count->successful() && is_int($entrada) && $entrada >= 0, 503,
            'No se pudo contar la entrada. Por seguridad no se inició la generación.');
        $id = $this->consumo->reservar($userId, $operacion, $payload['model'], $entrada, $payload['max_output_tokens']);
        $inicio = microtime(true);
        try {
            $response = $http()->post('https://api.openai.com/v1/responses', $payload);
        } catch (ConnectionException) {
            $this->consumo->finalizar($id, null, 'error_conexion', (int) ((microtime(true) - $inicio) * 1000));
            throw new HttpException(504, 'La respuesta de IA es incierta. La reserva de tokens se conserva.');
        }
        $body = $response->json();
        $this->consumo->finalizar($id, is_array($body) ? $body : null,
            $response->successful() ? ($body['status'] ?? 'respuesta_invalida') : 'error_proveedor', (int) ((microtime(true) - $inicio) * 1000));
        abort_unless($response->successful(), in_array($response->status(), [401, 403, 429]) ? 503 : 502,
            'OpenAI no está disponible. Revise acceso, cuota o saldo del proyecto.');
        abort_unless(is_array($body) && ($body['status'] ?? null) === 'completed', 502, 'La respuesta de IA quedó incompleta; no se aplicaron cambios.');
        return $body;
    }
}
