<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Collection;


class User extends Authenticatable implements FilamentUser, HasTenants
{

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class);
    }

    public function getTenants(Panel $panel): Collection
    {
        return $this->companies;
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->companies()->whereKey($tenant)->exists();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }
}
