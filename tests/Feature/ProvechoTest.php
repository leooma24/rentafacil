<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Package;
use App\Models\User;
use App\Support\Herramienta;
use App\Support\Provecho;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * La guía de provecho.
 *
 * Lo que se prueba es que el estado salga de los datos y no de un texto fijo:
 * una guía que le diga "sin estrenar" a algo que ya usa deja de creerse a la
 * primera, y entonces no sirve para nada.
 */
class ProvechoTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

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

        $user = User::create(['name' => 'Dueño', 'email' => 'd@x.com', 'password' => bcrypt('s')]);
        $user->assignRole('super_admin');
        // Shield corre con define_via_gate en false: super_admin no se salta las
        // políticas, así que los permisos van explícitos.
        $user->givePermissionTo(array_map(
            fn (string $permiso) => \Spatie\Permission\Models\Permission::findOrCreate($permiso, 'web'),
            [
                'view_any_customer', 'view_any_washing::machine', 'view_any_rental',
                'view_any_payment', 'view_any_maintenance', 'view_any_incident',
            ]
        ));
        $this->company->members()->attach($user);

        $this->actingAs($user);
        Filament::setTenant($this->company, true);
    }

    private function herramienta(string $clave): ?Herramienta
    {
        return collect(Provecho::for($this->company->fresh())->herramientas)
            ->firstWhere('clave', $clave);
    }

    private function cliente(string $email = 'c@x.mx'): Customer
    {
        return $this->company->customers()->create([
            'name' => 'Cliente', 'email' => $email, 'phone' => '1',
        ]);
    }

    private function direccion(Customer $cliente, ?float $lat = null): void
    {
        DB::table('addresses')->insert([
            'addressable_type' => Customer::class,
            'addressable_id' => $cliente->id,
            'street' => 'Calle', 'number' => '1', 'city' => 'Culiacán', 'postal_code' => '80000',
            'latitude' => $lat,
            'longitude' => $lat === null ? null : -107.39,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_una_cuenta_recien_abierta_tiene_herramientas_sin_estrenar(): void
    {
        $sinEstrenar = collect(Provecho::for($this->company)->sinEstrenar())
            ->pluck('clave')
            ->all();

        $this->assertContains('rutas', $sinEstrenar);
        $this->assertContains('abonos', $sinEstrenar);
        $this->assertContains('mantenimientos', $sinEstrenar);
        $this->assertContains('incidencias', $sinEstrenar);
    }

    /**
     * El caso que motivó la guía: 71 direcciones capturadas en producción y
     * cero con coordenadas, así que el planificador nunca pudo trazar nada.
     */
    public function test_rutas_avisa_cuando_hay_direcciones_pero_sin_ubicacion(): void
    {
        $this->direccion($this->cliente('a@x.mx'));
        $this->direccion($this->cliente('b@x.mx'));

        $rutas = $this->herramienta('rutas');

        $this->assertTrue($rutas->sinEstrenar());
        $this->assertStringContainsString('2 clientes con dirección', $rutas->pista);
        $this->assertStringContainsString('ninguno con ubicación', $rutas->pista);
    }

    public function test_rutas_se_da_por_estrenada_en_cuanto_hay_una_ubicacion(): void
    {
        $this->direccion($this->cliente('a@x.mx'), 24.8049);
        $this->direccion($this->cliente('b@x.mx'));

        $rutas = $this->herramienta('rutas');

        $this->assertTrue($rutas->usando());
        $this->assertStringContainsString('1 de tus 2 clientes', $rutas->pista);
    }

    public function test_abonos_se_marca_como_usada_cuando_hay_un_pago_sin_aplicar(): void
    {
        $this->assertTrue($this->herramienta('abonos')->sinEstrenar());

        $cliente = $this->cliente();
        $maquina = $this->company->washingMachines()->create([
            'machine_code' => 'LAV-001', 'brand' => 'Mabe', 'status' => 'rentada',
        ]);
        $renta = $this->company->rentals()->create([
            'customer_id' => $cliente->id,
            'washing_machine_id' => $maquina->id,
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'status' => 'activa',
        ]);
        $renta->payments()->create([
            'company_id' => $this->company->id,
            'amount' => 100,
            'payment_date' => today()->toDateString(),
            'payment_method' => 'Efectivo',
            'status' => 'completado',
            'applied' => false,
        ]);

        $this->assertTrue($this->herramienta('abonos')->usando());
    }

    /** La pista es lo que convence, y tiene que traer sus propios números. */
    public function test_el_estado_de_cuenta_dice_cuanto_le_deben_ahorita(): void
    {
        $cliente = $this->cliente();
        $maquina = $this->company->washingMachines()->create([
            'machine_code' => 'LAV-001', 'brand' => 'Mabe', 'status' => 'rentada',
        ]);
        $this->company->rentals()->create([
            'customer_id' => $cliente->id,
            'washing_machine_id' => $maquina->id,
            'start_date' => now()->subMonths(2)->toDateString(),
            'end_date' => now()->subDays(14)->toDateString(),
            'status' => 'vencida',
        ]);

        $pista = $this->herramienta('estado-de-cuenta')->pista;

        $this->assertStringContainsString('1 cliente te debe', $pista);
        $this->assertStringContainsString('$500.00', $pista);
    }

    /** Sin cupo lleno la importación sobra: sólo aparece cuando hay poco cargado. */
    public function test_la_importacion_se_ofrece_solo_cuando_hay_poco_cargado(): void
    {
        $this->assertNotNull($this->herramienta('importar'));

        for ($i = 1; $i <= 10; $i++) {
            $this->company->washingMachines()->create([
                'machine_code' => sprintf('LAV-%03d', $i), 'brand' => 'Mabe', 'status' => 'disponible',
            ]);
            $this->cliente("c{$i}@x.mx");
        }

        $this->assertNull($this->herramienta('importar'));
    }

    public function test_la_pagina_abre_y_enseña_las_herramientas(): void
    {
        $this->get("/propietario/{$this->company->id}/sacale-provecho")
            ->assertOk()
            ->assertSee('Sal a cobrar en el orden más corto')
            ->assertSee('Sin estrenar');
    }

    /** Cada herramienta apunta a una ruta que existe; un enlace roto mata la guía. */
    public function test_todas_las_herramientas_apuntan_a_una_pantalla_real(): void
    {
        foreach (Provecho::for($this->company)->herramientas as $herramienta) {
            $this->assertNotNull(
                app('router')->getRoutes()->getByName($herramienta->ruta),
                "La herramienta {$herramienta->clave} apunta a {$herramienta->ruta}, que no existe."
            );

            $this->get($herramienta->url())
                ->assertOk("La herramienta {$herramienta->clave} lleva a una pantalla que no abre.");
        }
    }

    public function test_el_menu_avisa_cuantas_faltan_por_estrenar(): void
    {
        $badge = \App\Filament\Pages\SacaleProvecho::getNavigationBadge();

        $this->assertSame(
            (string) Provecho::for($this->company)->totalSinEstrenar(),
            $badge
        );
    }
}
