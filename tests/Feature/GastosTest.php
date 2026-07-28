<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Expense;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use App\Support\Utilidad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Los gastos y la ganancia real.
 *
 * El escritorio decía "Ingresos del Mes" y ahí paraba, así que ese número se
 * leía como ganancia. No lo es: falta la gasolina de salir a cobrar, los
 * sueldos, las refacciones y lo que cuesta reparar las lavadoras.
 */
class GastosTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $dueno;

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

        $this->dueno = User::create(['name' => 'Dueño', 'email' => 'd@x.com', 'password' => bcrypt('s')]);
        $this->dueno->assignRole(Role::findOrCreate('propietario', 'web'));
        $this->company->members()->attach($this->dueno);
        $this->actingAs($this->dueno);
    }

    private function gasto(float $monto, string $categoria = 'gasolina', $fecha = null): Expense
    {
        return Expense::create([
            'company_id' => $this->company->id,
            'category' => $categoria,
            'description' => 'Un gasto',
            'amount' => $monto,
            'expense_date' => ($fecha ?? today())->toDateString(),
        ]);
    }

    private function ingreso(float $monto, $fecha = null): Payment
    {
        $cliente = $this->company->customers()->create([
            'name' => 'C', 'email' => uniqid() . '@x.mx', 'phone' => '1',
        ]);
        $maquina = $this->company->washingMachines()->create([
            'machine_code' => 'LAV-' . uniqid(), 'brand' => 'Mabe', 'status' => 'rentada',
        ]);
        $renta = $this->company->rentals()->create([
            'customer_id' => $cliente->id,
            'washing_machine_id' => $maquina->id,
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'status' => 'activa',
        ]);

        return Payment::create([
            'company_id' => $this->company->id,
            'rental_id' => $renta->id,
            'amount' => $monto,
            'payment_date' => ($fecha ?? today())->toDateString(),
            'payment_method' => 'Efectivo',
            'status' => 'completado',
        ]);
    }

    public function test_el_gasto_anota_solo_quien_lo_registro(): void
    {
        $this->assertSame($this->dueno->id, $this->gasto(500)->user_id);
    }

    public function test_la_ganancia_es_lo_que_entro_menos_lo_que_salio(): void
    {
        $this->ingreso(5000);
        $this->gasto(800, 'gasolina');
        $this->gasto(1200, 'sueldos');

        $utilidad = Utilidad::delMes($this->company);

        $this->assertSame(5000.0, $utilidad->ingresos);
        $this->assertSame(2000.0, $utilidad->gastos);
        $this->assertSame(3000.0, $utilidad->ganancia());
        $this->assertSame(60.0, $utilidad->margen());
        $this->assertFalse($utilidad->pierde());
    }

    /**
     * El mantenimiento vive en otra tabla pero sale del mismo bolsillo:
     * dejarlo fuera volvería a inflar la ganancia.
     */
    public function test_el_mantenimiento_tambien_cuenta_como_salida(): void
    {
        $this->ingreso(5000);
        $this->gasto(1000);

        $maquina = $this->company->washingMachines()->create([
            'machine_code' => 'LAV-999', 'brand' => 'Mabe', 'status' => 'mantenimiento',
        ]);
        $this->company->maintenances()->create([
            'washing_machine_id' => $maquina->id,
            'technician_name' => 'Téc',
            'start_date' => today()->toDateString(),
            'maintenance_type' => 'correctivo',
            'description' => 'x',
            'cost' => 1500,
            'status' => 'completado',
        ]);

        $utilidad = Utilidad::delMes($this->company);

        $this->assertSame(1500.0, $utilidad->mantenimiento);
        $this->assertSame(2500.0, $utilidad->salidas());
        $this->assertSame(2500.0, $utilidad->ganancia());
    }

    public function test_avisa_cuando_el_mes_va_en_perdida(): void
    {
        $this->ingreso(1000);
        $this->gasto(3000, 'compra');

        $utilidad = Utilidad::delMes($this->company);

        $this->assertTrue($utilidad->pierde());
        $this->assertSame(-2000.0, $utilidad->ganancia());
    }

    /**
     * Un margen sobre cero ingresos no significa nada, y mostrarlo como 0%
     * haría creer que se trabajó y no quedó, en vez de que no se trabajó.
     */
    public function test_sin_ingresos_no_se_inventa_un_margen(): void
    {
        $this->gasto(500);

        $this->assertNull(Utilidad::delMes($this->company)->margen());
    }

    public function test_no_mezcla_los_meses(): void
    {
        $this->ingreso(1000);
        $this->gasto(200);
        $this->gasto(9999, 'sueldos', today()->copy()->subMonthNoOverflow()->startOfMonth());

        $this->assertSame(200.0, Utilidad::delMes($this->company)->gastos);
    }

    public function test_dice_en_que_se_va_mas_dinero(): void
    {
        $this->gasto(300, 'gasolina');
        $this->gasto(2500, 'sueldos');
        $this->gasto(400, 'refacciones');

        $porCategoria = Utilidad::delMes($this->company)->porCategoria($this->company);

        $this->assertSame('sueldos', array_key_first($porCategoria));
        $this->assertSame(2500.0, $porCategoria['sueldos']);
    }

    public function test_la_pantalla_abre_y_muestra_lo_que_quedo(): void
    {
        $this->ingreso(5000);
        $this->gasto(2000);

        $this->get("/propietario/{$this->company->id}/gastos")
            ->assertOk()
            ->assertSee('Te quedó')
            ->assertSee('$3,000.00');
    }

    /** Los sueldos y la renta del local no son asunto de quien sale a cobrar. */
    public function test_el_cobrador_no_ve_los_gastos(): void
    {
        $cobrador = User::create(['name' => 'Beto', 'email' => 'b@x.com', 'password' => bcrypt('s')]);
        $cobrador->assignRole(Role::findOrCreate('cobrador', 'web'));
        $this->company->members()->attach($cobrador);

        $this->actingAs($cobrador)
            ->get("/propietario/{$this->company->id}/gastos")
            ->assertForbidden();
    }

    public function test_el_escritorio_ya_no_deja_los_ingresos_solos(): void
    {
        $clases = collect((new \App\Filament\Pages\Dashboard())->getWidgets())
            ->map(fn ($w) => $w instanceof \Filament\Widgets\WidgetConfiguration ? $w->widget : $w);

        $posicionIngresos = $clases->search(\App\Filament\Widgets\PaymentStats::class);
        $posicionUtilidad = $clases->search(\App\Filament\Widgets\UtilidadStats::class);

        $this->assertNotFalse($posicionUtilidad, 'Falta el recuadro de lo que quedó.');
        $this->assertGreaterThan(
            $posicionIngresos,
            $posicionUtilidad,
            'Lo que quedó tiene que ir debajo de los ingresos, no antes.'
        );
    }

    public function test_un_gasto_de_otra_empresa_no_se_cuela(): void
    {
        $otra = Company::create(['name' => 'Otra', 'phone' => '2', 'email' => 'o@x.com']);
        Expense::create([
            'company_id' => $otra->id,
            'category' => 'sueldos',
            'description' => 'Ajeno',
            'amount' => 9999,
            'expense_date' => today()->toDateString(),
        ]);

        $this->gasto(500);

        $this->assertSame(500.0, Utilidad::delMes($this->company)->gastos);
    }
}
