<?php

namespace Tests\Feature;

use App\Filament\Resources\CustomerResource\Widgets\ClientesStats;
use App\Filament\Resources\IncidentResource\Widgets\IncidenciasStats;
use App\Filament\Resources\MaintenanceResource\Widgets\MantenimientosStats;
use App\Filament\Resources\PaymentResource\Widgets\PagosStats;
use App\Filament\Resources\RentalResource\Widgets\RentasStats;
use App\Filament\Resources\WashingMachineResource\Widgets\LavadorasStats;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Package;
use App\Models\User;
use App\Models\WashingMachine;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Los recuadros que encabezan cada catálogo.
 *
 * Se prueba el número que sale, no que el widget cargue: un recuadro que
 * siempre dice cero es peor que no tenerlo, porque se le cree.
 */
class CatalogoStatsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

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

        $this->user = User::create(['name' => 'Dueño', 'email' => 'd@x.com', 'password' => bcrypt('secret')]);
        $this->user->assignRole('super_admin');
        $this->company->members()->attach($this->user);

        $this->actingAs($this->user);
        Filament::setTenant($this->company, true);
    }

    /** @return array<string, string> etiqueta => valor */
    private function valores(string $widget): array
    {
        $instancia = new $widget();

        $stats = (fn () => $this->getStats())->call($instancia);

        return collect($stats)
            ->mapWithKeys(fn ($stat) => [$stat->getLabel() => (string) $stat->getValue()])
            ->all();
    }

    private function maquina(string $codigo, string $estado): WashingMachine
    {
        return $this->company->washingMachines()->create([
            'machine_code' => $codigo, 'brand' => 'Mabe', 'status' => $estado,
        ]);
    }

    private function cliente(string $nombre, string $email): Customer
    {
        return $this->company->customers()->create([
            'name' => $nombre, 'email' => $email, 'phone' => '1',
        ]);
    }

    public function test_clientes_separa_a_los_que_traen_lavadora_de_los_que_no(): void
    {
        $conRenta = $this->cliente('Con renta', 'a@x.mx');
        $this->cliente('Sin renta', 'b@x.mx');
        $this->cliente('Otro sin renta', 'c@x.mx');

        $this->company->rentals()->create([
            'customer_id' => $conRenta->id,
            'washing_machine_id' => $this->maquina('LAV-001', 'rentada')->id,
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'status' => 'activa',
        ]);

        $valores = $this->valores(ClientesStats::class);

        $this->assertSame('1', $valores['Con renta']);
        $this->assertSame('2', $valores['Sin renta']);
        $this->assertSame('0', $valores['Te deben'], 'Nadie está atrasado todavía.');
    }

    public function test_clientes_cuenta_a_los_que_deben(): void
    {
        $moroso = $this->cliente('Moroso', 'm@x.mx');

        $this->company->rentals()->create([
            'customer_id' => $moroso->id,
            'washing_machine_id' => $this->maquina('LAV-001', 'rentada')->id,
            'start_date' => now()->subMonths(2)->toDateString(),
            'end_date' => now()->subDays(14)->toDateString(),
            'status' => 'vencida',
        ]);

        $this->assertSame('1', $this->valores(ClientesStats::class)['Te deben']);
    }

    public function test_lavadoras_calcula_la_ocupacion_sin_contar_las_vendidas(): void
    {
        $this->maquina('LAV-001', 'rentada');
        $this->maquina('LAV-002', 'rentada');
        $this->maquina('LAV-003', 'disponible');
        $this->maquina('LAV-004', 'mantenimiento');
        // La vendida ya no es del parque: si contara, la ocupación sería 40%.
        $this->maquina('LAV-005', 'vendida');

        $valores = $this->valores(LavadorasStats::class);

        $this->assertSame('50%', $valores['Ocupación']);
        $this->assertSame('1', $valores['Disponibles']);
        $this->assertSame('1', $valores['Detenidas']);
    }

    public function test_lavadoras_no_truena_con_el_parque_vacio(): void
    {
        $this->assertSame('0%', $this->valores(LavadorasStats::class)['Ocupación']);
    }

    public function test_rentas_distingue_activas_vencidas_y_las_de_esta_semana(): void
    {
        $cliente = $this->cliente('Cliente', 'c@x.mx');

        $casos = [
            ['LAV-001', now()->subDays(10), 'vencida'],
            ['LAV-002', now()->addDays(3), 'activa'],   // vence esta semana
            ['LAV-003', now()->addDays(40), 'activa'],  // todavía no
            ['LAV-004', now()->subDays(60), 'completada'],
        ];

        foreach ($casos as [$codigo, $fin, $estado]) {
            $this->company->rentals()->create([
                'customer_id' => $cliente->id,
                'washing_machine_id' => $this->maquina($codigo, 'rentada')->id,
                'start_date' => now()->subMonths(3)->toDateString(),
                'end_date' => $fin->toDateString(),
                'status' => $estado,
            ]);
        }

        $valores = $this->valores(RentasStats::class);

        $this->assertSame('2', $valores['Activas']);
        $this->assertSame('1', $valores['Vencidas']);
        $this->assertSame('1', $valores['Vencen esta semana']);
    }

    public function test_pagos_separa_lo_de_hoy_lo_del_mes_y_lo_que_entro_en_efectivo(): void
    {
        $cliente = $this->cliente('Cliente', 'c@x.mx');
        $renta = $this->company->rentals()->create([
            'customer_id' => $cliente->id,
            'washing_machine_id' => $this->maquina('LAV-001', 'rentada')->id,
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'status' => 'activa',
        ]);

        $cobros = [
            [300, today(), 'Efectivo'],
            [200, today(), 'transferencia'],
            // Del mes pero no de hoy: entra al mes, no al día.
            [500, today()->copy()->startOfMonth(), 'Efectivo'],
            // Del mes pasado: no entra a ninguno de los dos.
            [900, today()->copy()->subMonthNoOverflow()->startOfMonth(), 'Efectivo'],
        ];

        foreach ($cobros as [$monto, $fecha, $metodo]) {
            $renta->payments()->create([
                'company_id' => $this->company->id,
                'amount' => $monto,
                'payment_date' => $fecha->toDateString(),
                'payment_method' => $metodo,
                'status' => 'completado',
            ]);
        }

        $valores = $this->valores(PagosStats::class);

        $this->assertSame('$500.00', $valores['Cobrado hoy']);
        $this->assertSame('$1,000.00', $valores['Cobrado este mes']);
        $this->assertSame('$800.00', $valores['En efectivo']);
    }

    public function test_mantenimientos_avisa_de_los_programados_y_suma_el_costo_del_mes(): void
    {
        $maquina = $this->maquina('LAV-001', 'mantenimiento');

        $servicios = [
            ['programada', now()->subDays(4), 300],   // atrasado
            ['programada', now()->addDays(3), 150],
            ['completado', now()->subDays(2), 450],
            // Del mes pasado: no debe sumar al costo.
            ['completado', now()->subMonthNoOverflow()->startOfMonth(), 900],
        ];

        foreach ($servicios as [$estado, $inicio, $costo]) {
            $this->company->maintenances()->create([
                'washing_machine_id' => $maquina->id,
                'technician_name' => 'Téc',
                'start_date' => $inicio->toDateString(),
                'maintenance_type' => 'correctivo',
                'description' => 'x',
                'cost' => $costo,
                'status' => $estado,
            ]);
        }

        $valores = $this->valores(MantenimientosStats::class);

        $this->assertSame('2', $valores['Programados']);
        $this->assertSame('1', $valores['Completados este mes']);
        $this->assertSame('$900.00', $valores['Costo del mes']);
    }

    public function test_incidencias_promedia_los_dias_que_tardan_en_cerrarse(): void
    {
        $maquina = $this->maquina('LAV-001', 'rentada');

        $reportes = [
            ['abierta', 'alta', null, null],
            ['abierta', 'baja', null, null],
            ['en_progreso', 'media', null, null],
            ['cerrada', 'baja', now()->subDays(10), now()->subDays(6)],  // 4 días
            ['cerrada', 'baja', now()->subDays(9), now()->subDays(7)],   // 2 días
        ];

        foreach ($reportes as $i => [$estado, $prioridad, $creada, $resuelta]) {
            $incidencia = $this->company->incidents()->create([
                'title' => "Reporte {$i}",
                'description' => 'x',
                'status' => $estado,
                'priority' => $prioridad,
                'washing_machine_id' => $maquina->id,
                'user_id' => $this->user->id,
                'resolved_at' => $resuelta,
            ]);

            if ($creada) {
                $incidencia->forceFill(['created_at' => $creada])->save();
            }
        }

        $valores = $this->valores(IncidenciasStats::class);

        $this->assertSame('2', $valores['Abiertas']);
        $this->assertSame('1', $valores['En progreso']);
        $this->assertSame('3', $valores['Días para resolver']);
    }

    public function test_incidencias_no_inventa_un_promedio_cuando_no_hay_cerradas(): void
    {
        $this->assertSame('—', $this->valores(IncidenciasStats::class)['Días para resolver']);
    }

    /**
     * Salía en el demo: el reporte se creaba hoy y se marcaba resuelto cuatro
     * días antes, así que el recuadro anunciaba "-4 días para resolver".
     */
    public function test_incidencias_ignora_los_reportes_cerrados_antes_de_abrirse(): void
    {
        $maquina = $this->maquina('LAV-001', 'rentada');

        $incoherente = $this->company->incidents()->create([
            'title' => 'Cerrada antes de abrirse',
            'description' => 'x',
            'status' => 'cerrada',
            'priority' => 'baja',
            'washing_machine_id' => $maquina->id,
            'user_id' => $this->user->id,
            'resolved_at' => now()->subDays(4),
        ]);
        $incoherente->forceFill(['created_at' => now()])->save();

        $this->assertSame('—', $this->valores(IncidenciasStats::class)['Días para resolver']);
    }

    /**
     * Los recuadros van en el encabezado, no en el escritorio: si alguien los
     * moviera a app/Filament/Widgets, discoverWidgets() los metería al escritorio.
     */
    public function test_los_listados_montan_su_recuadro(): void
    {
        $paginas = [
            \App\Filament\Resources\CustomerResource\Pages\ListCustomers::class => ClientesStats::class,
            \App\Filament\Resources\WashingMachineResource\Pages\ListWashingMachines::class => LavadorasStats::class,
            \App\Filament\Resources\RentalResource\Pages\ListRentals::class => RentasStats::class,
            \App\Filament\Resources\PaymentResource\Pages\ListPayments::class => PagosStats::class,
            \App\Filament\Resources\MaintenanceResource\Pages\ListMaintenances::class => MantenimientosStats::class,
            \App\Filament\Resources\IncidentResource\Pages\ListIncidents::class => IncidenciasStats::class,
        ];

        foreach ($paginas as $pagina => $widget) {
            $declarados = (fn () => $this->getHeaderWidgets())->call(new $pagina());

            $this->assertContains($widget, $declarados, "{$pagina} no declara {$widget}.");

            // Y que de verdad se dibuje: un widget perezoso se queda en blanco.
            Livewire::test($widget)->assertOk();
            $this->assertFalse(
                (new \ReflectionClass($widget))->getStaticPropertyValue('isLazy'),
                "{$widget} es perezoso y se quedará en el placeholder de carga."
            );
        }
    }
}
