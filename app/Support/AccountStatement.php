<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Rental;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Calcula cuánto debe un cliente.
 *
 * El negocio es prepago: al pagar, ExtendRentAction empuja el end_date de la renta.
 * Por eso el adeudo se deriva de qué tan atrás quedó ese end_date, sin guardar saldos
 * en ningún lado y sin riesgo de que se desincronicen.
 */
class AccountStatement
{
    private const ACTIVE_STATUSES = ['activa', 'vencida'];

    public function forCustomer(Customer $customer): Statement
    {
        // Si quien llama ya trajo las rentas (la lista de clientes lo hace para no
        // disparar tres consultas por fila), se reutilizan en vez de repetirlas.
        $rentals = $customer->relationLoaded('rentals')
            ? $customer->rentals->whereIn('status', self::ACTIVE_STATUSES)->values()
            : $customer->rentals()
                ->whereIn('status', self::ACTIVE_STATUSES)
                ->with(['payments', 'washingMachine'])
                ->get();

        return $this->buildStatement($customer, $rentals, $customer->company?->settings);
    }

    /**
     * Los clientes que deben, de mayor a menor.
     *
     * Arranca del conjunto chico (rentas activas o vencidas con end_date pasado) en vez
     * de recorrer a todos los clientes, y trae las relaciones de una vez para no hacer
     * una consulta por cliente.
     *
     * @return Collection<int, Statement>
     */
    public function forCompany(Company $company): Collection
    {
        $settings = $company->settings;

        $customers = Customer::where('company_id', $company->id)
            ->whereHas('rentals', fn ($query) => $query
                ->whereIn('status', self::ACTIVE_STATUSES)
                ->whereDate('end_date', '<', Carbon::today()))
            ->with(['rentals' => fn ($query) => $query
                ->whereIn('status', self::ACTIVE_STATUSES)
                ->with(['payments', 'washingMachine'])])
            ->get();

        return $customers
            ->map(fn (Customer $customer) => $this->buildStatement($customer, $customer->rentals, $settings))
            ->filter(fn (Statement $statement) => $statement->hasDebt())
            ->sortByDesc(fn (Statement $statement) => $statement->total)
            ->values();
    }

    public function totalForCompany(Company $company): float
    {
        return (float) $this->forCompany($company)->sum(fn (Statement $statement) => $statement->total);
    }

    /**
     * @param Collection<int, Rental> $rentals
     */
    private function buildStatement(Customer $customer, Collection $rentals, ?Setting $settings): Statement
    {
        $daysPerPeriod = (int) ($settings->days_per_payment ?? 0);
        $defaultPrice = (float) ($settings->price ?? 0);

        if ($daysPerPeriod <= 0 || $defaultPrice <= 0) {
            return new Statement($customer, 0.0, null, [], false);
        }

        $lines = [];
        $total = 0.0;
        $owingSince = null;

        foreach ($rentals as $rental) {
            $line = $this->debtFor($rental, $daysPerPeriod, $defaultPrice);
            $lines[] = $line;
            $total += $line->amount;

            if ($line->amount > 0) {
                $end = Carbon::parse($rental->end_date)->startOfDay();
                if ($owingSince === null || $end->lt($owingSince)) {
                    $owingSince = $end;
                }
            }
        }

        return new Statement($customer, $total, $owingSince, $lines, true);
    }

    private function debtFor(Rental $rental, int $daysPerPeriod, float $defaultPrice): RentalDebt
    {
        $price = $this->priceFor($rental, $defaultPrice);
        $end = Carbon::parse($rental->end_date)->startOfDay();
        $today = Carbon::today();

        if ($end->gte($today)) {
            return new RentalDebt($rental, 0, $price, 0.0);
        }

        $daysOverdue = $end->diffInDays($today);
        $periods = (int) ceil($daysOverdue / $daysPerPeriod);

        return new RentalDebt($rental, $periods, $price, $periods * $price);
    }

    private function priceFor(Rental $rental, float $defaultPrice): float
    {
        $last = $rental->payments
            ->where('status', 'completado')
            ->sortBy([['payment_date', 'desc'], ['id', 'desc']])
            ->first();

        return $last && $last->amount > 0 ? (float) $last->amount : $defaultPrice;
    }
}
