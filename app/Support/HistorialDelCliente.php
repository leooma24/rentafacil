<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\Rental;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cómo se ha portado un cliente, para decidir si se le vuelve a rentar.
 *
 * Es la pregunta más cara del negocio y no había con qué contestarla. La ficha
 * del cliente tenía nombre, correo y teléfono; el error que cuesta un aparato
 * completo —volverle a entregar una lavadora a quien ya te falló— se cometía sin
 * un solo aviso en pantalla.
 *
 * Los datos existían repartidos y nadie los juntaba. Ahora, además, existe el más
 * importante: desde que recoger dejó de borrar el adeudo, queda escrito quién
 * quedó a deber cuando se le quitó el equipo.
 *
 * TODO lo que se mide aquí son hechos, no opiniones:
 *
 * - Cuántas veces se le recogió el equipo quedando a deber.
 * - Cuánto trae congelado sin pagar de esas veces.
 * - Cuánto debe ahora de lo que trae rentado.
 * - Cada cuántos días paga de verdad, contra cada cuántos debería.
 *
 * Ese último es el que más dice y el que menos se nota: alguien que paga cada 11
 * días en un periodo de 7 no está al corriente, está siempre atrás, y en la
 * pantalla se veía igual que el que paga cada semana.
 */
class HistorialDelCliente
{
    /** Menos de esto no alcanza para decir nada de nadie. */
    private const PAGOS_MINIMOS_PARA_JUZGAR = 3;

    /** Cuánto se le tolera al ritmo de pago antes de llamarlo atrasado. */
    private const HOLGURA = 1.25;

    private function __construct(
        public readonly Customer $cliente,
        public readonly int $rentasTotales,
        public readonly int $rentasAbiertas,
        public readonly int $vecesQueQuedoADeber,
        public readonly float $adeudoCongelado,
        public readonly float $adeudoActual,
        public readonly int $pagos,
        public readonly ?float $diasEntrePagos,
        public readonly int $diasDelPeriodo,
    ) {
    }

    public static function for(Customer $cliente): self
    {
        $rentas = $cliente->rentals()->get();
        $ids = $rentas->pluck('id');

        // Agrupados POR RENTA, no todos revueltos: entre una renta que terminó y
        // la siguiente hay meses sin pagos porque el cliente no tenía nada
        // rentado, y medir por encima de ese hueco lo pinta como atrasado.
        //
        // Pasó con los datos del demo: alguien que pagó puntual en dos rentas
        // separadas por cuatro meses salía con un ritmo de 15.9 días sobre un
        // periodo de 7. Un cliente cumplido marcado como atrasado, que es
        // exactamente el error que esta pantalla no puede permitirse.
        $porRenta = $ids->isEmpty()
            ? collect()
            : DB::table('payments')
                ->whereIn('rental_id', $ids)
                ->where('status', 'completado')
                ->orderBy('payment_date')
                ->get(['rental_id', 'payment_date'])
                ->groupBy('rental_id');

        $pagos = $porRenta->sum(fn ($fechas) => $fechas->count());

        return new self(
            cliente: $cliente,
            rentasTotales: $rentas->count(),
            rentasAbiertas: $rentas->whereIn('status', ['activa', 'vencida'])->count(),
            // Se cuentan las veces que se le recogió QUEDANDO A DEBER, perdonadas
            // incluidas: que el dueño se lo haya perdonado no borra que pasó, y es
            // justo lo que hay que recordar la próxima vez.
            vecesQueQuedoADeber: $rentas->filter(fn (Rental $r) => (float) $r->debt_at_close > 0)->count(),
            adeudoCongelado: (float) $rentas
                ->filter(fn (Rental $r) => ! $r->debt_settled && (float) $r->debt_at_close > 0)
                ->sum('debt_at_close'),
            adeudoActual: app(AccountStatement::class)->forCustomer($cliente)->total,
            pagos: $pagos,
            diasEntrePagos: self::ritmo($porRenta),
            diasDelPeriodo: (int) ($cliente->company?->settings?->days_per_payment ?? 0),
        );
    }

    /**
     * Cada cuántos días paga en realidad. Nulo si no hay con qué decirlo.
     *
     * Se mide DENTRO de cada renta y luego se promedia por número de huecos. Los
     * meses en que el cliente no tenía nada rentado no cuentan: no pagó porque no
     * debía, no porque se haya atrasado.
     *
     * @param \Illuminate\Support\Collection $porRenta Pagos agrupados por renta.
     */
    private static function ritmo($porRenta): ?float
    {
        $dias = 0;
        $huecos = 0;

        foreach ($porRenta as $fechas) {
            if ($fechas->count() < 2) {
                continue;
            }

            $primera = Carbon::parse($fechas->first()->payment_date);
            $ultima = Carbon::parse($fechas->last()->payment_date);

            // Entre N pagos hay N-1 huecos.
            $dias += $primera->diffInDays($ultima);
            $huecos += $fechas->count() - 1;
        }

        // Con dos huecos no se ve un ritmo, se ve una coincidencia.
        if ($huecos < self::PAGOS_MINIMOS_PARA_JUZGAR - 1 || $dias <= 0) {
            return null;
        }

        return round($dias / $huecos, 1);
    }

    /** Paga, pero siempre tarde. */
    public function pagaTarde(): bool
    {
        return $this->diasEntrePagos !== null
            && $this->diasDelPeriodo > 0
            && $this->diasEntrePagos > $this->diasDelPeriodo * self::HOLGURA;
    }

    public function yaFallo(): bool
    {
        return $this->vecesQueQuedoADeber > 0;
    }

    public function esNuevo(): bool
    {
        return $this->pagos < self::PAGOS_MINIMOS_PARA_JUZGAR && ! $this->yaFallo();
    }

    /**
     * Una palabra, para la lista y para el badge.
     *
     * Se habla del hecho y no de la persona: "quedó a deber" y no "moroso". Es la
     * ficha de un cliente del dueño, no un juicio, y con la etiqueta puesta se
     * decide si se le entrega o no un aparato de once mil pesos.
     */
    public function etiqueta(): string
    {
        return match (true) {
            $this->yaFallo() => $this->adeudoCongelado > 0 ? 'Quedó a deber' : 'Ya falló una vez',
            $this->esNuevo() => 'Nuevo',
            $this->pagaTarde() => 'Se atrasa',
            $this->adeudoActual > 0 => 'Debe ahora',
            default => 'Cumplido',
        };
    }

    public function color(): string
    {
        return match (true) {
            $this->yaFallo() => 'danger',
            $this->esNuevo() => 'gray',
            $this->pagaTarde() => 'warning',
            $this->adeudoActual > 0 => 'warning',
            default => 'success',
        };
    }

    /** Hay algo que valga la pena advertir antes de entregarle otro equipo. */
    public function hayQueAdvertir(): bool
    {
        return $this->yaFallo() || $this->pagaTarde() || $this->adeudoActual > 0;
    }

    /**
     * La advertencia, escrita.
     *
     * Dice qué pasó y qué hacer al respecto, no sólo que hay un problema. Una
     * alarma sin salida se aprende a ignorar en dos semanas.
     */
    public function advertencia(): string
    {
        $partes = [];

        if ($this->yaFallo()) {
            $veces = $this->vecesQueQuedoADeber === 1
                ? 'Ya le recogiste un equipo'
                : 'Ya le recogiste ' . $this->vecesQueQuedoADeber . ' equipos';

            $partes[] = $this->adeudoCongelado > 0
                ? $veces . ' y quedó debiendo <strong>$' . number_format($this->adeudoCongelado, 2) . '</strong>.'
                : $veces . ' quedando a deber, aunque después quedaron en paz.';
        }

        if ($this->adeudoActual > 0 && $this->adeudoCongelado <= 0) {
            $partes[] = 'Ahora mismo debe <strong>$' . number_format($this->adeudoActual, 2) . '</strong>.';
        }

        if ($this->pagaTarde()) {
            $partes[] = 'Paga cada <strong>' . $this->diasEntrePagos . ' días</strong> cuando el periodo es de '
                . $this->diasDelPeriodo . '.';
        }

        if ($partes === []) {
            return '';
        }

        $partes[] = $this->yaFallo()
            ? 'Considera pedirle más depósito o esperar a que se ponga al corriente.'
            : 'Considera pedirle depósito.';

        return implode(' ', $partes);
    }

    /** Cómo le ha ido en una frase, para la ficha. Siempre dice algo. */
    public function resumen(): string
    {
        if ($this->rentasTotales === 0) {
            return 'Todavía no le has rentado nada.';
        }

        $partes = [];

        $partes[] = $this->rentasTotales === 1
            ? 'Una renta en total'
            : $this->rentasTotales . ' rentas en total';

        if ($this->pagos > 0) {
            $partes[] = $this->pagos === 1 ? '1 cobro' : $this->pagos . ' cobros';
        }

        if ($this->diasEntrePagos !== null) {
            $partes[] = 'paga cada ' . $this->diasEntrePagos . ' días';
        }

        if ($this->vecesQueQuedoADeber > 0) {
            $partes[] = $this->vecesQueQuedoADeber === 1
                ? 'una vez quedó a deber'
                : $this->vecesQueQuedoADeber . ' veces quedó a deber';
        }

        return ucfirst(implode(' · ', $partes)) . '.';
    }
}
