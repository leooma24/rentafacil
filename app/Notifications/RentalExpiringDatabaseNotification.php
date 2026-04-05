<?php

namespace App\Notifications;

use App\Models\Rental;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RentalExpiringDatabaseNotification extends Notification
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
        $daysLeft = now()->diffInDays($this->rental->end_date);

        return [
            'title' => "Renta por vencer en {$daysLeft} días",
            'body' => "Cliente: {$customer->name} | Lavadora: {$machine->machine_code}",
            'icon' => 'heroicon-o-clock',
            'iconColor' => 'warning',
        ];
    }
}
