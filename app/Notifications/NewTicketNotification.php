<?php

namespace App\Notifications;

use App\Models\Incident;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewTicketNotification extends Notification
{
    use Queueable;

    public function __construct(protected Incident $incident)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => "Nuevo ticket: {$this->incident->title}",
            'body' => "Prioridad: {$this->incident->priority} | Tipo: {$this->incident->type}",
            'icon' => 'heroicon-o-ticket',
            'iconColor' => 'info',
        ];
    }
}
