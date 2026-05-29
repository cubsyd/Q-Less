<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DeliveryCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public $deliveryCode;

    public function __construct($deliveryCode)
    {
        $this->deliveryCode = $deliveryCode;
    }

    public function build()
    {
        return $this->subject('Codigo de entrega Q-LESS')
            ->view('emails.delivery-code');
    }
}