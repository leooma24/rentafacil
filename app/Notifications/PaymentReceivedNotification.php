<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PaymentReceivedNotification extends Notification
{
    use Queueable;

    public function __construct(protected Payment $payment)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $machine = $this->payment->rental?->washingMachine;

        return [
            'title' => 'Pago registrado: $' . number_format($this->payment->amount, 2),
            'body' => "Método: {$this->payment->payment_method}" . ($machine ? " | Lavadora: {$machine->machine_code}" : ''),
            'icon' => 'heroicon-o-banknotes',
            'iconColor' => 'success',
        ];
    }
}
