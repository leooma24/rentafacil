<?php

namespace App\Observers;

use App\Models\Company;

class CompanyObserver
{
    /**
     * Handle the Company "created" event.
     */
    public function created(Company $company): void
    {
        // Aquí se asignaba el paquete Gratuito a toda empresa nueva. Como
        // RegisterCompany ya asigna la prueba de 15 días, quedaban dos planes y
        // ganaba el Gratuito (3 lavadoras), dejando al cliente topado justo
        // durante su prueba "con todas las funciones".
        //
        // Quién arranca con qué plan lo decide RegisterCompany y nadie más.
    }

    /**
     * Handle the Company "updated" event.
     */
    public function updated(Company $company): void
    {
        //
    }

    /**
     * Handle the Company "deleted" event.
     */
    public function deleted(Company $company): void
    {
        //
    }

    /**
     * Handle the Company "restored" event.
     */
    public function restored(Company $company): void
    {
        //
    }

    /**
     * Handle the Company "force deleted" event.
     */
    public function forceDeleted(Company $company): void
    {
        //
    }
}
