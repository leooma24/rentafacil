<?php

namespace App\Filament\Administrador\Resources\UserResource\Pages;

use App\Filament\Administrador\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;
}
