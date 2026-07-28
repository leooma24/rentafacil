<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Package;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DemoAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * CompanyObserver asigna package_id 1 a toda empresa nueva y el AUTO_INCREMENT
     * de MySQL no se reinicia entre tests, así que el id va forzado.
     */
    private function seedPackage(): void
    {
        Package::forceCreate([
            'id' => 1, 'name' => 'Pro', 'max_clients' => 500, 'max_washers' => 500, 'price' => 999,
        ]);
    }

    public function test_las_migraciones_corren_en_la_base_de_pruebas(): void
    {
        $this->assertSame('flavadoras_testing', config('database.connections.mysql.database'));
        $this->assertTrue(Schema::hasTable('companies'));
    }

    public function test_la_pantalla_de_espera_responde(): void
    {
        $this->get('/demo')->assertOk()->assertSee('Preparando tu demo');
    }

    public function test_iniciar_demo_crea_sandbox_y_autentica(): void
    {
        $this->seedPackage();

        $response = $this->postJson('/demo/iniciar');

        $response->assertOk()->assertJsonStructure(['url']);
        $this->assertAuthenticated();

        $company = Company::demo()->first();
        $this->assertNotNull($company);
        $this->assertStringContainsString("/propietario/{$company->id}", $response->json('url'));
        $this->assertTrue(auth()->user()->companies->contains($company));
        $this->assertGreaterThan(0, $company->washingMachines()->count());
    }

    public function test_el_limite_por_ip_corta_al_sexto_intento(): void
    {
        $this->seedPackage();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/demo/iniciar')->assertOk();
        }

        $this->postJson('/demo/iniciar')->assertStatus(429);
    }

    public function test_un_segundo_demo_funciona_aunque_el_primero_ya_se_haya_borrado(): void
    {
        $this->seedPackage();

        $this->postJson('/demo/iniciar')->assertOk();
        $primero = Company::demo()->first();

        // demo:cleanup borra al usuario del primer sandbox mientras el visitante
        // sigue con esa cookie de sesión.
        $primero->update(['demo_expires_at' => now()->subHour()]);
        $this->artisan('demo:cleanup')->assertSuccessful();

        $response = $this->postJson('/demo/iniciar');

        $response->assertOk();
        $this->assertAuthenticated();
        $segundo = Company::demo()->first();
        $this->assertNotNull($segundo);
        $this->assertStringContainsString("/propietario/{$segundo->id}", $response->json('url'));
    }

    public function test_la_home_ofrece_el_demo(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Ver demo en vivo');
        $response->assertSee('href="/demo"', false);
    }
}
