<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\TareaFallidaNotification;
use App\Support\LatidoDeTareas;
use Illuminate\Console\Command;

/**
 * Detecta las tareas que dejaron de correr.
 *
 * Una tarea que truena avisa sola: hay una excepción y el onFailure la atrapa.
 * La que deja de correr no avisa nada —simplemente no pasa— y ésa es la que
 * muerde. El script de limpieza de temporales falló 48 semanas seguidas porque
 * nunca llegó a arrancar, así que nunca hubo error que reportar.
 *
 * Sólo se puede detectar por ausencia: comparando la última corrida contra lo que
 * su horario permite.
 */
class RevisarTareas extends Command
{
    protected $signature = 'tareas:revisar';

    protected $description = 'Avisa de las tareas programadas que fallaron o dejaron de correr.';

    public function handle(): int
    {
        $problemas = LatidoDeTareas::conProblema();

        // El historial viejo se va aquí y no en su propia tarea programada: una
        // tarea más que vigilar por borrar unas filas no se paga.
        LatidoDeTareas::limpiarHistorial();

        if ($problemas->isEmpty()) {
            $this->info('Todas las tareas al corriente.');

            return self::SUCCESS;
        }

        foreach ($problemas as $tarea) {
            $this->warn($tarea->tarea . ': ' . $this->porque($tarea));
        }

        // Un solo aviso con todo, y no uno por tarea: si se cae el scheduler
        // completo se caen las seis a la vez, y seis correos idénticos hacen que
        // se filtren todos.
        $este = $problemas->first();

        foreach (User::role('super_admin')->get() as $admin) {
            $admin->notify(new TareaFallidaNotification(
                $este->tarea,
                $problemas->count() === 1
                    ? $this->porque($este)
                    : $problemas->count() . ' tareas con problema: '
                        . $problemas->pluck('tarea')->implode(', ') . '.',
            ));
        }

        return self::SUCCESS;
    }

    private function porque(object $tarea): string
    {
        if ($tarea->ok === false) {
            return 'falló la última vez que corrió (' . $tarea->ultima->diffForHumans() . ').';
        }

        return 'lleva sin correr desde ' . $tarea->ultima->diffForHumans()
            . ', y debería hacerlo cada ' . $tarea->cada . ' horas.';
    }
}
