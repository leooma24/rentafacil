<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Package;
use App\Models\ProspectiveClient;
use App\Models\Rental;
use App\Models\User;
use App\Models\WashingMachine;
use App\Support\BitacoraDelEquipo;
use App\Support\DecisionDeCrecer;
use App\Support\DepositoSugerido;
use App\Support\Recoleccion;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Las tres piezas de administración del parque: cuánto depósito pedir, cuándo
 * conviene comprar otro equipo, y toda la historia de un aparato.
 */
class AdministracionDelParqueTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        if (! Package::find(1)) {
            Package::forceCreate([
                'id' => 1, 'name' => 'Pro', 'max_clients' => 500, 'max_washers' => 500, 'price' => 999,
            ]);
        }

        $this->company = Company::create(['name' => 'Lavandería', 'phone' => '1', 'email' => 'l@x.com']);
        $this->company->settings()->create(['price' => 250, 'days_per_payment' => 7]);

        $user = User::create(['name' => 'Dueño', 'email' => 'd@x.com', 'password' => bcrypt('s')]);
        $user->assignRole(Role::findOrCreate('propietario', 'web'));
        $user->givePermissionTo([
            Permission::findOrCreate('view_any_washing::machine', 'web'),
            Permission::findOrCreate('view_washing::machine', 'web'),
        ]);
        $this->company->members()->attach($user);
        $this->actingAs($user);

        Filament::setCurrentPanel(Filament::getPanel('propietario'));
        Filament::setTenant($this->company, true);
    }

    private function cliente(string $nombre): Customer
    {
        return $this->company->customers()->create([
            'name' => $nombre,
            'phone' => '6681234567',
            'email' => \Illuminate\Support\Str::slug($nombre) . uniqid() . '@x.com',
        ]);
    }

    private function equipo(string $codigo, string $estado = 'disponible', ?float $compra = null): WashingMachine
    {
        return $this->company->washingMachines()->create([
            'machine_code' => $codigo,
            'kind' => 'lavadora',
            'brand' => 'Mabe',
            'status' => $estado,
            'purchase_price' => $compra,
        ]);
    }

    private function renta(Customer $cliente, WashingMachine $equipo, array $extra = []): Rental
    {
        return $this->company->rentals()->create(array_merge([
            'customer_id' => $cliente->id,
            'washing_machine_id' => $equipo->id,
            'start_date' => now()->subWeeks(10)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'status' => 'activa',
            'price' => 250,
        ], $extra));
    }

    private function pagos(Rental $renta, int $cuantos, int $cada = 7): void
    {
        for ($i = 0; $i < $cuantos; $i++) {
            $renta->payments()->create([
                'company_id' => $this->company->id,
                'amount' => 250,
                'payment_date' => now()->subDays($cada * ($cuantos - $i))->toDateString(),
                'payment_method' => 'Efectivo',
                'status' => 'completado',
            ]);
        }
    }

    // --- 3. El depósito sugerido ---

    /** Al nuevo se le piden dos periodos: todavía no sabes cómo paga. */
    public function test_al_cliente_nuevo_se_le_sugieren_dos_periodos(): void
    {
        $sugerido = DepositoSugerido::para($this->cliente('Nuevo'), 250);

        $this->assertSame(2, $sugerido->periodos);
        $this->assertSame(500.0, $sugerido->monto);
        $this->assertTrue($sugerido->haceFalta());
        $this->assertStringContainsString('cliente nuevo', $sugerido->ayuda());
    }

    /** Al que ya te falló, más: es el que más caro sale. */
    public function test_al_que_ya_fallo_se_le_sugiere_mas(): void
    {
        $cliente = $this->cliente('Ya Falló');
        $renta = $this->renta($cliente, $this->equipo('LAV-001', 'rentada'), [
            'status' => 'vencida',
            'end_date' => now()->subWeeks(3)->toDateString(),
        ]);

        app(Recoleccion::class)->ejecutar($renta, quedaronEnPaz: false);

        $sugerido = DepositoSugerido::para($cliente->fresh(), 250);

        $this->assertSame(3, $sugerido->periodos);
        $this->assertSame(750.0, $sugerido->monto);
        $this->assertStringContainsString('recogiste', $sugerido->ayuda());
    }

    /**
     * Y al cumplido no se le pide nada: es justo lo que permite competir sin
     * bajar el precio.
     */
    public function test_al_cliente_cumplido_no_se_le_pide_deposito(): void
    {
        $cliente = $this->cliente('Cumplido');
        $this->pagos($this->renta($cliente, $this->equipo('LAV-002', 'rentada')), 10);

        $sugerido = DepositoSugerido::para($cliente->fresh(), 250);

        $this->assertSame(0, $sugerido->periodos);
        $this->assertFalse($sugerido->haceFalta());
        $this->assertStringContainsString('no hace falta', $sugerido->ayuda());
    }

    /**
     * "Se atrasa siempre" y "trae un adeudo hoy" piden lo mismo pero no son lo
     * mismo, y decirle al dueño la que no es le hace perder la confianza en el
     * resto de lo que dice la pantalla.
     */
    public function test_distingue_al_que_debe_hoy_del_que_siempre_se_atrasa(): void
    {
        $debeHoy = $this->cliente('Debe Hoy');
        $renta = $this->renta($debeHoy, $this->equipo('LAV-003', 'rentada'), [
            'status' => 'vencida',
            'end_date' => now()->subDays(10)->toDateString(),
        ]);
        $this->pagos($renta, 6, 7);

        $sugerido = DepositoSugerido::para($debeHoy->fresh(), 250);

        $this->assertSame(2, $sugerido->periodos);
        $this->assertStringContainsString('adeudo contigo', $sugerido->ayuda());
        $this->assertStringNotContainsString('Se atrasa', $sugerido->ayuda());
    }

    /** El monto sigue al precio de esa renta, no a uno fijo. */
    public function test_el_deposito_sugerido_sigue_al_precio_de_la_renta(): void
    {
        $cliente = $this->cliente('Precio Alto');

        $this->assertSame(600.0, DepositoSugerido::para($cliente, 300)->monto);
        $this->assertSame(400.0, DepositoSugerido::para($cliente, 200)->monto);
    }

    // --- 4. La decisión de crecer ---

    /**
     * LO PRIMERO ES EL "NO".
     *
     * Comprar con aparatos parados en la bodega es cambiar dinero por más dinero
     * parado, y es el caso más común.
     */
    public function test_con_equipo_parado_no_conviene_comprar(): void
    {
        $this->equipo('LAV-010', 'rentada');
        $this->equipo('LAV-011', 'disponible');

        $d = DecisionDeCrecer::for($this->company);

        $this->assertSame(1, $d->parados);
        $this->assertFalse($d->convieneComprar());
        $this->assertSame('warning', $d->color());
        $this->assertStringContainsString('colocar ésos', $d->veredicto());
    }

    /** Con lugar de sobra tampoco: primero se llena lo que hay. */
    public function test_con_ocupacion_baja_no_conviene_comprar(): void
    {
        $this->equipo('LAV-020', 'rentada');
        foreach (range(1, 4) as $i) {
            $this->equipo('LAV-02' . $i, 'mantenimiento');
        }

        $d = DecisionDeCrecer::for($this->company);

        $this->assertSame(20, $d->ocupacion());
        $this->assertFalse($d->ocupacionAprieta());
        $this->assertFalse($d->convieneComprar());
        $this->assertStringContainsString('Todavía hay lugar', $d->veredicto());
    }

    /** Todo colocado pero sin nadie esperando no es demanda, es coincidencia. */
    public function test_todo_colocado_sin_prospectos_no_conviene_comprar(): void
    {
        foreach (range(1, 4) as $i) {
            $this->equipo('LAV-03' . $i, 'rentada');
        }

        $d = DecisionDeCrecer::for($this->company);

        $this->assertSame(100, $d->ocupacion());
        $this->assertTrue($d->ocupacionAprieta());
        $this->assertSame(0, $d->prospectos);
        $this->assertFalse($d->convieneComprar());
        $this->assertStringContainsString('Consigue la demanda', $d->veredicto());
    }

    /** Todo colocado y con gente esperando: ahí sí, y dice en cuánto se paga. */
    public function test_todo_colocado_con_prospectos_si_conviene_comprar(): void
    {
        foreach (range(1, 4) as $i) {
            $this->equipo('LAV-04' . $i, 'rentada', compra: 7000);
        }

        ProspectiveClient::create(['name' => 'Interesado', 'phone' => '6681111111']);

        $d = DecisionDeCrecer::for($this->company);

        $this->assertTrue($d->convieneComprar());
        $this->assertSame('success', $d->color());
        // $7,000 entre $250 el periodo: 28 cobros, o sea 196 días ≈ 7 meses.
        $this->assertSame(28, $d->cobrosParaPagarse());
        $this->assertSame('7 meses', $d->tiempoParaPagarse());
        $this->assertStringContainsString('7 meses', $d->veredicto());
    }

    /**
     * El costo típico sale sólo de los que tienen precio: meter los que no lo
     * traen como cero haría ver la compra más barata de lo que es.
     */
    public function test_el_costo_tipico_ignora_los_equipos_sin_precio(): void
    {
        $this->equipo('LAV-050', 'rentada', compra: 8000);
        $this->equipo('LAV-051', 'rentada', compra: null);

        $this->assertSame(8000.0, DecisionDeCrecer::for($this->company)->costoTipico);
    }

    /** Sin precios capturados lo dice, en vez de inventar un plazo. */
    public function test_sin_precios_capturados_pide_capturarlos(): void
    {
        foreach (range(1, 4) as $i) {
            $this->equipo('LAV-06' . $i, 'rentada');
        }

        ProspectiveClient::create(['name' => 'Interesado', 'phone' => '6681111111']);

        $d = DecisionDeCrecer::for($this->company);

        $this->assertNull($d->cobrosParaPagarse());
        $this->assertStringContainsString('Captura el precio de compra', $d->veredicto());
    }

    /** Lo que entra por periodo con todo lo colocado. */
    public function test_dice_cuanto_entra_por_periodo(): void
    {
        $a = $this->cliente('Uno');
        $b = $this->cliente('Dos');
        $this->renta($a, $this->equipo('LAV-070', 'rentada'), ['price' => 250]);
        $this->renta($b, $this->equipo('LAV-071', 'rentada'), ['price' => 300]);

        $this->assertSame(550.0, DecisionDeCrecer::for($this->company)->ingresoPorPeriodo);
    }

    // --- 5. La bitácora ---

    /**
     * Todo lo que le ha pasado a un aparato, en orden, en una pantalla. Antes
     * estaba repartido en Rentas, Mantenimientos, Incidencias y el historial de
     * cambios.
     */
    public function test_la_bitacora_junta_todo_lo_que_le_paso_al_equipo(): void
    {
        $equipo = $this->equipo('LAV-100', 'rentada', compra: 7200);
        $equipo->update(['purchase_date' => now()->subYear()->toDateString()]);

        $cliente = $this->cliente('Jesús Ruiz');
        $renta = $this->renta($cliente, $equipo, [
            'status' => 'vencida',
            'end_date' => now()->subWeeks(3)->toDateString(),
        ]);
        $this->pagos($renta, 5);

        $this->company->maintenances()->create([
            'washing_machine_id' => $equipo->id,
            'technician_name' => 'Luis Herrera',
            'start_date' => now()->subMonths(2)->toDateString(),
            'maintenance_type' => 'correctivo',
            'status' => 'completado',
            'description' => 'Cambio de banda',
            'cost' => 780,
        ]);

        $this->company->incidents()->create([
            'washing_machine_id' => $equipo->id,
            'title' => 'No centrifuga',
            'description' => 'Reporte del cliente.',
            'status' => 'cerrada',
            'priority' => 'alta',
            'type' => 'mecánica',
            'user_id' => auth()->id(),
        ]);

        app(Recoleccion::class)->ejecutar($renta->fresh(), quedaronEnPaz: false);

        $b = BitacoraDelEquipo::for($equipo->fresh());
        $tipos = $b->eventos->pluck('tipo');

        $this->assertTrue($tipos->contains('compra'), 'Falta la compra.');
        $this->assertTrue($tipos->contains('renta'), 'Falta a quién se le rentó.');
        $this->assertTrue($tipos->contains('devolucion'), 'Falta la devolución.');
        $this->assertTrue($tipos->contains('mantenimiento'), 'Falta la reparación.');
        $this->assertTrue($tipos->contains('incidencia'), 'Falta el reporte.');

        $this->assertSame(1, $b->clientesQueLaHanTenido());
        $this->assertSame(1, $b->reparaciones());
        $this->assertSame(1, $b->vecesQueVolvio());

        // Y el adeudo con que volvió queda contado en su historia.
        $devolucion = $b->eventos->firstWhere('tipo', 'devolucion');
        $this->assertStringContainsString('Quedó debiendo', $devolucion->detalle);
    }

    /** De lo más reciente a lo más viejo: es como se lee una historia. */
    public function test_la_bitacora_va_de_lo_mas_nuevo_a_lo_mas_viejo(): void
    {
        $equipo = $this->equipo('LAV-101', 'disponible', compra: 7000);
        $equipo->update(['purchase_date' => now()->subYear()->toDateString()]);

        $this->renta($this->cliente('Alguien'), $equipo, [
            'status' => 'completada',
            'start_date' => now()->subMonths(3)->toDateString(),
            'end_date' => now()->subMonth()->toDateString(),
        ]);

        $fechas = BitacoraDelEquipo::for($equipo->fresh())->eventos->pluck('fecha');

        $this->assertSame(
            $fechas->sortByDesc(fn ($f) => $f->timestamp)->values()->map(fn ($f) => $f->toDateString())->all(),
            $fechas->map(fn ($f) => $f->toDateString())->all(),
            'La línea de tiempo no está ordenada.'
        );
    }

    /** El equipo sin historia lo dice, en vez de una pantalla en blanco. */
    public function test_el_equipo_sin_historia_lo_dice(): void
    {
        $b = BitacoraDelEquipo::for($this->equipo('LAV-102'));

        $this->assertTrue($b->eventos->isEmpty());
        $this->assertSame(0, $b->clientesQueLaHanTenido());
    }

    /** Y cuántos días lleva parado, que es el dinero detenido. */
    public function test_la_bitacora_dice_cuantos_dias_lleva_parada(): void
    {
        $equipo = $this->equipo('LAV-103', 'disponible');

        $this->renta($this->cliente('Ya Devolvió'), $equipo, [
            'status' => 'completada',
            'end_date' => now()->subDays(40)->toDateString(),
        ]);

        $this->assertSame(40, BitacoraDelEquipo::for($equipo->fresh())->diasParada());
    }

    /** El que está rentado no lleva días parado: está trabajando. */
    public function test_el_equipo_rentado_no_lleva_dias_parada(): void
    {
        $equipo = $this->equipo('LAV-104', 'rentada');
        $this->renta($this->cliente('La Trae'), $equipo);

        $this->assertNull(BitacoraDelEquipo::for($equipo->fresh())->diasParada());
    }

    /** Y la pantalla abre y enseña la historia. */
    public function test_la_pantalla_de_bitacora_abre(): void
    {
        $equipo = $this->equipo('LAV-105', 'rentada', compra: 7200);
        $renta = $this->renta($this->cliente('María González'), $equipo);
        $this->pagos($renta, 4);

        $this->get(\App\Filament\Resources\WashingMachineResource::getUrl('bitacora', [
            'record' => $equipo,
            'tenant' => $this->company,
        ]))
            ->assertOk()
            ->assertSee('LAV-105')
            ->assertSee('Todo lo que le ha pasado')
            ->assertSee('María González');
    }
}
