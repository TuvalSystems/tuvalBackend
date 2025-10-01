<?php

namespace App\Mail\Reservas;

use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReservaMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;
    public int|array $backoff = 10;
    public $cotizacion;
    public $detalles;
    public $actor;
    public $asunto;
    public function __construct($cotizacion, array $detalles, $actor, $asunto)
    {
        $this->cotizacion = $cotizacion;
        $this->detalles = $detalles;
        $this->actor = $actor;
        $this->asunto = $asunto;

        // ✅ Configura cola/conexión/reintentos sin declarar propiedades
        $this->onQueue('emails');           // cola
        $this->onConnection('database');    // conexión (o 'redis')
    }


    public function retryUntil(): DateTimeInterface
    {
        return now()->addMinutes(2); // por ejemplo
    }


    public function build()
    {
        $ptoDesc = $this->cotizacion->PTO_VTA_DESCRIPCION ?? '';
        return $this->subject($this->asunto . ($ptoDesc ? ' - ' . $ptoDesc : ''))
            ->view('emails.reservas.reserva_demanda_mail')
            ->with([
                'cotizacion' => $this->cotizacion,
                'actor' => $this->actor,
                'detalles' => $this->detalles
            ]);
    }
}
