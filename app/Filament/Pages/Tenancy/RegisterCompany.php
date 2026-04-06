<?php

namespace App\Filament\Pages\Tenancy;

use App\Models\Company;
use App\Models\Package;
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

        $company->members()->attach(auth()->user());

        // Assign the best package as a 15-day free trial
        $bestPackage = Package::orderByDesc('price')->first();

        if ($bestPackage) {
            $company->companyPackage()->create([
                'package_id' => $bestPackage->id,
                'start_date' => now(),
                'end_date' => now()->addDays(15),
            ]);

            Notification::make()
                ->title('Prueba gratuita activada')
                ->body("Tienes 15 días gratis con el plan {$bestPackage->name}. Disfruta todas las funciones.")
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
