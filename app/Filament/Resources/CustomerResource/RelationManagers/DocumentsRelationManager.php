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

            Forms\Components\FileUpload::make('file_path')
                ->label('Archivo')
                ->directory('documentos-clientes')
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                ->maxSize(8192)
                ->required()
                ->helperText('Foto o PDF, hasta 8 MB.')
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
                    ->url(fn (CustomerDocument $record) => Storage::disk('public')->url($record->file_path))
                    ->openUrlInNewTab(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading('Sin documentos')
            ->emptyStateDescription('Guarda aquí la INE y el comprobante de domicilio. Es lo que te sirve para recuperar el equipo si el cliente se muda.')
            ->emptyStateIcon('heroicon-o-identification');
    }
}
