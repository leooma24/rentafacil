<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExpenseResource\Pages;
use App\Models\Expense;
use App\Support\Acceso;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Los gastos del negocio.
 *
 * Es de dueño: quien sale a cobrar no tiene por qué ver los sueldos ni la renta
 * del local.
 */
class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-trending-down';

    protected static ?string $navigationGroup = 'Finanzas';

    protected static ?string $navigationLabel = 'Gastos';

    protected static ?string $modelLabel = 'gasto';

    protected static ?string $pluralModelLabel = 'gastos';

    protected static ?string $slug = 'gastos';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return Acceso::soloDueno();
    }

    public static function canViewAny(): bool
    {
        return Acceso::soloDueno();
    }

    public static function canCreate(): bool
    {
        return Acceso::soloDueno();
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return Acceso::soloDueno();
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return Acceso::soloDueno();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('category')
                ->label('En qué')
                ->options(Expense::CATEGORIAS)
                ->required()
                ->native(false)
                ->searchable(),

            Forms\Components\TextInput::make('amount')
                ->label('Cuánto')
                ->numeric()
                ->minValue(0)
                ->step('0.01')
                ->prefix('$')
                ->required(),

            Forms\Components\TextInput::make('description')
                ->label('De qué se trata')
                ->placeholder('Gasolina de la ruta del norte')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),

            Forms\Components\DatePicker::make('expense_date')
                ->label('Cuándo')
                ->native(false)
                ->default(today())
                ->maxDate(today())
                ->required(),

            Forms\Components\Select::make('payment_method')
                ->label('Cómo lo pagaste')
                ->options([
                    'Efectivo' => 'Efectivo',
                    'transferencia' => 'Transferencia',
                    'tarjeta' => 'Tarjeta',
                ])
                ->native(false),

            Forms\Components\Textarea::make('notes')
                ->label('Notas')
                ->rows(2)
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('expense_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('expense_date')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),

                // En celular esta columna carga sola con la categoría de subtítulo.
                Tables\Columns\TextColumn::make('description')
                    ->label('Gasto')
                    ->description(fn (Expense $record) => $record->categoriaLegible())
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('category')
                    ->label('En qué')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Expense::CATEGORIAS[$state] ?? $state)
                    ->visibleFrom('lg'),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Monto')
                    ->money('MXN')
                    ->weight('bold')
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Lo registró')
                    ->visibleFrom('xl'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label('En qué')
                    ->options(Expense::CATEGORIAS),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Todavía no registras gastos')
            ->emptyStateDescription('Anota la gasolina, los sueldos y las refacciones. Sin eso, lo que cobras se lee como ganancia y no lo es.')
            ->emptyStateIcon('heroicon-o-arrow-trending-down');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExpenses::route('/'),
            'create' => Pages\CreateExpense::route('/create'),
            'edit' => Pages\EditExpense::route('/{record}/edit'),
        ];
    }
}
