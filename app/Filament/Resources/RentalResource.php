<?php

namespace App\Filament\Resources;

use App\Exports\RentalsExport;
use App\Filament\Resources\RentalResource\Pages;
use App\Filament\Resources\RentalResource\RelationManagers;
use App\Models\Rental;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Facades\Filament;
use Maatwebsite\Excel\Facades\Excel;

class RentalResource extends Resource
{
    protected static ?string $model = Rental::class;

    protected static ?string $navigationIcon = 'heroicon-s-currency-dollar';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationGroup = 'Gestión Principal';
    protected static ?string $modelLabel = 'Renta';
    protected static ?string $pluralModelLabel = 'Rentas';
    protected static ?string $navigationLabel = 'Mis Rentas';
    protected static ?string $slug = 'mis-rentas';

    public static function getNavigationBadge(): ?string
    {
        $tenant = Filament::getTenant();
        if (!$tenant) return null;
        $count = $tenant->rentals()->where('status', 'vencida')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Rentas vencidas';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['customer.name', 'washingMachine.machine_code'];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        return ($record->customer?->name ?? 'Cliente') . ' - ' . ($record->washingMachine?->machine_code ?? '');
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return [
            'Estado' => ucfirst($record->status),
            'Vence' => $record->end_date ? \Carbon\Carbon::parse($record->end_date)->format('d/m/Y') : '-',
        ];
    }

    public static function form(Form $form): Form
    {
        $tenant = Filament::getTenant();
        return $form
            ->schema([
                //
                Forms\Components\Select::make('customer_id')
                    ->label('Cliente')
                    ->options(
                        $tenant->customers()->pluck('name', 'id')
                    )
                    ->required(),
                Forms\Components\Select::make('washing_machine_id')
                    ->label('Lavadora')
                    ->options(function ($record) use ($tenant) {
                        $options = $tenant->washingMachines()
                            ->where('status', 'disponible');
                        if ($record) {
                            $options->orWhere('id', $record->washing_machine_id);
                        }
                        return $options->get()
                            ->pluck('machine_code', 'id');
                    })
                    ->required(),
                Forms\Components\DatePicker::make('start_date')
                    ->label('Fecha de Inicio')
                    ->default(now())
                    ->native(false)
                    ->format('Y-m-d')
                    ->required(),
                Forms\Components\DatePicker::make('end_date')
                    ->label('Fecha de Fin')
                    ->native(false)
                    ->format('Y-m-d')
                    ->afterOrEqual('start_date'),
                Forms\Components\Select::make('status')
                    ->label('Estatus')
                    ->options([
                        'activa' => 'Activa',
                        'vencida' => 'Vencida',
                        'completada' => 'Completada',
                        'cancelada' => 'Cancelada',
                    ])
                    ->default('activa')
                    ->hiddenOn(['edit'])
                    ->required(),
                Forms\Components\Textarea::make('notes')
                    ->label('Notas')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                //
                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('washingMachine.machine_code')
                    ->label('Lavadora')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('start_date')
                    ->label('Fecha de Inicio')
                    ->date()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_date')
                    ->label('Fecha de Fin')
                    ->date()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->searchable()
                    ->sortable(),

            ])
            ->headerActions([
                Tables\Actions\Action::make('export')
                    ->label('Exportar Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn () => Excel::download(
                        new RentalsExport(Filament::getTenant()->id),
                        'rentas-' . now()->format('Y-m-d') . '.xlsx'
                    )),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'activa' => 'Activa',
                        'vencida' => 'Vencida',
                        'completada' => 'Completada',
                        'cancelada' => 'Cancelada',
                    ]),
                Tables\Filters\Filter::make('end_date')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('Vence desde'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Vence hasta'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('end_date', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('end_date', '<=', $date));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators[] = Tables\Filters\Indicator::make('Desde ' . \Carbon\Carbon::parse($data['from'])->format('d/m/Y'));
                        }
                        if ($data['until'] ?? null) {
                            $indicators[] = Tables\Filters\Indicator::make('Hasta ' . \Carbon\Carbon::parse($data['until'])->format('d/m/Y'));
                        }
                        return $indicators;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('download_contract')
                    ->label('Contrato')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->url(fn ($record) => route('contract.download', $record))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    //Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRentals::route('/'),
            'create' => Pages\CreateRental::route('/create'),
            'edit' => Pages\EditRental::route('/{record}/edit'),
        ];
    }
}
