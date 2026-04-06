<?php

namespace App\Console\Commands;

use App\Mail\InactiveUserMail;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class CheckInactiveUsers extends Command
{
    protected $signature = 'users:check-inactive';

    protected $description = 'Detecta usuarios que se registraron hace 2+ días pero no tienen actividad y les envía ayuda';

    public function handle(): void
    {
        // Users registered 2+ days ago with no washing machines
        $inactive = User::where('created_at', '<=', now()->subDays(2))
            ->where('created_at', '>=', now()->subDays(7)) // Only within last week
            ->whereHas('companies')
            ->get()
            ->filter(function ($user) {
                // Check if any of their companies have 0 washing machines
                return $user->companies->contains(fn ($company) =>
                    $company->washingMachines()->count() === 0
                );
            });

        $sent = 0;
        foreach ($inactive as $user) {
            try {
                Mail::to($user->email)->send(new InactiveUserMail($user));
                $this->line("  Ayuda enviada → {$user->name} ({$user->email})");
                $sent++;
            } catch (\Exception $e) {
                $this->error("  Error: {$e->getMessage()}");
            }
        }

        $this->info("Correos de ayuda enviados: {$sent}");
    }
}
