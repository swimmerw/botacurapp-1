<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class GiftCardMailable extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $gc;
    public $pdfData;


    public function __construct($gc, $pdfData)
    {
        $this->gc = $gc;
        // La cola guarda el trabajo como JSON: el PDF binario no cabe tal cual (daba error 500 al Enviar).
        $this->pdfData = base64_encode($pdfData);
    }


    public function build()
    {
        return $this->view('emails.giftcard')
                    ->subject('Botacura Entrega de Gift Card')
                    ->attachData(base64_decode($this->pdfData), 'GiftCard-'.$this->gc->para.'-'.$this->gc->id.'.pdf');
    }
}
