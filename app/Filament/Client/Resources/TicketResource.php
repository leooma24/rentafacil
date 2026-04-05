<?php

namespace App\Filament\Client\Resources;

use App\Filament\Client\Resources\TicketResource\Pages;
use App\Models\Incident;
use App\Models\Rental;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components as InfolistComponent;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TicketResource extends Resource
{
    protected static ?string $model = Incident::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';
    protected static ?string $modelLabel = 'Ticket';
    protected static ?string $pluralModelLabel = 'Mis Tickets';
    protected static ?string $navigationLabel = 'Reportar Problema';
    protected static ?string $slug = 'tickets';

    public static function form(Form $form): Form
    {
        $user = auth()->user();
        $companyIds = $user->companies()->pluck('companies.id');

        $rentals = Rental::whereIn('company_id', $companyIds)
            ->whereIn('status', ['activa', 'vencida'])
            ->with('washingMachine')
            ->get();

        return $form
            ->schema([
                Forms\Components\Section::make('Reportar un Problema')
                    ->description('Describe el problema que tienes con tu lavadora')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Asunto')
                            ->placeholder('Ej: La lavadora no enciende')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('washing_machine_id')
                            ->label('Lavadora')
                            ->options(
                                $rentals->mapWithKeys(fn ($r) => [
                                    $r->washing_machine_id => $r->washingMachine->machine_code . ' - ' . $r->washingMachine->brand,
                                ])
                            )
                            ->required(),
                        Forms\Components\Select::make('type')
                            ->label('Tipo de Problema')
                            ->options([
                                'mecánica' => 'Mecánica (no gira, ruidos, vibraciones)',
                                'eléctrica' => 'Eléctrica (no enciende, cortos)',
                                'software' => 'Software (panel no responde)',
                                'otra' => 'Otra',
                            ])
                            ->required(),
                        Forms\Components\Select::make('priority')
                            ->label('Urgencia')
                            ->options([
                                'baja' => 'Baja - Puede esperar',
                                'media' => 'Media - Necesito solución pronto',
                                'alta' => 'Alta - No puedo usar la lavadora',
                            ])
                            ->default('media')
                            ->required(),
                        Forms\Components\Textarea::make('description')
                            ->label('Descripción del problema')
                            ->placeholder('Describe con detalle qué sucede...')
                            ->rows(4)
                            ->required()
                            ->maxLength(65535),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                $companyIds = auth()->user()->companies()->pluck('companies.id');
                $query->whereIn('company_id', $companyIds);
            })
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Asunto')
                    ->searchable(),
                Tables\Columns\TextColumn::make('washingMachine.machine_code')
                    ->label('Lavadora'),
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge(),
                Tables\Columns\TextColumn::make('priority')
                    ->label('Urgencia')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'baja' => 'success',
                        'media' => 'warning',
                        'alta' => 'danger',
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'abierta' => 'warning',
                        'en_progreso' => 'info',
                        'cerrada' => 'success',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->since(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                InfolistComponent\Section::make('Detalle del Ticket')
                    ->schema([
                        TextEntry::make('title')->label('Asunto'),
                        TextEntry::make('washingMachine.machine_code')->label('Lavadora'),
                        TextEntry::make('type')->label('Tipo')->badge(),
                        TextEntry::make('priority')->label('Urgencia')->badge(),
                        TextEntry::make('status')->label('Estado')->badge(),
                        TextEntry::make('description')->label('Descripción')->columnSpanFull(),
                        TextEntry::make('comments')
                            ->label('Respuesta del soporte')
                            ->default('Sin respuesta aún')
                            ->columnSpanFull(),
                        TextEntry::make('resolved_at')
                            ->label('Resuelto el')
                            ->placeholder('Pendiente'),
                        TextEntry::make('created_at')
                            ->label('Creado el')
                            ->dateTime(),
                    ])
                    ->columns(3),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTickets::route('/'),
            'create' => Pages\CreateTicket::route('/create'),
            'view' => Pages\ViewTicket::route('/{record}'),
        ];
    }
}
