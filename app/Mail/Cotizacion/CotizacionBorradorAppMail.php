<?php

namespace App\Mail\Cotizacion;

use App\Models\GpCotizacion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CotizacionBorradorAppMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;


    public GpCotizacion $quote;

    public function __construct(GpCotizacion $quote)
    {
        $this->quote = $quote;
        $this->onConnection('redis');
        $this->onQueue('emails');
    }

    public function build()
    {
        return $this->subject('Cotización Generada desde App Móvil')
            ->view('emails.cotizacion.cotizacion_borrador')
            ->with(([
                'cotizacion'    => $this->quote,
            ]));
    }
}
