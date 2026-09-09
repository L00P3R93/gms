<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SecurityAlertEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $change,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Security alert: :change', ['change' => $this->change]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.security-alert',
            with: [
                'user' => $this->user,
                'change' => $this->change,
                'when' => now()->format('j M Y, H:i T'),
                'appName' => config('app.name'),
                'appUrl' => config('app.url'),
            ],
        );
    }
}
