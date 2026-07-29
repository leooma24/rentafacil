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
        $cortes = \App\Models\CashClosing::where('company_id', $company->id)->count();

        $delMes = fn () => Payment::where('company_id', $company->id)
            ->where('status', 'completado')
            ->whereYear('payment_date', now()->year)
            ->whereMonth('payment_date', now()->month);

        $efectivoDelMes = (float) $delMes()->where('payment_method', 'like', '%fectivo%')->sum('amount');
        $ingresosDelMes = (float) $delMes()->sum('amount');

        $gastos = \App\Models\Expense::where('company_id', $company->id)
            ->whereYear('expense_date', now()->year)
            ->whereMonth('expense_date', now()->month)
            ->count();

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
                clave: 'gastos',
                titulo: 'Sabe de verdad cuánto ganas',
                beneficio: 'Anota la gasolina, los sueldos y las refacciones, y el escritorio te dice lo que te quedó de verdad. Sin eso, lo que cobras se lee como ganancia y no lo es.',
                comoSeUsa: 'En Finanzas, entra a Gastos y registra lo que vas pagando.',
                pista: $gastos === 0 && $ingresosDelMes > 0
                    ? 'Este mes cobraste $' . number_format($ingresosDelMes, 2) . ' y no has anotado un solo gasto: ese número no es tu ganancia.'
                    : ($gastos > 0
                        ? "Llevas {$gastos} " . ($gastos === 1 ? 'gasto anotado' : 'gastos anotados') . ' este mes.'
                        : 'Empieza por la gasolina de salir a cobrar: es la que más se escapa.'),
                estado: $gastos > 0 ? self::USANDO : self::SIN_ESTRENAR,
                icono: 'heroicon-o-arrow-trending-down',
                ruta: 'filament.propietario.resources.gastos.index',
                accion: 'Registrar un gasto',
                peso: 93,
            ),

            new Herramienta(
                clave: 'corte-de-caja',
                titulo: 'Cierra el día y cuadra tu efectivo',
                beneficio: 'Te dice cuánto efectivo deberías traer encima al terminar el día. Cuentas, lo anotas, y si no cuadra queda registrada la diferencia con su nota.',
                comoSeUsa: 'En Finanzas, entra a Corte de caja y toca "Cerrar el día".',
                pista: $efectivoDelMes > 0
                    ? 'Este mes has cobrado $' . number_format($efectivoDelMes, 2) . ' en efectivo. Eso es lo que pasa por tus manos sin dejar rastro en el banco.'
                    : 'Sirve para los días en que cobras en efectivo.',
                estado: $cortes > 0 ? self::USANDO : self::SIN_ESTRENAR,
                icono: 'heroicon-o-calculator',
                ruta: 'filament.propietario.pages.corte-de-caja',
                accion: 'Abrir el corte de caja',
                peso: 92,
            ),

            new Herramienta(
                clave: 'abonos',
                titulo: 'Acepta lo que el cliente traiga',
                beneficio: 'Si te trae menos de la renta completa, lo registras como abono en vez de rechazarlo. El saldo baja y cuando junta el periodo la renta se extiende sola.',
                comoSeUsa: 'En Rentas o en Equipos, toca "Abonar" y escribe lo que te dio.',
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
                titulo: 'Lleva el historial de cada equipo',
                beneficio: 'Sabes qué se le ha hecho a cada aparato y cuánto llevas gastado en él. Con eso decides cuál ya no conviene reparar y cuál cambiar.',
                comoSeUsa: 'En Mantenimientos, registra el servicio con su costo. El equipo queda marcado mientras dura.',
                pista: $mantenimientos > 0
                    ? "Llevas {$mantenimientos} " . ($mantenimientos === 1 ? 'servicio registrado' : 'servicios registrados') . '.'
                    : ($lavadoras > 0
                        ? "Tienes {$lavadoras} equipos y ningún servicio registrado: sin esto no sabes cuánto te cuesta cada uno."
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
                    pista: "Llevas {$lavadoras} equipos y {$clientes} clientes cargados.",
                    estado: null,
                    icono: 'heroicon-o-arrow-up-tray',
                    ruta: 'filament.propietario.resources.lavadoras.index',
                    accion: 'Ir a equipos',
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
