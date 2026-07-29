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

    public function test_genera_catorce_lavadoras_tres_secadoras_y_veinte_clientes(): void
    {
        $this->seedPackage();

        $company = (new DemoCompanyBuilder())->build();

        $machines = $company->washingMachines;
        $this->assertCount(17, $machines);

        $lavadoras = $machines->where('kind', 'lavadora');
        $this->assertCount(14, $lavadoras);
        $this->assertSame(10, $lavadoras->where('status', 'rentada')->count());
        $this->assertSame(2, $lavadoras->where('status', 'disponible')->count());
        $this->assertSame(1, $lavadoras->where('status', 'mantenimiento')->count());
        $this->assertSame(1, $lavadoras->where('status', 'fuera_de_servicio')->count());

        // El negocio también renta secadoras: sin ellas en el demo, el prospecto
        // que las renta no ve que la app las contempla.
        $secadoras = $machines->where('kind', 'secadora');
        $this->assertCount(3, $secadoras);
        $this->assertSame(2, $secadoras->where('status', 'rentada')->count());

        $this->assertSame('LAV-001', $machines->sortBy('machine_code')->first()->machine_code);
        $this->assertTrue($machines->every(fn ($m) => $m->purchase_price > 0));

        $customers = $company->customers;
        $this->assertCount(20, $customers);
        $this->assertTrue($customers->every(fn ($c) => $c->addresses()->exists()));
        $this->assertTrue($customers->every(fn ($c) => filled($c->phone)));
    }

    /**
     * Una máquina marcada como rentada sin renta que la respalde descuadra la
     * ocupación y el desglose por tipo diría "0/3 secadoras".
     */
    public function test_toda_maquina_rentada_del_demo_tiene_su_renta(): void
    {
        $this->seedPackage();

        $company = (new DemoCompanyBuilder())->build();

        foreach ($company->washingMachines->where('status', 'rentada') as $maquina) {
            $this->assertNotNull(
                $maquina->activeRental,
                "{$maquina->machine_code} figura como rentada y no tiene renta activa."
            );
        }
    }

    public function test_genera_rentas_activas_vencidas_y_completadas_con_pagos(): void
    {
        $this->seedPackage();

        $company = (new DemoCompanyBuilder())->build();
        $rentals = $company->rentals;

        // 8 de lavadora + 2 de secadora.
        $this->assertSame(10, $rentals->where('status', 'activa')->count());
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

    /**
     * Sin gastos, el escritorio del demo presumía un margen del 93%, que a
     * cualquier rentador le suena a cuento.
     */
    public function test_el_demo_trae_gastos_del_mes_en_curso(): void
    {
        $this->seedPackage();

        $company = (new DemoCompanyBuilder())->build();
        $utilidad = \App\Support\Utilidad::delMes($company);

        $this->assertGreaterThan(0, $utilidad->gastos);
        $this->assertFalse(
            $utilidad->gananciaInflada(),
            'El demo enseña una ganancia sin gastos, que es justo lo que la función viene a arreglar.'
        );

        // Los gastos salen de lo cobrado en el mes, así que el margen cae en la
        // misma banda cualquier día: con cifras fijas, el día 3 habría enseñado
        // pérdida y el día 28 un margen del 90%.
        $this->assertGreaterThan(20, $utilidad->margen());
        $this->assertLessThan(60, $utilidad->margen());
    }

    /** Se arma el demo como si fuera el día 2 del mes, que es el caso feo. */
    public function test_el_margen_del_demo_aguanta_a_principios_de_mes(): void
    {
        $this->seedPackage();

        \Carbon\Carbon::setTestNow(now()->startOfMonth()->addDay()->setTime(10, 0));

        try {
            $utilidad = \App\Support\Utilidad::delMes((new DemoCompanyBuilder())->build());

            $this->assertFalse(
                $utilidad->pierde(),
                'El demo enseña pérdida a principios de mes.'
            );
        } finally {
            \Carbon\Carbon::setTestNow();
        }
    }

    /**
     * La geografía se tomaba con State::first(), que con la tabla sembrada
     * devuelve Aguascalientes: el demo enseñaba direcciones de "Los Mochis,
     * Aguascalientes", que además no las encuentra ningún mapa.
     */
    public function test_las_direcciones_del_demo_son_de_un_lugar_que_existe(): void
    {
        $this->seedPackage();

        // Aguascalientes primero, que es justo el orden que causaba el problema.
        $pais = \App\Models\Country::create(['nombre' => 'México']);
        \App\Models\State::forceCreate(['nombre' => 'Aguascalientes', 'pais_id' => $pais->id]);
        \App\Models\State::forceCreate(['nombre' => 'Sinaloa', 'pais_id' => $pais->id]);

        $company = (new DemoCompanyBuilder())->build();

        $direccion = $company->customers->first()->addresses->first();

        $this->assertSame('Los Mochis', $direccion->city);
        $this->assertSame('Sinaloa', $direccion->state->nombre);
    }

    /**
     * El reporte cerrado se creaba hoy y se marcaba resuelto cuatro días antes,
     * así que el demo le enseñaba al prospecto "-4 días para resolver".
     */
    public function test_la_incidencia_cerrada_se_abre_antes_de_resolverse(): void
    {
        $this->seedPackage();

        $company = (new DemoCompanyBuilder())->build();

        $cerrada = $company->incidents->firstWhere('status', 'cerrada');

        $this->assertNotNull($cerrada->resolved_at);
        // resolved_at no trae cast en el modelo, así que llega como cadena.
        $this->assertTrue(
            Carbon::parse($cerrada->resolved_at)->gte($cerrada->created_at),
            'La incidencia quedó resuelta antes de haberse abierto.'
        );

        // Y las abiertas no traen fecha de resolución.
        $this->assertTrue(
            $company->incidents->whereIn('status', ['abierta', 'en_progreso'])
                ->every(fn ($i) => $i->resolved_at === null)
        );
    }
}
