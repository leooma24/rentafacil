<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ActivityLogWidget;
use Filament\Pages\Page;

class Bitacora extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Mi cuenta';

    protected static ?string $navigationLabel = 'Actividad';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'actividad';

    /** La bitácora es justo para vigilar lo que hacen los demás. */
    public static function canAccess(): bool
    {
        return \App\Support\Acceso::soloDueno();
    }

    protected static ?string $title = 'Actividad reciente';

    protected static string $view = 'filament.pages.bitacora';

    protected function getHeaderWidgets(): array
    {
        return [
            ActivityLogWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }
}
