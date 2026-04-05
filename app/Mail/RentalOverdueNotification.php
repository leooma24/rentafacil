<?php

namespace App\Mail;

use App\Models\Rental;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RentalOverdueNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Rental $rental)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tu renta ha vencido - Renta Fácil',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.rental-overdue',
            with: [
                'rental' => $this->rental,
                'customer' => $this->rental->customer,
                'machine' => $this->rental->washingMachine,
                'daysOverdue' => now()->diffInDays($this->rental->end_date),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
