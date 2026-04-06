<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FirstRentalCongratsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $machineName, public string $customerName)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: '¡Felicidades! Ya creaste tu primera renta');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.first-rental-congrats');
    }
}
