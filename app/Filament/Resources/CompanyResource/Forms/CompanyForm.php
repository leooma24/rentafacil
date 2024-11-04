<?php
namespace App\Filament\Resources\CompanyResource\Forms;

use Filament\Forms;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Get;
use Filament\Forms\Set;
use App\Models\Neighborhood;
use App\Models\Township;
use Illuminate\Support\Collection;

class CompanyForm
{
    public static function getFormCompanyFields(): array
    {
        return [
            Forms\Components\TextInput::make('name')
                ->label('Nombre')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('phone')
                ->label('Telefóno')
                ->tel()
                ->maxLength(15),
            Forms\Components\TextInput::make('email')
                ->label('Correo Electrónico')
                ->email()
                ->maxLength(255),
        ];
    }

}
