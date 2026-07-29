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

    public function test_genera_dieciseis_lavadoras_tres_secadoras_y_veinte_clientes(): void
    {
        $this->seedPackage();

        $company = (new DemoCompanyBuilder())->build();

        $machines = $company->washingMachines;
        $this->assertCount(19, $machines);

        $lavadoras = $machines->where('kind', 'lavadora');
        $this->assertCount(16, $lavadoras);
        $this->assertSame(10, $lavadoras->where('status', 'rentada')->count());
        $this->assertSame(1, $lavadoras->where('status', 'fuera_de_servicio')->count());

        // Un equipo extraviado: es de las cosas que de verdad pasan y el demo
        // enseñaba un parque impecable donde nunca se pierde nada.
        $this->assertSame(1, $lavadoras->where('status', 'extraviada')->count());

        // Y uno recién recogido esperando revisión: es el paso entre que vuelve
        // del cliente y que se puede volver a colocar.
        $this->assertSame(1, $lavadoras->where('status', 'en_revision')->count());

        // Disponibles y en mantenimiento no se cuentan a número fijo: el cambio
        // de equipo del demo mueve una de cada, y clavar el número aquí obliga a
        // corregir esta prueba cada vez que se toque aquello.
        $this->assertGreaterThan(0, $lavadoras->where('status', 'disponible')->count());
        $this->assertGreaterThan(0, $lavadoras->where('status', 'mantenimiento')->count());

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
        // 2 morosos + la del equipo extraviado, que se deja abierta a propósito.
        $this->assertSame(3, $rentals->where('status', 'vencida')->count());
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

        $payments = Payment::where('company_id', $company->id)->with('rental')->get();
        $this->assertGreaterThan(50, $payments->count());
        $this->assertTrue($payments->every(fn ($p) => $p->status === 'completado'));

        // Cada cobro aplicado vale lo que su renta: con precios distintos por
        // equipo, un monto fijo dejaría pagos de 250 en una renta pactada en 300.
        //
        // Los abonos quedan fuera a propósito: valen menos que el periodo, que
        // es justamente lo que los hace abonos.
        $this->assertTrue(
            $payments->where('applied', true)
                ->every(fn ($p) => (float) $p->amount === (float) ($p->rental->price ?: 250)),
            'Hay cobros que no coinciden con el precio de su renta.'
        );

        // Hay historial repartido en al menos 5 meses distintos.
        $months = $payments->map(fn ($p) => Carbon::parse($p->payment_date)->format('Y-m'))->unique();
        $this->assertGreaterThanOrEqual(5, $months->count());
    }

    public function test_genera_mantenimientos_e_incidencias(): void
    {
        $this->seedPackage();

        $company = (new DemoCompanyBuilder())->build();

        // Cuatro de ejemplo más la que abre el cambio de equipo al mandar la
        // lavadora anterior al taller.
        $maintenances = $company->maintenances;
        $this->assertCount(5, $maintenances);

        // La del cambio nace sin costo: todavía nadie la revisó.
        $this->assertTrue(
            $maintenances->where('status', '!=', 'programada')->every(fn ($m) => $m->cost > 0)
        );
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

    /** El demo enseña los dos estados: entregas con acuse y entregas pendientes. */
    public function test_el_demo_trae_entregas_registradas_y_pendientes(): void
    {
        $this->seedPackage();

        $activas = (new DemoCompanyBuilder())->build()->rentals->where('status', 'activa');

        $entregadas = $activas->filter(fn ($r) => $r->isDelivered());
        $pendientes = $activas->filter(fn ($r) => $r->needsDelivery());

        $this->assertGreaterThan(0, $entregadas->count(), 'Ninguna entrega registrada.');
        $this->assertGreaterThan(0, $pendientes->count(), 'Ninguna entrega pendiente.');

        // La entrega ocurre después de que arranca la renta, no antes.
        $this->assertTrue(
            $entregadas->every(fn ($r) => $r->delivered_at->gte(\Carbon\Carbon::parse($r->start_date))),
            'Hay entregas registradas antes de que empezara la renta.'
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

    /**
     * El planificador de rutas es de lo que más vende la app, y recibía al
     * prospecto con "todavía no hay clientes ubicados en el mapa": no había
     * absolutamente nada que enseñar.
     */
    public function test_los_clientes_del_demo_estan_ubicados_en_el_mapa(): void
    {
        $this->seedPackage();

        $company = (new DemoCompanyBuilder())->build();

        $direcciones = $company->customers->map(fn ($c) => $c->addresses->first());

        $this->assertTrue(
            $direcciones->every(fn ($d) => $d?->hasCoordinates()),
            'Hay clientes sin coordenadas: el planificador de rutas los ignora.'
        );

        // Repartidos, no encimados: veinte clientes en el mismo punto arman una
        // ruta que no significa nada.
        $this->assertSame(
            20,
            $direcciones->map(fn ($d) => $d->latitude . ',' . $d->longitude)->unique()->count()
        );

        // Y dentro de Los Mochis, no en medio del mar.
        $this->assertTrue($direcciones->every(
            fn ($d) => abs((float) $d->latitude - 25.7933) < 0.05
                && abs((float) $d->longitude + 108.9942) < 0.05
        ));
    }

    /**
     * Con un solo usuario, "Mi equipo" y el corte por persona salían en blanco y
     * los permisos del cobrador —una de las razones de comprar esto— no se veían.
     */
    public function test_el_demo_trae_dueno_y_cobrador_con_cobros_de_los_dos(): void
    {
        $this->seedPackage();

        $company = (new DemoCompanyBuilder())->build();
        $miembros = $company->members;

        $this->assertCount(2, $miembros);

        $dueno = $miembros->first(fn ($u) => $u->hasRole('propietario'));
        $cobrador = $miembros->first(fn ($u) => $u->hasRole('cobrador'));

        $this->assertNotNull($dueno, 'Sin rol de dueño se le esconde media app al visitante.');
        $this->assertNotNull($cobrador);
        $this->assertTrue($miembros->every(fn ($u) => $u->is_demo));

        $porPersona = Payment::where('company_id', $company->id)
            ->selectRaw('collected_by, count(*) as n')
            ->groupBy('collected_by')
            ->pluck('n', 'collected_by');

        $this->assertGreaterThan(0, $porPersona[$dueno->id] ?? 0);
        $this->assertGreaterThan(0, $porPersona[$cobrador->id] ?? 0);
    }

    /** Sin días cerrados, el corte de caja era un formulario en blanco. */
    public function test_el_demo_trae_cortes_de_caja_cerrados_y_uno_con_faltante(): void
    {
        $this->seedPackage();

        $company = (new DemoCompanyBuilder())->build();
        $cortes = \App\Models\CashClosing::where('company_id', $company->id)->get();

        $this->assertGreaterThan(0, $cortes->count());
        $this->assertTrue($cortes->every(fn ($c) => $c->expected_cash > 0));
        $this->assertTrue($cortes->every(fn ($c) => $c->payments_count > 0));

        // Uno sale descuadrado: un demo donde siempre cuadra no le explica a
        // nadie para qué sirve cerrar la caja.
        $this->assertSame(1, $cortes->filter(fn ($c) => $c->falta())->count());
    }

    /**
     * Se cobra en la puerta y la gente paga lo que trae. El demo enseñaba a
     * todo mundo pagando completo, que no es este negocio.
     */
    public function test_el_demo_trae_abonos_que_todavia_no_compran_tiempo(): void
    {
        $this->seedPackage();

        $company = (new DemoCompanyBuilder())->build();

        $abonos = Payment::where('company_id', $company->id)->where('applied', false)->get();

        $this->assertCount(2, $abonos);
        $this->assertTrue($abonos->every(fn ($p) => $p->status === 'completado'));

        // Un abono vale menos que el periodo: si alcanzara, se habría aplicado
        // solo y no quedaría nada pendiente que enseñar.
        $this->assertTrue(
            $abonos->every(fn ($p) => (float) $p->amount < (float) ($p->rental->price ?: 250)),
            'Un "abono" del demo alcanza para el periodo completo.'
        );

        $this->assertTrue(
            $abonos->every(fn ($p) => \App\Support\Abonos::creditFor($p->rental) > 0)
        );
    }

    /**
     * Que la renta sobreviva al cambio de aparato es de lo que más tranquiliza
     * a quien lleva su control en libreta.
     */
    public function test_el_demo_trae_un_cambio_de_equipo_que_conserva_la_renta(): void
    {
        $this->seedPackage();

        $company = (new DemoCompanyBuilder())->build();

        $cambio = \App\Models\RentalMachineChange::whereIn(
            'rental_id',
            $company->rentals()->select('id')
        )->first();

        $this->assertNotNull($cambio, 'Sin un cambio de ejemplo, la pantalla del historial sale vacía.');
        $this->assertNotSame($cambio->from_machine_id, $cambio->to_machine_id);

        $renta = $cambio->rental;
        $this->assertSame($cambio->to_machine_id, $renta->washing_machine_id);
        $this->assertGreaterThan(0, $renta->payments()->count(), 'El cambio le borró los pagos al cliente.');
        $this->assertSame('rentada', $renta->washingMachine->status);
    }

    /** Los papeles y las fotos son la defensa del dueño; sin ejemplo no se ven. */
    public function test_el_demo_trae_papeles_del_cliente_y_fotos_de_entrega(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('Sin GD no se pueden generar las imágenes del demo.');
        }

        $this->seedPackage();
        \Illuminate\Support\Facades\Storage::fake('local');
        \Illuminate\Support\Facades\Storage::fake('privado');

        $company = (new DemoCompanyBuilder())->build();

        $documentos = \App\Models\CustomerDocument::whereIn(
            'customer_id',
            $company->customers()->select('id')
        )->get();

        $this->assertGreaterThan(0, $documentos->count());
        foreach ($documentos as $documento) {
            \Illuminate\Support\Facades\Storage::disk('local')->assertExists($documento->file_path);
        }

        $conFotos = $company->rentals->filter(fn ($r) => filled($r->delivery_photos));
        $this->assertGreaterThan(0, $conFotos->count());

        foreach ($conFotos as $renta) {
            foreach ($renta->delivery_photos as $ruta) {
                \Illuminate\Support\Facades\Storage::disk('privado')->assertExists($ruta);
            }
        }
    }

    /** Con recargo en cero, la pantalla de recargos no dice nada. */
    public function test_el_demo_trae_recargo_configurado(): void
    {
        $this->seedPackage();

        $ajustes = (new DemoCompanyBuilder())->build()->settings;

        $this->assertGreaterThan(0, (float) $ajustes->late_fee_amount);
        $this->assertSame('fijo', $ajustes->late_fee_type);
        $this->assertGreaterThan(0, (int) $ajustes->late_fee_grace_days);
    }
}
