<?php

namespace App\Filament\Pages;

use App\Support\AvisosDelDia;
use Filament\Facades\Filament;
use Filament\Pages\Page;

/**
 * A quién avisarle hoy, con el mensaje ya escrito.
 *
 * El botón de WhatsApp existe desde hace tiempo, pero hay que ir cliente por
 * cliente buscándolos en la lista. Con treinta clientes son treinta búsquedas, y
 * por eso no se hace.
 *
 * También la ve el cobrador: avisar es parte de su trabajo.
 */
class AvisosPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'Avisos de hoy';

    protected static ?string $title = 'Avisos de hoy';

    protected static ?string $navigationGroup = 'Gestión Principal';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'avisos';

    protected static string $view = 'filament.pages.avisos';

    public static function getNavigationBadge(): ?string
    {
        $tenant = Filament::getTenant();

        if (! $tenant) {
            return null;
        }

        $cuantos = AvisosDelDia::for($tenant)->avisos->count();

        return $cuantos > 0 ? (string) $cuantos : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public function getSubheading(): ?string
    {
        return 'Los que ya se vencieron y los que vencen pronto, con el mensaje listo. Tocas y se abre WhatsApp.';
    }

    public function getViewData(): array
    {
        $empresa = Filament::getTenant();

        return [
            'avisos' => AvisosDelDia::for($empresa),
            // Los que ya pasaron de la raya van aparte y arriba: a ésos no se les
            // vuelve a avisar, se va por la lavadora. Tratarlos igual que a los de
            // tres días era lo que hacía que un equipo se quedara meses allá.
            'recoger' => \App\Support\ParaRecoger::for($empresa),
        ];
    }
}
