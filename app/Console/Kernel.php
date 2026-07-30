<?php

namespace App\Console;

use App\Support\LatidoDeTareas;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();
        $this->vigilada($schedule->command('rentals:mark-overdue')->daily());
        $this->vigilada($schedule->command('rentals:send-reminders')->dailyAt('09:00'));
        $schedule->command('prospects:contact --limit=10')->hourly()->between('8:00', '20:00');
        $schedule->command('prospects:followup --limit=10')->hourly()->between('8:00', '20:00');
        $this->vigilada($schedule->command('users:check-inactive')->dailyAt('10:00'));
        $this->vigilada($schedule->command('users:lifecycle-emails')->dailyAt('09:30'));
        $schedule->command('backup:clean')->daily()->at('01:00');
        // Sin --only-db: ahora también entran los archivos que sube la gente
        // (fotos de entrega, de recolección e identificaciones de clientes), que
        // no se recuperan de ningún lado. El config acota qué se incluye para
        // que no se respalde el proyecto entero.
        $this->vigilada($schedule->command('backup:run')->daily()->at('02:00'));
        // Avisa si el respaldo lleva más de un día sin correr o creció de más.
        // Sin esto, un respaldo que deja de hacerse no se nota hasta que hace
        // falta, que es cuando ya no sirve enterarse.
        $schedule->command('backup:monitor')->daily()->at('02:30');
        $this->vigilada($schedule->command('demo:cleanup')->hourly());

        // La que vigila a las demás: detecta las que dejaron de correr, que es lo
        // que no truena y por eso nadie nota.
        $schedule->command('tareas:revisar')->hourly();

        // La coherencia de los datos: equipos marcados como rentados sin renta,
        // parados sin orden abierta, rentas abiertas con el equipo libre. Son
        // fallas que no revientan nada y sólo se notan cuando hace falta el
        // aparato y no aparece.
        $schedule->command('datos:revisar')->dailyAt('06:00');
    }

    /**
     * Deja registrado el latido de una tarea y avisa si truena.
     *
     * Antes no había nada de esto. El respaldo tenía su monitor, pero todo lo
     * demás moría en silencio, y el silencio es el problema: si marcar vencidas
     * se cae, nadie aparece como vencido y el panel se ve idéntico a una semana
     * en que todos pagaron.
     */
    private function vigilada(Event $evento): Event
    {
        $tarea = $this->nombreDe($evento);

        return $evento
            ->onSuccess(fn () => LatidoDeTareas::registrar($tarea))
            ->onFailure(fn () => LatidoDeTareas::registrarFallo(
                $tarea,
                'La tarea terminó con error. Revisa storage/logs.'
            ));
    }

    /** El comando sin la ruta del php ni las comillas que le mete el scheduler. */
    private function nombreDe(Event $evento): string
    {
        if (preg_match("/artisan['\"]? ([a-z0-9:_-]+)/i", (string) $evento->command, $coincidencias)) {
            return $coincidencias[1];
        }

        return trim($evento->description ?? (string) $evento->command) ?: 'desconocida';
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
