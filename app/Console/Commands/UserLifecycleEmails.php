<?php

namespace App\Console\Commands;

use App\Mail\NpsSurveyMail;
use App\Mail\TrialExpiredMail;
use App\Mail\TrialExpiringMail;
use App\Mail\WeeklyReportMail;
use App\Mail\WeeklyTipMail;
use App\Models\Company;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class UserLifecycleEmails extends Command
{
    protected $signature = 'users:lifecycle-emails';

    protected $description = 'Send lifecycle emails: trial expiring, trial expired, weekly report, NPS, tips';

    protected array $tips = [
        ['title' => 'Genera un QR para cada lavadora', 'body' => 'En **Mis Lavadoras**, haz clic en el botón **QR** de cualquier lavadora. Se descarga un SVG que puedes imprimir y pegar en la máquina. Cuando tu cliente lo escanee, verá toda la info del equipo.'],
        ['title' => 'Cobra sin perseguir a nadie', 'body' => 'En **Mis Rentas**, usa el botón **WhatsApp** para enviar un recordatorio de pago automático a tu cliente. El mensaje ya viene escrito con todos los datos.'],
        ['title' => 'Planifica tu ruta de cobranza', 'body' => 'En **Planificador de Rutas**, selecciona los clientes que necesitas visitar y el sistema te calcula el orden óptimo. Luego abre en Google Maps y navega directo.'],
        ['title' => 'Genera un contrato PDF en 1 clic', 'body' => 'En **Mis Rentas**, cada renta tiene un botón **Contrato** que genera un PDF profesional con los datos del cliente, la lavadora y los términos. Listo para firmar.'],
        ['title' => 'Usa el calendario para no perder nada', 'body' => 'En tu **Dashboard** tienes un calendario que muestra todos los vencimientos del mes. Las rentas vencidas se marcan en rojo para que las veas de un vistazo.'],
        ['title' => 'Exporta tus datos a Excel', 'body' => 'En **Mis Rentas**, **Pagos** y **Clientes** tienes un botón **Exportar Excel**. Útil para hacer tu contabilidad o llevar registros externos.'],
        ['title' => 'Importa clientes desde Excel', 'body' => '¿Tienes muchos clientes? En **Mis Clientes** usa el botón **Importar** para cargar un archivo Excel con columnas: nombre, email, telefono. Se dan de alta todos al instante.'],
        ['title' => 'Registra mantenimientos para no perder dinero', 'body' => 'En **Mantenimientos** puedes registrar cuando envías una lavadora a reparar. El sistema extiende automáticamente la renta del cliente por los días que duró el servicio.'],
    ];

    public function handle(): void
    {
        $this->handleTrialExpiring();
        $this->handleTrialExpired();
        $this->handleWeeklyReport();
        $this->handleNps();
        $this->handleWeeklyTip();
    }

    protected function handleTrialExpiring(): void
    {
        $companies = Company::with(['companyPackage', 'members'])
            ->whereHas('companyPackage', function ($q) {
                $q->whereBetween('end_date', [now()->addDays(1), now()->addDays(3)]);
            })
            ->get();

        foreach ($companies as $company) {
            $days = (int) now()->diffInDays($company->companyPackage->end_date, false);
            $planUrl = "https://rentafacil.tu-app.co/propietario/{$company->id}/mi-plan";

            foreach ($company->members as $member) {
                try {
                    Mail::to($member->email)->send(new TrialExpiringMail($member, $days, $planUrl));
                    $this->line("  Trial expiring ({$days}d) → {$member->name}");
                } catch (\Exception $e) {
                    $this->error("  Error: {$e->getMessage()}");
                }
            }
        }
    }

    protected function handleTrialExpired(): void
    {
        // Expired yesterday (send once)
        $companies = Company::with(['companyPackage', 'members'])
            ->whereHas('companyPackage', function ($q) {
                $q->whereDate('end_date', now()->subDay()->toDateString());
            })
            ->get();

        foreach ($companies as $company) {
            $planUrl = "https://rentafacil.tu-app.co/propietario/{$company->id}/mi-plan";
            foreach ($company->members as $member) {
                try {
                    Mail::to($member->email)->send(new TrialExpiredMail($member, $planUrl));
                    $this->line("  Trial expired → {$member->name}");
                } catch (\Exception $e) {
                    $this->error("  Error: {$e->getMessage()}");
                }
            }
        }
    }

    protected function handleWeeklyReport(): void
    {
        // Only on Mondays
        if (now()->dayOfWeek !== 1) return;

        $companies = Company::with('members')->whereHas('companyPackage', function ($q) {
            $q->where('end_date', '>=', now());
        })->get();

        foreach ($companies as $company) {
            $revenue = Payment::where('company_id', $company->id)
                ->where('status', 'completado')
                ->where('payment_date', '>=', now()->subWeek())
                ->sum('amount');
            $activeRentals = $company->rentals()->where('status', 'activa')->count();
            $overdueRentals = $company->rentals()->where('status', 'vencida')->count();
            $totalMachines = $company->washingMachines()->count();

            foreach ($company->members as $member) {
                try {
                    Mail::to($member->email)->send(new WeeklyReportMail($member, $revenue, $activeRentals, $overdueRentals, $totalMachines));
                    $this->line("  Weekly report → {$member->name}");
                } catch (\Exception $e) {
                    $this->error("  Error: {$e->getMessage()}");
                }
            }
        }
    }

    protected function handleNps(): void
    {
        // Users registered exactly 14 days ago
        $users = User::whereDate('created_at', now()->subDays(14)->toDateString())
            ->whereHas('companies')
            ->get();

        foreach ($users as $user) {
            try {
                Mail::to($user->email)->send(new NpsSurveyMail($user));
                $this->line("  NPS survey → {$user->name}");
            } catch (\Exception $e) {
                $this->error("  Error: {$e->getMessage()}");
            }
        }
    }

    protected function handleWeeklyTip(): void
    {
        // Only on Thursdays
        if (now()->dayOfWeek !== 4) return;

        $weekNum = now()->weekOfYear % count($this->tips);
        $tip = $this->tips[$weekNum];

        $users = User::whereHas('companies', function ($q) {
            $q->whereHas('companyPackage', fn ($q2) => $q2->where('end_date', '>=', now()));
        })->get();

        foreach ($users as $user) {
            try {
                Mail::to($user->email)->send(new WeeklyTipMail($user, $tip['title'], $tip['body']));
                $this->line("  Weekly tip → {$user->name}");
            } catch (\Exception $e) {
                $this->error("  Error: {$e->getMessage()}");
            }
        }
    }
}
