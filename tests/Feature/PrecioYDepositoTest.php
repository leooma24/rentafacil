<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Rental;
use App\Models\User;
use App\Models\WashingMachine;
use App\Support\AccountStatement;
use App\Support\RentalTerms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Precio por renta y depósito en garantía.
 *
 * El precio vivía sólo en settings y era uno para toda la empresa, y el adeudo lo
 * deducía del último pago aplicado: una adivinanza que se rompe en cuanto alguien
 * paga distinto. El depósito no existía.
 */
class PrecioYDepositoTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Customer $cliente;

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
        $this->company->members()->attach($user);
        $this->actingAs($user);

        $this->cliente = $this->company->customers()->create([
            'name' => 'Cliente', 'email' => 'c@x.mx', 'phone' => '1',
        ]);
    }

    private function equipo(string $codigo = 'LAV-001'): WashingMachine
    {
        return $this->company->washingMachines()->create([
            'machine_code' => $codigo, 'brand' => 'Mabe', 'status' => 'rentada',
        ]);
    }

    private function renta(array $extra = [], int $diasVencida = 14): Rental
    {
        return $this->company->rentals()->create(array_merge([
            'customer_id' => $this->cliente->id,
            'washing_machine_id' => $this->equipo('LAV-' . fake()->unique()->numberBetween(100, 999))->id,
            'start_date' => now()->subMonths(2)->toDateString(),
            'end_date' => now()->subDays($diasVencida)->toDateString(),
            'status' => 'vencida',
        ], $extra));
    }

    private function adeudo(Rental $renta): float
    {
        return app(AccountStatement::class)->forRental($renta->fresh())->amount;
    }

    /** Sin precio propio, todo sigue exactamente como antes. */
    public function test_una_renta_sin_precio_usa_el_de_la_empresa(): void
    {
        $renta = $this->renta();

        // 14 días vencida, periodo de 7, precio 250 → 2 periodos.
        $this->assertSame(500.0, $this->adeudo($renta));
        $this->assertSame(250.0, RentalTerms::forRental($renta)->price);
    }

    public function test_una_renta_con_precio_propio_manda_sobre_el_de_la_empresa(): void
    {
        $renta = $this->renta(['price' => 400]);

        $this->assertSame(800.0, $this->adeudo($renta));
        $this->assertSame(400.0, RentalTerms::forRental($renta)->price);
    }

    /**
     * El caso que motivó la columna: se puede cobrar distinto por equipo sin que
     * uno afecte al otro.
     */
    public function test_dos_rentas_pueden_cobrar_distinto_al_mismo_tiempo(): void
    {
        $barata = $this->renta(['price' => 200]);
        $cara = $this->renta(['price' => 350]);

        $this->assertSame(400.0, $this->adeudo($barata));
        $this->assertSame(700.0, $this->adeudo($cara));
    }

    /**
     * Cambiarle el precio a la empresa no debe mover lo que ya está rentado con
     * precio propio: el cliente pactó una cantidad.
     */
    public function test_subirle_el_precio_a_la_empresa_no_toca_las_rentas_pactadas(): void
    {
        $pactada = $this->renta(['price' => 200]);
        $sinPrecio = $this->renta();

        $this->company->settings->update(['price' => 500]);
        $this->company->refresh();

        $this->assertSame(400.0, $this->adeudo($pactada), 'Se movió una renta con precio pactado.');
        $this->assertSame(1000.0, $this->adeudo($sinPrecio->fresh()), 'La renta sin precio debió seguir al de la empresa.');
    }

    /**
     * Las 85 rentas que ya existían se crearon sin la columna, así que su precio
     * se sigue deduciendo del último pago aplicado. Si esto se rompe, todos los
     * adeudos históricos cambian de golpe.
     */
    public function test_las_rentas_viejas_siguen_deduciendo_el_precio_de_su_ultimo_pago(): void
    {
        $renta = $this->renta();

        $renta->payments()->create([
            'company_id' => $this->company->id,
            'amount' => 300,
            'payment_date' => now()->subMonth()->toDateString(),
            'payment_method' => 'Efectivo',
            'status' => 'completado',
            'applied' => true,
        ]);

        $this->assertNull($renta->price);
        // Manda el pago (300) y no el de configuración (250): 2 periodos.
        $this->assertSame(600.0, $this->adeudo($renta));
    }

    /** Y en cuanto se le pone precio, deja de adivinar. */
    public function test_el_precio_propio_le_gana_a_la_deduccion_del_pago(): void
    {
        $renta = $this->renta(['price' => 180]);

        $renta->payments()->create([
            'company_id' => $this->company->id,
            'amount' => 300,
            'payment_date' => now()->subMonth()->toDateString(),
            'payment_method' => 'Efectivo',
            'status' => 'completado',
            'applied' => true,
        ]);

        $this->assertSame(360.0, $this->adeudo($renta));
    }

    // --- Depósito ---

    public function test_una_renta_nace_sin_deposito(): void
    {
        $renta = $this->renta();

        $this->assertSame(0.0, (float) $renta->deposit);
        $this->assertFalse($renta->hasPendingDeposit());
    }

    public function test_el_deposito_queda_pendiente_hasta_que_se_devuelve(): void
    {
        $renta = $this->renta(['deposit' => 500]);

        $this->assertTrue($renta->hasPendingDeposit());

        $renta->update(['deposit_returned' => 500, 'deposit_returned_at' => now()]);

        $this->assertFalse($renta->fresh()->hasPendingDeposit());
    }

    /**
     * El depósito es dinero del cliente en poder del dueño: va aparte y nunca se
     * le resta a la deuda. Mezclarlos haría creer que debe menos de lo que debe.
     */
    public function test_el_deposito_no_baja_la_deuda(): void
    {
        $this->renta(['deposit' => 1000]);

        $estado = app(AccountStatement::class)->forCustomer($this->cliente->fresh());

        $this->assertSame(500.0, $estado->total);
        $this->assertSame(1000.0, $estado->depositosEnGarantia());
    }

    public function test_un_deposito_ya_devuelto_deja_de_contar(): void
    {
        $renta = $this->renta(['deposit' => 1000]);
        $renta->update(['deposit_returned' => 1000, 'deposit_returned_at' => now()]);

        $estado = app(AccountStatement::class)->forCustomer($this->cliente->fresh());

        $this->assertSame(0.0, $estado->depositosEnGarantia());
    }

    /** Se puede retener parte por daños, y queda anotado cuánto se devolvió. */
    public function test_se_puede_devolver_el_deposito_incompleto(): void
    {
        $renta = $this->renta(['deposit' => 1000]);
        $renta->update(['deposit_returned' => 700, 'deposit_returned_at' => now()]);

        $renta->refresh();

        $this->assertSame('700.00', $renta->deposit_returned);
        $this->assertFalse($renta->hasPendingDeposit());
        $this->assertNotNull($renta->deposit_returned_at);
    }

    /** De punta a punta: rentar desde la pantalla guarda precio y depósito. */
    public function test_al_rentar_desde_el_equipo_se_guardan_precio_y_deposito(): void
    {
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('propietario'));
        \Filament\Facades\Filament::setTenant($this->company, true);

        auth()->user()->givePermissionTo(
            \Spatie\Permission\Models\Permission::findOrCreate('view_any_washing::machine', 'web')
        );

        $equipo = $this->company->washingMachines()->create([
            'machine_code' => 'LAV-777', 'brand' => 'Mabe', 'status' => 'disponible',
        ]);

        \Livewire\Livewire::test(
            \App\Filament\Resources\WashingMachineResource\Pages\ListWashingMachines::class,
            ['tenant' => $this->company],
        )
            ->assertOk()
            ->callTableAction('rent', $equipo, [
                'customer_id' => $this->cliente->id,
                'start_date' => today()->toDateString(),
                'end_date' => today()->addDays(7)->toDateString(),
                'price' => 375,
                'deposit' => 600,
            ]);

        $renta = Rental::where('washing_machine_id', $equipo->id)->firstOrFail();

        $this->assertSame('375.00', $renta->price);
        $this->assertSame('600.00', $renta->deposit);
        $this->assertTrue($renta->hasPendingDeposit());
        $this->assertSame('rentada', $equipo->fresh()->status);
    }

    /** Y al recoger se devuelve, con la posibilidad de retener parte. */
    public function test_al_recoger_se_devuelve_el_deposito(): void
    {
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('propietario'));
        \Filament\Facades\Filament::setTenant($this->company, true);

        auth()->user()->givePermissionTo(
            \Spatie\Permission\Models\Permission::findOrCreate('view_any_washing::machine', 'web')
        );

        $equipo = $this->equipo('LAV-888');
        $renta = $this->company->rentals()->create([
            'customer_id' => $this->cliente->id,
            'washing_machine_id' => $equipo->id,
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'status' => 'activa',
            'deposit' => 1000,
        ]);

        \Livewire\Livewire::test(
            \App\Filament\Resources\WashingMachineResource\Pages\ListWashingMachines::class,
            ['tenant' => $this->company],
        )
            ->callTableAction('pick_up', $equipo, [
                'deposit_returned' => 800,
                'deposit_notes' => 'Le faltaba la manguera.',
            ]);

        $renta->refresh();

        $this->assertSame('completada', $renta->status);
        $this->assertSame('800.00', $renta->deposit_returned);
        $this->assertNotNull($renta->deposit_returned_at);
        $this->assertStringContainsString('manguera', $renta->notes);
        $this->assertSame('disponible', $equipo->fresh()->status);
    }
}
