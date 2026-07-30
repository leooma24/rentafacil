<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Hay cuentas con equipos o rentas cuyos estados se contradicen.
 *
 * Sólo por campana: no urge como una tarea caída, y un correo diario por algo que
 * no cambia de un día a otro se aprende a ignorar en una semana.
 */
class DatosIncoherentesNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected int $cuentas,
        protected int $total,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->total === 1
                ? 'Hay 1 dato que se contradice'
                : "Hay {$this->total} datos que se contradicen",
            'body' => ($this->cuentas === 1 ? 'En 1 cuenta' : "En {$this->cuentas} cuentas")
                . '. Son equipos que no aparecen para rentar sin que nada explique por qué.'
                . ' Corre "datos:revisar" para ver el detalle.',
            'icon' => 'heroicon-o-exclamation-circle',
            'iconColor' => 'warning',
        ];
    }
}
