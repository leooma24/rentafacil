<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use App\Support\PendientesDelDia;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Los pendientes del día en el escritorio.
 *
 * El escritorio decía cuánto le deben y quién, pero no lo que hay que HACER.
 * Cada pendiente sólo aparece cuando de verdad lo está: una lista que siempre
 * enseña lo mismo deja de leerse a la semana.
 */
class PendientesDelDiaTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $dueno;
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

        $this->dueno = User::create(['name' => 'Dueño', 'email' => 'd@x.com', 'password' => bcrypt('s')]);
        $this->dueno->assignRole(Role::findOrCreate('propietario', 'web'));
        // Shield corre con define_via_gate en false: los permisos van explícitos.
        $this->dueno->givePermissionTo(
            \Spatie\Permission\Models\Permission::findOrCreate('view_any_rental', 'web')
        );
        $this->company->members()->attach($this->dueno);
        $this->actingAs($this->dueno);

        Filament::setCurrentPanel(Filament::getPanel('propietario'));
        Filament::setTenant($this->company, true);

        $this->cliente = $this->company->customers()->create([
            'name' => 'Cliente', 'email' => 'c@x.mx', 'phone' => '1',
        ]);
    }

    /** @return array<int, string> */
    private function claves(): array
    {
        return collect(PendientesDelDia::for($this->company->fresh(), $this->dueno)->pendientes)
            ->pluck('clave')
            ->all();
    }

    private function renta(array $extra = []): \App\Models\Rental
    {
        $equipo = $this->company->washingMachines()->create([
            'machine_code' => 'LAV-' . fake()->unique()->numberBetween(100, 999),
            'brand' => 'Mabe', 'status' => 'rentada',
        ]);

        return $this->company->rentals()->create(array_merge([
            'customer_id' => $this->cliente->id,
            'washing_machine_id' => $equipo->id,
            'start_date' => now()->subDays(3)->toDateString(),
            'end_date' => now()->addDays(10)->toDateString(),
            'status' => 'activa',
            'delivered_at' => now(),
        ], $extra));
    }

    private function ubicarAlCliente(): void
    {
        DB::table('addresses')->insert([
            'addressable_type' => Customer::class,
            'addressable_id' => $this->cliente->id,
            'street' => 'Calle', 'number' => '1', 'city' => 'Culiacán', 'postal_code' => '80000',
            'latitude' => 24.8049, 'longitude' => -107.39,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_una_empresa_sin_nada_pendiente_no_enseña_la_lista(): void
    {
        $this->assertSame([], $this->claves());
        $this->assertFalse(PendientesDelDia::for($this->company, $this->dueno)->hayPendientes());
    }

    public function test_avisa_de_las_entregas_sin_registrar(): void
    {
        $this->renta(['delivered_at' => null]);

        $this->assertContains('entregas', $this->claves());
    }

    public function test_una_renta_ya_entregada_no_aparece(): void
    {
        $this->renta();

        $this->assertNotContains('entregas', $this->claves());
    }

    /** Las rentas cerradas no piden entrega: ocurrieron antes de la función. */
    public function test_una_renta_completada_no_pide_entrega(): void
    {
        $this->renta(['delivered_at' => null, 'status' => 'completada']);

        $this->assertNotContains('entregas', $this->claves());
    }

    public function test_avisa_de_a_quien_hay_que_cobrarle_hoy(): void
    {
        $this->renta(['end_date' => today()->toDateString()]);

        $this->assertContains('cobranza', $this->claves());
    }

    /**
     * Sin clientes ubicados el planificador no puede trazar nada, así que manda
     * a la lista de rentas en vez de a una pantalla vacía.
     */
    public function test_sin_clientes_ubicados_no_manda_al_planificador(): void
    {
        $this->renta(['end_date' => today()->subDay()->toDateString(), 'status' => 'vencida']);

        $pendiente = collect(PendientesDelDia::for($this->company, $this->dueno)->pendientes)
            ->firstWhere('clave', 'cobranza');

        $this->assertStringContainsString('mis-rentas', $pendiente->ruta);
        $this->assertSame('Ver a quién cobrar', $pendiente->accion);
    }

    public function test_con_clientes_ubicados_ofrece_armar_la_ruta(): void
    {
        $this->renta(['end_date' => today()->subDay()->toDateString(), 'status' => 'vencida']);
        $this->ubicarAlCliente();

        $pendiente = collect(PendientesDelDia::for($this->company, $this->dueno)->pendientes)
            ->firstWhere('clave', 'cobranza');

        $this->assertSame('filament.propietario.pages.rutas', $pendiente->ruta);
        $this->assertSame('Armar la ruta', $pendiente->accion);
    }

    // --- Corte de caja ---

    private function cobroEnEfectivo(float $monto = 500): Payment
    {
        return Payment::create([
            'company_id' => $this->company->id,
            'rental_id' => $this->renta()->id,
            'amount' => $monto,
            'payment_date' => today()->toDateString(),
            'payment_method' => 'Efectivo',
            'status' => 'completado',
            'collected_by' => $this->dueno->id,
        ]);
    }

    public function test_avisa_del_corte_cuando_se_cobro_en_efectivo(): void
    {
        $this->cobroEnEfectivo();

        $pendiente = collect(PendientesDelDia::for($this->company, $this->dueno)->pendientes)
            ->firstWhere('clave', 'corte');

        $this->assertNotNull($pendiente);
        $this->assertStringContainsString('$500.00', $pendiente->detalle);
    }

    /** Lo cobrado por transferencia ya está en el banco: no hay nada que cuadrar. */
    public function test_una_transferencia_no_pide_corte(): void
    {
        Payment::create([
            'company_id' => $this->company->id,
            'rental_id' => $this->renta()->id,
            'amount' => 500,
            'payment_date' => today()->toDateString(),
            'payment_method' => 'transferencia',
            'status' => 'completado',
            'collected_by' => $this->dueno->id,
        ]);

        $this->assertNotContains('corte', $this->claves());
    }

    public function test_una_vez_cerrado_el_dia_deja_de_avisar(): void
    {
        $this->cobroEnEfectivo();

        $this->assertContains('corte', $this->claves());

        \App\Support\CorteDeCaja::para($this->company, today(), $this->dueno)
            ->cerrar($this->dueno, 500);

        $this->assertNotContains('corte', $this->claves());
    }

    /** El corte es de cada quien: el del dueño no le aparece al cobrador. */
    public function test_el_corte_pendiente_es_de_cada_persona(): void
    {
        $cobrador = User::create(['name' => 'Beto', 'email' => 'b@x.com', 'password' => bcrypt('s')]);
        $cobrador->assignRole(Role::findOrCreate('cobrador', 'web'));
        $this->company->members()->attach($cobrador);

        $this->cobroEnEfectivo();

        $delCobrador = collect(PendientesDelDia::for($this->company, $cobrador)->pendientes)
            ->pluck('clave')
            ->all();

        $this->assertNotContains('corte', $delCobrador);
    }

    public function test_el_widget_se_esconde_cuando_no_hay_nada_que_hacer(): void
    {
        $this->assertFalse(\App\Filament\Widgets\PendientesWidget::canView());

        $this->renta(['delivered_at' => null]);

        $this->assertTrue(\App\Filament\Widgets\PendientesWidget::canView());
    }

    public function test_cada_pendiente_apunta_a_una_pantalla_real(): void
    {
        $this->renta(['delivered_at' => null, 'end_date' => today()->toDateString()]);
        $this->cobroEnEfectivo();
        $this->ubicarAlCliente();

        $pendientes = PendientesDelDia::for($this->company->fresh(), $this->dueno)->pendientes;

        $this->assertCount(3, $pendientes, 'Deberían salir los tres pendientes.');

        foreach ($pendientes as $pendiente) {
            $this->assertNotNull(
                app('router')->getRoutes()->getByName($pendiente->ruta),
                "El pendiente {$pendiente->clave} apunta a {$pendiente->ruta}, que no existe."
            );

            $this->get($pendiente->url())->assertOk();
        }
    }
}
