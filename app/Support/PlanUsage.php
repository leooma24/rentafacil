<?php

namespace App\Support;

use App\Models\Company;

/**
 * Qué plan tiene una empresa y qué tanto de su cupo lleva usado.
 *
 * Es para mostrar. Quien decide si se puede dar de alta una lavadora o un cliente
 * sigue siendo Company::canAddMoreWashingMachines() y canAddMoreClients().
 */
class PlanUsage
{
    public function __construct(
        public readonly ?string $planName,
        public readonly bool $hasPlan,
        public readonly bool $isActive,
        public readonly bool $isOnTrial,
        public readonly int $trialDaysLeft,
        public readonly int $machines,
        public readonly ?int $maxMachines,
        public readonly int $customers,
        public readonly ?int $maxCustomers,
    ) {
    }

    public static function for(?Company $company): self
    {
        if (! $company) {
            return self::sinPlan(0, 0);
        }

        $machines = $company->washingMachines()->count();
        $customers = $company->customers()->count();

        $package = $company->companyPackage?->package;

        if (! $package) {
            return self::sinPlan($machines, $customers);
        }

        return new self(
            planName: $package->name,
            hasPlan: true,
            isActive: $company->hasActivePackage(),
            isOnTrial: $company->isOnTrial(),
            trialDaysLeft: $company->trialDaysLeft(),
            machines: $machines,
            maxMachines: (int) $package->max_washers,
            customers: $customers,
            maxCustomers: (int) $package->max_clients,
        );
    }

    private static function sinPlan(int $machines, int $customers): self
    {
        return new self(
            planName: null,
            hasPlan: false,
            isActive: false,
            isOnTrial: false,
            trialDaysLeft: 0,
            machines: $machines,
            maxMachines: null,
            customers: $customers,
            maxCustomers: null,
        );
    }

    public function machinesMaxed(): bool
    {
        return $this->maxMachines !== null && $this->machines >= $this->maxMachines;
    }

    public function customersMaxed(): bool
    {
        return $this->maxCustomers !== null && $this->customers >= $this->maxCustomers;
    }

    /** Sin plan no se está "topado": se está sin plan, que es otra cosa. */
    public function isMaxedOut(): bool
    {
        return $this->hasPlan && ($this->machinesMaxed() || $this->customersMaxed());
    }

    public function machinesLabel(): string
    {
        return $this->maxMachines === null
            ? (string) $this->machines
            : "{$this->machines} / {$this->maxMachines}";
    }

    public function customersLabel(): string
    {
        return $this->maxCustomers === null
            ? (string) $this->customers
            : "{$this->customers} / {$this->maxCustomers}";
    }

    public function planLabel(): string
    {
        if (! $this->hasPlan) {
            return 'Sin plan';
        }

        if ($this->isOnTrial && $this->isActive) {
            return "{$this->planName} · prueba, {$this->trialDaysLeft} días";
        }

        return $this->isActive ? $this->planName : "{$this->planName} · vencido";
    }

    public function planColor(): string
    {
        if (! $this->hasPlan) {
            return 'gray';
        }

        if (! $this->isActive) {
            return 'danger';
        }

        return $this->isOnTrial ? 'warning' : 'success';
    }
}
