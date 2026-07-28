<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use App\Support\PlanUsage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-s-user-group';

    protected static ?string $navigationGroup = 'Administrador';
    protected static ?string $modelLabel = 'Usuario';
    protected static ?string $pluralModelLabel = 'Usuarios';
    protected static ?string $navigationLabel = 'Usuarios';
    protected static ?string $slug = 'usuarios';
    protected static bool $isScopedToTenant = false;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->label('Correo electrónico')
                    ->email()
                    ->required()
                    ->maxLength(255),
                Forms\Components\DateTimePicker::make('email_verified_at')
                    ->label('Correo electrónico verificado')
                    ->nullable(),
                Forms\Components\TextInput::make('password')
                    ->label('Contraseña')
                    ->password()
                    ->required()
                    ->hiddenOn('edit')
                    ->maxLength(255),
                Forms\Components\Select::make('roles')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable()
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // Las columnas de plan necesitan la empresa de cada usuario con su
            // paquete; sin esto son varias consultas por fila.
            ->modifyQueryUsing(fn ($query) => $query->with('companies.companyPackage.package'))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Correo electrónico')
                    ->searchable(),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge(),
                Tables\Columns\TextColumn::make('companies.name')
                    ->label('Compañías')
                    ->badge(),
                Tables\Columns\TextColumn::make('plan')
                    ->label('Plan')
                    ->badge()
                    ->state(fn (User $record) => static::variasEmpresas($record)
                        ? 'varias empresas'
                        : PlanUsage::for($record->companies->first())->planLabel())
                    ->color(fn (User $record) => static::variasEmpresas($record)
                        ? 'gray'
                        : PlanUsage::for($record->companies->first())->planColor()),
                Tables\Columns\TextColumn::make('lavadoras')
                    ->label('Lavadoras')
                    ->state(fn (User $record) => static::variasEmpresas($record)
                        ? '—'
                        : PlanUsage::for($record->companies->first())->machinesLabel())
                    ->color(fn (User $record) => ! static::variasEmpresas($record)
                        && PlanUsage::for($record->companies->first())->machinesMaxed()
                            ? 'danger' : 'gray'),
                Tables\Columns\TextColumn::make('clientes')
                    ->label('Clientes')
                    ->state(fn (User $record) => static::variasEmpresas($record)
                        ? '—'
                        : PlanUsage::for($record->companies->first())->customersLabel())
                    ->color(fn (User $record) => ! static::variasEmpresas($record)
                        && PlanUsage::for($record->companies->first())->customersMaxed()
                            ? 'danger' : 'gray'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registro')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('topados')
                    ->label('Ya topó su límite')
                    // El cupo se calcula, no vive en la base, así que se filtra
                    // sobre la colección ya traída.
                    ->query(fn (Builder $query) => $query->whereIn(
                        'id',
                        User::with('companies')->get()
                            ->filter(fn (User $user) => ! static::variasEmpresas($user)
                                && PlanUsage::for($user->companies->first())->isMaxedOut())
                            ->pluck('id')
                    )),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * El plan es de la empresa, no del usuario. Casi todos tienen una sola; si
     * alguno tuviera más, las columnas lo dicen en vez de escoger una y mentir.
     */
    protected static function variasEmpresas(User $user): bool
    {
        return $user->companies->count() > 1;
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
