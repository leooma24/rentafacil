<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use App\Models\CustomerDocument;
use App\Support\Acceso;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

/**
 * Los papeles del cliente: INE, comprobante de domicilio, referencias.
 *
 * Es lo que permite recuperar un aparato cuando alguien se muda con él, y hasta
 * ahora no había dónde guardarlos.
 *
 * Sólo el dueño: un cobrador no tiene por qué andar viendo la identificación de
 * nadie para salir a cobrar.
 */
class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'Documentos';

    protected static ?string $modelLabel = 'documento';

    protected static ?string $pluralModelLabel = 'documentos';

    public static function canViewForRecord(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): bool
    {
        return Acceso::soloDueno();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('type')
                ->label('Qué es')
                ->options(CustomerDocument::TYPES)
                ->required()
                ->native(false),

            // Disco privado, NO el público.
            //
            // storage/app/public se sirve tal cual en /storage/..., así que un
            // archivo ahí lo baja cualquiera que tenga la liga, sin sesión.
            // Para una identificación oficial eso no es aceptable: se guarda
            // fuera del alcance del navegador y se entrega por una ruta que
            // comprueba quién la pide.
            Forms\Components\FileUpload::make('file_path')
                ->label('Archivo')
                ->disk('local')
                ->directory('documentos-clientes')
                ->visibility('private')
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                ->maxSize(8192)
                ->required()
                ->helperText('Foto o PDF, hasta 8 MB. Sólo tú puedes verlo.')
                ->columnSpanFull(),

            Forms\Components\Textarea::make('notes')
                ->label('Notas')
                ->rows(2)
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('type')
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->label('Qué es')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => CustomerDocument::TYPES[$state] ?? $state)
                    ->description(fn (CustomerDocument $record) => $record->notes),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Subido')
                    ->date('d/m/Y')
                    ->visibleFrom('md'),

                Tables\Columns\TextColumn::make('uploader.name')
                    ->label('Lo subió')
                    ->visibleFrom('lg'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Subir documento'),
            ])
            ->actions([
                Tables\Actions\Action::make('ver')
                    ->label('Ver')
                    ->icon('heroicon-o-eye')
                    // Por una ruta que comprueba la sesión y la empresa, no por
                    // una liga pública.
                    ->url(fn (CustomerDocument $record) => route('documentos.ver', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading('Sin documentos')
            ->emptyStateDescription('Guarda aquí la INE y el comprobante de domicilio. Es lo que te sirve para recuperar el equipo si el cliente se muda.')
            ->emptyStateIcon('heroicon-o-identification');
    }
}
