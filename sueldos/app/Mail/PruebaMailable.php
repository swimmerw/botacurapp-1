<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/** Correo de prueba para revisar que la cola y el servidor de correo envían. */
class PruebaMailable extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $hora;

    public function __construct($hora)
    {
        $this->hora = $hora;
    }

    public function build()
    {
        return $this->subject("Prueba BotacurApp por cola {$this->hora}")
            ->html('Prueba enviada por la cola de BotacurApp. Si te llega, la cola envía bien.');
    }
}
