<?php

namespace App\Mail;

use App\Models\ProspectiveClient;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProspectFollowUp1Mail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ProspectiveClient $prospect)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Cómo Juan dejó de perder $5,000 al mes en rentas no cobradas',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.prospect-followup1',
        );
    }
}
