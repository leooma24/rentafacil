<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProspectiveClientResource\Pages;
use App\Filament\Resources\ProspectiveClientResource\RelationManagers;
use App\Models\ProspectiveClient;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProspectiveClientResource extends Resource
{
    protected static ?string $model = ProspectiveClient::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Administrador';
    protected static ?string $modelLabel = 'Prospecto';
    protected static ?string $pluralModelLabel = 'Prospectos';
    protected static ?string $navigationLabel = 'Prospectos';
    protected static ?string $slug = 'prospectos';

    protected static bool $isScopedToTenant = false;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
                Forms\Components\TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->label('Correo electrónico')
                    ->email()
                    ->maxLength(255),
                Forms\Components\TextInput::make('phone')
                    ->label('Teléfono')
                    ->maxLength(255),
                Forms\Components\Select::make('source')
                    ->label('Fuente')
                    ->options([
                        'facebook' => 'Facebook',
                        'instagram' => 'Instagram',
                        'whatsapp' => 'WhatsApp',
                        'google' => 'Google',
                        'referido' => 'Referido',
                        'otro' => 'Otro',
                    ])
                    ->required(),
                Forms\Components\Textarea::make('notes')
                    ->label('Notas'),
                Forms\Components\DateTimePicker::make('last_contacted_at')
                    ->label('Último contacto')
                    ->nullable(),
                Forms\Components\Select::make('status')
                    ->label('Estatus')
                    ->options([
                        'nuevo' => 'Nuevo',
                        'contactado' => 'Contactado',
                        'interesado' => 'Interesado',
                        'no_interesado' => 'No Interesado',
                        'cliente' => 'Cliente',
                    ])
                    ->default('nuevo')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                //
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Correo electrónico')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Teléfono')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('source')
                    ->label('Fuente')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                //
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

    public static function getRelations(): array
    {
        return [
            //
        ];
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
