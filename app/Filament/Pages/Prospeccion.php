<?php

namespace App\Filament\Pages;

use App\Models\ProspectiveClient;
use App\Support\ProspectOutreach;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * La cola de prospectos por contactar.
 *
 * Existe porque una tabla de 94 renglones en el menú de Administrador no invita
 * a trabajarla: llevaban meses sin que nadie contactara a ninguno.
 */
class Prospeccion extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationGroup = 'Administrador';

    protected static ?string $navigationLabel = 'Contactar hoy';

    protected static ?string $slug = 'contactar';

    /**
     * Esta pantalla es de quien opera la plataforma, no de los rentadores: trae
     * la lista de prospectos del negocio. No tenía candado y la veía cualquier
     * dueño, igual que ProspectiveClientResource, que sí lo tiene.
     */
    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    protected static ?string $title = 'Contactar hoy';

    protected static ?int $navigationSort = 0;

    protected static string $view = 'filament.pages.prospeccion';

    public ?string $ciudad = null;

    public string $plantilla = 'primero';

    /** Los que ya se saltaron en esta sesión, para no volverlos a mostrar. */
    public array $saltados = [];

    public static function getNavigationBadge(): ?string
    {
        $pendientes = ProspectOutreach::pendingCount();

        return $pendientes > 0 ? (string) $pendientes : null;
    }

    public function getProspect(): ?ProspectiveClient
    {
        return ProspectOutreach::queue($this->ciudad)
            ->reject(fn (ProspectiveClient $p) => in_array($p->id, $this->saltados, true))
            ->first();
    }

    public function getPendingCount(): int
    {
        return ProspectOutreach::pendingCount($this->ciudad);
    }

    public function getCities(): array
    {
        return ProspectOutreach::cities();
    }

    public function getWhatsappUrl(): ?string
    {
        $prospect = $this->getProspect();

        return $prospect ? ProspectOutreach::whatsappUrl($prospect, $this->plantilla) : null;
    }

    public function getMessagePreview(): ?string
    {
        $prospect = $this->getProspect();

        return $prospect ? ProspectOutreach::message($prospect, $this->plantilla) : null;
    }

    /** Abrir WhatsApp cuenta como contacto: se sella aunque luego no conteste. */
    public function marcarContactado(): void
    {
        $prospect = $this->getProspect();

        if (! $prospect) {
            return;
        }

        $prospect->update([
            'last_contacted_at' => now(),
            'status' => $prospect->status === 'nuevo' ? 'contactado' : $prospect->status,
        ]);
    }

    public function calificar(string $status): void
    {
        $prospect = $this->getProspect();

        if (! $prospect) {
            return;
        }

        $prospect->update([
            'status' => $status,
            'last_contacted_at' => $prospect->last_contacted_at ?? now(),
        ]);

        Notification::make()
            ->title("{$prospect->name}: " . static::etiquetaEstado($status))
            ->success()
            ->send();
    }

    public function saltar(): void
    {
        $prospect = $this->getProspect();

        if ($prospect) {
            $this->saltados[] = $prospect->id;
        }
    }

    public function cambiarCiudad(): void
    {
        $this->saltados = [];
    }

    public static function etiquetaEstado(string $status): string
    {
        return match ($status) {
            'interesado' => 'le interesó',
            'demo' => 'demo agendada',
            'no_interesado' => 'no le interesó',
            'cliente' => 'ya es cliente',
            default => $status,
        };
    }
}
