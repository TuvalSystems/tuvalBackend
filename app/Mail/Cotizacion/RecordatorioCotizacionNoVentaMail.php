<?php

namespace App\Mail\Cotizacion;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RecordatorioCotizacionNoVentaMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $cotizaciones;
    public $PtoVentaDescripcion;
    public function __construct($cotizaciones, $PtoVentaDescripcion)
    {
        $this->cotizaciones = $cotizaciones;
        $this->PtoVentaDescripcion = $PtoVentaDescripcion;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Recordatorio de Cotizaciones No Vendidas'.' - ' .$this->PtoVentaDescripcion)
            ->view('emails.cotizacion.recordatorio_cotizacion_no_venta')
            ->with([
                'cotizaciones' => $this->cotizaciones,
            ]);
    }
}
