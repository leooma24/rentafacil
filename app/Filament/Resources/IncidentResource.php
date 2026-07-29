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
    protected static ?string $navigationLabel = 'Incidencias';
    protected static ?string $slug = 'incidencias';

    protected static ?int $navigationSort = 4;

    public static function getNavigationBadge(): ?string
    {
        $tenant = Filament::getTenant();
        if (!$tenant) return null;
        $count = $tenant->incidents()->whereIn('status', ['abierta', 'en_progreso'])->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Tickets abiertos';
    }

    public static function form(Form $form): Form
    {
        $tenant = Filament::getTenant();
        return $form
            ->schema([
                Forms\Components\Section::make('Qué pasó')
                    ->description('Como te lo dijo el cliente. Después esto es lo que buscas para acordarte.')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->iconColor('danger')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('El reporte')
                            ->placeholder('No centrifuga')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\Select::make('type')
                            ->label('De qué es')
                            ->options([
                                'mecánica' => 'Mecánica',
                                'eléctrica' => 'Eléctrica',
                                'software' => 'Tablero / programación',
                                'otra' => 'Otra',
                            ])
                            ->default('otra')
                            ->native(false)
                            ->required(),

                        Forms\Components\Select::make('priority')
                            ->label('Qué tan urgente')
                            ->options([
                                'alta' => 'Alta — el cliente no la puede usar',
                                'media' => 'Media — funciona a medias',
                                'baja' => 'Baja — puede esperar',
                            ])
                            ->default('media')
                            ->native(false)
                            ->required(),

                        Forms\Components\Textarea::make('description')
                            ->label('Detalle')
                            ->rows(3)
                            ->maxLength(65535)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('De qué equipo y quién lo atiende')
                    ->icon('heroicon-o-cube')
                    ->iconColor('primary')
                    ->schema([
                        Forms\Components\Select::make('washing_machine_id')
                            ->label('Equipo')
                            ->options(function () use ($tenant) {
                                // Con quién está: casi siempre el reporte llega
                                // por nombre del cliente y no por código.
                                return $tenant->washingMachines()
                                    ->with('activeRental.customer')
                                    ->orderBy('machine_code')
                                    ->get()
                                    ->mapWithKeys(function ($equipo) {
                                        $etiqueta = "{$equipo->machine_code} · {$equipo->kindLabel()}";
                                        $cliente = $equipo->activeRental?->customer?->name;

                                        return [$equipo->id => $cliente
                                            ? "{$etiqueta} — {$cliente}"
                                            : $etiqueta];
                                    });
                            })
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\Select::make('assigned_to')
                            ->label('Quién lo ve')
                            ->options(fn () => $tenant->members()->pluck('name', 'users.id'))
                            ->native(false)
                            ->nullable()
                            ->helperText('Opcional.'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Cómo va')
                    ->icon('heroicon-o-check-circle')
                    ->iconColor('success')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Estado')
                            ->options([
                                'abierta' => 'Abierta',
                                'en_progreso' => 'En proceso',
                                'cerrada' => 'Cerrada',
                            ])
                            ->default('abierta')
                            ->native(false)
                            ->required()
                            ->live()
                            // Al cerrarla se sella la fecha sola. Escribirla a
                            // mano es de donde salieron reportes resueltos antes
                            // de haberse abierto, que dejaban el promedio de
                            // atención en negativo.
                            ->afterStateUpdated(function (?string $state, Forms\Set $set, Forms\Get $get) {
                                if ($state === 'cerrada' && blank($get('resolved_at'))) {
                                    $set('resolved_at', now()->format('Y-m-d H:i:s'));
                                }

                                if ($state !== 'cerrada') {
                                    $set('resolved_at', null);
                                }
                            }),

                        Forms\Components\DateTimePicker::make('resolved_at')
                            ->label('Cuándo se resolvió')
                            ->native(false)
                            ->displayFormat('d/m/Y H:i')
                            ->seconds(false)
                            ->nullable()
                            ->visible(fn (Forms\Get $get) => $get('status') === 'cerrada')
                            // Un reporte no se puede resolver antes de existir.
                            ->minDate(fn (?Incident $record) => $record?->created_at)
                            ->maxDate(now()),

                        Forms\Components\Textarea::make('comments')
                            ->label('Qué se hizo')
                            ->rows(2)
                            ->maxLength(65535)
                            ->nullable()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Fotos')
                    ->description('Cómo se ve el problema. Sirven para pedir la refacción sin ir a verlo otra vez.')
                    ->icon('heroicon-o-camera')
                    ->iconColor('gray')
                    ->collapsible()
                    ->collapsed(fn (?Incident $record) => blank($record?->photos))
                    ->schema([
                        Forms\Components\FileUpload::make('photos')
                            ->hiddenLabel()
                            ->disk('privado')
                            ->image()
                            ->multiple()
                            ->maxFiles(5)
                            ->directory('incidents')
                            ->columnSpanFull(),
                    ]),
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
                        'en_progreso' => 'En Progreso',
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
                    ->slideOver()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['user_id'] = $data['user_id'] ?? auth()->id();
                        return $data;
                    }),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->slideOver()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['user_id'] = auth()->id();
                        return $data;
                    }),
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
                        InfolistComponent\ImageEntry::make('photos')
                            ->disk('privado')
                            ->label('Fotos')
                            ->disk('public')
                            ->columnSpanFull(),
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
