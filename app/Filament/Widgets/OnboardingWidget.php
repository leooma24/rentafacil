<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\Settings;
use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\RentalResource;
use App\Filament\Resources\WashingMachineResource;
use App\Support\Onboarding;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

/**
 * El checklist de arranque. Se esconde solo cuando ya no hay pendientes, así que
 * la cuenta que ya opera nunca lo ve.
 */
class OnboardingWidget extends Widget
{
    protected static string $view = 'filament.widgets.onboarding';

    protected static ?int $sort = -1;

    protected int|string|array $columnSpan = 'full';

    // Sin esto el widget se queda en el placeholder de carga y nunca aparece.
    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        $tenant = Filament::getTenant();

        return $tenant && ! Onboarding::for($tenant)->isComplete();
    }

    public function getOnboarding(): Onboarding
    {
        return Onboarding::for(Filament::getTenant());
    }

    public function urlFor(string $clave): string
    {
        return match ($clave) {
            'precio' => Settings::getUrl(),
            'lavadoras' => WashingMachineResource::getUrl('index'),
            'clientes' => CustomerResource::getUrl('index'),
            'renta' => RentalResource::getUrl('index'),
            // Al mismo lugar que la renta: el cobro se registra con el botón
            // Cobrar de cada renta, no en una pantalla aparte.
            'cobro' => RentalResource::getUrl('index'),
        };
    }
}
