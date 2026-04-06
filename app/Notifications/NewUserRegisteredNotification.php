<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewUserRegisteredNotification extends Notification
{
    use Queueable;

    public function __construct(protected User $newUser)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => "Nuevo usuario registrado: {$this->newUser->name}",
            'body' => "Email: {$this->newUser->email}",
            'icon' => 'heroicon-o-user-plus',
            'iconColor' => 'success',
        ];
    }
}
