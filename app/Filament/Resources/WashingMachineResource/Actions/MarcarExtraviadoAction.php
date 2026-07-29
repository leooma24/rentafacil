<?php

namespace App\Filament\Resources\WashingMachineResource\Actions;

use App\Models\WashingMachine;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Illuminate\Support\Facades\DB;

/**
 * Marcar un equipo como extraviado.
 *
 * Pasa: el cliente se muda y se lleva la lavadora. Hasta ahora había que cerrar
 * la renta como cancelada, y el equipo volvía al inventario como "disponible" —
 * inflando la ocupación con un aparato que ya no está.
 *
 * La renta NO se cierra. El adeudo se deriva de qué tan atrás quedó end_date, así
 * que cerrarla lo haría desaparecer del estado de cuenta — y que el cliente se
 * haya llevado el aparato no significa que deje de deber. Se queda abierta y
 * siguiendo, que es justo lo que el dueño quiere mientras trata de cobrarle.
 *
 * Cuando decida darlo por perdido, cancela la renta a mano y ahí sí deja de
 * contar.
 */
class MarcarExtraviadoAction
{
    public static function make(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('marcar_extraviado')
            ->label('Marcar como extraviado')
            ->icon('heroicon-o-question-mark-circle')
            ->color('danger')
            ->visible(fn (WashingMachine $record) => $record->status !== 'extraviada')
            ->modalHeading('Marcar el equipo como extraviado')
            ->modalDescription('Deja de contar en tu inventario y en la ocupación. La renta se queda abierta para que el adeudo del cliente siga corriendo.')
            ->modalSubmitActionLabel('Marcar como extraviado')
            ->form([
                Forms\Components\Textarea::make('notes')
                    ->label('Qué pasó')
                    ->rows(3)
                    ->placeholder('Se mudó sin avisar. Ya no contesta el teléfono.')
                    ->required(),
            ])
            ->action(function (array $data, WashingMachine $record) {
                DB::transaction(function () use ($data, $record) {
                    $record->update(['status' => 'extraviada']);

                    // La renta se queda como está: el adeudo sale de qué tan
                    // atrás quedó end_date, y cerrarla lo borraría del estado de
                    // cuenta justo cuando más se quiere cobrar.
                    foreach ($record->rentals()->whereIn('status', ['activa', 'vencida'])->get() as $renta) {
                        $renta->update([
                            'notes' => trim(($renta->notes ? $renta->notes . ' · ' : '')
                                . 'Equipo extraviado: ' . $data['notes']),
                        ]);
                    }
                });

                $conRenta = $record->rentals()->whereIn('status', ['activa', 'vencida'])->exists();

                Notification::make()
                    ->title('Marcado como extraviado')
                    ->body($conRenta
                        ? 'Ya no cuenta en tu inventario. La renta sigue abierta y el adeudo sigue corriendo; cancélala cuando lo des por perdido.'
                        : 'Ya no cuenta en tu inventario ni en la ocupación.')
                    ->warning()
                    ->send();
            });
    }
}
