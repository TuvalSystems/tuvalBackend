<?php

namespace App\Jobs\Sistemas;

use App\Models\Admin\Usuarios\GpUsuario;
use App\Notifications\User\UserNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class NotifyIaTokenLimit implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $userId, public string $fecha, public ?int $perfilId = null)
    {
        $this->onQueue('notifications');
        $this->afterCommit();
    }

    public function handle(): void
    {
        DB::transaction(function () {
            $config = DB::table('ia_configuracion')->where('id', 1)->lockForUpdate()->first();
            if (!$config) return;
            $dia = substr($this->fecha, 0, 10);
            $mes = substr($dia, 0, 7).'-01';
            $perfilId = $this->perfilId ?? DB::table('gp_usuarios')->where('id', $this->userId)->value('perfil_id');
            $puntos = (int) DB::table('ia_presupuestos_perfil')->where('perfil_id', $perfilId)->value('puntos_porcentaje');
            $cuotas = [
                ['diario_perfil', (int) $perfilId, $dia, intdiv((int) $config->limite_diario_global * $puntos, 10000), 'diario del perfil'],
                ['mensual_perfil', (int) $perfilId, $mes, intdiv((int) $config->limite_mensual_global * $puntos, 10000), 'mensual del perfil'],
                ['diario_global', 0, $dia, $config->limite_diario_global, 'diario global'],
                ['mensual_global', 0, $mes, $config->limite_mensual_global, 'mensual global'],
            ];
            foreach ($cuotas as [$tipo, $perfil, $desde, $limite, $nombre]) {
                if ($limite <= 0) continue;
                $hasta = str_starts_with($tipo, 'diario_')
                    ? date('Y-m-d', strtotime($desde.' +1 day'))
                    : date('Y-m-d', strtotime($desde.' +1 month'));
                $usado = (int) DB::table('ia_consumos')->where('created_at', '>=', $desde)
                    ->where('created_at', '<', $hasta)
                    ->when($perfil !== 0, fn ($q) => $q->where('perfil_id', $perfil))
                    ->sum('tokens_contabilizados');
                if ($usado * 100 < $limite * 80) continue;
                $clave = ['tipo' => $tipo, 'user_id' => 0, 'perfil_id' => $perfil, 'periodo' => $desde];
                if (DB::table('ia_alertas_tokens')->where($clave)->exists()) continue;
                $destinatarios = GpUsuario::whereIn('perfil_id', [1, 2])->where('status', 1)->get();
                if ($destinatarios->isEmpty()) continue;
                $identidad = $perfil === 0 ? 'global' : 'del perfil '.
                    (DB::table('perfiles')->where('id', $perfil)->value('name') ?? $perfil).' (ID '.$perfil.')';
                $porcentaje = round($usado * 100 / $limite, 1);
                $mensaje = "El consumo de tokens {$identidad} alcanzó el {$porcentaje}% del límite {$nombre}: "
                    .number_format($usado, 0, ',', '.').' de '.number_format($limite, 0, ',', '.')
                    ." tokens. Período desde {$desde}. Está alcanzando el límite. "
                    .'El consumo contabilizado incluye reservas. Revise Sistemas / Inteligencia artificial.';
                Notification::send($destinatarios, new UserNotification('Límite de tokens próximo a alcanzarse', $mensaje));
                DB::table('ia_alertas_tokens')->insert($clave + ['created_at' => now()]);
            }
        });
    }
}
