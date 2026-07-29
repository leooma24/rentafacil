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
     * El adeudo de una sola renta, con la misma regla del estado de cuenta.
     */
    public function forRental(Rental $rental, ?Setting $settings = null): RentalDebt
    {
        $settings ??= $rental->company?->settings;

        $daysPerPeriod = (int) ($settings->days_per_payment ?? 0);
        $defaultPrice = (float) ($settings->price ?? 0);

        if ($daysPerPeriod <= 0 || $defaultPrice <= 0) {
            return new RentalDebt($rental, 0, 0.0, 0.0);
        }

        if (! $rental->relationLoaded('payments')) {
            $rental->load('payments');
        }

        return $this->debtFor($rental, $daysPerPeriod, $defaultPrice);
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

        // Lo abonado que todavía no compra tiempo baja el adeudo.
        $credit = $this->creditFor($rental);

        if ($end->gte($today)) {
            return new RentalDebt($rental, 0, $price, 0.0, $credit);
        }

        $daysOverdue = $end->diffInDays($today);
        $periods = (int) ceil($daysOverdue / $daysPerPeriod);

        // Nunca baja de cero: un abono grande no vuelve el saldo negativo.
        $amount = max(0.0, $periods * $price - $credit);

        return new RentalDebt($rental, $periods, $price, $amount, $credit);
    }

    private function creditFor(Rental $rental): float
    {
        if ($rental->relationLoaded('payments')) {
            return (float) $rental->payments
                ->where('status', 'completado')
                ->where('applied', false)
                ->sum('amount');
        }

        return Abonos::creditFor($rental);
    }

    /**
     * A cuánto se le cobra a esta renta.
     *
     * Manda el precio de la renta. Antes no existía y había que deducirlo del
     * último pago aplicado: una adivinanza que se rompe en cuanto alguien paga de
     * más o de menos, y que además impedía cobrar distinto por equipo o cliente.
     *
     * La deducción se queda como respaldo para las rentas viejas, que se crearon
     * cuando la columna no existía y tienen price en nulo.
     */
    private function priceFor(Rental $rental, float $defaultPrice): float
    {
        if ($rental->price > 0) {
            return (float) $rental->price;
        }

        // Solo los pagos aplicados dicen a cuánto se le cobra a este cliente.
        // Un abono de $150 no es su tarifa: es un pedazo de ella.
        $last = $rental->payments
            ->where('status', 'completado')
            ->where('applied', true)
            ->sortBy([['payment_date', 'desc'], ['id', 'desc']])
            ->first();

        return $last && $last->amount > 0 ? (float) $last->amount : $defaultPrice;
    }
}
