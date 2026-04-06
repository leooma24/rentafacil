<?php

namespace App\Console\Commands;

use App\Mail\ProspectWelcomeMail;
use App\Models\ProspectiveClient;
use App\Services\WhatsAppService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class ContactProspects extends Command
{
    protected $signature = 'prospects:contact {--limit=10 : Número máximo de prospectos a contactar}';

    protected $description = 'Contactar prospectos nuevos por email y/o WhatsApp (máx 10 por ejecución)';

    public function handle(): void
    {
        $limit = (int) $this->option('limit');

        $prospects = ProspectiveClient::where('status', 'nuevo')
            ->where(function ($q) {
                $q->whereNotNull('email')
                    ->where('email', '!=', '')
                    ->orWhere(function ($q2) {
                        $q2->whereNotNull('phone')
                            ->where('phone', '!=', '');
                    });
            })
            ->take($limit)
            ->get();

        if ($prospects->isEmpty()) {
            $this->info('No hay prospectos nuevos para contactar.');
            return;
        }

        $whatsapp = app(WhatsAppService::class);
        $emailSent = 0;
        $whatsappSent = 0;

        foreach ($prospects as $prospect) {
            $contacted = false;

            // Send email if available
            if ($prospect->email) {
                try {
                    Mail::to($prospect->email)->send(new ProspectWelcomeMail($prospect));
                    $emailSent++;
                    $contacted = true;
                    $this->line("  Email → {$prospect->name} ({$prospect->email})");
                } catch (\Exception $e) {
                    $this->error("  Email falló para {$prospect->name}: {$e->getMessage()}");
                }
            }

            // Send WhatsApp if available
            if ($prospect->phone) {
                $message = "Hola {$prospect->name} 👋\n\n"
                    . "¿Sigues llevando tu negocio de renta de lavadoras en una libreta? "
                    . "Sabemos lo difícil que es: cobrar sin orden, no saber quién te debe, perder el control cuando creces.\n\n"
                    . "Con *Renta Fácil* puedes gestionar todo desde tu celular:\n"
                    . "✅ Saber dónde está cada lavadora\n"
                    . "✅ Cobrar por WhatsApp con un clic\n"
                    . "✅ Generar contratos y recibos PDF\n"
                    . "✅ Ver cuánto ganas con gráficas claras\n\n"
                    . "🎁 *Prueba gratis 15 días, sin tarjeta de crédito.*\n\n"
                    . "👉 Regístrate aquí: https://rentafacil.tu-app.co/propietario/registrar\n\n"
                    . "¿Dudas? Responde este mensaje y te ayudamos.\n\n"
                    . "— Renta Fácil";

                $sent = $whatsapp->sendMessage($prospect->phone, $message);
                if ($sent) {
                    $whatsappSent++;
                    $contacted = true;
                    $this->line("  WhatsApp → {$prospect->name} ({$prospect->phone})");
                } else {
                    $this->warn("  WhatsApp no configurado para {$prospect->name}");
                }
            }

            // Mark as contacted
            if ($contacted) {
                $prospect->update([
                    'status' => 'contactado',
                    'last_contacted_at' => now(),
                ]);
            }
        }

        $this->newLine();
        $this->info("Resumen:");
        $this->info("  Emails enviados: {$emailSent}");
        $this->info("  WhatsApps enviados: {$whatsappSent}");
        $this->info("  Total contactados: " . $prospects->count());
    }
}
