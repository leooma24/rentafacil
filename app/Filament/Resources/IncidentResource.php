<?php

namespace App\Filament\Resources;

use App\Filament\Resources\IncidentResource\Pages;
use App\Filament\Resources\IncidentResource\RelationManagers;
use App\Models\Incident;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components as InfolistComponent;
use Filament\Infolists\Components\TextEntry;
use Filament\Facades\Filament;


class IncidentResource extends Resource
{
    protected static ?string $model = Incident::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-circle';

    protected static ?string $navigationGroup = 'Gestión Principal';
    protected static ?string $modelLabel = 'Incidente';
    protected static ?string $pluralModelLabel = 'Incidentes';
    protected static ?string $navigationLabel = 'Incidentes';
    protected static ?string $slug = 'incidencias';

    protected static ?int $navigationSort = 4;


    public static function form(Form $form): Form
    {
        $tenant = Filament::getTenant();
        return $form
            ->schema([
                Forms\Components\Section::make('Detalles de la Incidencia')
                    ->description('Información detallada sobre la incidencia reportada')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Título')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('description')
                            ->label('Descripción')
                            ->maxLength(65535),
                        Forms\Components\Select::make('status')
                            ->label('Estado')
                            ->options([
                                'abierta' => 'Abierta',
                                'en_progreso' => 'En Progreso',
                                'cerrada' => 'Cerrada',
                            ])
                            ->default('abierta')
                            ->required(),
                        Forms\Components\Select::make('priority')
                            ->label('Prioridad')
                            ->options([
                                'baja' => 'Baja',
                                'media' => 'Media',
                                'alta' => 'Alta',
                            ])
                            ->default('media')
                            ->required(),
                        Forms\Components\Select::make('washing_machine_id')
                            ->label('Lavadora')
                            ->options(function ($record) use ($tenant) {
                                return $tenant->washingMachines()->get()
                                    ->pluck('machine_code', 'id');
                            })
                            ->required(),
                        Forms\Components\Select::make('assigned_to')
                            ->label('Asignado a')
                            ->options(function ($record) use ($tenant) {
                                return $tenant->members()->get()
                                    ->pluck('name', 'id');
                            })
                            ->nullable(),
                        Forms\Components\Select::make('type')
                            ->label('Tipo de Incidencia')
                            ->options([
                                'mecánica' => 'Mecánica',
                                'eléctrica' => 'Eléctrica',
                                'software' => 'Software',
                                'otra' => 'Otra',
                            ])
                            ->default('otra')
                            ->required(),
                        Forms\Components\DateTimePicker::make('resolved_at')
                            ->label('Fecha de Resolución')
                            ->nullable(),
                        Forms\Components\Textarea::make('comments')
                            ->label('Comentarios')
                            ->maxLength(65535)
                            ->nullable(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Título')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'abierta' => 'danger',
                        'en_progreso' => 'success',
                        'cerrada' => 'danger',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('priority')
                    ->label('Prioridad')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'baja' => 'success',
                        'media' => 'warning',
                        'alta' => 'danger',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Autor')
                    ->sortable(),
                Tables\Columns\TextColumn::make('washingMachine.machine_code')
                    ->label('Lavadora')
                    ->sortable(),
                Tables\Columns\TextColumn::make('assignedTo.name')
                    ->label('Asignado a')
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('resolved_at')
                    ->label('Fecha de Resolución')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado el')
                    ->datetime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'abierta' => 'Abierta',
                        'in_progress' => 'En Progreso',
                        'cerrada' => 'Cerrada',
                    ]),
                Tables\Filters\SelectFilter::make('priority')
                    ->label('Prioridad')
                    ->options([
                        'baja' => 'Baja',
                        'media' => 'Media',
                        'alta' => 'Alta',
                    ]),
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        'mecánica' => 'Mecánica',
                        'electrica' => 'Eléctrica',
                        'software' => 'Software',
                        'otra' => 'Otra',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->slideOver(),
                Tables\Actions\EditAction::make()
                    ->slideOver(),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                InfolistComponent\Section::make('Detalles de la Incidencia')
                    ->description('Información detallada sobre la incidencia reportada')
                    ->schema([
                        TextEntry::make('title')->label('Título'),
                        TextEntry::make('description')->label('Descripción'),
                        TextEntry::make('status')->label('Estado')->formatStateUsing(fn($state) => ucfirst($state)),
                        TextEntry::make('priority')->label('Prioridad')->formatStateUsing(fn($state) => ucfirst($state)),
                        TextEntry::make('user.name')->label('Usuario'),
                        TextEntry::make('washingMachine.machine_code')->label('Lavadora'),
                        TextEntry::make('assignedTo.name')->label('Asignado a')->formatStateUsing(fn($state) => $state ?? 'No asignado'),
                        TextEntry::make('type')->label('Tipo de Incidencia')->formatStateUsing(fn($state) => ucfirst($state)),
                        TextEntry::make('resolved_at')->label('Fecha de Resolución')->formatStateUsing(fn($state) => $state ? $state->format('d/m/Y H:i') : 'No resuelta'),
                        TextEntry::make('comments')->label('Comentarios'),
                        TextEntry::make('created_at')->label('Creado el')->formatStateUsing(fn($state) => $state->format('d/m/Y H:i')),
                        TextEntry::make('updated_at')->label('Actualizado el')->formatStateUsing(fn($state) => $state->format('d/m/Y H:i')),
                    ])
                    ->columns(3),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListIncidents::route('/'),
            //'create' => Pages\CreateIncident::route('/create'),
            //'edit' => Pages\EditIncident::route('/{record}/edit'),
            //'view' => Pages\ViewIncident::route('/{record}'),
        ];
    }
}
