<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * Qué herramientas tiene una empresa y cuáles no ha estrenado.
 *
 * De 17 cuentas reales, 9 cargaron lavadoras y sólo 4 registran pagos. El
 * problema no es que falten funciones: es que nadie les dijo para qué sirven.
 *
 * Cada tarjeta se apoya en la huella que la función deja en los datos, no en
 * "¿ya lo probaste?". Cuando no hay huella que mirar el estado queda en null y
 * la tarjeta no presume de saber algo que no sabe.
 */
class Provecho
{
    public const USANDO = 'usando';
    public const SIN_ESTRENAR = 'sin_estrenar';

    /** @param array<int, Herramienta> $herramientas */
    private function __construct(public readonly array $herramientas)
    {
    }

    public static function for(Company $company): self
    {
        $lavadoras = $company->washingMachines()->count();
        $clientes = $company->customers()->count();

        $direcciones = DB::table('addresses')
            ->join('customers', 'customers.id', '=', 'addresses.addressable_id')
            ->where('addresses.addressable_type', \App\Models\Customer::class)
            ->where('customers.company_id', $company->id)
            ->selectRaw('count(*) total, sum(case when addresses.latitude is not null and addresses.latitude <> 0 then 1 else 0 end) ubicadas')
            ->first();

        $conDireccion = (int) ($direcciones->total ?? 0);
        $ubicadas = (int) ($direcciones->ubicadas ?? 0);

        $estados = app(AccountStatement::class)->forCompany($company);
        $deudores = $estados->count();
        $adeudo = (float) $estados->sum(fn (Statement $estado) => $estado->total);

        $cobros = Payment::where('company_id', $company->id)->where('status', 'completado')->count();
        $abonos = Payment::where('company_id', $company->id)->where('applied', false)->count();
        $mantenimientos = $company->maintenances()->count();
        $incidencias = $company->incidents()->count();

        $porVencer = $company->rentals()
            ->where('status', 'activa')
            ->whereDate('end_date', '>=', today())
            ->whereDate('end_date', '<=', today()->addDays(30))
            ->count();

        return new self(array_values(array_filter([
            new Herramienta(
                clave: 'estado-de-cuenta',
                titulo: 'Manda el estado de cuenta por WhatsApp',
                beneficio: 'El cliente ve cuánto debe y desde cuándo, en una página que abre sin instalar nada. Se acaba el "yo ya te pagué".',
                comoSeUsa: 'En Clientes, abre uno y toca "Estado de cuenta". De ahí sale el botón de WhatsApp con el mensaje ya escrito.',
                pista: $deudores > 0
                    ? "Ahorita {$deudores} " . ($deudores === 1 ? 'cliente te debe' : 'clientes te deben') . ' $' . number_format($adeudo, 2) . '.'
                    : 'Ahorita nadie te debe, pero tenlo a la mano para cuando alguien se atrase.',
                estado: null,
                icono: 'heroicon-o-chat-bubble-left-right',
                ruta: 'filament.propietario.resources.clientes.index',
                accion: 'Ver mis clientes',
                peso: $deudores > 0 ? 100 : 40,
            ),

            new Herramienta(
                clave: 'rutas',
                titulo: 'Sal a cobrar en el orden más corto',
                beneficio: 'Le dices a quién vas a visitar y te arma la ruta más corta, lista para abrir en Google Maps. Menos vueltas y menos gasolina.',
                comoSeUsa: 'Necesita la ubicación de cada cliente. Ábrela desde la dirección del cliente con el botón de ubicación.',
                pista: $conDireccion > 0 && $ubicadas === 0
                    ? "Tienes {$conDireccion} " . ($conDireccion === 1 ? 'cliente con dirección' : 'clientes con dirección') . ', pero ninguno con ubicación en el mapa: por eso la ruta todavía no se puede trazar.'
                    : ($ubicadas > 0
                        ? "{$ubicadas} de tus {$conDireccion} clientes ya están ubicados en el mapa."
                        : 'Captura la dirección de tus clientes y podrás armar la ruta del día.'),
                estado: $ubicadas > 0 ? self::USANDO : self::SIN_ESTRENAR,
                icono: 'heroicon-o-map',
                ruta: 'filament.propietario.pages.rutas',
                accion: 'Abrir el planificador',
                peso: 95,
            ),

            new Herramienta(
                clave: 'abonos',
                titulo: 'Acepta lo que el cliente traiga',
                beneficio: 'Si te trae menos de la renta completa, lo registras como abono en vez de rechazarlo. El saldo baja y cuando junta el periodo la renta se extiende sola.',
                comoSeUsa: 'En Rentas o en Lavadoras, toca "Abonar" y escribe lo que te dio.',
                pista: $abonos > 0
                    ? "Llevas {$abonos} " . ($abonos === 1 ? 'abono registrado' : 'abonos registrados') . '.'
                    : 'Nunca has registrado un abono: cada vez que alguien traiga incompleto, esto te evita perder el cobro.',
                estado: $abonos > 0 ? self::USANDO : self::SIN_ESTRENAR,
                icono: 'heroicon-o-banknotes',
                ruta: 'filament.propietario.resources.mis-rentas.index',
                accion: 'Ver mis rentas',
                peso: 90,
            ),

            new Herramienta(
                clave: 'mantenimientos',
                titulo: 'Lleva el historial de cada lavadora',
                beneficio: 'Sabes qué se le ha hecho a cada aparato y cuánto llevas gastado en él. Con eso decides cuál ya no conviene reparar y cuál cambiar.',
                comoSeUsa: 'En Mantenimientos, registra el servicio con su costo. La lavadora queda marcada mientras dura.',
                pista: $mantenimientos > 0
                    ? "Llevas {$mantenimientos} " . ($mantenimientos === 1 ? 'servicio registrado' : 'servicios registrados') . '.'
                    : ($lavadoras > 0
                        ? "Tienes {$lavadoras} lavadoras y ningún servicio registrado: sin esto no sabes cuánto te cuesta cada una."
                        : null),
                estado: $mantenimientos > 0 ? self::USANDO : self::SIN_ESTRENAR,
                icono: 'heroicon-o-wrench-screwdriver',
                ruta: 'filament.propietario.resources.mantenimientos.index',
                accion: 'Ver mantenimientos',
                peso: 70,
            ),

            new Herramienta(
                clave: 'incidencias',
                titulo: 'Que no se te pierda ningún reporte',
                beneficio: 'Cada falla que te reporta un cliente queda anotada con su prioridad, y ves cuánto tardas en resolverlas. Es lo que hace que no se te olvide una.',
                comoSeUsa: 'En Incidencias, levanta el reporte cuando te hablen. Ciérralo cuando quede resuelto.',
                pista: $incidencias > 0
                    ? "Llevas {$incidencias} " . ($incidencias === 1 ? 'reporte levantado' : 'reportes levantados') . '.'
                    : 'Ningún reporte levantado todavía: aquí es donde se anotan las fallas para que no se te pasen.',
                estado: $incidencias > 0 ? self::USANDO : self::SIN_ESTRENAR,
                icono: 'heroicon-o-exclamation-triangle',
                ruta: 'filament.propietario.resources.incidencias.index',
                accion: 'Ver incidencias',
                peso: 60,
            ),

            new Herramienta(
                clave: 'recibos',
                titulo: 'Dale su recibo al momento',
                beneficio: 'Al registrar un cobro sale un recibo con liga propia que le mandas por WhatsApp. El cliente tiene su comprobante y tú tu respaldo.',
                comoSeUsa: 'En Pagos, cada cobro trae el botón para compartirlo.',
                pista: $cobros > 0
                    ? "Llevas {$cobros} " . ($cobros === 1 ? 'cobro registrado' : 'cobros registrados') . ', y a cada uno le puedes mandar su recibo.'
                    : 'Cuando registres tu primer cobro, de ahí sale el recibo.',
                estado: null,
                icono: 'heroicon-o-receipt-percent',
                ruta: 'filament.propietario.resources.pagos.index',
                accion: 'Ver mis pagos',
                peso: 55,
            ),

            new Herramienta(
                clave: 'calendario',
                titulo: 'Mira la semana de un vistazo',
                beneficio: 'Todas las rentas en un calendario: qué vence qué día. Sirve para planear la semana en lugar de ir apagando fuegos.',
                comoSeUsa: 'Está en el menú, en Calendario.',
                pista: $porVencer > 0
                    ? "{$porVencer} " . ($porVencer === 1 ? 'renta vence' : 'rentas vencen') . ' en los próximos 30 días.'
                    : null,
                estado: null,
                icono: 'heroicon-o-calendar-days',
                ruta: 'filament.propietario.pages.calendario',
                accion: 'Abrir el calendario',
                peso: 50,
            ),

            // Sólo tiene sentido ofrecerlo cuando todavía hay poco cargado.
            $lavadoras < 10 || $clientes < 10
                ? new Herramienta(
                    clave: 'importar',
                    titulo: 'Sube tu Excel de un jalón',
                    beneficio: 'No tienes que capturar uno por uno. Si ya llevas tu lista en Excel, se sube completa en un paso.',
                    comoSeUsa: 'En Lavadoras y en Clientes, con el botón "Importar".',
                    pista: "Llevas {$lavadoras} lavadoras y {$clientes} clientes cargados.",
                    estado: null,
                    icono: 'heroicon-o-arrow-up-tray',
                    ruta: 'filament.propietario.resources.lavadoras.index',
                    accion: 'Ir a lavadoras',
                    peso: 85,
                )
                : null,
        ])));
    }

    /** Lo que no ha estrenado, primero lo que más le sirve. */
    public function sinEstrenar(): array
    {
        return collect($this->herramientas)
            ->filter(fn (Herramienta $h) => $h->estado === self::SIN_ESTRENAR)
            ->sortByDesc(fn (Herramienta $h) => $h->peso)
            ->values()
            ->all();
    }

    public function resto(): array
    {
        return collect($this->herramientas)
            ->reject(fn (Herramienta $h) => $h->estado === self::SIN_ESTRENAR)
            ->sortByDesc(fn (Herramienta $h) => $h->peso)
            ->values()
            ->all();
    }

    public function totalSinEstrenar(): int
    {
        return count($this->sinEstrenar());
    }
}
