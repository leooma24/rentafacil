<?php

namespace Tests\Feature;

use App\Filament\Resources\WashingMachineResource\Pages\ListWashingMachines;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Rental;
use App\Models\User;
use App\Models\WashingMachine;
use App\Support\RentalTerms;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FlujosDelDiaTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        Role::findOrCreate('super_admin', 'web');
        Role::findOrCreate('propietario', 'web');

        $this->company = $this->prepararEmpresa();
    }

    private function prepararEmpresa(float $precio = 250, int $dias = 7): Company
    {
        if (! Package::find(1)) {
            Package::forceCreate([
                'id' => 1, 'name' => 'Pro', 'max_clients' => 500, 'max_washers' => 500, 'price' => 999,
            ]);
        }

        $company = Company::create(['name' => 'Lavandería', 'phone' => '1', 'email' => 'l@x.com']);

        if ($precio > 0) {
            $company->settings()->create(['price' => $precio, 'days_per_payment' => $dias]);
        }

        $company->companyPackage()->create([
            'package_id' => 1, 'start_date' => now(), 'end_date' => now()->addDays(30),
        ]);

        $user = User::create([
            'name' => 'Dueño', 'email' => 'dueno@x.com', 'password' => bcrypt('secret'),
        ]);
        $user->assignRole('super_admin');
        $user->givePermissionTo([
            Permission::findOrCreate('view_any_washing::machine', 'web'),
            Permission::findOrCreate('view_washing::machine', 'web'),
        ]);
        $company->members()->attach($user);

        $this->actingAs($user);
        Filament::setTenant($company->fresh(), true);

        return $company->fresh();
    }

    private function makeCustomer(): Customer
    {
        return $this->company->customers()->create([
            'name' => 'Juan Pérez', 'email' => 'juan' . uniqid() . '@x.mx', 'phone' => '6681234567',
        ]);
    }

    private function makeMachine(string $status = 'disponible'): WashingMachine
    {
        return $this->company->washingMachines()->create([
            'machine_code' => 'LAV-' . uniqid(), 'brand' => 'Mabe', 'status' => $status,
        ]);
    }

    private function makeRental(WashingMachine $machine, string $status, string $endDate): Rental
    {
        return $this->company->rentals()->create([
            'customer_id' => $this->makeCustomer()->id,
            'washing_machine_id' => $machine->id,
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => $endDate,
            'status' => $status,
        ]);
    }

    // --- Las condiciones de renta ---

    public function test_las_condiciones_salen_de_la_configuracion(): void
    {
        $terms = RentalTerms::for($this->company);

        $this->assertTrue($terms->isConfigured());
        $this->assertSame(250.0, $terms->price);
        $this->assertSame(7, $terms->days);
        $this->assertStringContainsString('250.00', $terms->summary());
        $this->assertStringContainsString('7 días', $terms->summary());
    }

    public function test_sin_configuracion_no_hay_precio_pero_si_un_periodo_de_respaldo(): void
    {
        $company = Company::create(['name' => 'Sin config', 'phone' => '1', 'email' => 'sc@x.com']);

        $terms = RentalTerms::for($company);

        $this->assertFalse($terms->isConfigured());
        $this->assertNull($terms->price);
        $this->assertSame(RentalTerms::DIAS_POR_OMISION, $terms->days);
    }

    public function test_la_fecha_de_fin_se_calcula_desde_la_de_inicio(): void
    {
        $terms = RentalTerms::for($this->company);

        $this->assertSame(
            Carbon::parse('2026-08-01')->addDays(7)->toDateString(),
            $terms->endDateFrom('2026-08-01')->toDateString()
        );
    }

    // --- Cobrar ---

    public function test_cobrar_extiende_la_renta_y_registra_el_pago(): void
    {
        $machine = $this->makeMachine('rentada');
        $rental = $this->makeRental($machine, 'activa', now()->addDays(2)->toDateString());
        $finAnterior = Carbon::parse($rental->end_date);

        Livewire::test(ListWashingMachines::class)
            ->callTableAction('extend_rent', $machine, [
                'payment_date' => now()->toDateString(),
                'price' => 250,
                'days' => 7,
                'payment_method' => 'Efectivo',
                'status' => 'completado',
            ]);

        $this->assertSame(
            $finAnterior->copy()->addDays(7)->toDateString(),
            Carbon::parse($rental->fresh()->end_date)->toDateString()
        );

        $pago = Payment::where('rental_id', $rental->id)->first();
        $this->assertNotNull($pago, 'Cobrar debe dejar el pago registrado.');
        $this->assertSame(250.0, (float) $pago->amount);
    }

    public function test_cobrar_una_renta_vencida_la_regresa_a_activa(): void
    {
        $machine = $this->makeMachine('rentada');
        $rental = $this->makeRental($machine, 'vencida', now()->subDays(5)->toDateString());

        Livewire::test(ListWashingMachines::class)
            ->callTableAction('extend_rent', $machine, [
                'payment_date' => now()->toDateString(),
                'price' => 250,
                'days' => 7,
                'payment_method' => 'Efectivo',
                'status' => 'completado',
            ]);

        $this->assertSame('activa', $rental->fresh()->status);
    }

    // --- Entregar ---

    public function test_entregar_crea_la_renta_activa_y_ocupa_la_lavadora(): void
    {
        $machine = $this->makeMachine('disponible');
        $customer = $this->makeCustomer();

        Livewire::test(ListWashingMachines::class)
            ->callTableAction('rent', $machine, [
                'customer_id' => $customer->id,
                'start_date' => now()->toDateString(),
                'end_date' => now()->addDays(7)->toDateString(),
            ]);

        $rental = Rental::where('washing_machine_id', $machine->id)->first();

        $this->assertNotNull($rental);
        $this->assertSame('activa', $rental->status, 'Toda entrega nace activa.');
        $this->assertSame($customer->id, $rental->customer_id);
        $this->assertSame('rentada', $machine->fresh()->status);
    }

    public function test_entregar_sin_cliente_no_pasa_de_la_validacion(): void
    {
        $machine = $this->makeMachine('disponible');

        Livewire::test(ListWashingMachines::class)
            ->callTableAction('rent', $machine, [
                'start_date' => now()->toDateString(),
                'end_date' => now()->addDays(7)->toDateString(),
            ])
            ->assertHasTableActionErrors(['customer_id']);

        $this->assertSame(0, Rental::where('washing_machine_id', $machine->id)->count());
        $this->assertSame('disponible', $machine->fresh()->status);
    }

    // --- Recoger ---

    public function test_recoger_una_renta_activa_la_deja_completada(): void
    {
        $machine = $this->makeMachine('rentada');
        $rental = $this->makeRental($machine, 'activa', now()->addDays(10)->toDateString());

        Livewire::test(ListWashingMachines::class)
            ->callTableAction('pick_up', $machine);

        $this->assertSame('completada', $rental->fresh()->status, 'El cliente estaba al corriente: la renta se cumplió.');
        // A revisión y no directo a disponible: la lavadora regresa sucia o con
        // algo roto, y sin este paso eso se descubría en la puerta del siguiente.
        $this->assertSame('en_revision', $machine->fresh()->status);
    }

    public function test_recoger_una_renta_vencida_tambien_la_deja_completada(): void
    {
        $machine = $this->makeMachine('rentada');
        $rental = $this->makeRental($machine, 'vencida', now()->subDays(8)->toDateString());

        Livewire::test(ListWashingMachines::class)
            ->callTableAction('pick_up', $machine);

        $this->assertSame('completada', $rental->fresh()->status);
        $this->assertSame('en_revision', $machine->fresh()->status);

        // Y lo que quedó debiendo NO se borra al recogerle el equipo.
        $this->assertGreaterThan(
            0,
            (float) $rental->fresh()->debt_at_close,
            'Se le recogió la lavadora a un moroso y su adeudo quedó en cero.'
        );
    }

    public function test_cancelar_sigue_dejando_la_renta_cancelada(): void
    {
        $machine = $this->makeMachine('rentada');
        $rental = $this->makeRental($machine, 'activa', now()->addDays(10)->toDateString());

        Livewire::test(ListWashingMachines::class)
            ->callTableAction('make_available', $machine);

        $this->assertSame('cancelada', $rental->fresh()->status);
        $this->assertSame('disponible', $machine->fresh()->status);
    }
}
