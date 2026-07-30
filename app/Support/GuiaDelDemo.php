<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Rental;
use Illuminate\Support\Facades\DB;

/**
 * Qué probar dentro del demo.
 *
 * El problema que resuelve: el visitante entra, ve un escritorio lleno de
 * números bonitos y se sale sin haber tocado nada. Lo que vende esta app no son
 * las gráficas —eso lo tiene cualquiera— sino que cobrar extiende la fecha
 * sola, que la ruta se ordena sola y que el cobrador no ve tus precios. Nada de
 * eso se descubre mirando.
 *
 * Es una lista de cosas concretas que hacer, no un tour flotante encima de la
 * pantalla: esos se cierran en el primer clic y en el celular estorban. Ésta se
 * queda en el escritorio, se puede ir haciendo en cualquier orden y va marcando
 * lo ya visto.
 *
 * Cada paso se cuelga de datos que este demo sí tiene —el moroso más viejo, el
 * equipo que se cambió, el cobrador dado de alta— porque "ve a Rentas" no le
 * dice nada a nadie y "cóbrale a Verónica, que debe desde hace 38 días" sí.
 */
class GuiaDelDemo
{
    /** Dónde se recuerda lo ya visitado. Vive en la sesión: el demo dura un rato. */
    public const SESSION_KEY = 'demo.guia.vistos';

    private function __construct(
        public readonly Company $empresa,
        /** @var array<int, array<string, string>> */
        public readonly array $pasos,
    ) {
    }

    public static function for(Company $empresa): self
    {
        return new self($empresa, self::armarPasos($empresa));
    }

    public function total(): int
    {
        return count($this->pasos);
    }

    public function vistos(): int
    {
        return count(array_filter($this->pasos, fn (array $paso) => $paso['visto']));
    }

    public function termino(): bool
    {
        return $this->vistos() >= $this->total();
    }

    /** El primero que le falta, que es por donde conviene que siga. */
    public function siguiente(): ?array
    {
        foreach ($this->pasos as $paso) {
            if (! $paso['visto']) {
                return $paso;
            }
        }

        return null;
    }

    public static function marcarVisto(string $clave): void
    {
        $vistos = session(self::SESSION_KEY, []);
        $vistos[$clave] = true;

        session([self::SESSION_KEY => $vistos]);
    }

    /**
     * @return array<int, array<string, string>>
     */
    private static function armarPasos(Company $empresa): array
    {
        $vistos = session(self::SESSION_KEY, []);

        $pasos = array_filter([
            self::pasoCobrar($empresa),
            self::pasoAvisar($empresa),
            self::pasoRuta($empresa),
            self::pasoCorte($empresa),
            self::pasoCambio($empresa),
            self::pasoPapeles($empresa),
            self::pasoCobrador($empresa),
            self::pasoGanancia($empresa),
            self::pasoRecoger($empresa),
            self::pasoBitacora($empresa),
        ]);

        return array_values(array_map(
            fn (array $paso) => $paso + ['visto' => (bool) ($vistos[$paso['clave']] ?? false)],
            $pasos
        ));
    }

    private static function pasoCobrar(Company $empresa): ?array
    {
        $renta = $empresa->rentals()
            ->whereIn('status', ['activa', 'vencida'])
            ->with('customer')
            ->orderBy('end_date')
            ->first();

        if (! $renta) {
            return null;
        }

        $dias = (int) now()->startOfDay()->diffInDays($renta->end_date, false);
        $quien = $renta->customer?->name ?? 'un cliente';

        return [
            'clave' => 'cobrar',
            'icono' => 'heroicon-o-banknotes',
            'titulo' => 'Regístrale un cobro a ' . $quien,
            'gancho' => $dias < 0
                ? 'Lleva ' . abs($dias) . ' días vencido. El botón Cobrar le extiende la fecha solo: no hay que sacar cuentas ni corregir nada a mano.'
                : 'El botón Cobrar le extiende la fecha solo: no hay que sacar cuentas ni corregir nada a mano.',
            'url' => \App\Filament\Resources\RentalResource::getUrl('index', tenant: $empresa),
        ];
    }

    private static function pasoAvisar(Company $empresa): ?array
    {
        $vencidas = $empresa->rentals()
            ->whereIn('status', ['activa', 'vencida'])
            ->whereDate('end_date', '<', now())
            ->count();

        if ($vencidas === 0) {
            return null;
        }

        return [
            'clave' => 'avisar',
            'icono' => 'heroicon-o-chat-bubble-left-right',
            'titulo' => 'Mándale un aviso por WhatsApp a los ' . $vencidas . ' vencidos',
            'gancho' => 'El mensaje ya viene escrito con su nombre, su equipo y cuánto debe. Tocas y se abre WhatsApp.',
            'url' => \App\Filament\Pages\AvisosPage::getUrl(tenant: $empresa),
        ];
    }

    private static function pasoRuta(Company $empresa): array
    {
        $ubicados = DB::table('addresses')
            ->where('addressable_type', Customer::class)
            ->whereIn('addressable_id', $empresa->customers()->select('id'))
            ->whereNotNull('latitude')
            ->count();

        return [
            'clave' => 'ruta',
            'icono' => 'heroicon-o-map',
            'titulo' => 'Arma la ruta de cobranza del día',
            'gancho' => 'Tus ' . $ubicados . ' clientes están ubicados. Marcas a quién vas a visitar y te ordena las paradas de la más corta, listo para abrir en Google Maps.',
            'url' => \App\Filament\Pages\RoutePlanner::getUrl(tenant: $empresa),
        ];
    }

    private static function pasoCorte(Company $empresa): array
    {
        return [
            'clave' => 'corte',
            'icono' => 'heroicon-o-calculator',
            'titulo' => 'Cierra la caja del día',
            'gancho' => 'Te dice cuánto efectivo deberías traer encima y lo comparas con lo que contaste. Si falta, queda asentado quién cerró ese día.',
            'url' => \App\Filament\Pages\CorteDeCajaPage::getUrl(tenant: $empresa),
        ];
    }

    private static function pasoCambio(Company $empresa): ?array
    {
        $cambio = DB::table('rental_machine_changes')
            ->whereIn('rental_id', $empresa->rentals()->select('id'))
            ->orderByDesc('id')
            ->first();

        if (! $cambio) {
            return null;
        }

        $renta = Rental::with('customer')->find($cambio->rental_id);

        if (! $renta) {
            return null;
        }

        return [
            'clave' => 'cambio',
            'icono' => 'heroicon-o-arrow-path',
            'titulo' => 'Mira el cambio de equipo de ' . ($renta->customer?->name ?? 'un cliente'),
            'gancho' => 'Se le descompuso la lavadora y se le llevó otra. La renta no se canceló: conserva sus pagos, su saldo y su fecha, y quedó escrito qué equipo traía antes.',
            'url' => \App\Filament\Resources\RentalResource::getUrl('edit', ['record' => $renta], tenant: $empresa),
        ];
    }

    private static function pasoPapeles(Company $empresa): ?array
    {
        $documento = DB::table('customer_documents')
            ->whereIn('customer_id', $empresa->customers()->select('id'))
            ->orderBy('id')
            ->first();

        if (! $documento) {
            return null;
        }

        $cliente = Customer::find($documento->customer_id);

        if (! $cliente) {
            return null;
        }

        return [
            'clave' => 'papeles',
            'icono' => 'heroicon-o-identification',
            'titulo' => 'Abre los papeles de ' . $cliente->name,
            'gancho' => 'Su INE y su comprobante de domicilio, guardados en su ficha. Es con lo que se recupera un aparato cuando alguien se muda con él. Tu cobrador no los puede ver.',
            'url' => \App\Filament\Resources\CustomerResource::getUrl('edit', ['record' => $cliente], tenant: $empresa),
        ];
    }

    private static function pasoCobrador(Company $empresa): ?array
    {
        $cobrador = $empresa->members()
            ->whereHas('roles', fn ($query) => $query->where('name', 'cobrador'))
            ->first();

        if (! $cobrador) {
            return null;
        }

        return [
            'clave' => 'cobrador',
            'icono' => 'heroicon-o-users',
            'titulo' => 'Revisa qué alcanza a ver ' . $cobrador->name,
            'gancho' => 'Entra con su propia clave, ve a quién cobrar y registra los pagos. No ve tus precios, tus reportes ni los papeles de nadie, y no puede borrar.',
            'url' => \App\Filament\Resources\EquipoResource::getUrl('index', tenant: $empresa),
        ];
    }

    /**
     * Recoger sin perder el adeudo.
     *
     * Es el paso del ciclo que más se pregunta —"¿y si dejan de pagar?"— y la
     * respuesta es justo lo que separa esto de una libreta: se recupera el
     * equipo y lo que quedó debiendo no se borra.
     */
    private static function pasoRecoger(Company $empresa): ?array
    {
        $recogida = $empresa->rentals()
            ->where('debt_at_close', '>', 0)
            ->with(['customer', 'washingMachine'])
            ->latest('end_date')
            ->first();

        if (! $recogida) {
            return null;
        }

        return [
            'clave' => 'recoger',
            'icono' => 'heroicon-o-arrow-uturn-left',
            'titulo' => 'Mira qué pasó cuando dejaron de pagar',
            'gancho' => 'A ' . ($recogida->customer?->name ?? 'un cliente') . ' se le recogió el equipo y quedó anotado que debe $'
                . number_format((float) $recogida->debt_at_close, 2) . '. Si vuelve a pedir lavadora, el sistema te lo advierte antes de entregársela.',
            'url' => \App\Filament\Resources\CustomerResource::getUrl('edit', ['record' => $recogida->customer], tenant: $empresa),
        ];
    }

    /** Toda la vida de un aparato, que es lo que nadie lleva en papel. */
    private static function pasoBitacora(Company $empresa): ?array
    {
        $equipo = $empresa->washingMachines()
            ->withCount('rentals')
            ->orderByDesc('rentals_count')
            ->first();

        if (! $equipo) {
            return null;
        }

        return [
            'clave' => 'bitacora',
            'icono' => 'heroicon-o-clock',
            'titulo' => 'Abre la historia completa de ' . $equipo->machine_code,
            'gancho' => 'Quién la ha tenido, qué se le ha reparado y cuánto costó, y si ya se pagó sola. Todo en orden y en una pantalla.',
            'url' => \App\Filament\Resources\WashingMachineResource::getUrl('bitacora', ['record' => $equipo], tenant: $empresa),
        ];
    }

    private static function pasoGanancia(Company $empresa): array
    {
        return [
            'clave' => 'ganancia',
            'icono' => 'heroicon-o-chart-bar',
            'titulo' => 'Descuenta tus gastos y mira qué te quedó',
            'gancho' => 'Gasolina, sueldos, refacciones y bodega salen de lo cobrado. Lo que el escritorio llama ganancia es lo que de verdad te queda, no lo que entró.',
            'url' => \App\Filament\Resources\ExpenseResource::getUrl('index', tenant: $empresa),
        ];
    }
}
