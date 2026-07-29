<?php

namespace Tests\Feature;

use App\Filament\Resources\CustomerResource\Pages\ListCustomers;
use App\Filament\Resources\RentalResource\Pages\ListRentals;
use App\Filament\Resources\WashingMachineResource\Pages\ListWashingMachines;
use App\Filament\Widgets\PaymentStats;
use App\Filament\Widgets\StatsOverview;
use App\Models\Company;
use App\Models\Package;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MobileListsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        Role::findOrCreate('super_admin', 'web');
        Role::findOrCreate('propietario', 'web');

        $this->prepararEmpresa();
    }

    private function prepararEmpresa(): Company
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
            Permission::findOrCreate('view_any_washing::machine', 'web'),
            Permission::findOrCreate('view_washing::machine', 'web'),
        ]);
        $company->members()->attach($user);

        $this->actingAs($user);
        Filament::setTenant($company->fresh(), true);

        return $company->fresh();
    }

    /**
     * Las columnas que se esconden hasta tableta, tomadas de la tabla de verdad.
     *
     * @return array<int, string>
     */
    private function columnasOcultasEnCelular(string $pagina): array
    {
        $tabla = Livewire::test($pagina)->instance()->getTable();

        return collect($tabla->getColumns())
            ->filter(fn ($columna) => $columna->getVisibleFrom() === 'md')
            ->keys()
            ->all();
    }

    /**
     * En celular solo sobrevive una columna por lista; lo demás viaja como
     * subtítulo de ella. Con las columnas sueltas la fila seguía saliéndose de
     * la pantalla, que es el criterio que importa.
     */
    public function test_en_rentas_solo_queda_el_cliente_en_celular(): void
    {
        $ocultas = $this->columnasOcultasEnCelular(ListRentals::class);

        foreach (['start_date', 'end_date', 'washingMachine.machine_code', 'status'] as $columna) {
            $this->assertContains($columna, $ocultas, "{$columna} debería esconderse en celular.");
        }

        $this->assertNotContains('customer.name', $ocultas, 'El cliente debe verse siempre.');
    }

    public function test_en_clientes_solo_queda_el_nombre_en_celular(): void
    {
        $ocultas = $this->columnasOcultasEnCelular(ListCustomers::class);

        foreach (['email', 'phone', 'debt'] as $columna) {
            $this->assertContains($columna, $ocultas, "{$columna} debería esconderse en celular.");
        }

        $this->assertNotContains('name', $ocultas, 'El nombre debe verse siempre.');
    }

    public function test_en_lavadoras_solo_queda_el_codigo_en_celular(): void
    {
        $ocultas = $this->columnasOcultasEnCelular(ListWashingMachines::class);

        foreach ([
            'brand', 'model', 'status', 'activeRental.status',
            'activeRental.customer.name', 'activeRental.start_date', 'activeRental.end_date',
        ] as $columna) {
            $this->assertContains($columna, $ocultas, "{$columna} debería esconderse en celular.");
        }

        $this->assertNotContains('machine_code', $ocultas, 'El código debe verse siempre.');
    }

    /** Los métodos del widget son protected, así que se cuenta lo que se dibuja. */
    private function contarRecuadros(string $html): int
    {
        return substr_count($html, 'fi-wi-stats-overview-stat ');
    }

    public function test_los_equipos_se_resumen_en_un_solo_recuadro(): void
    {
        $html = Livewire::test(StatsOverview::class)->html();

        $this->assertSame(1, $this->contarRecuadros($html), 'Eran cuatro recuadros de un número cada uno.');
        // "Equipos" y no "Lavadoras": el parque también trae secadoras.
        $this->assertStringContainsString('Equipos', $html);
        $this->assertStringContainsString('rentados', $html);
        $this->assertStringContainsString('libres', $html);
    }

    /**
     * El mantenimiento y las secadoras sólo se nombran cuando los hay: "0 en
     * mantenimiento" es ruido en la tarjeta de quien no tiene ninguno.
     */
    public function test_el_recuadro_solo_nombra_lo_que_existe(): void
    {
        $this->assertStringNotContainsString(
            'mantenimiento',
            Livewire::test(StatsOverview::class)->html(),
            'Sin equipos en mantenimiento, no debería nombrarlo.'
        );

        Filament::getTenant()->washingMachines()->create([
            'machine_code' => 'SEC-001', 'brand' => 'Mabe',
            'kind' => 'secadora', 'status' => 'mantenimiento',
        ]);

        $html = Livewire::test(StatsOverview::class)->html();

        $this->assertStringContainsString('en mantenimiento', $html);
        $this->assertStringContainsString('son secadoras', $html);
    }

    public function test_los_pagos_se_resumen_en_tres_recuadros(): void
    {
        $html = Livewire::test(PaymentStats::class)->html();

        $this->assertSame(3, $this->contarRecuadros($html), 'Eran cinco recuadros.');
        $this->assertStringContainsString('Rentas Activas', $html);
        $this->assertStringContainsString('vencidas', $html);
        $this->assertStringContainsString('pagos pendientes', $html);
        $this->assertStringNotContainsString('Pagos Pendientes', $html, 'Ese recuadro se fundió con el de rentas.');
    }
}
