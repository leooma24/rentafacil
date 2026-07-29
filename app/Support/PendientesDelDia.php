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
            self::cobranza($empresa),
            self::corte($empresa, $usuario),
        ])));
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
