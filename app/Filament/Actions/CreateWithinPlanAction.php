<?php

namespace App\Filament\Actions;

use App\Filament\Pages\MyPlan;
use App\Models\Company;
use App\Support\PlanUsage;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;

/**
 * El botón de crear, o el aviso de que ya no hay cupo.
 *
 * Antes, al topar el límite el botón simplemente desaparecía: el dueño concluía
 * que la app se descompuso y llamaba a soporte, justo cuando estaba dispuesto a
 * pagar más. Ahora el botón sigue ahí y explica qué pasa.
 */
class CreateWithinPlanAction
{
    public static function make(Company $tenant, string $recurso): Action|CreateAction
    {
        $usage = PlanUsage::for($tenant);

        $topado = match ($recurso) {
            'lavadoras' => $usage->machinesMaxed(),
            'clientes' => $usage->customersMaxed(),
            default => false,
        };

        if (! $topado) {
            return CreateAction::make();
        }

        [$usados, $tope] = match ($recurso) {
            'lavadoras' => [$usage->machines, $usage->maxMachines],
            'clientes' => [$usage->customers, $usage->maxCustomers],
        };

        $plan = $usage->planName ?? 'actual';

        return Action::make('limite_alcanzado')
            ->label($recurso === 'lavadoras' ? 'Crear Lavadora' : 'Crear Cliente')
            ->icon('heroicon-o-plus')
            ->color('warning')
            ->modalHeading('Llegaste al límite de tu plan')
            ->modalDescription(
                "Tu plan {$plan} incluye {$tope} {$recurso} y ya tienes {$usados}. "
                . 'Para agregar más, sube de plan.'
            )
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Entendido')
            ->extraModalFooterActions([
                Action::make('ver_planes')
                    ->label('Ver planes')
                    ->color('primary')
                    // El tenant va explícito: así la acción no depende de que
                    // Filament ya lo haya resuelto en el contexto.
                    ->url(MyPlan::getUrl(tenant: $tenant)),
            ]);
    }
}
