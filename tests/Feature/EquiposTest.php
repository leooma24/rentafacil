<?php

namespace Tests\Feature;

use App\Filament\Resources\WashingMachineResource\Widgets\LavadorasStats;
use App\Models\Company;
use App\Models\Package;
use App\Models\User;
use App\Models\WashingMachine;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Equipos: lavadoras y secadoras.
 *
 * El negocio también renta secadoras y hasta ahora sólo cabían como un *tipo* de
 * lavadora, así que el inventario y la ocupación quedaban mal contados.
 */
class EquiposTest extends TestCase
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
        $user->givePermissionTo(Permission::findOrCreate('view_any_washing::machine', 'web'));
        $this->company->members()->attach($user);
        $this->actingAs($user);

        Filament::setCurrentPanel(Filament::getPanel('propietario'));
        Filament::setTenant($this->company, true);
    }

    private function equipo(string $codigo, string $kind, string $estado): WashingMachine
    {
        return $this->company->washingMachines()->create([
            'machine_code' => $codigo,
            'brand' => 'Mabe',
            'kind' => $kind,
            'status' => $estado,
        ]);
    }

    /** @return array<string, string> */
    private function valores(): array
    {
        $stats = (fn () => $this->getStats())->call(new LavadorasStats());

        return collect($stats)
            ->mapWithKeys(fn ($stat) => [$stat->getLabel() => (string) $stat->getDescription()])
            ->all();
    }

    /** Las 60 máquinas que ya existían en producción son lavadoras. */
    public function test_un_equipo_sin_tipo_se_da_por_lavadora(): void
    {
        $equipo = $this->company->washingMachines()->create([
            'machine_code' => 'LAV-001', 'brand' => 'Mabe', 'status' => 'disponible',
        ]);

        $this->assertSame('lavadora', $equipo->fresh()->kind);
        $this->assertSame('Lavadora', $equipo->fresh()->kindLabel());
    }

    public function test_se_puede_dar_de_alta_una_secadora(): void
    {
        $secadora = $this->equipo('SEC-001', 'secadora', 'disponible');

        $this->assertSame('Secadora', $secadora->kindLabel());
        $this->assertSame(1, $this->company->washingMachines()->where('kind', 'secadora')->count());
    }

    /**
     * El desglose sólo aparece cuando hay más de un tipo: a quien nada más renta
     * lavadoras le quita lugar al dato sin decirle nada nuevo.
     */
    public function test_con_un_solo_tipo_la_ocupacion_no_se_desglosa(): void
    {
        $this->equipo('LAV-001', 'lavadora', 'rentada');
        $this->equipo('LAV-002', 'lavadora', 'disponible');

        $this->assertSame('1 de 2 rentadas', $this->valores()['Ocupación']);
    }

    public function test_con_secadoras_la_ocupacion_se_desglosa_por_tipo(): void
    {
        $this->equipo('LAV-001', 'lavadora', 'rentada');
        $this->equipo('LAV-002', 'lavadora', 'rentada');
        $this->equipo('LAV-003', 'lavadora', 'disponible');
        $this->equipo('SEC-001', 'secadora', 'rentada');
        $this->equipo('SEC-002', 'secadora', 'disponible');

        $this->assertSame('2/3 lavadoras · 1/2 secadoras', $this->valores()['Ocupación']);
    }

    /** Una vendida ya no es del parque y no debe bajar la ocupación. */
    public function test_las_vendidas_no_cuentan_en_el_desglose(): void
    {
        $this->equipo('LAV-001', 'lavadora', 'rentada');
        $this->equipo('LAV-002', 'lavadora', 'vendida');
        $this->equipo('SEC-001', 'secadora', 'rentada');

        $this->assertSame('1/1 lavadoras · 1/1 secadoras', $this->valores()['Ocupación']);
    }

    public function test_la_pantalla_dice_equipos_y_deja_filtrar_por_tipo(): void
    {
        $this->equipo('SEC-001', 'secadora', 'disponible');

        $this->get("/propietario/{$this->company->id}/lavadoras")
            ->assertOk()
            ->assertSee('Equipos')
            ->assertSee('Secadora');
    }

    /**
     * El importador acepta una columna opcional "que_es". Lo que no reconozca cae
     * a lavadora, que es lo que traen todos los archivos de hoy.
     */
    public function test_la_importacion_reconoce_secadoras_y_tolera_lo_demas(): void
    {
        $importador = new \App\Imports\WashingMachinesImport($this->company->id);

        $casos = [
            ['Secadora', 'secadora'],
            ['secadora', 'secadora'],
            ['  COMBO ', 'combo'],
            ['lavadora', 'lavadora'],
            ['cualquier cosa', 'lavadora'],
            [null, 'lavadora'],
        ];

        foreach ($casos as $i => [$escrito, $esperado]) {
            $equipo = $importador->model([
                'codigo' => "IMP-{$i}", 'marca' => 'Mabe', 'que_es' => $escrito,
            ]);

            $this->assertSame($esperado, $equipo->kind, "«{$escrito}» se leyó mal.");
        }
    }

    /**
     * El campo type describe cómo carga la lavadora, no qué es. Dejar ahí
     * "Lavadora-secadora" ahora que existe kind sería tener el dato en dos lados
     * diciendo cosas distintas.
     */
    public function test_el_tipo_de_carga_ya_no_ofrece_lavadora_secadora(): void
    {
        $recurso = file_get_contents(app_path('Filament/Resources/WashingMachineResource.php'));

        $this->assertStringContainsString("'Carga frontal'", $recurso);
        $this->assertStringNotContainsString("'Lavadora-secadora' => 'Lavadora-secadora'", $recurso);
    }
}
