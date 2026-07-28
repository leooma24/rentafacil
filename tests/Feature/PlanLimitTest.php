<?php

namespace Tests\Feature;

use App\Filament\Actions\CreateWithinPlanAction;
use App\Models\Company;
use App\Models\Package;
use Filament\Actions\CreateAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlanLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        Role::findOrCreate('super_admin', 'web');
        Role::findOrCreate('propietario', 'web');
    }

    /** Plan de 2 lavadoras y 2 clientes, para topar rápido. */
    private function makeCompanyWithOwner(): array
    {
        if (! Package::find(1)) {
            Package::forceCreate([
                'id' => 1, 'name' => 'Gratuito', 'max_clients' => 2, 'max_washers' => 2, 'price' => 0,
            ]);
        }

        $company = Company::create(['name' => 'Lavandería', 'phone' => '1', 'email' => 'l@x.com']);
        $company->settings()->create(['price' => 250, 'days_per_payment' => 7]);
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
            Permission::findOrCreate('create_washing::machine', 'web'),
            Permission::findOrCreate('view_any_customer', 'web'),
            Permission::findOrCreate('view_customer', 'web'),
            Permission::findOrCreate('create_customer', 'web'),
        ]);
        $company->members()->attach($user);

        return [$company->fresh(), $user];
    }

    private function addMachines(Company $company, int $n): void
    {
        for ($i = 0; $i < $n; $i++) {
            $company->washingMachines()->create([
                'machine_code' => 'LAV-' . uniqid(), 'brand' => 'Mabe', 'status' => 'disponible',
            ]);
        }
    }

    public function test_con_cupo_la_lista_ofrece_crear(): void
    {
        [$company, $user] = $this->makeCompanyWithOwner();
        $this->addMachines($company, 1);

        $this->actingAs($user)
            ->get("/propietario/{$company->id}/lavadoras")
            ->assertOk()
            ->assertSee('Crear')
            ->assertDontSee('Llegaste al límite');
    }

    /** Lo que antes fallaba: al topar el límite el botón simplemente desaparecía. */
    public function test_al_topar_el_limite_el_boton_sigue_estando(): void
    {
        [$company, $user] = $this->makeCompanyWithOwner();
        $this->addMachines($company, 2); // el plan permite 2

        $this->actingAs($user)
            ->get("/propietario/{$company->id}/lavadoras")
            ->assertOk()
            ->assertSee('Crear Lavadora');
    }

    /**
     * El texto del aviso vive en el modal, que Filament dibuja hasta que se abre,
     * así que se comprueba sobre la acción misma.
     */
    public function test_el_aviso_dice_cuantas_lleva_y_cuantas_permite(): void
    {
        [$company] = $this->makeCompanyWithOwner();
        $this->addMachines($company, 2);

        $accion = CreateWithinPlanAction::make($company->fresh(), 'lavadoras');

        $this->assertSame('Llegaste al límite de tu plan', $accion->getModalHeading());
        $this->assertStringContainsString(
            'Tu plan Gratuito incluye 2 lavadoras y ya tienes 2.',
            $accion->getModalDescription()
        );
    }

    public function test_con_cupo_la_accion_es_la_de_crear_de_verdad(): void
    {
        [$company] = $this->makeCompanyWithOwner();
        $this->addMachines($company, 1);

        $this->assertInstanceOf(
            CreateAction::class,
            CreateWithinPlanAction::make($company->fresh(), 'lavadoras')
        );
    }
}
