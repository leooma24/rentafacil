<?php
namespace App\Filament\Pages\Tenancy;

use App\Models\Company;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Tenancy\RegisterTenant;

class RegisterCompany extends RegisterTenant
{
    public static function getLabel(): string
    {
        return 'Register Company';
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

        return $company;
    }

    public static function canView(): bool
    {
        return ! auth()->user()->companies()->count();
    }


}
