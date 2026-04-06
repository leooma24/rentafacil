<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TrialExpiredMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $planUrl)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Tu prueba terminó, pero tu negocio no tiene que detenerse');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.trial-expired');
    }
}
