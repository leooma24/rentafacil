<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use App\Models\Rental;
use App\Models\WashingMachine;
use Carbon\Carbon;
use Filament\Actions\CreateAction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Facades\Filament;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Tables\Actions\ActionGroup;

class WashingMachinesRelationManager extends RelationManager
{
    protected static string $relationship = 'rentals';
    protected static ?string $modelLabel = 'Renta';

    // Sin esto la pestaña sale con el nombre de la relación —"Rentals"—, que es
    // la única palabra en inglés que veía el cliente en toda la ficha.
    protected static ?string $title = 'Rentas';

    public function form(Form $form): Form
    {
        $tenant = Filament::getTenant();
        return $form
            ->schema([
                Forms\Components\Select::make('washing_machine_id')
                    ->label('Lavadora')
                    ->required()
                    ->searchable()
                    ->options(
                        $tenant->washingMachines()->where('status', 'disponible')->get()->mapWithKeys(function ($washingMachine) {
                            return [$washingMachine->id => $washingMachine->machine_code . ' ' . $washingMachine->brand];
                        })
                    ),
                Forms\Components\DatePicker::make('start_date')
                    ->label('Fecha de Inicio')
                    ->native(false)
                    ->format('Y-m-d')
                    ->required()
                    ->default(function () {
                        return new Carbon();
                    })
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (string $operation, string $state, Forms\Set $set, Forms\Get $get) {
                        $startDate = new Carbon($get('start_date'));
                        $set('end_date', $startDate->add(7, 'days')->format('Y-m-d'));
                    }),
                Forms\Components\DatePicker::make('end_date')
                    ->label('Fecha de Fin')
                    ->native(false)
                    ->format('Y-m-d')
                    ->required()
                    ->default(function () {
                        return (new Carbon())->add(7, 'days');
                    }),
                Forms\Components\Select::make('status')
                    ->label('Estatus')
                    ->options([
                        'activa' => 'Activa',
                        'completada' => 'Completada',
                        'cancelada' => 'Cancelada',
                    ])
                    ->default('activa')
                    ->required(),
                Forms\Components\Textarea::make('notes')
                    ->label('Notas')
                    ->columnSpanFull(),

            ]);
    }

    public function table(Table $table): Table
    {
        $tenant = Filament::getTenant();
        return $table
            ->modifyQueryUsing(function (Builder $query) use ($tenant) {
                $query->whereIn('status', ['activa', 'vencida']);
            })
            ->recordTitleAttribute('start_date')
            ->columns([
                Tables\Columns\TextColumn::make('washingMachine.machine_code'),
                Tables\Columns\TextColumn::make('washingMachine.brand'),
                Tables\Columns\TextColumn::make('washingMachine.status')
                    ->badge(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'activa' => 'primary',
                        'vencida' => 'danger',
                        'completada' => 'info',
                        'cancelada' => 'danger',
                    }),
                Tables\Columns\TextColumn::make('start_date')->date(),
                Tables\Columns\TextColumn::make('end_date')->date(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->after(function (Rental $record) use ($tenant) {

                        $record->company_id = $tenant->id;
                        $record->save();

                        $washingMachine = $record->washingMachine;
                        $washingMachine->status = 'rentada';
                        $washingMachine->save();

                        Notification::make()
                            ->title('Se rento una lavadora')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                ActionGroup::make([
                    Tables\Actions\Action::make('extend_rent')
                        ->label('Extender Renta')
                        ->icon('heroicon-o-calendar')
                        ->form([
                            //
                            Forms\Components\TextInput::make('price')
                                ->label('Precio de renta')
                                ->default($tenant->settings->price),
                            Forms\Components\TextInput::make('days')
                                ->label('Días de renta')
                                ->default($tenant->settings->days_per_payment),

                        ])
                        ->action(function (array $data, Rental $record) use ($tenant) {
                            $newDate = new Carbon($record->end_date);
                            $newDate->add($data['days'], 'days');
                            $record->end_date = $newDate->format('Y-m-d');
                            $record->save();

                            Notification::make()
                                ->title('Se pago un periodo mas de renta')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\Action::make('make_available')
                        ->label('Cancelar Renta')
                        ->visible(fn(Rental $record) => $record->status === 'activa')
                        ->icon('heroicon-s-check-circle')
                        ->requiresConfirmation()
                        ->action(function (array $data, Rental $record) use ($tenant) {
                            $record->end_date = new Carbon();
                            $record->status = 'cancelada';
                            $record->save();

                            $record->washingMachine->update(['status' => 'disponible']);

                            Notification::make()
                                ->title('La lavadora esta disponible')
                                ->success()
                                ->send();
                        }),
                    // Este era el atajo peligroso: sólo aparecía con la renta
                    // vencida —o sea, justo con los morosos— y cerraba la renta
                    // con una simple confirmación. El adeudo se borraba sin que
                    // nadie lo viera, porque se deduce de end_date y cerrar la
                    // renta mueve esa fecha a hoy.
                    //
                    // Ahora pasa por la misma lógica que el botón de Equipos, y
                    // pregunta qué hacer con lo que quedó debiendo.
                    Tables\Actions\Action::make('pick_up')
                        ->visible(fn (Rental $record) => in_array($record->status, ['activa', 'vencida']))
                        ->label('Recoger equipo')
                        ->icon('heroicon-s-check-circle')
                        ->color('success')
                        ->modalHeading('Recoger el equipo')
                        ->modalDescription('La renta se cierra y el equipo queda en revisión.')
                        ->modalSubmitActionLabel('Recoger')
                        ->form(fn (Rental $record) => \App\Filament\Resources\WashingMachineResource::preguntaDelAdeudo($record))
                        ->action(function (array $data, Rental $record) {
                            $debia = app(\App\Support\Recoleccion::class)->ejecutar(
                                $record,
                                quedaronEnPaz: ($data['adeudo'] ?? 'anotado') === 'perdonado',
                            );

                            Notification::make()
                                ->title('Equipo recogido y en revisión')
                                ->body($debia > 0
                                    ? (($data['adeudo'] ?? 'anotado') === 'perdonado'
                                        ? 'Le perdonaste $' . number_format($debia, 2) . '.'
                                        : 'Quedó anotado que te debe $' . number_format($debia, 2) . '.')
                                    : 'El cliente quedó al corriente.')
                                ->success()
                                ->persistent()
                                ->send();
                        }),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
