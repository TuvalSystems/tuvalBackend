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

    public function __construct(public int $userId, public string $fecha)
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
            $cuotas = [
                ['diario_usuario', $this->userId, $dia, $config->limite_diario_usuario, 'diario'],
                ['mensual_usuario', $this->userId, $mes, $config->limite_mensual_usuario, 'mensual'],
                ['mensual_global', 0, $mes, $config->limite_mensual_global, 'mensual global'],
            ];
            foreach ($cuotas as [$tipo, $usuario, $desde, $limite, $nombre]) {
                if ($limite <= 0) continue;
                $hasta = $tipo === 'diario_usuario'
                    ? date('Y-m-d', strtotime($desde.' +1 day'))
                    : date('Y-m-d', strtotime($desde.' +1 month'));
                $usado = (int) DB::table('ia_consumos')->where('created_at', '>=', $desde)
                    ->where('created_at', '<', $hasta)
                    ->when($usuario !== 0, fn ($q) => $q->where('user_id', $usuario))
                    ->sum('tokens_contabilizados');
                if ($usado * 100 < $limite * 80) continue;
                $clave = ['tipo' => $tipo, 'user_id' => $usuario, 'periodo' => $desde];
                if (DB::table('ia_alertas_tokens')->where($clave)->exists()) continue;
                $destinatarios = GpUsuario::whereIn('perfil_id', [1, 2])->where('status', 1)->get();
                if ($destinatarios->isEmpty()) continue;
                $identidad = $usuario === 0 ? 'global' : 'del usuario '.
                    (GpUsuario::find($usuario)?->USERNAME ?? $usuario).' (ID '.$usuario.')';
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
