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

    public function form(Form $form): Form
    {
        $tenant = Filament::getTenant();
        return $form
            ->schema([
                Forms\Components\Select::make('washing_machine_id')
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
                        'completa' => 'Completa',
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
                    ->color(fn (string $state): string => match ($state) {
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
                        ->requiresConfirmation()
                        ->action(function (array $data, Rental $record) use ($tenant) {
                            $newDate = new Carbon($record->end_date);
                            $newDate->add(7, 'days');
                            $record->end_date = $newDate->format('Y-m-d');
                            $record->save();

                            Notification::make()
                                ->title('Se pago una semana mas de renta')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\Action::make('make_available')
                        ->label('Marcar Disponible')
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
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
