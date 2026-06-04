<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DeliveryCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public Order $order;
    public string $orderNumber;

    public function __construct(Order $order)
    {
        $this->order = $order;
        $this->orderNumber = (string) $order->order_number;
    }

    public function build()
    {
        return $this->subject('Pedido creado Q-LESS #' . $this->orderNumber)
            ->view('emails.delivery-code');
    }
}
