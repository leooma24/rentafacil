<?php

namespace App\Services;

use App\Models\CashClosing;
use App\Models\Company;
use App\Models\Country;
use App\Models\CustomerDocument;
use App\Models\Neighborhood;
use App\Models\Package;
use App\Models\Rental;
use App\Models\State;
use App\Models\Township;
use App\Models\User;
use App\Support\Abonos;
use App\Support\CambioDeEquipo;
use App\Support\RentalTerms;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Genera al vuelo una empresa demo desechable con datos de ejemplo.
 *
 * Todas las fechas se calculan contra now(), así el calendario, los morosos y
 * las gráficas se ven vivos cualquier día del año sin re-sembrar nada.
 */
class DemoCompanyBuilder
{
    /** Horas que vive un sandbox antes de que demo:cleanup lo borre. */
    public const LIFETIME_HOURS = 24;

    /**
     * Los dos que aparecen cobrando. Los pagos se reparten entre ambos para que
     * el corte de caja por persona tenga algo que cuadrar: con un solo usuario,
     * "cada quien cuadra lo que trae" no se entiende.
     *
     * @var array<int, int>
     */
    private array $cobradores = [];

    private const MACHINE_MODELS = [
        ['brand' => 'Whirlpool', 'model' => '7MWTW1602BM', 'capacity' => 16, 'price' => 8900],
        ['brand' => 'Mabe', 'model' => 'LMA6123PBAB0', 'capacity' => 12, 'price' => 7200],
        ['brand' => 'LG', 'model' => 'WT16DSB', 'capacity' => 16, 'price' => 10500],
        ['brand' => 'Easy', 'model' => 'LEA77114CBAB01', 'capacity' => 17, 'price' => 6800],
        ['brand' => 'Samsung', 'model' => 'WA17T6260BW', 'capacity' => 17, 'price' => 11200],
    ];

    /**
     * 10 rentadas (8 rentas activas + 2 vencidas), 2 disponibles, 1 en
     * mantenimiento, 1 fuera de servicio y 1 extraviada.
     *
     * La extraviada va al final a propósito: las rentas se reparten por
     * posición sobre las diez primeras, y meterla antes descuadraría todo.
     */
    private const MACHINE_STATUSES = [
        'rentada', 'rentada', 'rentada', 'rentada', 'rentada',
        'rentada', 'rentada', 'rentada', 'rentada', 'rentada',
        'disponible', 'disponible', 'mantenimiento', 'fuera_de_servicio',
        'extraviada',
    ];

    /**
     * Las secadoras van con código SEC- para que al ordenar por código queden
     * después de las lavadoras: las rentas de ejemplo se reparten por posición y
     * moverlas descuadraría todo lo demás.
     */
    private const DRYER_MODELS = [
        ['brand' => 'Whirlpool', 'model' => '7MWED1730JQ', 'capacity' => 16, 'price' => 9400],
        ['brand' => 'Mabe', 'model' => 'SME26N5MSBAB', 'capacity' => 22, 'price' => 8100],
        ['brand' => 'LG', 'model' => 'DLE3400W', 'capacity' => 20, 'price' => 12300],
    ];

    /** Dos rentadas y una libre, para que la ocupación por tipo se vea viva. */
    private const DRYER_STATUSES = ['rentada', 'rentada', 'disponible'];

    private const CUSTOMER_NAMES = [
        'María González', 'Juan Pérez', 'Guadalupe Ramírez', 'José Luis Torres',
        'Ana Beltrán', 'Carlos Medina', 'Rosa Elena Ochoa', 'Miguel Ángel Cota',
        'Patricia Sandoval', 'Jesús Armando Ruiz', 'Leticia Camacho', 'Ramón Ibarra',
        'Verónica Zazueta', 'Francisco Javier León', 'Silvia Angulo', 'Alfonso Castro',
        'Norma Alicia Peña', 'Hugo Valenzuela', 'Claudia Iribe', 'Sergio Bojórquez',
    ];

    private const STREETS = [
        'Av. Álvaro Obregón', 'Calle Rosales', 'Blvd. Pedro Infante', 'Calle Ángel Flores',
        'Av. Universitarios', 'Calle Juan Carrasco', 'Blvd. Madero', 'Calle Ruperto Paliza',
    ];

    private const RENT_PRICE = 250;

    public function build(): Company
    {
        // Son ~250 inserts: en una sola transacción se van en un commit
        // en vez de uno por fila, y un fallo a medias no deja basura.
        return DB::transaction(fn () => $this->buildCompany());
    }

    private function buildCompany(): Company
    {
        $expiresAt = now()->addHours(self::LIFETIME_HOURS);

        $company = Company::create([
            'name' => 'Lavandería Demo',
            'phone' => '6680000000',
            'email' => 'contacto@lavanderiademo.mx',
            'is_demo' => true,
            'demo_expires_at' => $expiresAt,
        ]);

        $user = User::create([
            'name' => 'Visitante Demo',
            'email' => 'demo+' . Str::uuid() . '@rentafacil.local',
            'password' => Hash::make(Str::random(32)),
            'is_demo' => true,
        ]);

        // El rol se asigna aquí y no se deja al camino que siga la petición:
        // sin él, Acceso::soloDueno() es falso y al visitante se le esconden
        // Gastos, Preferencias, Mi plan, Bitácora y "Sácale provecho" —o sea,
        // media app— sin ningún aviso de por qué.
        $user->assignRole(Role::findOrCreate('propietario', 'web'));

        $company->members()->attach($user);

        $this->cobradores = [$user->id, $this->createCobrador($company)->id];

        // CompanyObserver ya le asigna un paquete a toda empresa nueva; lo
        // reemplazamos por el más completo, vigente solo mientras dure el demo.
        $package = Package::orderByDesc('price')->first();
        if ($package) {
            $company->companyPackage()->delete();
            $company->companyPackage()->create([
                'package_id' => $package->id,
                'start_date' => now(),
                'end_date' => $expiresAt,
            ]);
        }

        $company->settings()->create([
            'price' => self::RENT_PRICE,
            'days_per_payment' => 7,
            // Con recargo en cero la pantalla de recargos no dice nada. $50 por
            // periodo vencido y tres días de gracia es lo que cobra un rentador
            // de verdad, y así el adeudo de los morosos se ve desglosado.
            'late_fee_amount' => 50,
            'late_fee_type' => 'fijo',
            'late_fee_grace_days' => 3,
        ]);

        $this->createMachines($company);
        $this->createCustomers($company);
        $this->createRentals($company);
        $this->createMaintenanceAndIncidents($company);
        $this->createExpenses($company);
        $this->createAbonos($company);
        $this->createCashClosings($company);
        $this->createMachineChange($company);
        $this->createDocumentsAndPhotos($company);

        return $company;
    }

    /**
     * El segundo par de manos: quien sale a la calle a cobrar.
     *
     * "Mi equipo" y el corte de caja por persona son de las cosas que separan a
     * esta app de una libreta, y con un solo usuario en el demo las dos salían
     * en blanco.
     */
    private function createCobrador(Company $company): User
    {
        $cobrador = User::create([
            'name' => 'Martín Sauceda',
            'email' => 'cobrador+' . Str::uuid() . '@rentafacil.local',
            'password' => Hash::make(Str::random(32)),
            'is_demo' => true,
        ]);

        $cobrador->assignRole(Role::findOrCreate('cobrador', 'web'));
        $company->members()->attach($cobrador);

        return $cobrador;
    }

    private function createMachines(Company $company): void
    {
        foreach (self::MACHINE_STATUSES as $index => $status) {
            $spec = self::MACHINE_MODELS[$index % count(self::MACHINE_MODELS)];

            $company->washingMachines()->create([
                'machine_code' => sprintf('LAV-%03d', $index + 1),
                'brand' => $spec['brand'],
                'model' => $spec['model'],
                'status' => $status,
                'serial_number' => 'SN' . str_pad((string) (1000 + $index), 6, '0', STR_PAD_LEFT),
                'purchase_date' => now()->subMonths(6 + $index)->toDateString(),
                'purchase_price' => $spec['price'],
                'type' => 'Carga superior',
                'kind' => 'lavadora',
                'color' => 'blanco',
                'load_capacity' => $spec['capacity'],
            ]);
        }

        foreach (self::DRYER_STATUSES as $index => $status) {
            $spec = self::DRYER_MODELS[$index % count(self::DRYER_MODELS)];

            $company->washingMachines()->create([
                'machine_code' => sprintf('SEC-%03d', $index + 1),
                'brand' => $spec['brand'],
                'model' => $spec['model'],
                'status' => $status,
                'kind' => 'secadora',
                'serial_number' => 'SN' . str_pad((string) (2000 + $index), 6, '0', STR_PAD_LEFT),
                'purchase_date' => now()->subMonths(4 + $index)->toDateString(),
                'purchase_price' => $spec['price'],
                'color' => 'blanco',
                'load_capacity' => $spec['capacity'],
            ]);
        }
    }

    private function createCustomers(Company $company): void
    {
        $geo = $this->geography();

        foreach (self::CUSTOMER_NAMES as $index => $name) {
            $street = self::STREETS[$index % count(self::STREETS)];
            $number = (string) (100 + $index * 7);

            // customers no tiene columna address (vive en addresses) y su email
            // es único a nivel global, así que va namespaceado por empresa.
            $customer = $company->customers()->create([
                'name' => $name,
                'email' => 'cliente' . ($index + 1) . '@demo' . $company->id . '.mx',
                'phone' => '668' . str_pad((string) (1000000 + $index * 37), 7, '0', STR_PAD_LEFT),
            ]);

            [$lat, $lng] = $this->puntoEnLosMochis($index);

            $customer->addresses()->create([
                'street' => $street,
                'number' => $number,
                'city' => 'Los Mochis',
                'postal_code' => '8120' . ($index % 10),
                'latitude' => $lat,
                'longitude' => $lng,
                'country_id' => $geo['country']->id,
                'state_id' => $geo['state']->id,
                'township_id' => $geo['township']->id,
                'neighborhood_id' => $geo['neighborhood']->id,
            ]);
        }
    }

    /**
     * Reparte a los clientes del demo por la mancha urbana de Los Mochis.
     *
     * Sin coordenadas, el planificador de rutas —de las cosas que más venden la
     * app— recibía al prospecto con "todavía no hay clientes ubicados en el
     * mapa" y no había nada que enseñar.
     *
     * Van calculadas y no geocodificadas: Nominatim admite una consulta por
     * segundo, así que ubicar a 20 clientes serían 20 segundos con el visitante
     * esperando la pantalla. Los clientes del demo son inventados y sus
     * domicilios también, así que no hay nada que consultar; lo que importa es
     * que queden repartidos como en una ciudad de verdad para que la ruta que
     * salga tenga sentido.
     *
     * @return array{0: float, 1: float}
     */
    private function puntoEnLosMochis(int $index): array
    {
        $columna = $index % 5 - 2;          // -2 … 2
        $renglon = intdiv($index, 5) - 1.5; // -1.5 … 1.5

        // ~6 km de ancho por ~4 de alto, que es la traza de la ciudad. La
        // diagonal cruzada evita que queden en cuadrícula perfecta, que en el
        // mapa se ve a leguas que son de mentira.
        return [
            round(25.7933 + $renglon * 0.0090 + $columna * 0.0021, 7),
            round(-108.9942 + $columna * 0.0125 - $renglon * 0.0032, 7),
        ];
    }

    /**
     * Las tablas de geografía pueden venir vacías en una instalación limpia,
     * así que reutilizamos lo que haya o sembramos un juego mínimo.
     */
    private function geography(): array
    {
        $country = Country::first() ?? Country::create(['nombre' => 'México']);

        // Se busca Sinaloa por nombre antes de conformarse con el primero de la
        // tabla. Con la geografía sembrada, first() devolvía Aguascalientes y el
        // demo le enseñaba al prospecto direcciones de "Los Mochis,
        // Aguascalientes", que además no las encuentra ningún mapa.
        // estados.pais_id es obligatorio pero no está en el $fillable del modelo.
        $state = State::where('nombre', 'like', 'Sinaloa%')->first()
            ?? State::first()
            ?? State::forceCreate(['nombre' => 'Sinaloa', 'pais_id' => $country->id]);

        $township = Township::where('estado_id', $state->id)->where('nombre', 'like', 'Ahome%')->first()
            ?? Township::where('estado_id', $state->id)->first()
            ?? Township::create(['nombre' => 'Ahome', 'estado_id' => $state->id]);

        $neighborhood = Neighborhood::where('municipio_id', $township->id)->first()
            ?? Neighborhood::create([
                'nombre' => 'Centro',
                'ciudad' => 'Los Mochis',
                'municipio_id' => $township->id,
                'asentamiento' => 'Colonia',
                'codigo_postal' => '81200',
            ]);

        return compact('country', 'state', 'township', 'neighborhood');
    }

    private function createRentals(Company $company): void
    {
        $machines = $company->washingMachines()->orderBy('machine_code')->get();
        $customers = $company->customers()->orderBy('id')->get();
        $payments = [];

        // 8 activas sobre las máquinas 1-8. Las dos primeras vencen esta semana.
        // Dos llevan precio propio y depósito, para que se vea que se puede
        // cobrar distinto por equipo y llevar el control de la garantía.
        $activeEndsInDays = [2, 5, 21, 34, 48, 60, 75, 92];
        foreach ($activeEndsInDays as $i => $endsIn) {
            $start = now()->subWeeks(3 + $i * 2)->startOfDay();
            $rental = $company->rentals()->create([
                'customer_id' => $customers[$i]->id,
                'washing_machine_id' => $machines[$i]->id,
                'start_date' => $start->toDateString(),
                'end_date' => now()->addDays($endsIn)->toDateString(),
                'status' => 'activa',
                'price' => match ($i) { 2 => 300, 5 => 200, default => null },
                'deposit' => match ($i) { 0 => 500, 3 => 800, default => 0 },
                // Las primeras cuatro ya se entregaron con su acuse; las demás
                // quedan pendientes para que se vea también ese estado.
                'delivered_at' => $i < 4 ? $start->copy()->addHours(9) : null,
                'delivery_notes' => $i < 4
                    ? 'Se entregó funcionando y se le explicó el uso al cliente.'
                    : null,
                'notes' => 'Renta de ejemplo generada para la demo.',
            ]);
            $this->collectWeeklyPayments($payments, $company, $rental, $start, now());
        }

        // 2 vencidas sobre las máquinas 9-10, con 6 y 15 días de atraso.
        foreach ([6, 15] as $j => $lateDays) {
            $index = 8 + $j;
            $start = now()->subWeeks(10 + $j * 4)->startOfDay();
            $end = now()->subDays($lateDays)->startOfDay();
            $rental = $company->rentals()->create([
                'customer_id' => $customers[$index]->id,
                'washing_machine_id' => $machines[$index]->id,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'status' => 'vencida',
                'notes' => 'Cliente con pago atrasado.',
            ]);
            // Deja de pagar justo cuando se atrasó.
            $this->collectWeeklyPayments($payments, $company, $rental, $start, $end);
        }

        // Las dos secadoras rentadas, a clientes que todavía no traían nada. Sin
        // esto quedarían marcadas como rentadas sin renta que las respalde, y el
        // desglose de ocupación diría 0/3 secadoras.
        $secadoras = $machines->where('kind', 'secadora')->where('status', 'rentada')->values();
        foreach ($secadoras as $s => $secadora) {
            $index = 10 + $s;
            $start = now()->subWeeks(4 + $s * 3)->startOfDay();
            $rental = $company->rentals()->create([
                'customer_id' => $customers[$index]->id,
                'washing_machine_id' => $secadora->id,
                'start_date' => $start->toDateString(),
                'end_date' => now()->addDays(12 + $s * 9)->toDateString(),
                'status' => 'activa',
                'notes' => 'Renta de secadora generada para la demo.',
            ]);
            $this->collectWeeklyPayments($payments, $company, $rental, $start, now());
        }

        // El equipo extraviado conserva su renta abierta a propósito: el adeudo
        // sale de qué tan atrás quedó end_date, así que cerrarla lo borraría del
        // estado de cuenta justo cuando más falta hace cobrarlo.
        $extraviada = $machines->firstWhere('status', 'extraviada');
        if ($extraviada) {
            $start = now()->subWeeks(14)->startOfDay();
            $end = now()->subDays(38)->startOfDay();
            $rental = $company->rentals()->create([
                'customer_id' => $customers[12]->id,
                'washing_machine_id' => $extraviada->id,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'status' => 'vencida',
                'deposit' => 500,
                'notes' => 'El cliente se cambió de domicilio y no se ha podido recuperar el equipo.',
            ]);
            $this->collectWeeklyPayments($payments, $company, $rental, $start, $end);
        }

        // 15 completadas en los últimos 6 meses, reusando máquinas y clientes.
        for ($k = 0; $k < 15; $k++) {
            $start = now()->subDays(175 - $k * 10)->startOfDay();
            $end = (clone $start)->addDays(45);
            if ($end->gte(now())) {
                $end = now()->subDays(3)->startOfDay();
            }
            $rental = $company->rentals()->create([
                'customer_id' => $customers[$k % $customers->count()]->id,
                'washing_machine_id' => $machines[$k % $machines->count()]->id,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'status' => 'completada',
                'notes' => 'Renta finalizada.',
            ]);
            $this->collectWeeklyPayments($payments, $company, $rental, $start, $end);
        }

        // Un solo insert en vez de ~200: el visitante está esperando la pantalla.
        DB::table('payments')->insert($payments);
    }

    /**
     * Acumula los cobros semanales de una renta para insertarlos todos de golpe.
     */
    private function collectWeeklyPayments(array &$rows, Company $company, Rental $rental, Carbon $from, Carbon $until): void
    {
        $date = $from->copy();
        $now = now();
        $n = 0;

        // El insert masivo no pasa por el modelo, así que quién cobró se pone a
        // mano: sin eso el corte de caja del demo saldría en cero y parecería
        // descompuesto.
        $cobradores = $this->cobradores ?: [$company->members()->first()?->id];

        // El cobro sigue el precio de SU renta: con precios distintos por equipo,
        // dejarlo fijo dejaría pagos de 250 en una renta pactada en 300.
        $precio = $rental->price > 0 ? (float) $rental->price : self::RENT_PRICE;

        while ($date->lte($until)) {
            $rows[] = [
                'company_id' => $company->id,
                'rental_id' => $rental->id,
                'amount' => $precio,
                'payment_date' => $date->toDateString(),
                'payment_method' => $n % 3 === 0 ? 'transferencia' : 'efectivo',
                'reference' => 'DEMO-' . $rental->id . '-' . ($n + 1),
                'status' => 'completado',
                // Se alternan por cobro y no por renta: así los dos traen
                // efectivo el mismo día y el corte por persona tiene sentido.
                'collected_by' => $cobradores[($rental->id + $n) % count($cobradores)],
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $date->addDays(7);
            $n++;
        }
    }

    /**
     * Gastos del mes, para que la ganancia del demo se vea como la de un
     * negocio de verdad.
     *
     * Sin ellos el escritorio presumía un margen del 93%, que a cualquier
     * rentador le suena a cuento y le resta credibilidad a la pantalla.
     */
    private function createExpenses(Company $company): void
    {
        $usuario = $company->members()->first()?->id;

        // Los montos salen de lo que se cobró este mes y no de números fijos:
        // el demo se arma cualquier día, y con cifras fijas el día 3 del mes
        // habría enseñado pérdida y el día 28 un margen del 90%.
        $ingresoDelMes = (float) DB::table('payments')
            ->where('company_id', $company->id)
            ->where('status', 'completado')
            ->whereYear('payment_date', now()->year)
            ->whereMonth('payment_date', now()->month)
            ->sum('amount');

        // Deja alrededor de un 40% de margen, que es lo que da este negocio.
        $bolsa = $ingresoDelMes * 0.55;

        if ($bolsa <= 0) {
            return;
        }

        $reparto = [
            ['sueldos', 'Sueldo del cobrador', .34, 5],
            ['gasolina', 'Gasolina de la ruta', .20, 3],
            ['local', 'Renta de la bodega', .18, 12],
            ['refacciones', 'Mangueras y bandas', .12, 9],
            ['servicios', 'Luz y teléfono', .09, 14],
            ['gasolina', 'Gasolina y casetas', .07, 18],
        ];

        $gastos = array_map(
            fn (array $r) => [$r[0], $r[1], round($bolsa * $r[2], -1), $r[3]],
            $reparto
        );

        foreach ($gastos as [$categoria, $descripcion, $monto, $diasAtras]) {
            // Los gastos son del mes en curso: si la fecha se saliera del mes,
            // el escritorio los dejaría fuera y la ganancia volvería a inflarse.
            $fecha = now()->subDays($diasAtras);

            $company->expenses()->create([
                'user_id' => $usuario,
                'category' => $categoria,
                'description' => $descripcion,
                'amount' => $monto,
                'expense_date' => $fecha->lt(now()->startOfMonth())
                    ? now()->startOfMonth()->toDateString()
                    : $fecha->toDateString(),
                'payment_method' => 'Efectivo',
            ]);
        }
    }

    private function createMaintenanceAndIncidents(Company $company): void
    {
        $machines = $company->washingMachines()->orderBy('machine_code')->get();
        $user = $company->members()->first();

        $maintenances = [
            ['type' => 'preventivo', 'desc' => 'Limpieza general y revisión de mangueras.', 'cost' => 350, 'daysAgo' => 12, 'status' => 'completado', 'technician' => 'Luis Herrera'],
            ['type' => 'correctivo', 'desc' => 'Cambio de banda del motor.', 'cost' => 780, 'daysAgo' => 34, 'status' => 'completado', 'technician' => 'Óscar Payán'],
            ['type' => 'correctivo', 'desc' => 'Reemplazo de bomba de desagüe.', 'cost' => 1250, 'daysAgo' => 58, 'status' => 'completado', 'technician' => 'Luis Herrera'],
            ['type' => 'preventivo', 'desc' => 'Revisión programada del tablero.', 'cost' => 400, 'daysAgo' => 1, 'status' => 'programada', 'technician' => 'Óscar Payán'],
        ];

        foreach ($maintenances as $i => $m) {
            $start = now()->subDays($m['daysAgo']);

            $company->maintenances()->create([
                'washing_machine_id' => ($machines[$i + 10] ?? $machines[$i])->id,
                'technician_name' => $m['technician'],
                'start_date' => $start->toDateString(),
                'end_date' => $m['status'] === 'completado' ? $start->copy()->addDay()->toDateString() : null,
                'maintenance_type' => $m['type'],
                'description' => $m['desc'],
                'cost' => $m['cost'],
                'status' => $m['status'],
            ]);
        }

        $incidents = [
            ['title' => 'La lavadora no centrifuga', 'status' => 'abierta', 'priority' => 'alta', 'type' => 'mecánica', 'openedDaysAgo' => 2, 'resolvedDaysAgo' => null],
            ['title' => 'Fuga de agua en la manguera', 'status' => 'en_progreso', 'priority' => 'media', 'type' => 'mecánica', 'openedDaysAgo' => 5, 'resolvedDaysAgo' => null],
            ['title' => 'No enciende el panel', 'status' => 'cerrada', 'priority' => 'alta', 'type' => 'eléctrica', 'openedDaysAgo' => 7, 'resolvedDaysAgo' => 4],
        ];

        foreach ($incidents as $i => $inc) {
            // El reporte tiene que abrirse antes de cerrarse. Sin la fecha de
            // alta explícita quedaba creado hoy y resuelto hace cuatro días, y
            // el promedio de atención salía en negativo.
            $abierta = now()->subDays($inc['openedDaysAgo']);

            $incidencia = $company->incidents()->create([
                'title' => $inc['title'],
                'description' => 'Reporte de ejemplo generado para la demo.',
                'status' => $inc['status'],
                'priority' => $inc['priority'],
                'type' => $inc['type'],
                'user_id' => $user?->id,
                'washing_machine_id' => $machines[$i]->id,
                'resolved_at' => $inc['resolvedDaysAgo'] === null
                    ? null
                    : now()->subDays($inc['resolvedDaysAgo']),
            ]);

            // created_at no es asignable en masa, así que la fecha de alta se
            // pone aparte.
            $incidencia->forceFill(['created_at' => $abierta, 'updated_at' => $abierta])->save();
        }
    }

    /**
     * Dos clientes que pagaron a medias.
     *
     * Es lo más común del negocio —se cobra en la puerta y la gente da lo que
     * trae— y el demo lo enseñaba como si todo mundo pagara completo y a
     * tiempo. Un abono no mueve el vencimiento: queda como saldo a favor hasta
     * que junta el periodo, y eso es justo lo que hay que ver funcionando.
     */
    private function createAbonos(Company $company): void
    {
        $rentas = $company->rentals()
            ->where('status', 'activa')
            ->orderBy('id')
            ->take(2)
            ->get();

        foreach ($rentas as $i => $renta) {
            $precio = RentalTerms::forRental($renta)->price ?? self::RENT_PRICE;

            // Menos de un periodo: si alcanzara, se aplicaría solo y no quedaría
            // ningún abono pendiente que enseñar.
            $monto = round($precio * ($i === 0 ? 0.6 : 0.4), -1);

            $resultado = Abonos::register(
                $renta,
                $monto,
                'Efectivo',
                now()->subDays(2 + $i)->toDateString(),
                'ABONO-DEMO-' . $renta->id,
            );

            $resultado['payment']->forceFill([
                'collected_by' => $this->cobradores[1] ?? $this->cobradores[0] ?? null,
            ])->save();
        }
    }

    /**
     * Tres días ya cerrados, para que el corte de caja tenga historia.
     *
     * Sin esto la pantalla sólo ofrecía el formulario en blanco y no se
     * entendía para qué sirve. Uno sale con faltante a propósito: es la razón
     * de ser del corte, y un demo donde siempre cuadra no convence a nadie que
     * haya manejado efectivo.
     */
    private function createCashClosings(Company $company): void
    {
        $porCerrar = DB::table('payments')
            ->selectRaw('payment_date, collected_by, SUM(amount) as efectivo, COUNT(*) as cuantos')
            ->where('company_id', $company->id)
            ->where('status', 'completado')
            ->where('payment_method', 'efectivo')
            ->whereNotNull('collected_by')
            ->whereDate('payment_date', '<', now()->toDateString())
            ->groupBy('payment_date', 'collected_by')
            ->orderByDesc('payment_date')
            ->limit(3)
            ->get();

        // El faltante va en el más viejo: así el visitante ve primero los días
        // que cuadraron y el descuadre no parece la norma.
        $conFaltante = $porCerrar->count() - 1;

        foreach ($porCerrar as $i => $dia) {
            $esperado = round((float) $dia->efectivo, 2);
            $contado = $i === $conFaltante ? $esperado - 50 : $esperado;

            CashClosing::create([
                'company_id' => $company->id,
                'user_id' => $dia->collected_by,
                'closing_date' => $dia->payment_date,
                'expected_cash' => $esperado,
                'counted_cash' => $contado,
                'difference' => round($contado - $esperado, 2),
                'payments_count' => (int) $dia->cuantos,
                'notes' => $i === $conFaltante
                    ? 'Faltaron $50. Quedó pendiente revisar con el cobrador.'
                    : null,
            ]);
        }
    }

    /**
     * A un cliente se le descompuso la lavadora y se le llevó otra.
     *
     * Pasa cada semana en este negocio. Lo importante de enseñarlo es que la
     * renta no se cancela: el cliente conserva sus pagos, su saldo y su fecha,
     * y queda escrito qué equipo tenía antes y por qué se le cambió.
     */
    private function createMachineChange(Company $company): void
    {
        // Una que todavía no se entrega: el cambio pide la entrega de nuevo (es
        // otro aparato), y hacerlo sobre una ya entregada borraría ese acuse.
        $renta = $company->rentals()
            ->where('status', 'activa')
            ->whereNull('delivered_at')
            ->orderBy('id')
            ->first();

        $nuevo = $company->washingMachines()
            ->where('status', 'disponible')
            ->where('kind', 'lavadora')
            ->orderBy('machine_code')
            ->first();

        if (! $renta || ! $nuevo) {
            return;
        }

        $cambio = app(CambioDeEquipo::class)->ejecutar(
            $renta,
            $nuevo,
            'falla',
            'No centrifugaba. Se le llevó otra el mismo día y se mandó la anterior a revisión.',
        );

        $cambio->forceFill([
            'changed_by' => $this->cobradores[0] ?? null,
            'created_at' => now()->subDays(9),
            'updated_at' => now()->subDays(9),
        ])->save();
    }

    /**
     * Papeles del cliente y fotos de la entrega.
     *
     * Son la defensa del dueño cuando alguien devuelve el aparato golpeado o se
     * muda con él, y sin un ejemplo cargado las dos pantallas salen vacías y
     * parecen de adorno.
     *
     * Las imágenes se dibujan al vuelo en vez de venir en el repositorio: no
     * son fotos de nada, y un archivo de ejemplo versionado se termina
     * confundiendo con uno real.
     */
    private function createDocumentsAndPhotos(Company $company): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            return; // Sin GD no hay imágenes; el resto del demo no depende de esto.
        }

        $usuario = $this->cobradores[0] ?? null;

        foreach ($company->customers()->orderBy('id')->take(2)->get() as $cliente) {
            foreach (['ine' => 'Identificación', 'comprobante' => 'Comprobante de domicilio'] as $tipo => $titulo) {
                $ruta = 'documentos-clientes/demo-' . $cliente->id . '-' . $tipo . '.png';

                if ($this->guardarImagen('local', $ruta, $titulo, $cliente->name)) {
                    CustomerDocument::create([
                        'customer_id' => $cliente->id,
                        'uploaded_by' => $usuario,
                        'type' => $tipo,
                        'file_path' => $ruta,
                        'original_name' => Str::slug($titulo) . '.png',
                        'notes' => 'Documento de ejemplo del demo.',
                    ]);
                }
            }
        }

        foreach ($company->rentals()->whereNotNull('delivered_at')->orderBy('id')->take(2)->get() as $renta) {
            $codigo = $renta->washingMachine?->machine_code ?? 'EQUIPO';
            $rutas = [];

            foreach (['frente' => 'Al entregar — frente', 'tablero' => 'Al entregar — tablero'] as $cual => $titulo) {
                $ruta = 'entregas/demo-' . $renta->id . '-' . $cual . '.png';

                if ($this->guardarImagen('privado', $ruta, $titulo, $codigo)) {
                    $rutas[] = $ruta;
                }
            }

            if ($rutas !== []) {
                $renta->update(['delivery_photos' => $rutas]);
            }
        }
    }

    /** Dibuja un recuadro con su rótulo. Devuelve si se pudo guardar. */
    private function guardarImagen(string $disco, string $ruta, string $titulo, string $pie): bool
    {
        $lienzo = imagecreatetruecolor(640, 420);
        $fondo = imagecolorallocate($lienzo, 226, 232, 240);
        $tinta = imagecolorallocate($lienzo, 51, 65, 85);
        $marca = imagecolorallocate($lienzo, 6, 182, 212);

        imagefilledrectangle($lienzo, 0, 0, 640, 420, $fondo);
        imagefilledrectangle($lienzo, 0, 0, 640, 8, $marca);
        imagestring($lienzo, 5, 30, 170, $titulo, $tinta);
        imagestring($lienzo, 4, 30, 200, $pie, $tinta);
        imagestring($lienzo, 3, 30, 380, 'Imagen de ejemplo generada para la demo', $tinta);

        ob_start();
        imagepng($lienzo);
        $png = ob_get_clean();
        imagedestroy($lienzo);

        return $png !== false && Storage::disk($disco)->put($ruta, $png);
    }
}
