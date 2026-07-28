<?php

namespace App\Support;

use App\Models\CashClosing;
use App\Models\Company;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Lo que se cobró un día y cómo cuadra con lo que hay en la mano.
 *
 * De 641 cobros en producción, 389 son en efectivo. El dueño terminaba el día
 * con ese dinero encima y ninguna forma de cerrar: cuánto debería traer, cuánto
 * trae, y si falta algo. Eso se hacía en papel o no se hacía.
 *
 * En transferencia no se cuenta nada: ese dinero ya está en el banco. Lo que
 * hay que cuadrar es el efectivo.
 */
class CorteDeCaja
{
    private function __construct(
        public readonly Company $empresa,
        public readonly Carbon $fecha,
        public readonly ?User $cobrador,
        /** @var Collection<int, Payment> */
        public readonly Collection $cobros,
        public readonly ?CashClosing $cerrado,
    ) {
    }

    /**
     * @param User|null $cobrador Para ver sólo lo de una persona. Nulo trae todo
     *                            el día de la empresa.
     */
    public static function para(Company $empresa, Carbon $fecha, ?User $cobrador = null): self
    {
        $cobros = Payment::where('company_id', $empresa->id)
            ->where('status', 'completado')
            ->whereDate('payment_date', $fecha)
            ->when($cobrador, fn ($query) => $query->where('collected_by', $cobrador->id))
            ->with(['rental.customer', 'rental.washingMachine', 'collector'])
            ->orderBy('created_at')
            ->get();

        $cerrado = $cobrador
            ? CashClosing::where('company_id', $empresa->id)
                ->where('user_id', $cobrador->id)
                ->whereDate('closing_date', $fecha)
                ->first()
            : null;

        return new self($empresa, $fecha, $cobrador, $cobros, $cerrado);
    }

    public function total(): float
    {
        return (float) $this->cobros->sum('amount');
    }

    /** Lo que hay que contar y entregar. */
    public function efectivo(): float
    {
        return (float) $this->cobros
            ->filter(fn (Payment $pago) => $this->esEfectivo($pago))
            ->sum('amount');
    }

    /** Lo que ya está en el banco y no se cuenta. */
    public function transferencias(): float
    {
        return $this->total() - $this->efectivo();
    }

    public function cuantos(): int
    {
        return $this->cobros->count();
    }

    public function estaCerrado(): bool
    {
        return $this->cerrado !== null;
    }

    /** Cuánto se cobró por persona, para el día de una empresa con varios cobradores. */
    public function porCobrador(): Collection
    {
        return $this->cobros
            ->groupBy(fn (Payment $pago) => $pago->collected_by ?? 0)
            ->map(fn (Collection $pagos) => [
                'nombre' => $pagos->first()->collector?->name ?? 'Sin registrar',
                'total' => (float) $pagos->sum('amount'),
                'efectivo' => (float) $pagos->filter(fn ($p) => $this->esEfectivo($p))->sum('amount'),
                'cuantos' => $pagos->count(),
            ])
            ->sortByDesc('total')
            ->values();
    }

    /**
     * Cierra el día con lo que de verdad se contó.
     *
     * La diferencia se guarda calculada y no se deduce después: si mañana se
     * corrige un cobro de hoy, el corte firmado tiene que seguir diciendo lo
     * que decía.
     */
    public function cerrar(User $quien, float $contado, ?string $notas = null): CashClosing
    {
        $esperado = $this->efectivo();

        return CashClosing::updateOrCreate(
            [
                'company_id' => $this->empresa->id,
                'user_id' => $quien->id,
                'closing_date' => $this->fecha->toDateString(),
            ],
            [
                'expected_cash' => $esperado,
                'counted_cash' => $contado,
                'difference' => round($contado - $esperado, 2),
                'payments_count' => $this->cuantos(),
                'notes' => $notas,
            ]
        );
    }

    /**
     * El método se captura como texto libre ("Efectivo", "efectivo"), así que
     * se compara sin acentos de por medio y sin importar mayúsculas.
     */
    private function esEfectivo(Payment $pago): bool
    {
        return str_contains(mb_strtolower((string) $pago->payment_method), 'efectivo');
    }
}
