<?php

namespace App\Mail;

use App\Models\ProspectiveClient;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProspectWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ProspectiveClient $prospect)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '¿Cuántas horas pierdes a la semana cobrando renta de lavadoras?',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.prospect-welcome',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
