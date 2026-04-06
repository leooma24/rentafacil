<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WeeklyTipMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $tipTitle, public string $tipBody)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Tip de la semana: {$this->tipTitle}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.weekly-tip');
    }
}
