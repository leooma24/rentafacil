<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProspectiveClientResource\Pages;
use App\Models\Company;
use App\Models\Package;
use App\Models\ProspectiveClient;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class ProspectiveClientResource extends Resource
{
    protected static ?string $model = ProspectiveClient::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-plus';
    protected static ?string $navigationGroup = 'Administrador';
    protected static ?string $modelLabel = 'Prospecto';
    protected static ?string $pluralModelLabel = 'Prospectos';
    protected static ?string $navigationLabel = 'Prospectos';
    protected static ?string $slug = 'prospectos';

    protected static bool $isScopedToTenant = false;

    public static function canAccess(): bool
    {
        return auth()->user()->hasRole('super_admin');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = ProspectiveClient::whereIn('status', ['nuevo', 'interesado'])->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos de Contacto')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('phone')
                            ->label('Teléfono')
                            ->tel()
                            ->maxLength(255),
                    ]),
                Forms\Components\Section::make('Datos del Negocio')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('business_name')
                            ->label('Nombre del Negocio'),
                        Forms\Components\TextInput::make('city')
                            ->label('Ciudad'),
                        Forms\Components\TextInput::make('num_washers')
                            ->label('Lavadoras que tiene/quiere')
                            ->numeric(),
                    ]),
                Forms\Components\Section::make('Seguimiento')
                    ->columns(3)
                    ->schema([
                        Forms\Components\Select::make('source')
                            ->label('Fuente')
                            ->options([
                                'facebook' => 'Facebook',
                                'instagram' => 'Instagram',
                                'whatsapp' => 'WhatsApp',
                                'google' => 'Google',
                                'landing' => 'Landing Page',
                                'referido' => 'Referido',
                                'web_scraping' => 'Web/Scraping',
                                'otro' => 'Otro',
                            ])
                            ->required(),
                        Forms\Components\Select::make('status')
                            ->label('Estatus')
                            ->options([
                                'nuevo' => 'Nuevo',
                                'contactado' => 'Contactado',
                                'seguimiento' => 'En Seguimiento',
                                'interesado' => 'Interesado',
                                'demo' => 'Demo Agendada',
                                'no_interesado' => 'No Interesado',
                                'cliente' => 'Cliente',
                            ])
                            ->default('nuevo')
                            ->required(),
                        Forms\Components\DateTimePicker::make('last_contacted_at')
                            ->label('Último contacto'),
                        Forms\Components\Textarea::make('notes')
                            ->label('Notas')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Teléfono')
                    ->searchable(),
                Tables\Columns\TextColumn::make('city')
                    ->label('Ciudad')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('business_name')
                    ->label('Negocio')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('num_washers')
                    ->label('Lavadoras')
                    ->alignCenter()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('source')
                    ->label('Fuente')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'facebook' => 'info',
                        'instagram' => 'danger',
                        'whatsapp' => 'success',
                        'google' => 'warning',
                        'landing' => 'primary',
                        'referido' => 'gray',
                        'web_scraping' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'nuevo' => 'info',
                        'contactado' => 'warning',
                        'seguimiento' => 'warning',
                        'interesado' => 'success',
                        'demo' => 'success',
                        'no_interesado' => 'danger',
                        'cliente' => 'primary',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('last_contacted_at')
                    ->label('Último contacto')
                    ->since()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estatus')
                    ->options([
                        'nuevo' => 'Nuevo',
                        'contactado' => 'Contactado',
                        'seguimiento' => 'En Seguimiento',
                        'interesado' => 'Interesado',
                        'demo' => 'Demo Agendada',
                        'no_interesado' => 'No Interesado',
                        'cliente' => 'Cliente',
                    ]),
                Tables\Filters\SelectFilter::make('source')
                    ->label('Fuente')
                    ->options([
                        'facebook' => 'Facebook',
                        'instagram' => 'Instagram',
                        'whatsapp' => 'WhatsApp',
                        'google' => 'Google',
                        'landing' => 'Landing Page',
                        'referido' => 'Referido',
                        'web_scraping' => 'Web/Scraping',
                        'otro' => 'Otro',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                // WhatsApp action
                Tables\Actions\Action::make('whatsapp')
                    ->label('WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('success')
                    ->visible(fn ($record) => $record->phone && $record->status !== 'cliente')
                    ->url(fn ($record) => 'https://wa.me/' . preg_replace('/[^0-9]/', '', (strlen(preg_replace('/[^0-9]/', '', $record->phone)) === 10 ? '52' : '') . $record->phone) . '?text=' . urlencode("Hola {$record->name}, te contactamos de Renta Fácil. Tenemos una plataforma para gestionar tu negocio de renta de lavadoras. ¿Te gustaría conocer más?"))
                    ->openUrlInNewTab()
                    ->after(function ($record) {
                        $record->update([
                            'last_contacted_at' => now(),
                            'status' => $record->status === 'nuevo' ? 'contactado' : $record->status,
                        ]);
                    }),

                // Convert to user
                Tables\Actions\Action::make('convert')
                    ->label('Convertir a Usuario')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('primary')
                    ->visible(fn ($record) => !$record->isConverted() && $record->email)
                    ->requiresConfirmation()
                    ->modalHeading('Convertir Prospecto a Usuario')
                    ->modalDescription(fn ($record) => "Se creará una cuenta para {$record->name} ({$record->email}) con una empresa y 15 días de prueba gratis.")
                    ->form([
                        Forms\Components\TextInput::make('password')
                            ->label('Contraseña temporal')
                            ->default(fn () => 'RentaFacil' . rand(1000, 9999))
                            ->required(),
                    ])
                    ->action(function (array $data, ProspectiveClient $record) {
                        // Check if user already exists
                        $existingUser = User::where('email', $record->email)->first();
                        if ($existingUser) {
                            Notification::make()
                                ->title('Ya existe un usuario con ese email')
                                ->danger()
                                ->send();
                            return;
                        }

                        // Create user
                        $user = User::create([
                            'name' => $record->name,
                            'email' => $record->email,
                            'password' => Hash::make($data['password']),
                        ]);
                        $user->assignRole('propietario');

                        // Create company
                        $company = Company::create([
                            'name' => $record->business_name ?? $record->name,
                            'phone' => $record->phone,
                            'email' => $record->email,
                        ]);
                        $company->members()->attach($user);

                        // Assign best package trial
                        $bestPackage = Package::orderByDesc('price')->first();
                        if ($bestPackage) {
                            $company->companyPackage()->create([
                                'package_id' => $bestPackage->id,
                                'start_date' => now(),
                                'end_date' => now()->addDays(15),
                            ]);
                        }

                        // Mark prospect as converted
                        $record->update([
                            'status' => 'cliente',
                            'converted_user_id' => $user->id,
                            'converted_at' => now(),
                        ]);

                        Notification::make()
                            ->title("Usuario creado: {$record->email}")
                            ->body("Contraseña: {$data['password']}")
                            ->success()
                            ->persistent()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProspectiveClients::route('/'),
            'create' => Pages\CreateProspectiveClient::route('/create'),
            'edit' => Pages\EditProspectiveClient::route('/{record}/edit'),
        ];
    }
}
