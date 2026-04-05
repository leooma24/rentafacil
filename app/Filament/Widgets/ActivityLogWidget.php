<?php

namespace App\Filament\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Spatie\Activitylog\Models\Activity;

class ActivityLogWidget extends BaseWidget
{
    protected static ?int $sort = 7;
    protected static ?string $heading = 'Actividad Reciente';
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Activity::query()->latest()->limit(20)
            )
            ->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('description')
                    ->label('Actividad'),
                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Tipo')
                    ->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : '-')
                    ->badge(),
                Tables\Columns\TextColumn::make('causer.name')
                    ->label('Usuario')
                    ->default('Sistema'),
                Tables\Columns\TextColumn::make('event')
                    ->label('Evento')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'info',
                        'deleted' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->since(),
            ]);
    }
}
