<?php

namespace App\Filament\Resources;

use App\Exports\CustomersExport;
use App\Imports\CustomersImport;
use App\Filament\Resources\Components\Forms\AddressForm;
use App\Filament\Resources\CustomerResource\Pages;
use App\Filament\Resources\CustomerResource\RelationManagers\WashingMachinesRelationManager;
use App\Models\Customer;
use App\Models\Township;
use App\Models\Neighborhood;
use App\Services\UbicarClientes;
use Filament\Facades\Filament;
use Filament\Forms;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Maatwebsite\Excel\Facades\Excel;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $navigationIcon = 'heroicon-s-user-group';

    protected static ?string $navigationGroup = 'Gestión Principal';
    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Cliente';
    protected static ?string $pluralModelLabel = 'Clientes';
    protected static ?string $navigationLabel = 'Clientes';
    protected static ?string $slug = 'clientes';
    protected static ?string $recordTitleAttribute = 'name';

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email', 'phone'];
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return [
            'Email' => $record->email ?? '-',
            'Teléfono' => $record->phone ?? '-',
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Información del Cliente')
                    ->columns('3')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->label('Correo Electrónico')
                            ->email()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('phone')
                            ->label('Teléfono')
                            ->tel()
                            ->maxLength(15),
                    ]),
                Section::make('Dirección')
                    ->collapsible()
                    ->schema([AddressForm::getFormAddressFields()]),


            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // La columna "Debe" necesita rentas y pagos de cada fila; sin esto son
            // tres consultas por cliente.
            ->modifyQueryUsing(fn ($query) => $query->with([
                'rentals.payments',
                'rentals.washingMachine',
                'company.settings',
            ]))
            ->columns([
                // En celular esta columna carga sola con el saldo como subtítulo,
                // para que la fila quepa y el botón no se salga de la pantalla.
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->description(function (Customer $record): string {
                        $saldo = app(\App\Support\AccountStatement::class)->forCustomer($record)->total;

                        return $saldo > 0
                            ? 'Debe $' . number_format($saldo, 2)
                            : 'Al corriente';
                    })
                    ->searchable(),
                // Se esconden en celular para dejarle lugar al saldo y al botón.
                Tables\Columns\TextColumn::make('email')
                    ->label('Correo Electrónico')
                    ->visibleFrom('md')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Teléfono')
                    ->visibleFrom('md')
                    ->searchable(),
                Tables\Columns\TextColumn::make('debt')
                    ->label('Debe')
                    ->visibleFrom('md')
                    // El saldo se calcula, no vive en la base de datos, así que esta
                    // columna no se puede ordenar. Para ver quién debe más está el
                    // widget "Clientes con adeudo" del escritorio.
                    ->state(fn (Customer $record) => app(\App\Support\AccountStatement::class)
                        ->forCustomer($record)->total)
                    ->formatStateUsing(fn ($state) => '$' . number_format((float) $state, 2))
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'gray')
                    ->weight(fn ($state) => $state > 0 ? 'bold' : 'normal'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha de Registro')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Última Actualización')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                Tables\Actions\Action::make('export')
                    ->label('Exportar')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn () => Excel::download(
                        new CustomersExport(Filament::getTenant()->id),
                        'clientes-' . now()->format('Y-m-d') . '.xlsx'
                    )),
                Tables\Actions\Action::make('import')
                    ->label('Importar')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('success')
                    ->form([
                        // Disco privado: el archivo trae la lista de clientes de
                        // la empresa, y storage/app/public se sirve tal cual en
                        // /storage/..., asi que ahi lo bajaria cualquiera.
                        Forms\Components\FileUpload::make('file')
                            ->label('Archivo Excel (.xlsx)')
                            ->disk('local')
                            ->directory('importaciones')
                            ->visibility('private')
                            ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'])
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $file = \Illuminate\Support\Facades\Storage::disk('local')->path($data['file']);

                        Excel::import(new CustomersImport(Filament::getTenant()->id), $file);
                        \Filament\Notifications\Notification::make()
                            ->title('Clientes importados correctamente')
                            ->success()
                            ->send();

                        // El archivo trae la lista completa de la empresa: se
                        // borra en cuanto se leyo, no se queda guardado.
                        \Illuminate\Support\Facades\Storage::disk('local')->delete($data['file']);
                    }),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\Filter::make('con_adeudo')
                    ->label('Solo con adeudo')
                    ->query(fn (\Illuminate\Database\Eloquent\Builder $query) => $query
                        ->whereHas('rentals', fn ($rentals) => $rentals
                            ->whereIn('status', ['activa', 'vencida'])
                            ->whereDate('end_date', '<', \Carbon\Carbon::today()))),
            ])
            ->actions([
                // El estado de cuenta es a lo que más se entra desde aquí: va suelto.
                Tables\Actions\Action::make('estado_de_cuenta')
                    ->label('Cuenta')
                    ->tooltip('Estado de cuenta')
                    ->icon('heroicon-o-banknotes')
                    ->button()
                    ->color('warning')
                    ->url(fn (Customer $record) => static::getUrl('estado-de-cuenta', ['record' => $record])),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                    Tables\Actions\RestoreAction::make(),
                ]),
            ])
            ->bulkActions([
                // Ubicar en el mapa las direcciones ya capturadas. Lo dispara el
                // dueño y no un proceso automático: buscar una dirección la manda
                // a un servidor ajeno, y esa decisión es suya.
                Tables\Actions\BulkAction::make('ubicar_en_el_mapa')
                    ->label('Ubicar en el mapa')
                    ->icon('heroicon-o-map-pin')
                    ->color('primary')
                    ->modalHeading('Ubicar clientes en el mapa')
                    ->modalDescription(
                        'Se buscará la dirección de cada cliente en OpenStreetMap para obtener su ubicación. '
                        . 'Con eso podrás incluirlos en la ruta del día. Se procesan hasta '
                        . UbicarClientes::MAXIMO_POR_TANDA . ' por tanda.'
                    )
                    ->modalSubmitActionLabel('Buscar ubicaciones')
                    ->deselectRecordsAfterCompletion()
                    ->action(fn (Collection $records) => app(UbicarClientes::class)->paraTodos($records)),

                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            WashingMachinesRelationManager::class,
            \App\Filament\Resources\CustomerResource\RelationManagers\DocumentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
            'estado-de-cuenta' => Pages\AccountStatementPage::route('/{record}/estado-de-cuenta'),
        ];
    }
}
