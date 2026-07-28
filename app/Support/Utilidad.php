<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Expense;
use App\Models\Payment;
use Carbon\Carbon;

/**
 * Cuánto entró, cuánto salió y qué quedó, en un mes.
 *
 * Hasta ahora el escritorio decía "Ingresos del Mes" y ahí paraba, así que ese
 * número se leía como ganancia. No lo es: falta la gasolina de salir a cobrar,
 * los sueldos, las refacciones y lo que cuesta reparar las lavadoras.
 *
 * El mantenimiento se suma a los gastos aunque viva en otra tabla: al dueño le
 * sale del mismo bolsillo, y dejarlo fuera volvería a inflar la ganancia.
 */
class Utilidad
{
    private function __construct(
        public readonly Carbon $mes,
        public readonly float $ingresos,
        public readonly float $gastos,
        public readonly float $mantenimiento,
    ) {
    }

    public static function delMes(Company $empresa, ?Carbon $mes = null): self
    {
        $mes = ($mes ?? now())->copy()->startOfMonth();

        $ingresos = (float) Payment::where('company_id', $empresa->id)
            ->where('status', 'completado')
            ->whereYear('payment_date', $mes->year)
            ->whereMonth('payment_date', $mes->month)
            ->sum('amount');

        $gastos = (float) Expense::where('company_id', $empresa->id)
            ->whereYear('expense_date', $mes->year)
            ->whereMonth('expense_date', $mes->month)
            ->sum('amount');

        $mantenimiento = (float) $empresa->maintenances()
            ->whereYear('start_date', $mes->year)
            ->whereMonth('start_date', $mes->month)
            ->sum('cost');

        return new self($mes, $ingresos, $gastos, $mantenimiento);
    }

    /** Todo lo que sale, venga de gastos o de reparaciones. */
    public function salidas(): float
    {
        return $this->gastos + $this->mantenimiento;
    }

    /**
     * Si el mes no trae un solo gasto anotado, la ganancia sale de más.
     *
     * Se mira el renglón de gastos y no el total de salidas: con un
     * mantenimiento registrado y ningún gasto, el total deja de ser cero y el
     * aviso se habría callado justo cuando más falta hace.
     */
    public function gananciaInflada(): bool
    {
        return $this->gastos <= 0 && $this->ingresos > 0;
    }

    public function ganancia(): float
    {
        return $this->ingresos - $this->salidas();
    }

    public function pierde(): bool
    {
        return $this->ganancia() < 0;
    }

    /**
     * Qué porcentaje de lo que entra se queda.
     *
     * Nulo cuando no hubo ingresos: un margen sobre cero no significa nada y
     * mostrarlo como 0% haría creer que se trabajó y no quedó, en vez de que no
     * se trabajó.
     */
    public function margen(): ?float
    {
        if ($this->ingresos <= 0) {
            return null;
        }

        return round($this->ganancia() / $this->ingresos * 100, 1);
    }

    /** @return array<string, float> categoría => total, de mayor a menor */
    public function porCategoria(Company $empresa): array
    {
        $gastos = Expense::where('company_id', $empresa->id)
            ->whereYear('expense_date', $this->mes->year)
            ->whereMonth('expense_date', $this->mes->month)
            ->selectRaw('category, sum(amount) as total')
            ->groupBy('category')
            ->pluck('total', 'category')
            ->map(fn ($total) => (float) $total)
            ->all();

        if ($this->mantenimiento > 0) {
            $gastos['mantenimiento'] = $this->mantenimiento;
        }

        arsort($gastos);

        return $gastos;
    }
}
