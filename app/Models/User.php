<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable; // Importar Notifiable
use Spatie\Permission\Traits\HasRoles;
use BezhanSalleh\FilamentShield\Traits\HasPanelShield;

class User extends Authenticatable implements FilamentUser, HasTenants
{
    use HasFactory, Notifiable, HasRoles, HasPanelShield; // Agregar Notifiable

    protected $fillable = [
        'name',
        'email',
        'password',
        'referral_code',
        'referred_by',
        'is_demo',
    ];

    protected $casts = [
        'is_demo' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (!$user->referral_code) {
                $user->referral_code = strtoupper(substr(md5(uniqid()), 0, 8));
            }
        });
    }

    public function referrer()
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    public function referrals()
    {
        return $this->hasMany(User::class, 'referred_by');
    }

    public function getReferralUrlAttribute(): string
    {
        return url("/propietario/registrar?ref={$this->referral_code}");
    }

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
