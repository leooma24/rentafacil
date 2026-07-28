<?php

namespace Tests\Feature;

use App\Filament\Resources\CompanyResource;
use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\IncidentResource;
use App\Filament\Resources\RentalResource;
use App\Filament\Resources\WashingMachineResource;
use App\Models\Company;
use App\Models\Package;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PanelIdentityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        Role::findOrCreate('super_admin', 'web');
        Role::findOrCreate('propietario', 'web');
    }

    private function makeCompanyWithOwner(): array
    {
        if (! Package::find(1)) {
            Package::forceCreate([
                'id' => 1, 'name' => 'Pro', 'max_clients' => 500, 'max_washers' => 500, 'price' => 999,
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
            Permission::findOrCreate('view_any_rental', 'web'),
            Permission::findOrCreate('view_rental', 'web'),
            Permission::findOrCreate('view_any_customer', 'web'),
            Permission::findOrCreate('view_customer', 'web'),
        ]);
        $company->members()->attach($user);

        return [$company->fresh(), $user];
    }

    public function test_la_marca_esta_bien_escrita_y_usa_el_color_del_landing(): void
    {
        $panel = Filament::getPanel('propietario');

        $this->assertSame('Renta Fácil', $panel->getBrandName());
        $this->assertStringContainsString('6, 182, 212', $panel->getColors()['primary'][500] ?? '');
    }

    public function test_existen_los_iconos_que_pide_el_manifest(): void
    {
        $manifest = json_decode(file_get_contents(public_path('manifest.json')), true);

        $this->assertNotEmpty($manifest['icons']);

        foreach ($manifest['icons'] as $icon) {
            $ruta = public_path(ltrim($icon['src'], '/'));

            $this->assertFileExists($ruta, "Falta el ícono {$icon['src']} que pide el manifest.");

            [$ancho, $alto] = getimagesize($ruta);
            [$esperado] = explode('x', $icon['sizes']);

            $this->assertSame((int) $esperado, $ancho, "El ícono {$icon['src']} no mide lo que dice.");
            $this->assertSame((int) $esperado, $alto, "El ícono {$icon['src']} no mide lo que dice.");
        }
    }

    public function test_ninguna_etiqueta_del_menu_empieza_con_mis(): void
    {
        $etiquetas = [
            CustomerResource::getNavigationLabel(),
            RentalResource::getNavigationLabel(),
            WashingMachineResource::getNavigationLabel(),
            IncidentResource::getNavigationLabel(),
            CompanyResource::getNavigationLabel(),
        ];

        foreach ($etiquetas as $etiqueta) {
            $this->assertStringStartsNotWith('Mis ', $etiqueta, "\"{$etiqueta}\" sigue con el Mis.");
        }

        $this->assertContains('Compañías', $etiquetas, 'Compañías iba sin acento.');
    }

    public function test_la_lista_de_rentas_ofrece_cobrar_y_sigue_abriendo(): void
    {
        [$company, $user] = $this->makeCompanyWithOwner();

        $customer = $company->customers()->create([
            'name' => 'Juan Pérez', 'email' => 'juan@ejemplo.mx', 'phone' => '1',
        ]);
        $machine = $company->washingMachines()->create([
            'machine_code' => 'LAV-001', 'brand' => 'Mabe', 'status' => 'rentada',
        ]);
        $company->rentals()->create([
            'customer_id' => $customer->id,
            'washing_machine_id' => $machine->id,
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'status' => 'activa',
        ]);

        $this->actingAs($user)
            ->get("/propietario/{$company->id}/mis-rentas")
            ->assertOk()
            ->assertSee('Cobrar');
    }

    public function test_la_lista_de_clientes_sigue_abriendo_con_las_acciones_agrupadas(): void
    {
        [$company, $user] = $this->makeCompanyWithOwner();

        $company->customers()->create([
            'name' => 'Juan Pérez', 'email' => 'juan@ejemplo.mx', 'phone' => '1',
        ]);

        $this->actingAs($user)
            ->get("/propietario/{$company->id}/clientes")
            ->assertOk()
            ->assertSee('Estado de cuenta');
    }
}
