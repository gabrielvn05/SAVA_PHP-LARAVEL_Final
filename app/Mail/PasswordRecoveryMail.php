<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordRecoveryMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $fullName,
        public readonly string $email,
        public readonly string $temporaryPassword,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Recuperación de acceso en SAVA',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: view('mail.password-recovery', [
                'fullName' => $this->fullName,
                'email' => $this->email,
                'temporaryPassword' => $this->temporaryPassword,
                'loginUrl' => route('login'),
            ])->render(),
        );
    }
}
