<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Rental;
use App\Models\User;
use App\Support\CorteDeCaja;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * El corte de caja.
 *
 * De 641 cobros en producción, 389 son en efectivo. El dueño terminaba el día
 * con ese dinero encima y ninguna forma de cerrar.
 */
class CorteDeCajaTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $dueno;
    private Customer $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Role::findOrCreate('super_admin', 'web');

        if (! Package::find(1)) {
            Package::forceCreate([
                'id' => 1, 'name' => 'Pro', 'max_clients' => 500, 'max_washers' => 500, 'price' => 999,
            ]);
        }

        $this->company = Company::create(['name' => 'Lavandería', 'phone' => '1', 'email' => 'l@x.com']);
        $this->company->settings()->create(['price' => 250, 'days_per_payment' => 7]);

        $this->dueno = User::create(['name' => 'Dueño', 'email' => 'd@x.com', 'password' => bcrypt('s')]);
        $this->dueno->assignRole('super_admin');
        $this->company->members()->attach($this->dueno);
        $this->actingAs($this->dueno);

        $this->cliente = $this->company->customers()->create([
            'name' => 'Cliente', 'email' => 'c@x.mx', 'phone' => '1',
        ]);
    }

    private function renta(string $codigo = 'LAV-001'): Rental
    {
        $maquina = $this->company->washingMachines()->create([
            'machine_code' => $codigo, 'brand' => 'Mabe', 'status' => 'rentada',
        ]);

        return $this->company->rentals()->create([
            'customer_id' => $this->cliente->id,
            'washing_machine_id' => $maquina->id,
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'status' => 'activa',
        ]);
    }

    private function cobro(float $monto, string $metodo, $fecha = null, ?User $quien = null): Payment
    {
        return Payment::create([
            'company_id' => $this->company->id,
            'rental_id' => $this->renta('LAV-' . fake()->unique()->numberBetween(100, 999))->id,
            'amount' => $monto,
            'payment_date' => ($fecha ?? today())->toDateString(),
            'payment_method' => $metodo,
            'status' => 'completado',
            'collected_by' => $quien?->id,
        ]);
    }

    private function corte($fecha = null, ?User $quien = null): CorteDeCaja
    {
        return CorteDeCaja::para($this->company, $fecha ?? today(), $quien);
    }

    /** Sin esto el corte no puede decir quién trae el dinero. */
    public function test_el_cobro_anota_solo_quien_lo_registro(): void
    {
        $pago = $this->cobro(250, 'Efectivo');

        $this->assertSame($this->dueno->id, $pago->collected_by);
        $this->assertSame('Dueño', $pago->collector->name);
    }

    public function test_separa_el_efectivo_de_lo_que_ya_esta_en_el_banco(): void
    {
        $this->cobro(250, 'Efectivo');
        $this->cobro(300, 'efectivo');
        $this->cobro(500, 'transferencia');

        $corte = $this->corte();

        $this->assertSame(550.0, $corte->efectivo());
        $this->assertSame(500.0, $corte->transferencias());
        $this->assertSame(1050.0, $corte->total());
        $this->assertSame(3, $corte->cuantos());
    }

    public function test_no_mezcla_los_cobros_de_otros_dias(): void
    {
        $this->cobro(250, 'Efectivo');
        $this->cobro(900, 'Efectivo', today()->subDay());

        $this->assertSame(250.0, $this->corte()->efectivo());
        $this->assertSame(900.0, $this->corte(today()->subDay())->efectivo());
    }

    public function test_puede_mirar_solo_lo_de_una_persona(): void
    {
        $cobrador = User::create(['name' => 'Cobrador', 'email' => 'co@x.com', 'password' => bcrypt('s')]);
        $this->company->members()->attach($cobrador);

        $this->cobro(250, 'Efectivo', null, $this->dueno);
        $this->cobro(400, 'Efectivo', null, $cobrador);

        $this->assertSame(650.0, $this->corte()->efectivo());
        $this->assertSame(400.0, $this->corte(null, $cobrador)->efectivo());
        $this->assertSame(250.0, $this->corte(null, $this->dueno)->efectivo());
    }

    public function test_reparte_el_dia_entre_quienes_cobraron(): void
    {
        $cobrador = User::create(['name' => 'Cobrador', 'email' => 'co@x.com', 'password' => bcrypt('s')]);
        $this->company->members()->attach($cobrador);

        $this->cobro(250, 'Efectivo', null, $this->dueno);
        $this->cobro(400, 'Efectivo', null, $cobrador);
        $this->cobro(100, 'transferencia', null, $cobrador);

        $reparto = $this->corte()->porCobrador();

        $this->assertCount(2, $reparto);
        // De mayor a menor: el cobrador trae 500 y el dueño 250.
        $this->assertSame('Cobrador', $reparto[0]['nombre']);
        $this->assertSame(500.0, $reparto[0]['total']);
        $this->assertSame(400.0, $reparto[0]['efectivo']);
        $this->assertSame(250.0, $reparto[1]['total']);
    }

    /** Los 641 cobros que ya existían no tienen dueño y no se les puede inventar. */
    public function test_los_cobros_viejos_sin_dueno_se_agrupan_aparte(): void
    {
        Payment::withoutEvents(fn () => Payment::create([
            'company_id' => $this->company->id,
            'rental_id' => $this->renta()->id,
            'amount' => 250,
            'payment_date' => today()->toDateString(),
            'payment_method' => 'Efectivo',
            'status' => 'completado',
        ]));

        $reparto = $this->corte()->porCobrador();

        $this->assertSame('Sin registrar', $reparto[0]['nombre']);
    }

    public function test_cerrar_el_dia_guarda_la_diferencia(): void
    {
        $this->cobro(250, 'Efectivo');
        $this->cobro(300, 'Efectivo');
        $this->cobro(500, 'transferencia');

        // Contó $50 menos de los $550 que debía traer.
        $cierre = $this->corte(null, $this->dueno)->cerrar($this->dueno, 500.0, 'Di cambio de más.');

        $this->assertSame('550.00', $cierre->expected_cash);
        $this->assertSame('500.00', $cierre->counted_cash);
        $this->assertSame('-50.00', $cierre->difference);
        $this->assertTrue($cierre->falta());
        $this->assertFalse($cierre->cuadra());
        // La transferencia no entra al conteo: ese dinero ya está en el banco.
        $this->assertSame(3, $cierre->payments_count);
    }

    public function test_un_corte_exacto_queda_marcado_como_cuadrado(): void
    {
        $this->cobro(250, 'Efectivo');

        $cierre = $this->corte(null, $this->dueno)->cerrar($this->dueno, 250.0);

        $this->assertTrue($cierre->cuadra());
    }

    /**
     * Lo esperado se guarda además de calcularse: si mañana se corrige un cobro
     * de hoy, el corte firmado tiene que seguir diciendo lo que decía.
     */
    public function test_corregir_un_cobro_viejo_no_reescribe_un_corte_firmado(): void
    {
        $pago = $this->cobro(250, 'Efectivo');
        $cierre = $this->corte(null, $this->dueno)->cerrar($this->dueno, 250.0);

        $pago->update(['amount' => 900]);

        $this->assertSame('250.00', $cierre->fresh()->expected_cash);
    }

    public function test_volver_a_cerrar_el_mismo_dia_corrige_en_vez_de_duplicar(): void
    {
        $this->cobro(250, 'Efectivo');

        $corte = $this->corte(null, $this->dueno);
        $corte->cerrar($this->dueno, 200.0);
        $corte->cerrar($this->dueno, 250.0);

        $this->assertSame(1, \App\Models\CashClosing::count());
        $this->assertSame('250.00', \App\Models\CashClosing::first()->counted_cash);
    }

    public function test_el_dia_sabe_si_ya_esta_cerrado(): void
    {
        $this->cobro(250, 'Efectivo');

        $this->assertFalse($this->corte(null, $this->dueno)->estaCerrado());

        $this->corte(null, $this->dueno)->cerrar($this->dueno, 250.0);

        $this->assertTrue($this->corte(null, $this->dueno)->estaCerrado());
    }

    public function test_la_pantalla_abre_y_muestra_el_efectivo_del_dia(): void
    {
        $this->cobro(250, 'Efectivo');
        $this->cobro(500, 'transferencia');

        $this->get("/propietario/{$this->company->id}/corte-de-caja")
            ->assertOk()
            ->assertSee('Efectivo que traes')
            ->assertSee('$250.00');
    }

    public function test_cerrar_desde_la_pantalla_deja_el_corte_guardado(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('propietario'));
        Filament::setTenant($this->company, true);

        $this->cobro(250, 'Efectivo');

        \Livewire\Livewire::test(
            \App\Filament\Pages\CorteDeCajaPage::class,
            ['tenant' => $this->company],
        )
            ->assertOk()
            ->callAction('cerrar', ['contado' => 230, 'notas' => 'Faltó un billete.']);

        $cierre = \App\Models\CashClosing::firstOrFail();

        $this->assertSame('-20.00', $cierre->difference);
        $this->assertSame('Faltó un billete.', $cierre->notes);
    }
}
