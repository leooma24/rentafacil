<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'phone',
        'email',
        'is_demo',
        'demo_expires_at',
    ];

    protected $casts = [
        'is_demo' => 'boolean',
        'demo_expires_at' => 'datetime',
    ];

    public function scopeDemo($query)
    {
        return $query->where('is_demo', true);
    }

    public function scopeExpiredDemos($query)
    {
        return $query->where('is_demo', true)->where('demo_expires_at', '<', now());
    }

    public function members()
    {
        return $this->belongsToMany(User::class);
    }

    public function washingMachines(): HasMany
    {
        return $this->hasMany(WashingMachine::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(Maintenance::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function addresses()
    {
        return $this->morphMany(Address::class, 'addressable');
    }

    public function payments()
    {
        return $this->hasManyThrough(Payment::class, Rental::class);
    }

    /**
     * El plan efectivo es el último asignado.
     *
     * Sin el latestOfMany, un hasOne devuelve el de id más bajo: si por lo que sea
     * vuelven a existir dos filas, se aplicaría el plan viejo en vez del nuevo.
     */
    public function companyPackage()
    {
        return $this->hasOne(CompanyPackage::class)->latestOfMany();
    }

    public function currentPackage()
    {
        return $this->hasOne(CompanyPackage::class)->where('end_date', '>=', now());
    }

    public function incidents()
    {
        return $this->hasMany(Incident::class);
    }

    // Método para verificar los límites del paquete
    public function canAddMoreClients()
    {
        $package = $this->companyPackage?->package;
        $clientCount = $this->customers()->count();
        return $package && $clientCount < $package->max_clients;
    }

    public function canAddMoreWashingMachines()
    {
        $package = $this->companyPackage?->package;
        $clientCount = $this->washingMachines()->count();
        return $package && $clientCount < $package->max_washers;
    }

    public function settings()
    {
        return $this->hasOne(Setting::class);
    }

    /** Todos los periodos que ha tenido la empresa, del más viejo al más nuevo. */
    public function companyPackages()
    {
        return $this->hasMany(CompanyPackage::class);
    }

    /**
     * Si la empresa está en su periodo de prueba.
     *
     * Antes tenía los 15 días escritos a mano, así que una prueba de 30 dejaba
     * de contarse como prueba a la mitad: la cuenta seguía funcionando pero el
     * letrero de "prueba, X días" se apagaba y el dueño no sabía cuánto le
     * quedaba.
     *
     * Ahora es prueba mientras sea el PRIMER periodo de la empresa: en cuanto
     * paga, se registra otro y deja de serlo. Así el plazo lo decide quien crea
     * el periodo y no una constante escondida aquí.
     */
    public function isOnTrial(): bool
    {
        $cp = $this->companyPackage;

        if (! $cp || ! $cp->end_date || $cp->end_date < now()) {
            return false;
        }

        return ! CompanyPackage::where('company_id', $this->id)
            ->where('id', '<', $cp->id)
            ->exists();
    }

    public function trialDaysLeft(): int
    {
        $cp = $this->companyPackage;
        if (!$cp || !$cp->end_date) return 0;
        return max(0, (int) now()->diffInDays($cp->end_date, false));
    }

    public function hasActivePackage(): bool
    {
        $cp = $this->companyPackage;
        return $cp && $cp->end_date && $cp->end_date >= now();
    }
}
