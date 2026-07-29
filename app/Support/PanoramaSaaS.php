<?php

namespace App\Support;

use App\Models\Company;
use App\Models\ProspectiveClient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cómo va el negocio de RentaFácil, para quien lo opera.
 *
 * No es el escritorio de un rentador: es el de quien vende la app. La pregunta
 * que contesta es dónde se están quedando las cuentas, porque el número duro es
 * que de 17 empresas registradas sólo 6 cargaron un equipo y una sola ha llegado
 * a registrar un cobro.
 *
 * Todo excluye las demos. Una demo trae 17 equipos y 200 pagos de mentiras, y
 * mezclarlas fue justo lo que hizo que las cifras se vieran cuatro veces mejor
 * de lo que son.
 */
class PanoramaSaaS
{
    /** Antes de esto una cuenta recién creada no está "atorada", va empezando. */
    public const DIAS_PARA_CONSIDERARLA_ATORADA = 2;

    /** Con cuántos días de anticipación avisar de una prueba por vencer. */
    public const DIAS_DE_AVISO_DE_PRUEBA = 7;

    private function __construct(
        public readonly int $registradas,
        public readonly int $conEquipo,
        public readonly int $rentando,
        public readonly int $cobrando,
    ) {
    }

    public static function actual(): self
    {
        $reales = Company::where('is_demo', false);

        $conAlgo = fn (string $tabla) => Company::where('is_demo', false)
            ->whereIn('id', DB::table($tabla)->select('company_id')->distinct())
            ->count();

        return new self(
            registradas: (clone $reales)->count(),
            conEquipo: $conAlgo('washing_machines'),
            rentando: $conAlgo('rentals'),
            cobrando: $conAlgo('payments'),
        );
    }

    /** Qué porcentaje del total llegó hasta cierto paso. */
    public function porcentaje(int $cuantas): int
    {
        return $this->registradas > 0
            ? (int) round($cuantas / $this->registradas * 100)
            : 0;
    }

    /**
     * Las cuentas que se registraron y nunca cargaron un equipo.
     *
     * Es la lista más accionable que hay: son gente que ya dijo que sí y se
     * quedó en la puerta. Trae su contacto para poder marcarles.
     *
     * @return Collection<int, Company>
     */
    public static function atoradas(): Collection
    {
        return Company::where('is_demo', false)
            ->where('created_at', '<=', now()->subDays(self::DIAS_PARA_CONSIDERARLA_ATORADA))
            ->whereNotIn('id', DB::table('washing_machines')->select('company_id')->distinct())
            ->withCount('members')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Las que cargaron equipo pero nunca han cobrado.
     *
     * Llegaron más lejos que las atoradas: entendieron para qué era. Se quedaron
     * a un paso, y ese paso es el que vuelve a la app parte de su rutina.
     *
     * @return Collection<int, Company>
     */
    public static function sinCobrar(): Collection
    {
        return Company::where('is_demo', false)
            ->whereIn('id', DB::table('washing_machines')->select('company_id')->distinct())
            ->whereNotIn('id', DB::table('payments')->select('company_id')->distinct())
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Pruebas que se acaban pronto.
     *
     * @return Collection<int, Company>
     */
    public static function pruebasPorVencer(): Collection
    {
        return Company::where('is_demo', false)
            ->with('companyPackage.package')
            ->get()
            ->filter(fn (Company $empresa) => $empresa->isOnTrial()
                && $empresa->trialDaysLeft() <= self::DIAS_DE_AVISO_DE_PRUEBA)
            ->sortBy(fn (Company $empresa) => $empresa->trialDaysLeft())
            ->values();
    }

    /**
     * Las que sí cargaron equipo, con cuánto cargó cada una.
     *
     * Aunque el plan ya se les haya vencido —de hecho sobre todo entonces. Una
     * cuenta expirada que alcanzó a dar de alta ocho lavadoras no es lo mismo
     * que una que se registró y nunca abrió: la primera ya sabe para qué sirve
     * la app, y es a quien conviene marcarle. Sin esta lista las dos se veían
     * igual desde afuera, o sea, no se veían.
     *
     * @return Collection<int, Company>
     */
    public static function queLoUsaron(): Collection
    {
        return Company::where('is_demo', false)
            ->whereIn('id', DB::table('washing_machines')->select('company_id')->distinct())
            ->withCount(['washingMachines', 'rentals', 'payments'])
            ->get()
            ->sortByDesc('washing_machines_count')
            ->values();
    }

    public static function prospectosSinContactar(): int
    {
        return ProspectiveClient::whereNull('last_contacted_at')->count();
    }

    /** Cuántos demos se generaron en los últimos 30 días: es señal de interés. */
    public static function demosRecientes(): int
    {
        return Company::where('is_demo', true)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();
    }
}
