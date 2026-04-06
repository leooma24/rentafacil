<?php

namespace App\Console\Commands;

use App\Mail\ProspectFollowUp1Mail;
use App\Mail\ProspectFollowUp2Mail;
use App\Models\ProspectiveClient;
use App\Services\WhatsAppService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class FollowUpProspects extends Command
{
    protected $signature = 'prospects:followup {--limit=10}';

    protected $description = 'Enviar seguimiento automático a prospectos por etapas';

    public function handle(): void
    {
        $limit = (int) $this->option('limit');
        $whatsapp = app(WhatsAppService::class);
        $sent = 0;

        // ETAPA 2: contactado hace 3+ días → seguimiento (caso de uso)
        $followup1 = ProspectiveClient::where('status', 'contactado')
            ->where('last_contacted_at', '<=', now()->subDays(3))
            ->take($limit)
            ->get();

        foreach ($followup1 as $prospect) {
            if ($prospect->email) {
                try {
                    Mail::to($prospect->email)->send(new ProspectFollowUp1Mail($prospect));
                    $this->line("  Follow-up 1 email → {$prospect->name}");
                } catch (\Exception $e) {
                    $this->error("  Email falló: {$e->getMessage()}");
                }
            }

            if ($prospect->phone) {
                $whatsapp->sendMessage($prospect->phone,
                    "Hola {$prospect->name} 👋\n\n"
                    . "¿Sabías que otros propietarios en tu zona ya gestionan sus lavadoras desde el celular?\n\n"
                    . "Con *Renta Fácil* dejas de perseguir pagos y el sistema cobra por ti.\n\n"
                    . "🎁 Prueba 15 días gratis: https://rentafacil.tu-app.co/propietario/registrar\n\n"
                    . "¿Dudas? Responde este mensaje.\n— Renta Fácil"
                );
            }

            $prospect->update(['status' => 'seguimiento', 'last_contacted_at' => now()]);
            $sent++;
        }

        // ETAPA 3: seguimiento hace 5+ días → último intento (urgencia)
        $followup2 = ProspectiveClient::where('status', 'seguimiento')
            ->where('last_contacted_at', '<=', now()->subDays(5))
            ->take($limit - $sent)
            ->get();

        foreach ($followup2 as $prospect) {
            if ($prospect->email) {
                try {
                    Mail::to($prospect->email)->send(new ProspectFollowUp2Mail($prospect));
                    $this->line("  Follow-up 2 email → {$prospect->name}");
                } catch (\Exception $e) {
                    $this->error("  Email falló: {$e->getMessage()}");
                }
            }

            if ($prospect->phone) {
                $whatsapp->sendMessage($prospect->phone,
                    "Hola {$prospect->name}\n\n"
                    . "Este es nuestro último mensaje 🙏\n\n"
                    . "Otros propietarios en tu ciudad ya están usando *Renta Fácil* para gestionar sus lavadoras.\n\n"
                    . "Si quieres probarlo gratis 15 días, solo regístrate:\n"
                    . "👉 https://rentafacil.tu-app.co/propietario/registrar\n\n"
                    . "Si no te interesa, no volveremos a escribirte.\n\n"
                    . "— Renta Fácil"
                );
            }

            $prospect->update(['status' => 'no_interesado', 'last_contacted_at' => now()]);
            $sent++;
        }

        $this->info("Seguimientos enviados: {$sent}");
    }
}
