<?php

namespace App\Filament\Pages;

use App\Models\Package;
use Filament\Facades\Filament;
use Filament\Pages\Page;

class MyPlan extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationGroup = 'Configuración';
    protected static ?string $title = 'Mi Plan';
    protected static ?string $slug = 'mi-plan';
    protected static ?int $navigationSort = 11;

    protected static string $view = 'filament.pages.my-plan';

    public function getViewData(): array
    {
        $tenant = Filament::getTenant();
        $currentPackage = $tenant->companyPackage;
        $packages = Package::orderBy('price')->get();

        return [
            'tenant' => $tenant,
            'currentPackage' => $currentPackage,
            'currentPlan' => $currentPackage?->package,
            'isOnTrial' => $tenant->isOnTrial(),
            'trialDaysLeft' => $tenant->trialDaysLeft(),
            'hasActivePackage' => $tenant->hasActivePackage(),
            'packages' => $packages,
        ];
    }
}
