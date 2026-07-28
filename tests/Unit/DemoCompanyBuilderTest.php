<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\Package;
use App\Models\Payment;
use App\Services\DemoCompanyBuilder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoCompanyBuilderTest extends TestCase
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

    public function test_los_scopes_separan_demos_vencidas_de_las_vivas(): void
    {
        // CompanyObserver le asigna package_id 1 a toda empresa nueva.
        $this->seedPackage();

        $real = Company::create(['name' => 'Real', 'phone' => '1', 'email' => 'r@x.com']);
        $viva = Company::create([
            'name' => 'Demo viva', 'phone' => '2', 'email' => 'v@x.com',
            'is_demo' => true, 'demo_expires_at' => now()->addHours(5),
        ]);
        $vencida = Company::create([
            'name' => 'Demo vencida', 'phone' => '3', 'email' => 'x@x.com',
            'is_demo' => true, 'demo_expires_at' => now()->subHour(),
        ]);

        $this->assertEqualsCanonicalizing(
            [$viva->id, $vencida->id],
            Company::demo()->pluck('id')->all()
        );
        $this->assertSame([$vencida->id], Company::expiredDemos()->pluck('id')->all());
        $this->assertFalse($real->fresh()->is_demo);
    }

    public function test_construye_una_empresa_demo_con_usuario_y_plan(): void
    {
        $this->seedPackage();

        $company = (new DemoCompanyBuilder())->build();

        $this->assertTrue($company->is_demo);
        $this->assertNotNull($company->demo_expires_at);
        $this->assertEqualsWithDelta(
            DemoCompanyBuilder::LIFETIME_HOURS,
            now()->diffInHours($company->demo_expires_at, false),
            1
        );

        $user = $company->members()->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->is_demo);
        $this->assertStringStartsWith('demo+', $user->email);

        $this->assertTrue($company->hasActivePackage());
        $this->assertSame(250.0, (float) $company->settings->price);
        $this->assertSame(7, (int) $company->settings->days_per_payment);
    }

    public function test_genera_catorce_lavadoras_y_veinte_clientes_con_direccion(): void
    {
        $this->seedPackage();

        $company = (new DemoCompanyBuilder())->build();

        $machines = $company->washingMachines;
        $this->assertCount(14, $machines);
        $this->assertSame(10, $machines->where('status', 'rentada')->count());
        $this->assertSame(2, $machines->where('status', 'disponible')->count());
        $this->assertSame(1, $machines->where('status', 'mantenimiento')->count());
        $this->assertSame(1, $machines->where('status', 'fuera_de_servicio')->count());
        $this->assertSame('LAV-001', $machines->sortBy('machine_code')->first()->machine_code);
        $this->assertTrue($machines->every(fn ($m) => $m->purchase_price > 0));

        $customers = $company->customers;
        $this->assertCount(20, $customers);
        $this->assertTrue($customers->every(fn ($c) => $c->addresses()->exists()));
        $this->assertTrue($customers->every(fn ($c) => filled($c->phone)));
    }

    public function test_genera_rentas_activas_vencidas_y_completadas_con_pagos(): void
    {
        $this->seedPackage();

        $company = (new DemoCompanyBuilder())->build();
        $rentals = $company->rentals;

        $this->assertSame(8, $rentals->where('status', 'activa')->count());
        $this->assertSame(2, $rentals->where('status', 'vencida')->count());
        $this->assertSame(15, $rentals->where('status', 'completada')->count());

        // Hay al menos una renta que vence dentro de los próximos 7 días.
        $this->assertTrue(
            $rentals->where('status', 'activa')->contains(
                fn ($r) => Carbon::parse($r->end_date)->between(now(), now()->addDays(7))
            ),
            'Ninguna renta activa vence esta semana; el calendario se vería vacío.'
        );

        // Las vencidas están realmente atrasadas.
        $this->assertTrue(
            $rentals->where('status', 'vencida')->every(
                fn ($r) => Carbon::parse($r->end_date)->lt(now())
            )
        );

        $payments = Payment::where('company_id', $company->id)->get();
        $this->assertGreaterThan(50, $payments->count());
        $this->assertTrue($payments->every(fn ($p) => $p->status === 'completado'));
        $this->assertTrue($payments->every(fn ($p) => (float) $p->amount === 250.0));

        // Hay historial repartido en al menos 5 meses distintos.
        $months = $payments->map(fn ($p) => Carbon::parse($p->payment_date)->format('Y-m'))->unique();
        $this->assertGreaterThanOrEqual(5, $months->count());
    }

    public function test_genera_mantenimientos_e_incidencias(): void
    {
        $this->seedPackage();

        $company = (new DemoCompanyBuilder())->build();

        $maintenances = $company->maintenances;
        $this->assertCount(4, $maintenances);
        $this->assertTrue($maintenances->every(fn ($m) => $m->cost > 0));
        $this->assertTrue($maintenances->every(
            fn ($m) => in_array($m->maintenance_type, ['preventivo', 'correctivo'], true)
        ));

        $incidents = $company->incidents;
        $this->assertCount(3, $incidents);
        $this->assertEqualsCanonicalizing(
            ['abierta', 'en_progreso', 'cerrada'],
            $incidents->pluck('status')->all()
        );
    }
}
