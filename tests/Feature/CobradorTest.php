<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use App\Support\Acceso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * El rol de cobrador.
 *
 * Hasta ahora sólo existían propietario y super_admin: el dueño no podía mandar
 * a nadie a cobrar sin darle acceso completo, incluidos precios y la
 * posibilidad de borrar.
 *
 * Lo que más importa aquí es lo que el cobrador NO puede: una prueba que sólo
 * revise que entra a lo suyo deja la puerta de atrás abierta.
 */
class CobradorTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $dueno;
    private User $cobrador;

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
        $this->company->members()->attach($this->dueno);

        $this->cobrador = User::create(['name' => 'Beto', 'email' => 'b@x.com', 'password' => bcrypt('s')]);
        $this->cobrador->assignRole(Role::findOrCreate('cobrador', 'web'));
        $this->company->members()->attach($this->cobrador);
    }

    private function url(string $ruta): string
    {
        return "/propietario/{$this->company->id}/{$ruta}";
    }

    public function test_la_migracion_dejo_el_rol_creado(): void
    {
        $this->assertNotNull(Role::where('name', 'cobrador')->first());
    }

    /** Su trabajo: ver a quién cobrar y registrar los cobros. */
    public function test_el_cobrador_entra_a_lo_que_necesita_para_trabajar(): void
    {
        $this->actingAs($this->cobrador);

        foreach (['clientes', 'mis-rentas', 'lavadoras', 'pagos', 'incidencias', 'rutas', 'corte-de-caja'] as $ruta) {
            $this->get($this->url($ruta))
                ->assertOk("El cobrador no puede entrar a {$ruta}, y lo necesita para trabajar.");
        }
    }

    /** Y lo que no le toca no lo ve ni escribiendo la dirección a mano. */
    public function test_el_cobrador_no_alcanza_las_pantallas_del_dueno(): void
    {
        $this->actingAs($this->cobrador);

        foreach ([
            'configuracion' => 'ahí se cambia el precio de la renta',
            'mi-plan' => 'ahí se ve lo que la empresa paga',
            'sacale-provecho' => 'habla de reportes y precios',
            'actividad' => 'la bitácora es para vigilarlo a él',
            'mi-equipo' => 'podría darse permisos a sí mismo',
            'referidos' => 'es del dueño',
        ] as $ruta => $porque) {
            $this->get($this->url($ruta))
                ->assertForbidden("El cobrador llegó a {$ruta} y {$porque}.");
        }
    }

    public function test_el_cobrador_no_puede_borrar_cobros(): void
    {
        $this->assertTrue($this->cobrador->can('create_payment'));
        $this->assertFalse($this->cobrador->can('delete_payment'));
        $this->assertFalse($this->cobrador->can('delete_any_payment'));
        $this->assertFalse($this->cobrador->can('update_payment'));
    }

    public function test_el_cobrador_mira_el_catalogo_pero_no_lo_toca(): void
    {
        foreach (['customer', 'rental', 'washing::machine'] as $modelo) {
            $this->assertTrue($this->cobrador->can("view_any_{$modelo}"), "No puede ver {$modelo}.");
            $this->assertFalse($this->cobrador->can("update_{$modelo}"), "Puede editar {$modelo}.");
            $this->assertFalse($this->cobrador->can("delete_{$modelo}"), "Puede borrar {$modelo}.");
            $this->assertFalse($this->cobrador->can("create_{$modelo}"), "Puede crear {$modelo}.");
        }
    }

    /**
     * Payment e Incident no tenían política, así que al ponerlas el propietario
     * se habría quedado fuera de su propia pantalla de pagos: nunca tuvo esos
     * permisos porque nadie los revisaba.
     */
    public function test_al_propietario_no_se_le_cerro_la_pantalla_de_pagos(): void
    {
        $this->assertTrue($this->dueno->can('view_any_payment'));
        $this->assertTrue($this->dueno->can('create_payment'));
        $this->assertTrue($this->dueno->can('delete_payment'));
        $this->assertTrue($this->dueno->can('view_any_incident'));

        $this->actingAs($this->dueno)
            ->get($this->url('pagos'))
            ->assertOk();
    }

    public function test_el_escritorio_del_cobrador_no_trae_los_numeros_del_negocio(): void
    {
        $this->actingAs($this->cobrador);

        $widgets = collect((new \App\Filament\Pages\Dashboard())->getWidgets())
            ->map(fn ($w) => $w instanceof \Filament\Widgets\WidgetConfiguration ? $w->widget : $w);

        $this->assertTrue($widgets->contains(\App\Filament\Widgets\CollectionsWidget::class));

        foreach ([
            \App\Filament\Widgets\PaymentStats::class,
            \App\Filament\Widgets\MonthlyRevenueChart::class,
            \App\Filament\Widgets\MachineProfitabilityWidget::class,
            \App\Filament\Widgets\BusinessAnalyticsWidget::class,
        ] as $delDueno) {
            $this->assertFalse($widgets->contains($delDueno), "{$delDueno} no es del cobrador.");
        }
    }

    /**
     * Cambiar el número en la petición traería el corte de otro. El candado va
     * en el servidor, no en el select de la pantalla.
     */
    public function test_el_cobrador_no_puede_espiar_el_corte_de_otro(): void
    {
        $this->actingAs($this->cobrador);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('propietario'));
        \Filament\Facades\Filament::setTenant($this->company, true);

        $pagina = new \App\Filament\Pages\CorteDeCajaPage();
        $pagina->cobradorId = $this->dueno->id;

        $this->assertSame(
            $this->cobrador->id,
            $pagina->corteDe()->id,
            'Pidió el corte del dueño y se lo dieron.'
        );
    }

    public function test_el_dueno_si_puede_ver_el_corte_de_su_cobrador(): void
    {
        $this->actingAs($this->dueno);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('propietario'));
        \Filament\Facades\Filament::setTenant($this->company, true);

        $pagina = new \App\Filament\Pages\CorteDeCajaPage();
        $pagina->cobradorId = $this->cobrador->id;

        $this->assertSame($this->cobrador->id, $pagina->corteDe()->id);
    }

    /** Ni el de alguien de otra empresa. */
    public function test_el_dueno_no_alcanza_a_gente_de_otra_empresa(): void
    {
        $otra = Company::create(['name' => 'Otra', 'phone' => '2', 'email' => 'o@x.com']);
        $ajeno = User::create(['name' => 'Ajeno', 'email' => 'a@x.com', 'password' => bcrypt('s')]);
        $otra->members()->attach($ajeno);

        $this->actingAs($this->dueno);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('propietario'));
        \Filament\Facades\Filament::setTenant($this->company, true);

        $pagina = new \App\Filament\Pages\CorteDeCajaPage();
        $pagina->cobradorId = $ajeno->id;

        $this->assertNull($pagina->corteDe(), 'Alcanzó a alguien de otra empresa.');
    }

    public function test_el_dueno_da_de_alta_a_su_gente_y_solo_ve_la_suya(): void
    {
        $otra = Company::create(['name' => 'Otra', 'phone' => '2', 'email' => 'o@x.com']);
        $ajeno = User::create(['name' => 'Ajeno', 'email' => 'a@x.com', 'password' => bcrypt('s')]);
        $otra->members()->attach($ajeno);

        $this->actingAs($this->dueno)
            ->get($this->url('mi-equipo'))
            ->assertOk()
            ->assertSee('Beto')
            ->assertDontSee('Ajeno');
    }

    public function test_el_cobro_del_cobrador_queda_a_su_nombre(): void
    {
        $this->actingAs($this->cobrador);

        $cliente = $this->company->customers()->create([
            'name' => 'Cliente', 'email' => 'c@x.mx', 'phone' => '1',
        ]);
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

        $pago = Payment::create([
            'company_id' => $this->company->id,
            'rental_id' => $renta->id,
            'amount' => 250,
            'payment_date' => today()->toDateString(),
            'payment_method' => 'Efectivo',
            'status' => 'completado',
        ]);

        $this->assertSame($this->cobrador->id, $pago->collected_by);
    }

    public function test_un_dueno_que_ademas_cobra_sigue_siendo_dueno(): void
    {
        $this->dueno->assignRole('cobrador');

        $this->actingAs($this->dueno->fresh());

        $this->assertFalse(Acceso::esCobrador());
        $this->assertTrue(Acceso::soloDueno());
    }

    /**
     * La pantalla de prospectos es de quien opera la plataforma, no de los
     * rentadores: trae la lista de prospectos del negocio y no tenía candado.
     */
    public function test_la_prospeccion_no_la_ve_un_rentador(): void
    {
        $this->actingAs($this->dueno)
            ->get($this->url('contactar'))
            ->assertForbidden();
    }

    /**
     * En pruebas separadas porque el panel trae AuthenticateSession: cambiar de
     * usuario a media prueba tira la sesión y la siguiente petición llega sin
     * autenticar.
     */
    public function test_la_prospeccion_si_la_ve_el_administrador(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'ad@x.com', 'password' => bcrypt('s')]);
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));
        $this->company->members()->attach($admin);

        $this->actingAs($admin)
            ->get($this->url('contactar'))
            ->assertOk();
    }
}
