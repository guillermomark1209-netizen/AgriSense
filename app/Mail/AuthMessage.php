<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AuthMessage extends Mailable
{
    public function __construct(public string $kind, public int $userId, public string $recipient, public string $name, #[\SensitiveParameter] public string $secret = '') {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: match ($this->kind) {
            'verification' => 'Verify Your Email — AgriSense', 'reset' => 'Reset Your AgriSense Password',
            'welcome' => 'Welcome to AgriSense!', default => 'AgriSense SMTP delivery test',
        });
    }

    public function content(): Content
    {
        return new Content(view: 'emails.auth', text: 'emails.auth-text');
    }
}
