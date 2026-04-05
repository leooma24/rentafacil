<?php

namespace App\Console\Commands;

use App\Mail\RentalExpiringNotification;
use App\Mail\RentalOverdueNotification;
use App\Models\Rental;
use App\Notifications\RentalExpiringDatabaseNotification;
use App\Notifications\RentalOverdueDatabaseNotification;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendRentalReminders extends Command
{
    protected $signature = 'rentals:send-reminders';

    protected $description = 'Enviar recordatorios por email de rentas por vencer y vencidas';

    public function handle(): void
    {
        $whatsapp = app(WhatsAppService::class);

        // Rentas que vencen en 3 días o menos
        $expiring = Rental::with(['customer', 'washingMachine', 'company.members'])
            ->where('status', 'activa')
            ->whereBetween('end_date', [now(), now()->addDays(3)])
            ->get();

        foreach ($expiring as $rental) {
            if ($rental->customer?->email) {
                Mail::to($rental->customer->email)
                    ->send(new RentalExpiringNotification($rental));
            }

            if ($rental->customer?->phone) {
                $whatsapp->sendPaymentReminder(
                    $rental->customer->phone,
                    $rental->customer->name,
                    $rental->washingMachine->machine_code,
                    Carbon::parse($rental->end_date)->format('d/m/Y'),
                );
            }

            $rental->company->members->each(fn ($member) =>
                $member->notify(new RentalExpiringDatabaseNotification($rental))
            );
        }

        $this->info("Enviados {$expiring->count()} recordatorios de rentas por vencer.");

        // Rentas ya vencidas
        $overdue = Rental::with(['customer', 'washingMachine', 'company.members'])
            ->where('status', 'vencida')
            ->get();

        foreach ($overdue as $rental) {
            if ($rental->customer?->email) {
                Mail::to($rental->customer->email)
                    ->send(new RentalOverdueNotification($rental));
            }

            if ($rental->customer?->phone) {
                $daysOverdue = now()->diffInDays($rental->end_date);
                $whatsapp->sendOverdueNotice(
                    $rental->customer->phone,
                    $rental->customer->name,
                    $rental->washingMachine->machine_code,
                    $daysOverdue,
                );
            }

            $rental->company->members->each(fn ($member) =>
                $member->notify(new RentalOverdueDatabaseNotification($rental))
            );
        }

        $this->info("Enviados {$overdue->count()} avisos de rentas vencidas.");
    }
}
