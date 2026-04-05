<?php

namespace App\Notifications;

use App\Models\Rental;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RentalOverdueDatabaseNotification extends Notification
{
    use Queueable;

    public function __construct(protected Rental $rental)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $customer = $this->rental->customer;
        $machine = $this->rental->washingMachine;
        $daysOverdue = now()->diffInDays($this->rental->end_date);

        return [
            'title' => "Renta vencida hace {$daysOverdue} días",
            'body' => "Cliente: {$customer->name} | Lavadora: {$machine->machine_code}",
            'icon' => 'heroicon-o-exclamation-triangle',
            'iconColor' => 'danger',
        ];
    }
}
