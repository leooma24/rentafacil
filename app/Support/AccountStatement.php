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

        return $this->buildStatement(
            $customer,
            $rentals,
            $customer->company?->settings,
            $this->congeladas($customer),
        );
    }

    /**
     * Las rentas ya cerradas donde el cliente quedó a deber.
     *
     * El adeudo normal se deduce de qué tan atrás quedó end_date, y al recoger el
     * equipo esa fecha se mueve a hoy: el saldo se evaporaba justo cuando había
     * que acordarse de él. Ahora la cifra queda congelada al cerrar, y sigue
     * contando hasta que el dueño diga que quedaron en paz.
     *
     * @return Collection<int, Rental>
     */
    private function congeladas(Customer $customer): Collection
    {
        $pendiente = fn (Rental $renta) => ! $renta->debt_settled
            && (float) $renta->debt_at_close > 0
            && ! in_array($renta->status, self::ACTIVE_STATUSES, true);

        if ($customer->relationLoaded('rentals')) {
            return $customer->rentals->filter($pendiente)->values();
        }

        return $customer->rentals()
            ->whereNotIn('status', self::ACTIVE_STATUSES)
            ->where('debt_settled', false)
            ->where('debt_at_close', '>', 0)
            ->with('washingMachine')
            ->get();
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
            ->where(fn ($query) => $query
                ->whereHas('rentals', fn ($sub) => $sub
                    ->whereIn('status', self::ACTIVE_STATUSES)
                    ->whereDate('end_date', '<', Carbon::today()))
                // Quien ya no tiene equipo pero quedó a deber sigue debiendo. Sin
                // esto desaparecía de la cobranza en el momento en que se le
                // recogía la lavadora, que es cuando más caro sale olvidarlo.
                ->orWhereHas('rentals', fn ($sub) => $sub
                    ->whereNotIn('status', self::ACTIVE_STATUSES)
                    ->where('debt_settled', false)
                    ->where('debt_at_close', '>', 0)))
            ->with(['rentals' => fn ($query) => $query
                ->where(fn ($sub) => $sub
                    ->whereIn('status', self::ACTIVE_STATUSES)
                    ->orWhere(fn ($cerrada) => $cerrada
                        ->where('debt_settled', false)
                        ->where('debt_at_close', '>', 0)))
                ->with(['payments', 'washingMachine'])])
            ->get();

        return $customers
            ->map(fn (Customer $customer) => $this->buildStatement(
                $customer,
                $customer->rentals->whereIn('status', self::ACTIVE_STATUSES)->values(),
                $settings,
                $this->congeladas($customer),
            ))
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

        return $this->debtFor($rental, $daysPerPeriod, $defaultPrice, $settings);
    }

    /**
     * @param Collection<int, Rental> $rentals
     */
    /**
     * @param Collection<int, Rental>|null $congeladas Rentas cerradas donde quedó a deber.
     */
    private function buildStatement(
        Customer $customer,
        Collection $rentals,
        ?Setting $settings,
        ?Collection $congeladas = null,
    ): Statement {
        $daysPerPeriod = (int) ($settings->days_per_payment ?? 0);
        $defaultPrice = (float) ($settings->price ?? 0);

        if ($daysPerPeriod <= 0 || $defaultPrice <= 0) {
            return new Statement($customer, 0.0, null, [], false);
        }

        $lines = [];
        $total = 0.0;
        $owingSince = null;

        $desde = function (Rental $rental) use (&$owingSince) {
            $end = Carbon::parse($rental->end_date)->startOfDay();

            if ($owingSince === null || $end->lt($owingSince)) {
                $owingSince = $end;
            }
        };

        foreach ($rentals as $rental) {
            $line = $this->debtFor($rental, $daysPerPeriod, $defaultPrice, $settings);
            $lines[] = $line;
            $total += $line->amount;

            if ($line->amount > 0) {
                $desde($rental);
            }
        }

        // Lo que quedó debiendo de equipos ya recogidos. No se recalcula: lo que
        // debía el día que se le quitó la lavadora es un hecho de ese día, y no
        // puede cambiar porque mañana se corrija un cobro viejo.
        $congelado = 0.0;

        foreach ($congeladas ?? collect() as $cerrada) {
            $monto = (float) $cerrada->debt_at_close;
            $congelado += $monto;
            $total += $monto;
            $desde($cerrada);
        }

        return new Statement($customer, $total, $owingSince, $lines, true, $congelado);
    }

    private function debtFor(
        Rental $rental,
        int $daysPerPeriod,
        float $defaultPrice,
        ?Setting $settings = null,
    ): RentalDebt {
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

        $lateFee = $this->lateFeeFor($settings, $periods, $price, $daysOverdue);

        // Nunca baja de cero: un abono grande no vuelve el saldo negativo.
        $amount = max(0.0, $periods * $price + $lateFee - $credit);

        return new RentalDebt($rental, $periods, $price, $amount, $credit, $lateFee);
    }

    /**
     * El recargo por atraso.
     *
     * Se cobra por periodo vencido, no por día: cobrar diario convierte un
     * atraso de un mes en una cifra impagable, y un cliente que se ve imposible
     * de alcanzar deja de intentar.
     *
     * Con late_fee_amount en cero devuelve cero y el cálculo queda idéntico al
     * de siempre. Las 17 empresas que ya usan la app no ven ningún cambio hasta
     * que ellas lo configuren.
     */
    private function lateFeeFor(?Setting $settings, int $periods, float $price, int $daysOverdue): float
    {
        $monto = (float) ($settings->late_fee_amount ?? 0);

        if ($monto <= 0 || $periods <= 0) {
            return 0.0;
        }

        // Los días de gracia perdonan el atraso corto entero, no sólo su primer
        // periodo: quien paga dos días tarde no debería llevarse un recargo.
        if ($daysOverdue <= (int) ($settings->late_fee_grace_days ?? 0)) {
            return 0.0;
        }

        return ($settings->late_fee_type ?? 'fijo') === 'porcentaje'
            ? round($periods * $price * ($monto / 100), 2)
            : round($periods * $monto, 2);
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
