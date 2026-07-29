<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Package;
use App\Models\Rental;
use App\Models\User;
use App\Support\AccountStatement;
use App\Support\Recoleccion;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * El ciclo de verdad del negocio: se renta por semana, el que deja de pagar se
 * queda sin equipo, y ese equipo tiene que volver a colocarse con otro cliente.
 */
class RecogerYVolverAColocarTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $dueno;

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
        $this->dueno->givePermissionTo([
            Permission::findOrCreate('view_any_washing::machine', 'web'),
            Permission::findOrCreate('update_washing::machine', 'web'),
        ]);
        $this->company->members()->attach($this->dueno);
        $this->actingAs($this->dueno);

        Filament::setCurrentPanel(Filament::getPanel('propietario'));
        Filament::setTenant($this->company, true);
    }

    /** Un moroso de tres semanas: dejó de pagar y hay que ir por la lavadora. */
    private function morosoDeTresSemanas(string $codigo = 'LAV-001'): Rental
    {
        $equipo = $this->company->washingMachines()->create([
            'machine_code' => $codigo,
            'kind' => 'lavadora',
            'brand' => 'Mabe',
            'status' => 'rentada',
        ]);

        $cliente = $this->company->customers()->create([
            'name' => 'Jesús Ruiz',
            'phone' => '6681234567',
            'email' => 'jesus' . uniqid() . '@x.com',
        ]);

        return $this->company->rentals()->create([
            'customer_id' => $cliente->id,
            'washing_machine_id' => $equipo->id,
            'start_date' => now()->subWeeks(8)->toDateString(),
            'end_date' => now()->subWeeks(3)->toDateString(),
            'status' => 'vencida',
            'price' => 250,
        ]);
    }

    /**
     * ESTO ES LO QUE ESTABA ROTO.
     *
     * El caso más común de recolección es el del que dejó de pagar. Al recoger,
     * la renta pasaba a "completada" y su end_date se movía a hoy; como el adeudo
     * se deduce de qué tan atrás quedó esa fecha, y el estado de cuenta sólo mira
     * rentas activas o vencidas, el saldo se borraba por los dos lados a la vez.
     *
     * El sistema olvidaba la deuda en el único momento en que de verdad importa
     * acordarse de ella: cuando ese mismo cliente vuelva a pedir una lavadora.
     */
    public function test_recoger_el_equipo_no_borra_lo_que_el_cliente_debia(): void
    {
        $renta = $this->morosoDeTresSemanas();
        $cliente = $renta->customer;

        $debiaAntes = app(AccountStatement::class)->forCustomer($cliente)->total;
        $this->assertGreaterThan(0, $debiaAntes, 'El moroso debía salir debiendo antes de recoger.');

        app(Recoleccion::class)->ejecutar($renta, quedaronEnPaz: false);

        $estado = app(AccountStatement::class)->forCustomer($cliente->fresh());

        $this->assertSame(
            $debiaAntes,
            $estado->total,
            'Al recoger se le borraron $' . number_format($debiaAntes - $estado->total, 2) . ' de adeudo.'
        );

        // Y se distingue del adeudo normal: ya no tiene equipo, así que no hay
        // nada que recogerle. O paga o se le perdona.
        $this->assertTrue($estado->debeDeEquipoRecogido());
        $this->assertSame($debiaAntes, $estado->adeudoDeEquiposRecogidos);
    }

    /** La cifra queda congelada: lo que debía ese día no cambia después. */
    public function test_el_adeudo_queda_congelado_y_no_se_recalcula(): void
    {
        $renta = $this->morosoDeTresSemanas();
        $debia = app(Recoleccion::class)->ejecutar($renta, quedaronEnPaz: false);

        $this->assertSame($debia, (float) $renta->fresh()->debt_at_close);

        // Pasan dos meses. El adeudo de un equipo que ya no tiene no crece.
        $this->travel(60)->days();

        $this->assertSame(
            $debia,
            app(AccountStatement::class)->forCustomer($renta->customer->fresh())->total,
            'El adeudo congelado siguió creciendo como si todavía tuviera la lavadora.'
        );
    }

    /** Y si quedaron en paz, se le perdona y desaparece de su cuenta. */
    public function test_si_quedaron_en_paz_el_adeudo_se_perdona(): void
    {
        $renta = $this->morosoDeTresSemanas();

        app(Recoleccion::class)->ejecutar($renta, quedaronEnPaz: true);

        $estado = app(AccountStatement::class)->forCustomer($renta->customer->fresh());

        $this->assertSame(0.0, $estado->total);
        $this->assertFalse($estado->debeDeEquipoRecogido());
        $this->assertTrue((bool) $renta->fresh()->debt_settled, 'No quedó escrito que se le perdonó.');
    }

    /** Al que estaba al corriente no se le inventa un adeudo al recogerle. */
    public function test_al_cliente_al_corriente_no_se_le_congela_nada(): void
    {
        $renta = $this->morosoDeTresSemanas('LAV-002');
        $renta->update(['end_date' => now()->addDays(5)->toDateString(), 'status' => 'activa']);

        app(Recoleccion::class)->ejecutar($renta->fresh(), quedaronEnPaz: false);

        $this->assertSame(0.0, (float) $renta->fresh()->debt_at_close);
        $this->assertSame(0.0, app(AccountStatement::class)->forCustomer($renta->customer->fresh())->total);
    }

    /**
     * El equipo recogido NO vuelve directo a disponible.
     *
     * Regresa sucia, con la manguera mordida o sin la tapa, y sin este paso eso
     * se descubría en la puerta del cliente siguiente.
     */
    public function test_el_equipo_recogido_queda_en_revision_y_no_disponible(): void
    {
        $renta = $this->morosoDeTresSemanas();

        app(Recoleccion::class)->ejecutar($renta, quedaronEnPaz: false);

        $equipo = $renta->washingMachine->fresh();

        $this->assertSame('en_revision', $equipo->status);
        $this->assertSame('completada', $renta->fresh()->status);

        // Y en revisión no se puede volver a rentar todavía.
        $disponibles = $this->company->washingMachines()->where('status', 'disponible')->count();
        $this->assertSame(0, $disponibles, 'Un equipo sin revisar ya aparece para rentar.');
    }

    /** Hasta que se marca lista, y entonces sí vuelve a la calle. */
    public function test_marcarla_lista_la_devuelve_a_disponible(): void
    {
        $renta = $this->morosoDeTresSemanas();
        app(Recoleccion::class)->ejecutar($renta, quedaronEnPaz: false);

        $equipo = $renta->washingMachine->fresh();
        $equipo->update(['status' => 'disponible']);

        $this->assertSame('disponible', $equipo->fresh()->status);
        $this->assertSame(1, $this->company->washingMachines()->where('status', 'disponible')->count());
    }

    /** Quien debe de un equipo ya recogido sigue saliendo en la cobranza. */
    public function test_el_que_quedo_a_deber_sigue_en_la_cobranza_de_la_empresa(): void
    {
        $renta = $this->morosoDeTresSemanas();
        $debia = app(Recoleccion::class)->ejecutar($renta, quedaronEnPaz: false);

        $cobranza = app(AccountStatement::class)->forCompany($this->company);

        $this->assertCount(1, $cobranza, 'Desapareció de la cobranza al recogerle el equipo.');
        $this->assertSame($debia, $cobranza->first()->total);
        $this->assertSame(
            $debia,
            app(AccountStatement::class)->totalForCompany($this->company)
        );
    }

    /**
     * LA COLA DE RECOLECCIÓN.
     *
     * El sistema trataba igual al de tres días y al del mes: los dos caían en
     * Avisos con el mismo recordatorio de WhatsApp. Pero no son la misma
     * conversación, y por eso un equipo se quedaba meses allá.
     */
    public function test_el_que_lleva_dos_periodos_sin_pagar_entra_a_la_cola_de_recoger(): void
    {
        $moroso = $this->morosoDeTresSemanas('LAV-010');

        $cola = \App\Support\ParaRecoger::for($this->company);

        $this->assertTrue($cola->hay());
        $this->assertTrue($cola->rentas->contains('id', $moroso->id));
        $this->assertSame(2, $cola->periodosDeTolerancia);
    }

    /** El de una semana todavía es de avisarle, no de ir por ella. */
    public function test_el_de_un_solo_periodo_no_entra_a_la_cola(): void
    {
        $renta = $this->morosoDeTresSemanas('LAV-011');
        $renta->update(['end_date' => now()->subDays(5)->toDateString()]);

        $this->assertFalse(
            \App\Support\ParaRecoger::for($this->company)->rentas->contains('id', $renta->id),
            'A un atraso de cinco días ya le está diciendo que vaya por la lavadora.'
        );
    }

    /** En cero se apaga: hay rentadores que no quieren esa lista. */
    public function test_en_cero_la_cola_se_apaga(): void
    {
        $this->morosoDeTresSemanas('LAV-012');
        $this->company->settings->update(['periodos_para_recoger' => 0]);

        $this->assertFalse(\App\Support\ParaRecoger::for($this->company->fresh())->hay());
    }

    /**
     * Y para recoger NO se exige teléfono: no hace falta poder mandarle un
     * mensaje, hace falta saber dónde vive. Los avisos sí lo exigen.
     */
    public function test_el_cliente_sin_telefono_si_entra_a_la_cola_de_recoger(): void
    {
        $renta = $this->morosoDeTresSemanas('LAV-013');
        $renta->customer->update(['phone' => null]);

        $this->assertTrue(
            \App\Support\ParaRecoger::for($this->company)->rentas->contains('id', $renta->id)
        );
    }

    /**
     * El extraviado no entra: el cliente se mudó con él y eso ya se sabe.
     * Decirle "ve por esa lavadora" ensucia una lista cuyo valor es que todo lo
     * que sale sí se puede ir a recoger hoy.
     */
    public function test_el_equipo_extraviado_no_entra_a_la_cola_de_recoger(): void
    {
        $renta = $this->morosoDeTresSemanas('LAV-016');
        $renta->washingMachine->update(['status' => 'extraviada']);

        $this->assertFalse(
            \App\Support\ParaRecoger::for($this->company)->rentas->contains('id', $renta->id)
        );
    }

    /** Lo que se está dejando de ganar cada periodo con esos equipos allá. */
    public function test_la_cola_dice_cuanta_renta_esta_detenida(): void
    {
        $this->morosoDeTresSemanas('LAV-014');
        $this->morosoDeTresSemanas('LAV-015');

        $this->assertSame(500.0, \App\Support\ParaRecoger::for($this->company)->rentaDetenidaPorPeriodo());
    }

    /**
     * EL EQUIPO PARADO.
     *
     * Una lavadora libre no avisa: no manda notificaciones y no le duele a nadie
     * hasta que la cuenta del mes no cuadra.
     */
    public function test_dice_cuantos_dias_lleva_parada_cada_libre(): void
    {
        $renta = $this->morosoDeTresSemanas('LAV-020');

        // Se recogió hace 40 días y ya se revisó.
        $this->travelTo(now()->subDays(40));
        app(Recoleccion::class)->ejecutar($renta, quedaronEnPaz: true);
        $renta->washingMachine->update(['status' => 'disponible']);
        $this->travelBack();

        $parado = \App\Support\EquipoParado::for($this->company->fresh());

        $this->assertTrue($parado->hay());
        $this->assertSame(40, $parado->diasDelPeor());
        $this->assertCount(1, $parado->olvidados(), 'Cuarenta días parada y no sale como olvidada.');
    }

    /** El que nunca se ha rentado cuenta desde que se dio de alta. */
    public function test_el_equipo_que_nunca_se_ha_rentado_tambien_cuenta(): void
    {
        $equipo = $this->company->washingMachines()->create([
            'machine_code' => 'LAV-030',
            'kind' => 'lavadora',
            'status' => 'disponible',
            'purchase_date' => now()->subDays(90)->toDateString(),
        ]);

        $parado = \App\Support\EquipoParado::for($this->company);
        $fila = $parado->equipos->firstWhere('equipo.id', $equipo->id);

        $this->assertNotNull($fila);
        $this->assertTrue($fila->nuncaRentado);
        $this->assertSame(90, $fila->dias);
    }

    /** El que está rentado no cuenta como parado: está trabajando. */
    public function test_el_equipo_rentado_no_cuenta_como_parado(): void
    {
        $renta = $this->morosoDeTresSemanas('LAV-040');

        $this->assertFalse(
            \App\Support\EquipoParado::for($this->company)->equipos
                ->contains(fn ($fila) => $fila->equipo->id === $renta->washing_machine_id)
        );
    }

    /** El que está en revisión sí: todavía no genera nada. */
    public function test_el_equipo_en_revision_cuenta_como_parado(): void
    {
        $renta = $this->morosoDeTresSemanas('LAV-050');
        app(Recoleccion::class)->ejecutar($renta, quedaronEnPaz: true);

        $fila = \App\Support\EquipoParado::for($this->company)->equipos
            ->firstWhere('equipo.id', $renta->washing_machine_id);

        $this->assertNotNull($fila);
        $this->assertTrue($fila->enRevision);
    }

    /** Al que debe se le avisa desde el escritorio, no hay que ir a buscarlo. */
    public function test_los_pendientes_del_dia_avisan_de_la_recoleccion(): void
    {
        $this->morosoDeTresSemanas('LAV-060');

        $claves = collect(\App\Support\PendientesDelDia::for($this->company)->pendientes)
            ->pluck('clave');

        $this->assertTrue($claves->contains('recoger'), 'Nadie avisa de que ya toca ir por el equipo.');
    }

    /** Al perdonado sí se le saca de la cobranza, que es lo que se decidió. */
    public function test_el_perdonado_sale_de_la_cobranza(): void
    {
        $renta = $this->morosoDeTresSemanas();

        app(Recoleccion::class)->ejecutar($renta, quedaronEnPaz: true);

        $this->assertCount(0, app(AccountStatement::class)->forCompany($this->company));
    }
}
