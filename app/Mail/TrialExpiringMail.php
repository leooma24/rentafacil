<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TrialExpiringMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public int $daysLeft, public string $planUrl)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Tu prueba gratis termina en {$this->daysLeft} días");
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.trial-expiring');
    }
}
