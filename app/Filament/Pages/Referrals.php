<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class Referrals extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-gift';
    protected static ?string $navigationGroup = 'Mi cuenta';
    protected static ?string $title = 'Invitar Amigos';
    protected static ?string $slug = 'referidos';

    public static function canAccess(): bool
    {
        return \App\Support\Acceso::soloDueno();
    }
    protected static ?int $navigationSort = 12;

    protected static string $view = 'filament.pages.referrals';

    public function getViewData(): array
    {
        $user = auth()->user();
        return [
            'referralUrl' => $user->referral_url,
            'referralCode' => $user->referral_code,
            'referralsCount' => $user->referrals()->count(),
        ];
    }
}
