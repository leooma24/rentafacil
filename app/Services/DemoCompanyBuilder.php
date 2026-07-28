<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Country;
use App\Models\Neighborhood;
use App\Models\Package;
use App\Models\Rental;
use App\Models\State;
use App\Models\Township;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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

    /** Quién aparece cobrando los pagos del demo. Se resuelve una sola vez. */
    private ?int $cobradorDemo = null;

    private const MACHINE_MODELS = [
        ['brand' => 'Whirlpool', 'model' => '7MWTW1602BM', 'capacity' => 16, 'price' => 8900],
        ['brand' => 'Mabe', 'model' => 'LMA6123PBAB0', 'capacity' => 12, 'price' => 7200],
        ['brand' => 'LG', 'model' => 'WT16DSB', 'capacity' => 16, 'price' => 10500],
        ['brand' => 'Easy', 'model' => 'LEA77114CBAB01', 'capacity' => 17, 'price' => 6800],
        ['brand' => 'Samsung', 'model' => 'WA17T6260BW', 'capacity' => 17, 'price' => 11200],
    ];

    /** 10 rentadas (8 rentas activas + 2 vencidas), 2 disponibles, 1 en mantenimiento, 1 fuera de servicio. */
    private const MACHINE_STATUSES = [
        'rentada', 'rentada', 'rentada', 'rentada', 'rentada',
        'rentada', 'rentada', 'rentada', 'rentada', 'rentada',
        'disponible', 'disponible', 'mantenimiento', 'fuera_de_servicio',
    ];

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

        $company->members()->attach($user);

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
        ]);

        $this->createMachines($company);
        $this->createCustomers($company);
        $this->createRentals($company);
        $this->createMaintenanceAndIncidents($company);
        $this->createExpenses($company);

        return $company;
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
                'type' => 'automatica',
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

            $customer->addresses()->create([
                'street' => $street,
                'number' => $number,
                'city' => 'Los Mochis',
                'postal_code' => '8120' . ($index % 10),
                'country_id' => $geo['country']->id,
                'state_id' => $geo['state']->id,
                'township_id' => $geo['township']->id,
                'neighborhood_id' => $geo['neighborhood']->id,
            ]);
        }
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
        $activeEndsInDays = [2, 5, 21, 34, 48, 60, 75, 92];
        foreach ($activeEndsInDays as $i => $endsIn) {
            $start = now()->subWeeks(3 + $i * 2)->startOfDay();
            $rental = $company->rentals()->create([
                'customer_id' => $customers[$i]->id,
                'washing_machine_id' => $machines[$i]->id,
                'start_date' => $start->toDateString(),
                'end_date' => now()->addDays($endsIn)->toDateString(),
                'status' => 'activa',
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
        // descompuesto. Se resuelve una vez y no en cada una de las 13 rentas.
        $cobrador = $this->cobradorDemo ??= $company->members()->first()?->id;

        while ($date->lte($until)) {
            $rows[] = [
                'company_id' => $company->id,
                'rental_id' => $rental->id,
                'amount' => self::RENT_PRICE,
                'payment_date' => $date->toDateString(),
                'payment_method' => $n % 3 === 0 ? 'transferencia' : 'efectivo',
                'reference' => 'DEMO-' . $rental->id . '-' . ($n + 1),
                'status' => 'completado',
                'collected_by' => $cobrador,
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
}
