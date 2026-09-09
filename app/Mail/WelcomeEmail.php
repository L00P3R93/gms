<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to '.config('app.name').' — Your Account Details',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.welcome',
            with: [
                'user' => $this->user,
                'appName' => config('app.name'),
                'appUrl' => config('app.url'),
            ],
        );
    }
}
