<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\CollectionsWidget;
use App\Filament\Widgets\RentalCalendarWidget;
use Filament\Facades\Filament;
use Livewire\Livewire;
use App\Models\Company;
use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardTest extends TestCase
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

        $user = User::create([
            'name' => 'Dueño', 'email' => 'dueno@x.com', 'password' => bcrypt('secret'),
        ]);
        $user->assignRole('super_admin');
        $user->givePermissionTo([
            Permission::findOrCreate('view_any_rental', 'web'),
            Permission::findOrCreate('view_rental', 'web'),
        ]);
        $company->members()->attach($user);

        return [$company->fresh(), $user];
    }

    public function test_la_lista_de_rentas_muestra_las_fechas_en_formato_mexicano(): void
    {
        [$company, $user] = $this->makeCompanyWithOwner();

        $customer = $company->customers()->create([
            'name' => 'Juan Pérez', 'email' => 'juan@ejemplo.mx', 'phone' => '1',
        ]);
        $machine = $company->washingMachines()->create([
            'machine_code' => 'LAV-001', 'brand' => 'Mabe', 'status' => 'rentada',
        ]);

        $fin = now()->addDays(3);

        $company->rentals()->create([
            'customer_id' => $customer->id,
            'washing_machine_id' => $machine->id,
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => $fin->toDateString(),
            'status' => 'activa',
        ]);

        $this->actingAs($user)
            ->get("/propietario/{$company->id}/mis-rentas")
            ->assertOk()
            ->assertSee($fin->format('d/m/Y'))
            ->assertSee('Activa');
    }

    public function test_la_tabla_de_cobranza_trae_lo_vencido_y_lo_proximo_ordenado_por_urgencia(): void
    {
        [$company] = $this->makeCompanyWithOwner();

        $customer = $company->customers()->create([
            'name' => 'Juan Pérez', 'email' => 'juan@ejemplo.mx', 'phone' => '1',
        ]);

        $casos = [
            ['LAV-001', now()->subDays(20), 'vencida'],    // el más atrasado
            ['LAV-002', now()->subDays(3), 'vencida'],
            ['LAV-003', now()->addDays(4), 'activa'],      // vence esta semana
            ['LAV-004', now()->addDays(40), 'activa'],     // todavía no urge: fuera
            ['LAV-005', now()->subDays(50), 'completada'], // cerrada: fuera
        ];

        foreach ($casos as [$code, $end, $status]) {
            $machine = $company->washingMachines()->create([
                'machine_code' => $code, 'brand' => 'Mabe', 'status' => 'rentada',
            ]);
            $company->rentals()->create([
                'customer_id' => $customer->id,
                'washing_machine_id' => $machine->id,
                'start_date' => now()->subMonths(3)->toDateString(),
                'end_date' => $end->toDateString(),
                'status' => $status,
            ]);
        }

        $codigos = CollectionsWidget::baseQuery($company)
            ->get()
            ->map(fn ($rental) => $rental->washingMachine->machine_code)
            ->all();

        $this->assertSame(['LAV-001', 'LAV-002', 'LAV-003'], $codigos);
    }

    /**
     * El calendario tronaba con 500 al abrirlo: el paquete declara $record sin
     * valor inicial y como widget de encabezado nadie se lo pone.
     */
    public function test_la_pagina_del_calendario_abre_sin_reventar(): void
    {
        [$company, $user] = $this->makeCompanyWithOwner();

        $this->actingAs($user)
            ->get("/propietario/{$company->id}/calendario")
            ->assertOk();
    }

    /**
     * Un GET a la página no basta ni montar el widget tampoco: el 500 salía al
     * hacer clic en un evento, que dispara mountAction('view'). La ViewAction que
     * trae el paquete lee el registro para armar el título del modal, y como
     * nuestros eventos no tienen registro que resolver, tronaba.
     */
    public function test_hacer_clic_en_un_evento_del_calendario_no_truena(): void
    {
        [$company, $user] = $this->makeCompanyWithOwner();

        $this->actingAs($user);
        Filament::setTenant($company, true);

        Livewire::test(RentalCalendarWidget::class)
            ->call('mountAction', 'view')
            ->assertOk();
    }

    public function test_la_pagina_de_actividad_abre_sin_reventar(): void
    {
        [$company, $user] = $this->makeCompanyWithOwner();

        $this->actingAs($user)
            ->get("/propietario/{$company->id}/actividad")
            ->assertOk();
    }

    /**
     * Los rótulos de sección van entre los bloques, así que la lista trae tanto
     * clases sueltas como configuraciones de widget.
     */
    public function test_el_escritorio_declara_solo_los_widgets_previstos(): void
    {
        $clases = collect((new Dashboard())->getWidgets())
            ->map(fn ($w) => $w instanceof \Filament\Widgets\WidgetConfiguration ? $w->widget : $w)
            ->all();

        $this->assertSame([
            \App\Filament\Widgets\OnboardingWidget::class,
            \App\Filament\Widgets\SectionHeading::class,
            \App\Filament\Widgets\TodayStats::class,
            \App\Filament\Widgets\CollectionsWidget::class,
            \App\Filament\Widgets\SectionHeading::class,
            \App\Filament\Widgets\PaymentStats::class,
            \App\Filament\Widgets\UtilidadStats::class,
            \App\Filament\Widgets\MonthlyRevenueChart::class,
            \App\Filament\Widgets\SectionHeading::class,
            \App\Filament\Widgets\StatsOverview::class,
            \App\Filament\Widgets\RentalStatusChart::class,
            \App\Filament\Widgets\MachineProfitabilityWidget::class,
            \App\Filament\Widgets\BusinessAnalyticsWidget::class,
        ], $clases);

        foreach ($clases as $clase) {
            $this->assertTrue(class_exists($clase), "{$clase} no existe.");
        }
    }

    /**
     * Se comprueba el archivo y no la clase: class_exists dispara el autoloader,
     * que con el mapa de Composer sin regenerar intenta incluir un archivo borrado.
     */
    public function test_los_widgets_eliminados_ya_no_existen(): void
    {
        foreach ([
            'LatestCustomers',
            'LatestWashingMachines',
            'OverdueRentalsWidget',
            'RentDueWashingMachines',
            'CustomersWithDebtWidget',
        ] as $widget) {
            $this->assertFileDoesNotExist(
                app_path("Filament/Widgets/{$widget}.php"),
                "{$widget} debió eliminarse."
            );
        }
    }
}
