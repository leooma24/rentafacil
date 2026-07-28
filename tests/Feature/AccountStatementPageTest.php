<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Package;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccountStatementPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        // UserObserver busca a los super_admin y HasPanelShield asigna el rol de
        // propietario al crear un usuario; sin estos roles ambos truenan.
        Role::findOrCreate('super_admin', 'web');
        Role::findOrCreate('propietario', 'web');
    }

    private function makeCompany(): Company
    {
        if (! Package::find(1)) {
            Package::forceCreate([
                'id' => 1, 'name' => 'Pro', 'max_clients' => 500, 'max_washers' => 500, 'price' => 999,
            ]);
        }

        $company = Company::create(['name' => 'Lavandería', 'phone' => '1', 'email' => 'l@x.com']);
        $company->settings()->create(['price' => 250, 'days_per_payment' => 7]);

        return $company->fresh();
    }

    public function test_la_pantalla_muestra_el_saldo_del_cliente(): void
    {
        $company = $this->makeCompany();
        $user = User::create([
            'name' => 'Dueño', 'email' => 'dueno@x.com', 'password' => bcrypt('secret'),
        ]);
        // Shield tiene define_via_gate en false, así que ni super_admin salta las
        // políticas: los permisos del recurso se otorgan explícitamente.
        $user->assignRole('super_admin');
        $user->givePermissionTo([
            Permission::findOrCreate('view_any_customer', 'web'),
            Permission::findOrCreate('view_customer', 'web'),
        ]);
        $company->members()->attach($user);

        $customer = $company->customers()->create([
            'name' => 'Juan Pérez', 'email' => 'juan@ejemplo.mx', 'phone' => '6681234567',
        ]);

        $machine = $company->washingMachines()->create([
            'machine_code' => 'LAV-001', 'brand' => 'Mabe', 'status' => 'rentada',
        ]);

        $company->rentals()->create([
            'customer_id' => $customer->id,
            'washing_machine_id' => $machine->id,
            'start_date' => now()->subMonths(2)->toDateString(),
            'end_date' => now()->subDays(10)->toDateString(),
            'status' => 'vencida',
        ]);

        $this->actingAs($user)
            ->get("/propietario/{$company->id}/clientes/{$customer->id}/estado-de-cuenta")
            ->assertOk()
            ->assertSee('Juan Pérez')
            ->assertSee('LAV-001')
            ->assertSee('500.00');
    }

    public function test_el_filtro_de_adeudo_deja_fuera_a_los_que_estan_al_corriente(): void
    {
        $company = $this->makeCompany();

        $alCorriente = $company->customers()->create([
            'name' => 'Al Corriente', 'email' => 'ok@ejemplo.mx', 'phone' => '1',
        ]);
        $moroso = $company->customers()->create([
            'name' => 'El Moroso', 'email' => 'moroso@ejemplo.mx', 'phone' => '2',
        ]);

        foreach ([[$alCorriente, now()->addDays(5)], [$moroso, now()->subDays(10)]] as $i => [$customer, $endDate]) {
            $machine = $company->washingMachines()->create([
                'machine_code' => 'LAV-00' . ($i + 1), 'brand' => 'Mabe', 'status' => 'rentada',
            ]);
            $company->rentals()->create([
                'customer_id' => $customer->id,
                'washing_machine_id' => $machine->id,
                'start_date' => now()->subMonths(2)->toDateString(),
                'end_date' => $endDate->toDateString(),
                'status' => 'activa',
            ]);
        }

        $conAdeudo = Customer::where('company_id', $company->id)
            ->whereHas('rentals', fn ($q) => $q
                ->whereIn('status', ['activa', 'vencida'])
                ->whereDate('end_date', '<', Carbon::today()))
            ->pluck('name');

        $this->assertSame(['El Moroso'], $conAdeudo->all());
    }
}
