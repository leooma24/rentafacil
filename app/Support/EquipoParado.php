<?php

namespace App\Support;

use App\Models\Company;
use App\Models\ProspectiveClient;
use App\Models\WashingMachine;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * El equipo que está libre y cuánto lleva sin generar.
 *
 * Una lavadora parada no avisa. No manda notificaciones, no sale en ninguna lista
 * y no le duele a nadie hasta que se hace la cuenta de fin de mes y no cuadra. En
 * un negocio donde el aparato ya está pagado, el único costo real es el tiempo que
 * pasa en la bodega.
 *
 * El "desde cuándo" sale de la última renta que tuvo. Si nunca se rentó, cuenta
 * desde que se dio de alta: un aparato comprado hace tres meses que nunca ha
 * salido es el peor caso de todos y era justo el que no se veía.
 */
class EquipoParado
{
    /** @param Collection<int, object> $equipos */
    private function __construct(
        public readonly Collection $equipos,
        public readonly float $rentaPerdidaPorPeriodo,
    ) {
    }

    public static function for(Company $empresa): self
    {
        // En revisión también está parado: todavía no genera. La diferencia es
        // que ése depende de una tarea de un rato, no de encontrar cliente.
        $libres = $empresa->washingMachines()
            ->whereIn('status', ['disponible', 'en_revision'])
            ->orderBy('machine_code')
            ->get();

        if ($libres->isEmpty()) {
            return new self(collect(), 0.0);
        }

        // Cuándo terminó la última renta de cada equipo, de un solo golpe en vez
        // de una consulta por aparato.
        $ultimas = DB::table('rentals')
            ->select('washing_machine_id', DB::raw('MAX(end_date) as ultima'))
            ->whereIn('washing_machine_id', $libres->pluck('id'))
            ->groupBy('washing_machine_id')
            ->pluck('ultima', 'washing_machine_id');

        $precio = (float) ($empresa->settings->price ?? 0);
        $hoy = Carbon::today();

        $equipos = $libres->map(function (WashingMachine $equipo) use ($ultimas, $hoy) {
            $desde = $ultimas[$equipo->id] ?? $equipo->purchase_date ?? $equipo->created_at;
            $desde = Carbon::parse($desde)->startOfDay();

            // Una renta que termina en el futuro no lleva días parada: son cero.
            $dias = max(0, (int) $desde->diffInDays($hoy, false));

            return (object) [
                'equipo' => $equipo,
                'dias' => $dias,
                'nuncaRentado' => ! isset($ultimas[$equipo->id]),
                'enRevision' => $equipo->status === 'en_revision',
            ];
        })->sortByDesc('dias')->values();

        return new self($equipos, $precio * $libres->count());
    }

    public function hay(): bool
    {
        return $this->equipos->isNotEmpty();
    }

    public function cuantos(): int
    {
        return $this->equipos->count();
    }

    /** Los que llevan más de un mes sin salir: ésos ya son un problema. */
    public function olvidados(): Collection
    {
        return $this->equipos->filter(fn (object $fila) => $fila->dias >= 30)->values();
    }

    public function diasDelPeor(): int
    {
        return (int) ($this->equipos->first()?->dias ?? 0);
    }

    /**
     * A quién ofrecerle lo que está libre.
     *
     * Hay equipo parado y, por otro lado, una lista de gente que pidió
     * información y nadie volvió a marcarle. Las dos cosas existían en pantallas
     * distintas y nada las juntaba, que es donde estaba el desperdicio.
     *
     * @return Collection<int, ProspectiveClient>
     */
    public static function aQuienOfrecerle(Company $empresa, int $cuantos = 5): Collection
    {
        return ProspectiveClient::whereNull('converted_user_id')
            ->whereNull('last_contacted_at')
            ->whereNotNull('phone')
            ->where('phone', '<>', '')
            ->orderByDesc('created_at')
            ->take($cuantos)
            ->get();
    }
}
