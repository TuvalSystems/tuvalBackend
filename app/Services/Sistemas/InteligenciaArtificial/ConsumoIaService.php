<?php

namespace App\Services\Sistemas\InteligenciaArtificial;

use App\Jobs\Sistemas\NotifyIaTokenLimit;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ConsumoIaService
{
    public function configuracion(): object
    {
        $config = DB::table('ia_configuracion')->where('id', 1)->first();
        abort_unless($config, 503, 'Falta instalar la configuración de IA.');
        return $config;
    }

    public function reservar(int $userId, string $operacion, string $modelo, int $entrada, int $salida): int
    {
        // The singleton row serializes reservations across users/workers. No network inside this transaction.
        return DB::transaction(function () use ($userId, $operacion, $modelo, $entrada, $salida) {
            $config = DB::table('ia_configuracion')->where('id', 1)->lockForUpdate()->first();
            abort_unless($config && $config->habilitado, 429, 'El uso de IA está deshabilitado en Sistemas.');
            abort_if($entrada > $config->max_entrada || $salida > $config->max_salida, 429, 'La solicitud supera el límite de tokens por llamada.');
            $perfilId = DB::table('gp_usuarios')->where('id', $userId)->value('perfil_id');
            abort_unless($perfilId, 429, 'El usuario no tiene un perfil con presupuesto de IA.');
            $puntos = (int) DB::table('ia_presupuestos_perfil')->where('perfil_id', $perfilId)->value('puntos_porcentaje');
            abort_if($puntos === 0, 429, 'El perfil no tiene presupuesto de tokens asignado.');
            $diario = intdiv((int) $config->limite_diario_global * $puntos, 10000);
            $mensual = intdiv((int) $config->limite_mensual_global * $puntos, 10000);
            $reserva = $entrada + $salida;
            $mes = now()->startOfMonth();
            $dia = now()->startOfDay();
            foreach ([[$dia, $perfilId, $diario], [$mes, $perfilId, $mensual], [$dia, null, $config->limite_diario_global], [$mes, null, $config->limite_mensual_global]] as [$desde, $perfil, $limite]) {
                $usado = DB::table('ia_consumos')->where('created_at', '>=', $desde)
                    ->when($perfil !== null, fn ($query) => $query->where('perfil_id', $perfil))->sum('tokens_contabilizados');
                if ($usado + $reserva > $limite) {
                    throw new HttpException(429, 'No hay cupo de tokens suficiente. Revise los límites y el consumo en Sistemas.');
                }
            }
            return DB::table('ia_consumos')->insertGetId([
                'user_id' => $userId, 'perfil_id' => $perfilId, 'operacion' => $operacion,
                'modelo' => $modelo, 'estado' => 'reservado', 'tokens_reservados' => $reserva,
                'tokens_contabilizados' => $reserva, 'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    public function finalizar(int $id, ?array $body, string $estado, int $duracion): void
    {
        $entrada = data_get($body, 'usage.input_tokens');
        $salida = data_get($body, 'usage.output_tokens');
        $conocido = is_int($entrada) && $entrada >= 0 && is_int($salida) && $salida >= 0;
        $datos = ['estado' => $conocido ? $estado : 'consumo_desconocido', 'duracion_ms' => $duracion,
            'response_id' => $body['id'] ?? null, 'updated_at' => now()];
        if ($conocido) {
            $datos += ['tokens_entrada' => $entrada, 'tokens_salida' => $salida, 'tokens_contabilizados' => $entrada + $salida];
        }
        // Unknown consumption keeps the full reservation; never assume a timeout was free.
        DB::table('ia_consumos')->where('id', $id)->update($datos);
        $consumo = DB::table('ia_consumos')->where('id', $id)->first();
        if ($consumo) {
            NotifyIaTokenLimit::dispatch((int) $consumo->user_id, (string) $consumo->created_at, (int) $consumo->perfil_id);
        }
    }

    public function resumen(int $userId): array
    {
        $query = DB::table('ia_consumos')->where('user_id', $userId)
            ->where('created_at', '>=', now()->startOfMonth());
        return ['inputTokens' => (int) (clone $query)->sum('tokens_entrada'),
            'outputTokens' => (int) (clone $query)->sum('tokens_salida'),
            'accountedTokens' => (int) (clone $query)->sum('tokens_contabilizados'),
            'unknownCalls' => (clone $query)->whereIn('estado', ['consumo_desconocido', 'reservado'])->count()];
    }
}
