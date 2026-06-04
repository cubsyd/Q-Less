<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VerifyEmailMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $verificationUrl;

    public function __construct(public User $user)
    {
        $this->verificationUrl = url('/api/email/verify/' . $user->email_verification_token);
    }

    public function build()
    {
        return $this->subject('Verifica tu correo en Q-LESS')
            ->view('emails.verify-email');
    }
}
