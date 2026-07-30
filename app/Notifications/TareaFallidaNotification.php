<?php

namespace App\Notifications;

use App\Support\LatidoDeTareas;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Una tarea programada falló.
 *
 * Va por campana y por correo: la campana sólo se ve si alguien entra al panel, y
 * una tarea caída puede pasar días sin que nadie entre. Ése fue exactamente el
 * caso del script de limpieza, que falló 48 semanas seguidas sin que nadie lo
 * notara.
 */
class TareaFallidaNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected string $tarea,
        protected ?string $mensaje = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => "Falló una tarea del sistema: {$this->tarea}",
            'body' => $this->queHace() . ($this->mensaje ? ' — ' . mb_substr($this->mensaje, 0, 200) : ''),
            'icon' => 'heroicon-o-exclamation-triangle',
            'iconColor' => 'danger',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $correo = (new MailMessage())
            ->error()
            ->subject("Falló una tarea del sistema: {$this->tarea}")
            ->line('La tarea **' . $this->tarea . '** no terminó bien.')
            ->line($this->queHace() . '.');

        if ($this->mensaje) {
            $correo->line('Lo que devolvió:')->line($this->mensaje);
        }

        return $correo->line(
            'Mientras siga caída, lo que hace esa tarea simplemente no pasa, '
            . 'y desde el panel se ve igual que si no hubiera nada que hacer.'
        );
    }

    /** Para qué sirve, porque el nombre del comando no se lo dice a nadie. */
    private function queHace(): string
    {
        return LatidoDeTareas::ESPERADAS[$this->tarea]['que_hace'] ?? 'Tarea programada del sistema';
    }
}
