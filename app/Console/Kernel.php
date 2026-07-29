<?php

namespace App\Console;

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
        $schedule->command('rentals:mark-overdue')->daily();
        $schedule->command('rentals:send-reminders')->dailyAt('09:00');
        $schedule->command('prospects:contact --limit=10')->hourly()->between('8:00', '20:00');
        $schedule->command('prospects:followup --limit=10')->hourly()->between('8:00', '20:00');
        $schedule->command('users:check-inactive')->dailyAt('10:00');
        $schedule->command('users:lifecycle-emails')->dailyAt('09:30');
        $schedule->command('backup:clean')->daily()->at('01:00');
        // Sin --only-db: ahora también entran los archivos que sube la gente
        // (fotos de entrega, de recolección e identificaciones de clientes), que
        // no se recuperan de ningún lado. El config acota qué se incluye para
        // que no se respalde el proyecto entero.
        $schedule->command('backup:run')->daily()->at('02:00');
        // Avisa si el respaldo lleva más de un día sin correr o creció de más.
        // Sin esto, un respaldo que deja de hacerse no se nota hasta que hace
        // falta, que es cuando ya no sirve enterarse.
        $schedule->command('backup:monitor')->daily()->at('02:30');
        $schedule->command('demo:cleanup')->hourly();
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
