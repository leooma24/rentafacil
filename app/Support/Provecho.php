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

        $porAvisar = $company->rentals()
            ->whereIn('status', ['activa', 'vencida'])
            ->whereDate('end_date', '<=', today()->addDays(AvisosDelDia::DIAS_DE_ANTICIPACION))
            ->whereHas('customer', fn ($q) => $q->whereNotNull('phone')->where('phone', '<>', ''))
            ->count();

        $porVencer = $company->rentals()
            ->where('status', 'activa')
            ->whereDate('end_date', '>=', today())
            ->whereDate('end_date', '<=', today()->addDays(30))
            ->count();

        // Las señales de lo que se agregó después de la primera versión de esta
        // pantalla. Sin ellas, media app quedaba sin quien la presentara: se
        // construyeron recolección, evidencia de entrega, papeles del cliente,
        // cambio de equipo, cobrador, recargos y la bitácora, y ninguna aparecía
        // aquí. Una función que nadie sabe que existe no existe.
        $conDeposito = $company->rentals()->where('deposit', '>', 0)->count();
        $conEntrega = $company->rentals()->whereNotNull('delivery_photos')->count();
        $recogidasConAdeudo = $company->rentals()->where('debt_at_close', '>', 0)->count();

        $documentos = \App\Models\CustomerDocument::whereIn(
            'customer_id',
            $company->customers()->select('id')
        )->count();

        $cambios = DB::table('rental_machine_changes')
            ->whereIn('rental_id', $company->rentals()->select('id'))
            ->count();

        $cobradores = $company->members()
            ->whereHas('roles', fn ($q) => $q->where('name', 'cobrador'))
            ->count();

        $cobraRecargo = $company->settings?->chargesLateFee() ?? false;
        $paraRecoger = ParaRecoger::for($company);
        $parados = EquipoParado::for($company);
        $crecer = DecisionDeCrecer::for($company);

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
                clave: 'avisos',
                titulo: 'Avisa antes de que se venza',
                beneficio: 'Te arma la lista de a quién avisarle hoy, con el mensaje ya escrito. Tocas y se abre WhatsApp. Avisar antes es lo que evita el atraso.',
                comoSeUsa: 'En Gestión Principal, entra a "Avisos de hoy".',
                pista: $porAvisar > 0
                    ? "Hoy hay {$porAvisar} " . ($porAvisar === 1 ? 'cliente' : 'clientes') . ' a quien avisarle.'
                    : 'Aquí van a salir los que se vencen en los próximos días.',
                estado: null,
                icono: 'heroicon-o-chat-bubble-left-right',
                ruta: 'filament.propietario.pages.avisos',
                accion: 'Ver los avisos',
                peso: $porAvisar > 0 ? 97 : 45,
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


            // --- Lo que protege el aparato y el dinero ---

            new Herramienta(
                clave: 'recoleccion',
                titulo: 'Recoge el equipo sin perder lo que te deben',
                beneficio: 'Al recoger, el sistema te enseña cuánto te quedó debiendo y tú decides: queda anotado o quedaron en paz. Antes cerrar la renta borraba el adeudo, y el que te falló volvía a pedirte lavadora con la cuenta en ceros.',
                comoSeUsa: 'En Equipos, con el botón Recoger equipo. El aparato queda en revisión hasta que lo marques listo.',
                pista: $paraRecoger->hay()
                    ? 'Ahorita ya toca ir por ' . $paraRecoger->cuantas()
                        . ($paraRecoger->cuantas() === 1 ? ' equipo' : ' equipos')
                        . ': son $' . number_format($paraRecoger->rentaDetenidaPorPeriodo(), 2)
                        . ' de renta detenida cada periodo.'
                    : ($recogidasConAdeudo > 0
                        ? "Llevas {$recogidasConAdeudo} " . ($recogidasConAdeudo === 1 ? 'recolección' : 'recolecciones') . ' donde quedó anotado el adeudo.'
                        : 'Cuando alguien deje de pagar, esto es lo que te deja recuperar el equipo sin regalarle lo que debía.'),
                estado: $recogidasConAdeudo > 0 ? self::USANDO : self::SIN_ESTRENAR,
                icono: 'heroicon-o-arrow-uturn-left',
                ruta: 'filament.propietario.pages.avisos',
                accion: $paraRecoger->hay() ? 'Ver a quiénes' : 'Ver los avisos',
                peso: $paraRecoger->hay() ? 99 : 88,
            ),

            new Herramienta(
                clave: 'historial-cliente',
                titulo: 'Sabe a quién le estás volviendo a rentar',
                beneficio: 'Cada cliente trae su etiqueta: cumplido, se atrasa, o ya quedó a deber. Y al escogerlo para una renta nueva te lo dice antes de entregarle. Volverle a dar equipo a quien ya te falló cuesta el aparato completo, no una semana de renta.',
                comoSeUsa: 'Sale solo en la lista de Clientes y en el formulario de renta. No hay que hacer nada.',
                pista: $recogidasConAdeudo > 0
                    ? 'Ya hay clientes marcados por haber quedado a deber: fíjate en la columna Cómo paga.'
                    : 'Se llena solo conforme cobras. Con tres cobros ya te dice si alguien paga tarde siempre.',
                estado: null,
                icono: 'heroicon-o-identification',
                ruta: 'filament.propietario.resources.clientes.index',
                accion: 'Ver mis clientes',
                peso: 96,
            ),

            new Herramienta(
                clave: 'deposito',
                titulo: 'Pide depósito según cómo paga cada quien',
                beneficio: 'Al armar la renta te sugiere cuánto pedirle, medido en periodos: al nuevo dos, al que ya te falló tres, y al que lleva años pagando puntual ninguno. Es lo que te deja competir sin bajar el precio.',
                comoSeUsa: 'En el formulario de renta, el campo de depósito se precarga solo al escoger al cliente.',
                pista: $conDeposito > 0
                    ? "Llevas {$conDeposito} " . ($conDeposito === 1 ? 'renta con depósito' : 'rentas con depósito') . '.'
                    : 'Ninguna de tus rentas trae depósito: es lo único que protege el aparato si alguien se muda con él.',
                estado: $conDeposito > 0 ? self::USANDO : self::SIN_ESTRENAR,
                icono: 'heroicon-o-shield-check',
                ruta: 'filament.propietario.resources.mis-rentas.index',
                accion: 'Ver mis rentas',
                peso: 94,
            ),

            new Herramienta(
                clave: 'entregas',
                titulo: 'Deja constancia de cómo lo entregaste',
                beneficio: 'Fotos del equipo al entregarlo y al recogerlo. Es lo único con que puedes responder al así me la diste cuando te la devuelven golpeada, y lo que te permite retener parte del depósito con razón.',
                comoSeUsa: 'En Rentas, con el botón Entregar. Al recoger te pide las de regreso para comparar.',
                pista: $conEntrega > 0
                    ? "Llevas {$conEntrega} " . ($conEntrega === 1 ? 'entrega con fotos' : 'entregas con fotos') . '.'
                    : 'Ninguna entrega tiene fotos: sin el antes, el después no compara contra nada.',
                estado: $conEntrega > 0 ? self::USANDO : self::SIN_ESTRENAR,
                icono: 'heroicon-o-camera',
                ruta: 'filament.propietario.resources.mis-rentas.index',
                accion: 'Ver mis rentas',
                peso: 91,
            ),

            new Herramienta(
                clave: 'documentos',
                titulo: 'Guarda la INE de tus clientes',
                beneficio: 'Su identificación y su comprobante de domicilio, en su ficha. Es con lo que se recupera un aparato cuando alguien se muda con él. Tu cobrador no los puede ver.',
                comoSeUsa: 'En Clientes, abre uno y ve a la pestaña Documentos.',
                pista: $documentos > 0
                    ? "Llevas {$documentos} " . ($documentos === 1 ? 'documento guardado' : 'documentos guardados') . '.'
                    : 'Ningún cliente tiene papeles guardados: sin eso, recuperar un equipo extraviado es cuesta arriba.',
                estado: $documentos > 0 ? self::USANDO : self::SIN_ESTRENAR,
                icono: 'heroicon-o-folder',
                ruta: 'filament.propietario.resources.clientes.index',
                accion: 'Ver mis clientes',
                peso: 89,
            ),

            // --- Lo que ordena el parque ---

            new Herramienta(
                clave: 'cambio-equipo',
                titulo: 'Cámbiale el aparato sin perder su historial',
                beneficio: 'Si se le descompone, le llevas otra y la renta sigue igual: conserva sus pagos, su saldo y su fecha. Antes había que cancelar y crear otra, y el cliente arrancaba de cero.',
                comoSeUsa: 'En Rentas o en Equipos, con el botón Cambiar equipo.',
                pista: $cambios > 0
                    ? "Llevas {$cambios} " . ($cambios === 1 ? 'cambio registrado' : 'cambios registrados') . '.'
                    : 'Pasa cada semana en este negocio: así no se le borra el historial al cliente.',
                estado: $cambios > 0 ? self::USANDO : self::SIN_ESTRENAR,
                icono: 'heroicon-o-arrow-path',
                ruta: 'filament.propietario.resources.mis-rentas.index',
                accion: 'Ver mis rentas',
                peso: 75,
            ),

            new Herramienta(
                clave: 'bitacora',
                titulo: 'Mira toda la vida de un aparato',
                beneficio: 'Quién la ha tenido, qué se le ha reparado y cuánto costó, cuánto ha generado y cuántas veces ha regresado, en una sola pantalla y en orden. Con eso contestas por qué una lavadora te está saliendo tan cara.',
                comoSeUsa: 'En Equipos, el botón del relojito en cada fila.',
                pista: $parados->hay()
                    ? 'El que más lleva parado son ' . $parados->diasDelPeor() . ' días sin generar nada.'
                    : ($lavadoras > 0 ? "Cualquiera de tus {$lavadoras} equipos tiene la suya." : null),
                estado: null,
                icono: 'heroicon-o-clock',
                ruta: 'filament.propietario.resources.lavadoras.index',
                accion: 'Ir a equipos',
                peso: 72,
            ),

            new Herramienta(
                clave: 'crecer',
                titulo: 'Sabe cuándo te conviene comprar otra',
                beneficio: 'Las tres cifras que deciden, juntas: si está todo colocado, si hay a quién dárselo, y en cuánto se paga una nueva con tu tarifa. Comprar con aparatos parados en la bodega es cambiar dinero por más dinero parado.',
                comoSeUsa: 'Está al final de tu escritorio, en el bloque Crecer.',
                pista: $crecer->parque > 0 ? $crecer->veredicto() : null,
                estado: null,
                icono: 'heroicon-o-arrow-trending-up',
                ruta: 'filament.propietario.pages.dashboard',
                accion: 'Ver mi escritorio',
                peso: 68,
            ),

            // --- Lo que reparte el trabajo ---

            new Herramienta(
                clave: 'cobrador',
                titulo: 'Dale acceso a tu cobrador',
                beneficio: 'Entra con su propia clave, ve a quién cobrar y registra los pagos desde su celular. No ve tus precios, tus reportes ni los papeles de nadie, y no puede borrar. Y su corte de caja se cuadra por separado.',
                comoSeUsa: 'En Mi cuenta, entra a Mi equipo y dale de alta.',
                pista: $cobradores > 0
                    ? "Tienes {$cobradores} " . ($cobradores === 1 ? 'cobrador dado de alta' : 'cobradores dados de alta') . '.'
                    : 'Si alguien más sale a cobrar por ti, esto le da su acceso sin enseñarle tus números.',
                estado: $cobradores > 0 ? self::USANDO : self::SIN_ESTRENAR,
                icono: 'heroicon-o-users',
                ruta: 'filament.propietario.resources.mi-equipo.index',
                accion: 'Ver mi equipo',
                peso: 65,
            ),

            new Herramienta(
                clave: 'recargos',
                titulo: 'Cobra recargo por atrasarse',
                beneficio: 'Un monto fijo o un porcentaje por cada periodo vencido, con los días de gracia que tú pongas. Atrasarse deja de salir gratis, y el recargo aparece desglosado en su estado de cuenta.',
                comoSeUsa: 'En Preferencias, en el bloque de recargo por atraso. En cero está apagado.',
                pista: $cobraRecargo
                    ? 'Ya lo tienes configurado.'
                    : 'Está apagado: hoy atrasarse no le cuesta nada a nadie.',
                estado: $cobraRecargo ? self::USANDO : self::SIN_ESTRENAR,
                icono: 'heroicon-o-exclamation-circle',
                ruta: 'filament.propietario.pages.configuracion',
                accion: 'Abrir Preferencias',
                peso: 58,
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

    /**
     * Cuántas se destacan arriba.
     *
     * La lista creció de once herramientas a veintiuna, y una cuenta nueva no
     * tiene estrenada casi ninguna: "Empieza por aquí" con quince tarjetas no es
     * un punto de partida, es una pared. Cinco es lo que se alcanza a leer y a
     * hacer en una sentada; el resto sigue abajo sin desaparecer.
     */
    public const CUANTAS_DESTACADAS = 5;

    /** Por dónde conviene empezar: las de más peso que no ha estrenado. */
    public function destacadas(): array
    {
        return array_slice($this->sinEstrenar(), 0, self::CUANTAS_DESTACADAS);
    }

    /** Todo lo demás: lo que ya usa y lo que no cupo arriba. */
    public function elResto(): array
    {
        $destacadas = collect($this->destacadas())->pluck('clave');

        return collect($this->herramientas)
            ->reject(fn (Herramienta $h) => $destacadas->contains($h->clave))
            ->sortByDesc(fn (Herramienta $h) => $h->peso)
            ->values()
            ->all();
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
