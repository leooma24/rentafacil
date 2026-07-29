<?php

namespace App\Support;

use App\Models\CashClosing;
use App\Models\Company;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Lo que le falta hacer al dueño hoy, con el botón para hacerlo.
 *
 * El escritorio decía cuánto le deben y quién, pero no las tareas que dejan
 * rastro y se olvidan: registrar una entrega, salir a cobrar en orden, cerrar el
 * día. Son cosas que sólo se hacen si algo te las recuerda.
 *
 * Cada pendiente sólo aparece cuando de verdad lo está. Una lista que siempre
 * enseña lo mismo deja de leerse a la semana.
 */
class PendientesDelDia
{
    /** @param array<int, Pendiente> $pendientes */
    private function __construct(public readonly array $pendientes)
    {
    }

    public static function for(Company $empresa, ?User $usuario = null): self
    {
        $usuario ??= auth()->user();

        return new self(array_values(array_filter([
            self::entregas($empresa),
            // Va antes que la cobranza: al que ya pasó de la raya no se le vuelve
            // a avisar, se va por la lavadora.
            self::recolecciones($empresa),
            self::cobranza($empresa),
            self::equiposParados($empresa),
            self::equiposSinColocar($empresa),
            self::corte($empresa, $usuario),
        ])));
    }

    /**
     * A quién ya toca irle a quitar el equipo.
     *
     * El número que se enseña no es lo que te deben —eso ya está perdido— sino lo
     * que sigue costando cada semana que ese aparato está allá en vez de estar con
     * alguien que sí paga. Es lo que hace que uno se suba a la camioneta.
     */
    private static function recolecciones(Company $empresa): ?Pendiente
    {
        $cola = ParaRecoger::for($empresa);

        if (! $cola->hay()) {
            return null;
        }

        $cuantas = $cola->cuantas();
        $detenido = $cola->rentaDetenidaPorPeriodo();

        return new Pendiente(
            clave: 'recoger',
            titulo: $cuantas === 1
                ? '1 equipo para recoger'
                : "{$cuantas} equipos para recoger",
            detalle: $detenido > 0
                ? 'Llevan ' . $cola->periodosDeTolerancia . ' periodos o más sin pagar. Son $'
                    . number_format($detenido, 2) . ' de renta detenida cada periodo.'
                : 'Llevan ' . $cola->periodosDeTolerancia . ' periodos o más sin pagar.',
            accion: 'Ver a quiénes',
            icono: 'heroicon-o-arrow-uturn-left',
            color: 'danger',
            ruta: 'filament.propietario.pages.avisos',
        );
    }

    /**
     * Equipo libre que lleva demasiado tiempo sin salir.
     *
     * Una lavadora parada no avisa: no manda notificaciones y no le duele a nadie
     * hasta que la cuenta del mes no cuadra. En este negocio el aparato ya está
     * pagado, así que el único costo real es el tiempo que pasa en la bodega.
     */
    private static function equiposSinColocar(Company $empresa): ?Pendiente
    {
        $olvidados = EquipoParado::for($empresa)->olvidados();

        if ($olvidados->isEmpty()) {
            return null;
        }

        $cuantos = $olvidados->count();
        $peor = (int) $olvidados->first()->dias;

        return new Pendiente(
            clave: 'sin_colocar',
            titulo: $cuantos === 1
                ? '1 equipo lleva ' . $peor . ' días sin rentarse'
                : "{$cuantos} equipos llevan más de un mes sin rentarse",
            detalle: 'Están libres y no generan nada. Tienes prospectos sin contactar a quienes ofrecérselos.',
            accion: 'Ver a quién ofrecerle',
            icono: 'heroicon-o-clock',
            color: 'warning',
            ruta: 'filament.propietario.pages.contactar',
        );
    }

    /**
     * Equipos marcados en mantenimiento sin ninguna orden abierta que lo explique.
     *
     * Es dinero parado y en silencio: el aparato no aparece para rentar, no hay
     * nada en la pantalla de mantenimientos que diga por qué, y el dueño se
     * entera cuando le hace falta una lavadora y no la encuentra. En producción
     * hay dos así, uno de ellos sin una sola orden en su historial.
     */
    private static function equiposParados(Company $empresa): ?Pendiente
    {
        $parados = $empresa->washingMachines()
            ->where('status', 'mantenimiento')
            ->whereDoesntHave('maintenances', fn ($query) => $query
                ->whereIn('status', ['programada', 'en_progreso']))
            ->count();

        if ($parados === 0) {
            return null;
        }

        return new Pendiente(
            clave: 'parados',
            titulo: $parados === 1
                ? '1 equipo parado sin orden abierta'
                : "{$parados} equipos parados sin orden abierta",
            detalle: 'Está marcado en mantenimiento pero no hay trabajo pendiente. Así no aparece para rentar.',
            accion: 'Revisarlos',
            icono: 'heroicon-o-wrench-screwdriver',
            color: 'warning',
            ruta: 'filament.propietario.resources.lavadoras.index',
        );
    }

    /** Equipos que ya están con el cliente y nadie registró cómo se entregaron. */
    private static function entregas(Company $empresa): ?Pendiente
    {
        $cuantas = $empresa->rentals()
            ->whereIn('status', ['activa', 'vencida'])
            ->whereNull('delivered_at')
            ->count();

        if ($cuantas === 0) {
            return null;
        }

        return new Pendiente(
            clave: 'entregas',
            titulo: $cuantas === 1
                ? '1 entrega sin registrar'
                : "{$cuantas} entregas sin registrar",
            detalle: 'Sin fotos del estado no tienes con qué responder si te lo devuelven dañado.',
            accion: 'Registrarlas',
            icono: 'heroicon-o-truck',
            color: 'info',
            ruta: 'filament.propietario.resources.mis-rentas.index',
        );
    }

    /** A quién hay que cobrarle hoy, y con qué orden salir. */
    private static function cobranza(Company $empresa): ?Pendiente
    {
        $porCobrar = $empresa->rentals()
            ->whereIn('status', ['activa', 'vencida'])
            ->whereDate('end_date', '<=', today())
            ->count();

        if ($porCobrar === 0) {
            return null;
        }

        // Si nadie está ubicado en el mapa, el planificador no puede trazar nada
        // y mandarlo ahí sería mandarlo a una pantalla vacía.
        $ubicados = DB::table('addresses')
            ->join('customers', 'customers.id', '=', 'addresses.addressable_id')
            ->where('addresses.addressable_type', \App\Models\Customer::class)
            ->where('customers.company_id', $empresa->id)
            ->whereNotNull('addresses.latitude')
            ->where('addresses.latitude', '<>', 0)
            ->count();

        return new Pendiente(
            clave: 'cobranza',
            titulo: $porCobrar === 1
                ? '1 cliente por cobrar'
                : "{$porCobrar} clientes por cobrar",
            detalle: $ubicados > 0
                ? 'Arma la ruta más corta y sal con ella en el celular.'
                : 'Ubica a tus clientes en el mapa para poder armar la ruta.',
            accion: $ubicados > 0 ? 'Armar la ruta' : 'Ver a quién cobrar',
            icono: 'heroicon-o-map',
            color: 'warning',
            ruta: $ubicados > 0
                ? 'filament.propietario.pages.rutas'
                : 'filament.propietario.resources.mis-rentas.index',
        );
    }

    /** Se cobró hoy y todavía no se cuadra el efectivo. */
    private static function corte(Company $empresa, ?User $usuario): ?Pendiente
    {
        if (! $usuario) {
            return null;
        }

        $efectivoDeHoy = (float) Payment::where('company_id', $empresa->id)
            ->where('status', 'completado')
            ->where('collected_by', $usuario->id)
            ->whereDate('payment_date', today())
            ->where('payment_method', 'like', '%fectivo%')
            ->sum('amount');

        if ($efectivoDeHoy <= 0) {
            return null;
        }

        $yaCerrado = CashClosing::where('company_id', $empresa->id)
            ->where('user_id', $usuario->id)
            ->whereDate('closing_date', today())
            ->exists();

        if ($yaCerrado) {
            return null;
        }

        return new Pendiente(
            clave: 'corte',
            titulo: 'Falta cerrar el día',
            detalle: 'Traes $' . number_format($efectivoDeHoy, 2) . ' en efectivo sin cuadrar.',
            accion: 'Cerrar el día',
            icono: 'heroicon-o-calculator',
            color: 'success',
            ruta: 'filament.propietario.pages.corte-de-caja',
        );
    }

    public function hayPendientes(): bool
    {
        return $this->pendientes !== [];
    }
}
