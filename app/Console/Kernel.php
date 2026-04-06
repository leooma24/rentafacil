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
        $schedule->command('backup:clean')->daily()->at('01:00');
        $schedule->command('backup:run --only-db')->daily()->at('02:00');
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
