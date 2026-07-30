<?php

namespace App\Support;

use App\Models\Company;
use App\Models\WashingMachine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Busca datos que se contradicen.
 *
 * En una sola sesión de trabajo apareció tres veces el mismo tipo de falla, y las
 * tres se encontraron a mano, de casualidad:
 *
 * - Un equipo en mantenimiento sin ninguna orden abierta que lo explicara. Dos en
 *   producción, uno de ellos sin una sola orden en su historial.
 * - Un equipo marcado como rentado sin renta que lo respaldara.
 * - Un equipo que se puede quedar en revisión para siempre porque nadie lo marca
 *   listo.
 *
 * Ninguna revienta nada. El aparato simplemente no aparece para rentar, y el
 * dueño se entera el día que le hace falta una lavadora y no la encuentra. Un
 * equipo parado es la única pérdida real de este negocio —el aparato ya está
 * pagado— así que esto es dinero detenido en silencio.
 *
 * Se revisa por empresa y no en global: cada dueño arregla lo suyo, y quien opera
 * la plataforma sólo necesita saber cuántas cuentas traen algo.
 */
class RevisionDeDatos
{
    /** Después de esto, "en revisión" ya no es un trámite: es un olvido. */
    public const DIAS_EN_REVISION_QUE_YA_ES_MUCHO = 7;

    /** @param Collection<int, object> $hallazgos */
    private function __construct(
        public readonly Company $empresa,
        public readonly Collection $hallazgos,
    ) {
    }

    public static function for(Company $empresa): self
    {
        return new self($empresa, collect([
            ...self::rentadasSinRenta($empresa),
            ...self::paradasSinOrden($empresa),
            ...self::olvidadasEnRevision($empresa),
            ...self::rentasAbiertasConEquipoLibre($empresa),
        ]));
    }

    public function hay(): bool
    {
        return $this->hallazgos->isNotEmpty();
    }

    public function cuantos(): int
    {
        return $this->hallazgos->count();
    }

    /**
     * Marcado como rentado y sin renta que lo respalde.
     *
     * Descuadra la ocupación y el desglose por tipo, y sobre todo: ese aparato no
     * aparece para rentar aunque no lo tenga nadie.
     *
     * @return array<int, object>
     */
    private static function rentadasSinRenta(Company $empresa): array
    {
        return $empresa->washingMachines()
            ->where('status', 'rentada')
            ->whereDoesntHave('rentals', fn ($q) => $q->whereIn('status', ['activa', 'vencida']))
            ->get()
            ->map(fn (WashingMachine $equipo) => (object) [
                'tipo' => 'rentada-sin-renta',
                'equipo' => $equipo->machine_code,
                'que_pasa' => 'Está marcado como rentado y no tiene renta abierta.',
                'que_hacer' => 'Si ya lo devolvieron, márcalo disponible. Si lo tiene alguien, ábrele su renta.',
            ])
            ->all();
    }

    /**
     * En mantenimiento sin orden abierta.
     *
     * No aparece para rentar, la pantalla de mantenimientos no dice por qué, y no
     * hay con qué darlo por terminado.
     *
     * @return array<int, object>
     */
    private static function paradasSinOrden(Company $empresa): array
    {
        return $empresa->washingMachines()
            ->where('status', 'mantenimiento')
            ->whereDoesntHave('maintenances', fn ($q) => $q->whereIn('status', ['programada', 'en_progreso']))
            ->get()
            ->map(fn (WashingMachine $equipo) => (object) [
                'tipo' => 'parada-sin-orden',
                'equipo' => $equipo->machine_code,
                'que_pasa' => 'Está en mantenimiento y no hay ningún trabajo pendiente.',
                'que_hacer' => 'Si ya quedó, márcalo disponible. Si le falta algo, ábrele su orden.',
            ])
            ->all();
    }

    /**
     * En revisión desde hace demasiado.
     *
     * Revisar un aparato es cosa de un rato. Una semana ahí quiere decir que a
     * nadie se le ocurrió marcarlo listo, y mientras tanto no se puede rentar.
     *
     * @return array<int, object>
     */
    private static function olvidadasEnRevision(Company $empresa): array
    {
        return $empresa->washingMachines()
            ->where('status', 'en_revision')
            ->where('updated_at', '<', now()->subDays(self::DIAS_EN_REVISION_QUE_YA_ES_MUCHO))
            ->get()
            ->map(fn (WashingMachine $equipo) => (object) [
                'tipo' => 'olvidada-en-revision',
                'equipo' => $equipo->machine_code,
                'que_pasa' => 'Lleva más de una semana en revisión sin que nadie lo marque listo.',
                'que_hacer' => 'Con el botón "Ya está lista" vuelve a aparecer para rentar.',
            ])
            ->all();
    }

    /**
     * Renta abierta pero el equipo figura libre.
     *
     * Es el espejo del primero, y el más caro de los dos: ese aparato se le puede
     * rentar a un segundo cliente porque el sistema lo cree disponible.
     *
     * @return array<int, object>
     */
    private static function rentasAbiertasConEquipoLibre(Company $empresa): array
    {
        return DB::table('rentals')
            ->join('washing_machines', 'washing_machines.id', '=', 'rentals.washing_machine_id')
            ->where('rentals.company_id', $empresa->id)
            ->whereIn('rentals.status', ['activa', 'vencida'])
            ->whereIn('washing_machines.status', ['disponible', 'en_revision'])
            ->select('washing_machines.machine_code')
            ->get()
            ->map(fn (object $fila) => (object) [
                'tipo' => 'renta-con-equipo-libre',
                'equipo' => $fila->machine_code,
                'que_pasa' => 'Tiene una renta abierta pero el equipo figura como libre.',
                'que_hacer' => 'Revísalo: así se le puede rentar el mismo aparato a otro cliente.',
            ])
            ->all();
    }

    /**
     * Las cuentas reales que traen algo por revisar.
     *
     * @return Collection<int, self>
     */
    public static function todasLasCuentas(): Collection
    {
        return Company::where('is_demo', false)
            ->get()
            ->map(fn (Company $empresa) => self::for($empresa))
            ->filter(fn (self $revision) => $revision->hay())
            ->values();
    }
}
