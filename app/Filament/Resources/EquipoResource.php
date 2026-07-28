<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EquipoResource\Pages;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

/**
 * La gente que trabaja en la empresa del dueño.
 *
 * UserResource ya existía pero es global y de super_admin: sirve para operar la
 * plataforma, no para que un rentador dé de alta a su cobrador. Hasta ahora no
 * había forma de hacerlo, y por eso las 17 cuentas reales son de una sola
 * persona.
 *
 * Va acotada por empresa con members(), así que un dueño nunca ve gente de otra.
 * Los permisos no pasan por Shield: sus permisos de usuario son de super_admin
 * a propósito, y dárselos al propietario abriría la pantalla global.
 */
class EquipoResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Mi cuenta';

    protected static ?string $navigationLabel = 'Mi equipo';

    protected static ?string $modelLabel = 'persona';

    protected static ?string $pluralModelLabel = 'mi equipo';

    protected static ?string $slug = 'mi-equipo';

    /**
     * Las dos puntas de la relación con la empresa.
     *
     * members() es Company → User y sirve para dar de alta; companies() es la
     * inversa y es con la que Filament acota la consulta. Sin la segunda, la
     * lista truena buscando una relación llamada "company".
     */
    protected static ?string $tenantRelationshipName = 'members';

    protected static ?string $tenantOwnershipRelationshipName = 'companies';

    public static function canAccess(): bool
    {
        return static::esDueno();
    }

    public static function canViewAny(): bool
    {
        return static::esDueno();
    }

    public static function canCreate(): bool
    {
        return static::esDueno();
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return static::esDueno();
    }

    /** Nadie se borra a sí mismo: dejaría la empresa sin dueño. */
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return static::esDueno() && $record->getKey() !== auth()->id();
    }

    private static function esDueno(): bool
    {
        return auth()->user()?->hasAnyRole(['propietario', 'super_admin']) ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nombre')
                ->required()
                ->maxLength(255),

            Forms\Components\TextInput::make('email')
                ->label('Correo electrónico')
                ->email()
                ->required()
                ->unique(ignoreRecord: true)
                ->helperText('Con este correo va a entrar al sistema.')
                ->maxLength(255),

            Forms\Components\TextInput::make('password')
                ->label('Contraseña')
                ->password()
                ->revealable()
                ->minLength(8)
                // Al editar se deja vacía para no cambiarla sin querer.
                ->required(fn (string $operation) => $operation === 'create')
                ->dehydrated(fn (?string $state) => filled($state))
                ->dehydrateStateUsing(fn (string $state) => Hash::make($state))
                ->helperText(fn (string $operation) => $operation === 'create'
                    ? 'Anótala y pásasela: no hay forma de verla después.'
                    : 'Déjala vacía si no la quieres cambiar.'),

            Forms\Components\Select::make('roles')
                ->label('Qué puede hacer')
                ->relationship('roles', 'name', fn ($query) => $query->whereIn('name', ['propietario', 'cobrador']))
                ->options([
                    'cobrador' => 'Cobrador — sale a cobrar y registra los pagos',
                    'propietario' => 'Dueño — puede todo, incluidos precios y reportes',
                ])
                ->getOptionLabelFromRecordUsing(fn ($record) => match ($record->name) {
                    'cobrador' => 'Cobrador — sale a cobrar y registra los pagos',
                    'propietario' => 'Dueño — puede todo, incluidos precios y reportes',
                    default => $record->name,
                })
                ->multiple()
                ->preload()
                ->required()
                ->helperText('El cobrador ve a quién cobrar y registra los pagos, pero no puede cambiar precios, borrar nada ni ver tus reportes.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->description(fn (User $record) => $record->email)
                    ->searchable(),

                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Qué puede hacer')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'cobrador' => 'Cobrador',
                        'propietario' => 'Dueño',
                        'super_admin' => 'Administrador',
                        default => $state,
                    })
                    ->color(fn (string $state) => $state === 'cobrador' ? 'info' : 'success'),

                // Cuánto ha cobrado: es la razón por la que el dueño entra aquí.
                Tables\Columns\TextColumn::make('cobrado_hoy')
                    ->label('Cobró hoy')
                    ->state(fn (User $record) => '$' . number_format(
                        \App\Models\Payment::where('company_id', Filament::getTenant()->id)
                            ->where('collected_by', $record->id)
                            ->where('status', 'completado')
                            ->whereDate('payment_date', today())
                            ->sum('amount'),
                        2
                    ))
                    ->visibleFrom('md'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->label('Quitar')
                    ->modalHeading('Quitar del equipo')
                    ->modalDescription('Ya no va a poder entrar. Los cobros que registró se quedan.'),
            ])
            ->emptyStateHeading('Todavía trabajas solo')
            ->emptyStateDescription('Da de alta a quien sale a cobrar: va a ver su lista del día y registrar los pagos, sin poder tocar precios ni borrar nada.')
            ->emptyStateIcon('heroicon-o-users');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEquipo::route('/'),
            'create' => Pages\CreateEquipo::route('/create'),
            'edit' => Pages\EditEquipo::route('/{record}/edit'),
        ];
    }
}
