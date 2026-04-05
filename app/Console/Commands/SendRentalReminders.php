<?php

namespace App\Console\Commands;

use App\Mail\RentalExpiringNotification;
use App\Mail\RentalOverdueNotification;
use App\Models\Rental;
use App\Notifications\RentalExpiringDatabaseNotification;
use App\Notifications\RentalOverdueDatabaseNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendRentalReminders extends Command
{
    protected $signature = 'rentals:send-reminders';

    protected $description = 'Enviar recordatorios por email de rentas por vencer y vencidas';

    public function handle(): void
    {
        // Rentas que vencen en 3 días o menos
        $expiring = Rental::with(['customer', 'washingMachine'])
            ->where('status', 'activa')
            ->whereBetween('end_date', [now(), now()->addDays(3)])
            ->whereHas('customer', fn ($q) => $q->whereNotNull('email'))
            ->get();

        foreach ($expiring as $rental) {
            Mail::to($rental->customer->email)
                ->send(new RentalExpiringNotification($rental));

            // Database notification to company members
            $rental->company->members->each(fn ($member) =>
                $member->notify(new RentalExpiringDatabaseNotification($rental))
            );
        }

        $this->info("Enviados {$expiring->count()} recordatorios de rentas por vencer.");

        // Rentas ya vencidas (notificar una vez al día)
        $overdue = Rental::with(['customer', 'washingMachine'])
            ->where('status', 'vencida')
            ->whereHas('customer', fn ($q) => $q->whereNotNull('email'))
            ->get();

        foreach ($overdue as $rental) {
            Mail::to($rental->customer->email)
                ->send(new RentalOverdueNotification($rental));

            $rental->company->members->each(fn ($member) =>
                $member->notify(new RentalOverdueDatabaseNotification($rental))
            );
        }

        $this->info("Enviados {$overdue->count()} avisos de rentas vencidas.");
    }
}
