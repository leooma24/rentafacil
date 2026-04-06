<?php

namespace App\Filament\Pages\Tenancy;

use App\Models\Company;
use App\Models\Package;
use App\Models\User;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Tenancy\RegisterTenant;

class RegisterCompany extends RegisterTenant
{
    public static function getLabel(): string
    {
        return 'Registrar Empresa';
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Nombre de la empresa')
                    ->required(),
                TextInput::make('phone')
                    ->label('Teléfono')
                    ->required(),
                TextInput::make('email')
                    ->label('Correo electrónico')
                    ->required()
                    ->email(),
            ]);
    }

    protected function handleRegistration(array $data): Company
    {
        $company = Company::create($data);
        $user = auth()->user();
        $company->members()->attach($user);

        $trialDays = 15;

        // Check referral code from URL
        $refCode = request()->query('ref');
        if ($refCode) {
            $referrer = User::where('referral_code', $refCode)->first();
            if ($referrer && $referrer->id !== $user->id) {
                $user->update(['referred_by' => $referrer->id]);
                $trialDays = 22; // 15 + 7 bonus days

                // Give referrer 7 extra days too
                $referrerCompany = $referrer->companies->first();
                if ($referrerCompany && $referrerCompany->companyPackage) {
                    $currentEnd = $referrerCompany->companyPackage->end_date;
                    $newEnd = \Carbon\Carbon::parse($currentEnd)->addDays(7);
                    $referrerCompany->companyPackage->update(['end_date' => $newEnd]);
                }
            }
        }

        // Assign trial
        $bestPackage = Package::orderByDesc('price')->first();
        if ($bestPackage) {
            $company->companyPackage()->create([
                'package_id' => $bestPackage->id,
                'start_date' => now(),
                'end_date' => now()->addDays($trialDays),
            ]);

            $msg = $trialDays > 15
                ? "¡Tienes {$trialDays} días gratis (15 + 7 por referido) con el plan {$bestPackage->name}!"
                : "Tienes 15 días gratis con el plan {$bestPackage->name}. Disfruta todas las funciones.";

            Notification::make()
                ->title('Prueba gratuita activada')
                ->body($msg)
                ->success()
                ->persistent()
                ->send();
        }

        return $company;
    }

    public static function canView(): bool
    {
        return ! auth()->user()->companies()->count();
    }
}
