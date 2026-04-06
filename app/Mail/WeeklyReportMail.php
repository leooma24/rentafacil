<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WeeklyReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public float $revenue,
        public int $activeRentals,
        public int $overdueRentals,
        public int $totalMachines,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Tu resumen semanal — Renta Fácil');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.weekly-report');
    }
}
