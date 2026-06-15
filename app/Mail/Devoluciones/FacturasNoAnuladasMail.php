<?php

namespace App\Mail\Devoluciones;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class FacturasNoAnuladasMail extends Mailable
{
    use Queueable, SerializesModels;

    public Collection $collections;

    public function __construct(Collection $collection)
    {
        $this->collections = $collection;
    }

    public function build()
    {
       return $this->subject('Facturas no Anuladas')
       ->view('emails.devoluciones.facturas_no_anuladas')
       ->with([
        'facturas' => $this->collections
       ]);
    }
}
