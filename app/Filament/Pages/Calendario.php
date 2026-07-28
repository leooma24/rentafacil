<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\RentalCalendarWidget;
use Filament\Pages\Page;

class Calendario extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Gestión Principal';

    protected static ?string $navigationLabel = 'Calendario';

    protected static ?int $navigationSort = 6;

    protected static ?string $slug = 'calendario';

    protected static ?string $title = 'Calendario de rentas';

    protected static string $view = 'filament.pages.calendario';

    protected function getHeaderWidgets(): array
    {
        return [
            RentalCalendarWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }
}
