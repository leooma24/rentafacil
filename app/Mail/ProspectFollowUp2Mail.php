<?php

namespace App\Mail;

use App\Models\ProspectiveClient;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProspectFollowUp2Mail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ProspectiveClient $prospect)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Última oportunidad: tu prueba gratis de 15 días te espera',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.prospect-followup2',
        );
    }
}
