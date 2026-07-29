<?php

namespace App\Filament\Resources\RentalResource\Actions;

use App\Models\Rental;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables;

/**
 * Registrar la entrega del equipo, con evidencia.
 *
 * Existía "Recoger" pero no "Entregar": la renta nacía y ya. Sin foto ni acuse no
 * hay con qué responder al "así me la entregaste" cuando devuelven el aparato
 * golpeado.
 *
 * Se arma con un resolvedor igual que AbonarAction, para poder colgarla tanto de
 * Rentas (donde el registro ES la renta) como de Equipos (donde hay que sacarla
 * de activeRental).
 */
class EntregarAction
{
    public static function make(\Closure $resolver): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('entregar')
            ->label('Entregar')
            ->icon('heroicon-o-truck')
            ->color('info')
            ->visible(fn ($record) => $resolver($record)?->needsDelivery() ?? false)
            ->modalHeading('Registrar la entrega')
            ->modalDescription('Toma fotos del equipo antes de dejarlo. Es lo que te respalda si te lo devuelven dañado.')
            ->modalSubmitActionLabel('Registrar entrega')
            ->form([
                Forms\Components\FileUpload::make('delivery_photos')
                    ->label('Fotos del equipo')
                    ->disk('privado')
                    ->image()
                    ->multiple()
                    ->maxFiles(6)
                    ->directory('entregas')
                    ->imageEditor()
                    ->helperText('Del tambor, la carcasa y las mangueras. Con el celular, ahí mismo.')
                    ->columnSpanFull(),

                Forms\Components\Textarea::make('delivery_notes')
                    ->label('Cómo se entregó')
                    ->rows(3)
                    ->placeholder('Funcionando, sin golpes. Se le explicó el uso al cliente.')
                    ->columnSpanFull(),
            ])
            ->action(function (array $data, $record) use ($resolver) {
                $renta = $resolver($record);

                if (! $renta) {
                    return;
                }

                $renta->update([
                    'delivered_at' => now(),
                    'delivery_notes' => $data['delivery_notes'] ?? null,
                    'delivery_photos' => $data['delivery_photos'] ?? [],
                ]);

                $cuantas = count($data['delivery_photos'] ?? []);

                Notification::make()
                    ->title('Entrega registrada')
                    ->body($cuantas > 0
                        ? "Quedaron {$cuantas} " . ($cuantas === 1 ? 'foto' : 'fotos') . ' como respaldo.'
                        : 'Sin fotos: si te lo devuelven dañado no vas a tener con qué comparar.')
                    ->color($cuantas > 0 ? 'success' : 'warning')
                    ->success()
                    ->send();
            });
    }

    /** El acuse de una entrega ya registrada, para consultarlo después. */
    public static function acuse(\Closure $resolver): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('ver_entrega')
            ->label('Entrega')
            ->icon('heroicon-o-clipboard-document-check')
            ->color('gray')
            ->visible(fn ($record) => $resolver($record)?->isDelivered() ?? false)
            ->modalHeading('Cómo se entregó')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar')
            ->infolist(fn ($record) => [
                \Filament\Infolists\Components\TextEntry::make('entregado')
                    ->label('Entregado el')
                    ->state(fn () => $resolver($record)->delivered_at->format('d/m/Y H:i')),
                \Filament\Infolists\Components\TextEntry::make('notas')
                    ->label('Notas')
                    ->state(fn () => $resolver($record)->delivery_notes ?: 'Sin notas.'),
                \Filament\Infolists\Components\ImageEntry::make('fotos')
                    ->label('Fotos')
                    ->disk('privado')
                    ->state(fn () => $resolver($record)->delivery_photos ?: [])
                    ->columnSpanFull(),
            ]);
    }
}
