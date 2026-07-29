<?php

namespace App\Filament\Resources\RentalResource\Actions;

use App\Models\RentalMachineChange;
use App\Models\WashingMachine;
use App\Support\CambioDeEquipo;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables;

/**
 * Cambiarle el equipo a un cliente conservando su renta.
 *
 * Se arma con un resolvedor igual que Abonar y Entregar, para poder colgarla
 * tanto de Rentas como de Equipos.
 */
class CambiarEquipoAction
{
    public static function make(\Closure $resolver): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('cambiar_equipo')
            ->label('Cambiar equipo')
            ->icon('heroicon-o-arrows-right-left')
            ->color('warning')
            ->visible(fn ($record) => in_array($resolver($record)?->status, ['activa', 'vencida'], true))
            ->modalHeading('Cambiarle el equipo al cliente')
            ->modalDescription('La renta, sus pagos y su saldo no se tocan. Sólo cambia el aparato que tiene.')
            ->modalSubmitActionLabel('Cambiar')
            ->form(fn ($record) => [
                Forms\Components\Placeholder::make('actual')
                    ->label('Trae ahora')
                    ->content(function () use ($resolver, $record) {
                        $equipo = $resolver($record)?->washingMachine;

                        return $equipo
                            ? "{$equipo->machine_code} · {$equipo->kindLabel()} {$equipo->brand}"
                            : '—';
                    }),

                Forms\Components\Select::make('to_machine_id')
                    ->label('Se le va a dar')
                    ->options(function () use ($resolver, $record) {
                        $renta = $resolver($record);

                        return $renta
                            ? app(CambioDeEquipo::class)->disponiblesPara($renta)
                                ->mapWithKeys(fn (WashingMachine $e) => [
                                    $e->id => "{$e->machine_code} · {$e->kindLabel()}"
                                        . ($e->brand ? " {$e->brand}" : ''),
                                ])
                            : [];
                    })
                    ->searchable()
                    ->required()
                    ->helperText('Sólo aparecen los que están disponibles.'),

                Forms\Components\Select::make('reason')
                    ->label('Por qué')
                    ->options(RentalMachineChange::REASONS)
                    ->default('falla')
                    ->required()
                    ->native(false)
                    ->helperText('Si se descompuso, el que retiras se va a mantenimiento; si no, queda disponible.'),

                Forms\Components\Textarea::make('notes')
                    ->label('Notas')
                    ->rows(2),
            ])
            ->action(function (array $data, $record) use ($resolver) {
                $renta = $resolver($record);

                if (! $renta) {
                    return;
                }

                try {
                    $cambio = app(CambioDeEquipo::class)->ejecutar(
                        $renta,
                        WashingMachine::findOrFail($data['to_machine_id']),
                        $data['reason'],
                        $data['notes'] ?? null,
                    );
                } catch (\RuntimeException $e) {
                    // Un equipo que otro acaba de rentar, por ejemplo. Se dice y
                    // no se deja a medias.
                    Notification::make()
                        ->title('No se pudo cambiar')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Equipo cambiado')
                    ->body("Ahora trae {$cambio->toMachine->machine_code}. Su saldo y sus pagos siguen igual; falta registrar la entrega.")
                    ->success()
                    ->send();
            });
    }
}
