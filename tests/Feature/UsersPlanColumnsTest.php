<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UsersPlanColumnsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        Role::findOrCreate('super_admin', 'web');
        Role::findOrCreate('propietario', 'web');
    }

    private function makePackages(): void
    {
        if (! Package::find(1)) {
            Package::forceCreate([
                'id' => 1, 'name' => 'Gratuito', 'max_clients' => 3, 'max_washers' => 3, 'price' => 0,
            ]);
        }
    }

    /** Un dueño con su empresa, su plan y N lavadoras. */
    private function makeOwner(string $email, int $machines): User
    {
        $this->makePackages();

        $company = Company::create([
            'name' => 'Lavandería ' . $email, 'phone' => '1', 'email' => 'c' . $email,
        ]);
        $company->companyPackage()->create([
            'package_id' => 1,
            'start_date' => now(),
            'end_date' => now()->addDays(30),
        ]);

        for ($i = 0; $i < $machines; $i++) {
            $company->washingMachines()->create([
                'machine_code' => 'LAV-' . uniqid(), 'brand' => 'Mabe', 'status' => 'disponible',
            ]);
        }

        $user = User::create(['name' => $email, 'email' => $email, 'password' => bcrypt('secret')]);
        $company->members()->attach($user);

        return $user->fresh();
    }

    /** @return array{0: User, 1: Company} */
    private function makeAdmin(): array
    {
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@x.com', 'password' => bcrypt('secret'),
        ]);
        $admin->assignRole('super_admin');
        $admin->givePermissionTo([
            Permission::findOrCreate('view_any_user', 'web'),
            Permission::findOrCreate('view_user', 'web'),
        ]);

        $company = Company::create(['name' => 'Sede', 'phone' => '1', 'email' => 'sede@x.com']);
        $company->members()->attach($admin);

        return [$admin->fresh(), $company];
    }

    public function test_la_lista_de_usuarios_muestra_el_plan_y_el_uso(): void
    {
        $this->makeOwner('topado@x.com', 3);   // 3 de 3: topado
        $this->makeOwner('concupo@x.com', 1);  // 1 de 3: con cupo
        [$admin, $sede] = $this->makeAdmin();

        $response = $this->actingAs($admin)->get("/propietario/{$sede->id}/usuarios");

        $response->assertOk()
            ->assertSee('Gratuito')
            ->assertSee('3 / 3')
            ->assertSee('1 / 3');
    }

    public function test_un_usuario_sin_empresa_no_truena_la_pantalla(): void
    {
        User::create(['name' => 'Suelto', 'email' => 'suelto@x.com', 'password' => bcrypt('secret')]);
        [$admin, $sede] = $this->makeAdmin();

        $this->actingAs($admin)
            ->get("/propietario/{$sede->id}/usuarios")
            ->assertOk()
            ->assertSee('Sin plan');
    }
}
