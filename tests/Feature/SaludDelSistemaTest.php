<?php

namespace Tests\Feature;

use App\Console\Commands\RevisarDatos;
use App\Console\Commands\RevisarTareas;
use App\Models\Company;
use App\Models\Package;
use App\Models\User;
use App\Notifications\DatosIncoherentesNotification;
use App\Notifications\TareaFallidaNotification;
use App\Support\LatidoDeTareas;
use App\Support\Onboarding;
use App\Support\RevisionDeDatos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Que se sepa cuándo el sistema dejó de trabajar.
 *
 * El script de limpieza de temporales falló 48 semanas seguidas por un salto de
 * línea en su primera línea, y nadie se enteró. Eso es lo que esto viene a evitar.
 */
class SaludDelSistemaTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $admin;

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

        $this->admin = User::create(['name' => 'Admin', 'email' => 'a@x.com', 'password' => bcrypt('s')]);
        $this->admin->assignRole(Role::findOrCreate('super_admin', 'web'));
    }

    // --- El latido de las tareas ---

    /** Lo normal: corrió, quedó anotado, y nadie molesta a nadie. */
    public function test_una_tarea_que_corre_bien_no_genera_aviso(): void
    {
        Notification::fake();

        LatidoDeTareas::registrar('rentals:mark-overdue');

        $this->assertFalse(LatidoDeTareas::hayProblema());

        $this->artisan(RevisarTareas::class)->assertSuccessful();
        Notification::assertNothingSent();
    }

    /** Si truena, se avisa en el momento y por correo, no sólo por campana. */
    public function test_una_tarea_que_truena_avisa_de_inmediato(): void
    {
        Notification::fake();

        LatidoDeTareas::registrarFallo('rentals:mark-overdue', 'Se murió la base.');

        Notification::assertSentTo($this->admin, TareaFallidaNotification::class);

        $canales = [];
        Notification::assertSentTo($this->admin, TareaFallidaNotification::class,
            function ($notificacion, $via) use (&$canales) {
                $canales = $via;

                return true;
            });

        $this->assertContains('mail', $canales, 'La campana sola no sirve: puede pasar días sin que nadie entre.');
        $this->assertContains('database', $canales);

        $this->assertTrue(LatidoDeTareas::hayProblema());
    }

    /**
     * ESTE ES EL CASO QUE MUERDE.
     *
     * Una tarea que deja de correr no truena: simplemente no pasa nada, y no hay
     * excepción que atrapar. Sólo se detecta por ausencia.
     */
    public function test_una_tarea_que_dejo_de_correr_se_detecta_por_ausencia(): void
    {
        Notification::fake();

        // Corrió bien, hace tres días. Es diaria.
        LatidoDeTareas::registrar('rentals:mark-overdue');

        \Illuminate\Support\Facades\DB::table('task_runs')
            ->where('tarea', 'rentals:mark-overdue')
            ->update(['corrio_en' => now()->subDays(3)]);

        $problema = LatidoDeTareas::conProblema();

        $this->assertCount(1, $problema);
        $this->assertSame('rentals:mark-overdue', $problema->first()->tarea);
        $this->assertTrue($problema->first()->perdida);
        $this->assertTrue($problema->first()->ok, 'No falló: dejó de correr, que es distinto.');

        $this->artisan(RevisarTareas::class)->assertSuccessful();
        Notification::assertSentTo($this->admin, TareaFallidaNotification::class);
    }

    /**
     * Con holgura: una diaria que corrió hace 25 horas no es una falla. Avisar por
     * eso enseña a ignorar los avisos.
     */
    public function test_una_demora_corta_no_cuenta_como_falla(): void
    {
        LatidoDeTareas::registrar('rentals:mark-overdue');

        \Illuminate\Support\Facades\DB::table('task_runs')
            ->where('tarea', 'rentals:mark-overdue')
            ->update(['corrio_en' => now()->subHours(25)]);

        $this->assertFalse(
            LatidoDeTareas::hayProblema(),
            'Una diaria con 25 horas encima ya se marca como perdida: eso es demasiado sensible.'
        );
    }

    /**
     * Nunca vista no es lo mismo que perdida: puede que se acabe de desplegar la
     * vigilancia y todavía no le toque correr.
     */
    public function test_una_tarea_sin_datos_todavia_no_es_una_falla(): void
    {
        $estado = LatidoDeTareas::estado();

        $this->assertTrue($estado->every(fn ($t) => $t->nuncaVista));
        $this->assertFalse(LatidoDeTareas::hayProblema());
    }

    /** Si se cae el scheduler completo, va un solo aviso y no seis idénticos. */
    public function test_varias_tareas_caidas_generan_un_solo_aviso(): void
    {
        Notification::fake();

        foreach (['rentals:mark-overdue', 'rentals:send-reminders', 'demo:cleanup'] as $tarea) {
            LatidoDeTareas::registrar($tarea);
        }

        \Illuminate\Support\Facades\DB::table('task_runs')->update(['corrio_en' => now()->subDays(5)]);

        $this->assertCount(3, LatidoDeTareas::conProblema());

        $this->artisan(RevisarTareas::class)->assertSuccessful();

        Notification::assertSentToTimes($this->admin, TareaFallidaNotification::class, 1);
    }

    /** El historial viejo se limpia, pero se guarda lo suficiente para ver patrones. */
    public function test_el_historial_viejo_se_limpia(): void
    {
        LatidoDeTareas::registrar('demo:cleanup');

        \Illuminate\Support\Facades\DB::table('task_runs')->update(['corrio_en' => now()->subDays(60)]);

        $this->assertSame(1, LatidoDeTareas::limpiarHistorial());
        $this->assertNull(LatidoDeTareas::ultima('demo:cleanup'));
    }

    /**
     * Cada tarea vigilada existe de verdad en el calendario y trae su latido.
     *
     * Es lo que evita el peor final posible de todo esto: una lista de vigiladas
     * que se desincroniza del calendario y deja de mirar justo la tarea que se
     * cayó, dando la impresión de que todo está bien.
     *
     * Los callbacks del Event son protegidos, así que se leen por reflexión: es
     * eso o comprobar el archivo con una expresión regular, que pasaría igual si
     * alguien borra el onSuccess y deja la llamada.
     */
    public function test_cada_tarea_vigilada_existe_en_el_calendario_y_trae_su_latido(): void
    {
        $eventos = app(\Illuminate\Console\Scheduling\Schedule::class)->events();

        $conLatido = collect($eventos)
            ->filter(function ($evento) {
                $propiedad = new \ReflectionProperty($evento, 'afterCallbacks');
                $propiedad->setAccessible(true);

                return $propiedad->getValue($evento) !== [];
            })
            ->map(fn ($e) => preg_replace("/.*artisan['\"]? ([a-z0-9:_-]+).*/i", '$1', (string) $e->command))
            ->values();

        $enElCalendario = collect($eventos)
            ->map(fn ($e) => preg_replace("/.*artisan['\"]? ([a-z0-9:_-]+).*/i", '$1', (string) $e->command))
            ->values();

        foreach (array_keys(LatidoDeTareas::ESPERADAS) as $tarea) {
            $this->assertTrue(
                $enElCalendario->contains($tarea),
                "Se vigila «{$tarea}» y no está en el calendario: nunca va a correr y se va a reportar como caída para siempre."
            );
            $this->assertTrue(
                $conLatido->contains($tarea),
                "La tarea «{$tarea}» está en la lista de vigiladas y el scheduler no le registra el latido."
            );
        }
    }

    /** Y las dos vigilantes también están programadas. */
    public function test_las_vigilantes_estan_programadas(): void
    {
        $comandos = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->map(fn ($e) => (string) $e->command)
            ->implode(' ');

        $this->assertStringContainsString('tareas:revisar', $comandos);
        $this->assertStringContainsString('datos:revisar', $comandos);
    }

    // --- La coherencia de los datos ---

    private function equipo(string $codigo, string $estado)
    {
        return $this->company->washingMachines()->create([
            'machine_code' => $codigo,
            'kind' => 'lavadora',
            'brand' => 'Mabe',
            'status' => $estado,
        ]);
    }

    /** Marcado como rentado sin renta: ese aparato no aparece para rentar. */
    public function test_detecta_el_equipo_rentado_sin_renta(): void
    {
        $this->equipo('LAV-001', 'rentada');

        $revision = RevisionDeDatos::for($this->company);

        $this->assertTrue($revision->hay());
        $this->assertSame('rentada-sin-renta', $revision->hallazgos->first()->tipo);
        $this->assertSame('LAV-001', $revision->hallazgos->first()->equipo);
    }

    /** En mantenimiento sin orden abierta: pasó dos veces en producción. */
    public function test_detecta_el_equipo_parado_sin_orden(): void
    {
        $this->equipo('LAV-002', 'mantenimiento');

        $tipos = RevisionDeDatos::for($this->company)->hallazgos->pluck('tipo');

        $this->assertTrue($tipos->contains('parada-sin-orden'));
    }

    /** Con su orden abierta no molesta: ahí sí está justificado. */
    public function test_el_equipo_con_su_orden_abierta_no_se_reporta(): void
    {
        $equipo = $this->equipo('LAV-003', 'mantenimiento');

        $this->company->maintenances()->create([
            'washing_machine_id' => $equipo->id,
            'technician_name' => 'Luis Herrera',
            'start_date' => now()->toDateString(),
            'maintenance_type' => 'correctivo',
            'status' => 'en_progreso',
            'description' => 'En taller.',
        ]);

        $this->assertFalse(RevisionDeDatos::for($this->company)->hay());
    }

    /** Revisar un aparato es cosa de un rato: una semana ahí es un olvido. */
    public function test_detecta_el_equipo_olvidado_en_revision(): void
    {
        $equipo = $this->equipo('LAV-004', 'en_revision');
        $equipo->forceFill(['updated_at' => now()->subDays(10)])->save();

        $tipos = RevisionDeDatos::for($this->company)->hallazgos->pluck('tipo');

        $this->assertTrue($tipos->contains('olvidada-en-revision'));
    }

    /** El recién recogido no: todavía le toca su revisión. */
    public function test_el_recien_recogido_no_se_reporta(): void
    {
        $this->equipo('LAV-005', 'en_revision');

        $this->assertFalse(RevisionDeDatos::for($this->company)->hay());
    }

    /**
     * El más caro de todos: renta abierta con el equipo marcado libre. Así se le
     * puede rentar el mismo aparato a un segundo cliente.
     */
    public function test_detecta_la_renta_abierta_con_el_equipo_libre(): void
    {
        $equipo = $this->equipo('LAV-006', 'disponible');

        $cliente = $this->company->customers()->create([
            'name' => 'Ana', 'phone' => '1', 'email' => 'ana@x.com',
        ]);

        $this->company->rentals()->create([
            'customer_id' => $cliente->id,
            'washing_machine_id' => $equipo->id,
            'start_date' => now()->subWeek()->toDateString(),
            'end_date' => now()->addWeek()->toDateString(),
            'status' => 'activa',
        ]);

        $tipos = RevisionDeDatos::for($this->company)->hallazgos->pluck('tipo');

        $this->assertTrue($tipos->contains('renta-con-equipo-libre'));
    }

    /** Las demos no cuentan: se borran solas y sus datos son de mentira. */
    public function test_las_demos_no_entran_a_la_revision(): void
    {
        $demo = Company::create([
            'name' => 'Demo', 'phone' => '2', 'email' => 'd@x.com',
            'is_demo' => true, 'demo_expires_at' => now()->addHours(5),
        ]);

        $demo->washingMachines()->create([
            'machine_code' => 'LAV-999', 'kind' => 'lavadora', 'status' => 'rentada',
        ]);

        $this->assertFalse(RevisionDeDatos::todasLasCuentas()->contains(
            fn (RevisionDeDatos $r) => $r->empresa->id === $demo->id
        ));
    }

    /** Y avisa a quien opera la plataforma, para saber si es de uno o de todos. */
    public function test_el_comando_avisa_de_los_datos_incoherentes(): void
    {
        Notification::fake();

        $this->equipo('LAV-007', 'rentada');

        $this->artisan(RevisarDatos::class)->assertSuccessful();

        Notification::assertSentTo($this->admin, DatosIncoherentesNotification::class);
    }

    /** Sin nada que reportar no manda nada: un aviso diario vacío se ignora. */
    public function test_sin_incoherencias_no_manda_nada(): void
    {
        Notification::fake();

        $this->artisan(RevisarDatos::class)->assertSuccessful();

        Notification::assertNothingSent();
    }

    // --- El quinto paso del onboarding ---

    /**
     * LA PARED REAL DEL PRODUCTO.
     *
     * La lista terminaba en "registra tu primera renta" y ahí se daba por
     * completa. Pero de las 6 cuentas que cargaron equipo, 5 nunca registraron un
     * cobro. Y como el recuadro se esconde al completarse, la cuenta que más
     * necesitaba el empujón era justo la que se quedaba sin nada.
     */
    public function test_el_onboarding_no_se_da_por_completo_sin_el_primer_cobro(): void
    {
        $equipo = $this->equipo('LAV-100', 'rentada');

        $cliente = $this->company->customers()->create([
            'name' => 'Ana', 'phone' => '1', 'email' => 'ana2@x.com',
        ]);

        $renta = $this->company->rentals()->create([
            'customer_id' => $cliente->id,
            'washing_machine_id' => $equipo->id,
            'start_date' => now()->subWeek()->toDateString(),
            'end_date' => now()->addWeek()->toDateString(),
            'status' => 'activa',
        ]);

        $onboarding = Onboarding::for($this->company->fresh());

        $this->assertFalse(
            $onboarding->isComplete(),
            'Se da por arrancada una cuenta que nunca ha cobrado, y ahí es donde se caen.'
        );
        $this->assertTrue($onboarding->faltaElPrimerCobro());
        $this->assertSame('cobro', $onboarding->siguiente()['clave']);

        // Con el primer cobro sí queda completa.
        $renta->payments()->create([
            'company_id' => $this->company->id,
            'amount' => 250,
            'payment_date' => today()->toDateString(),
            'payment_method' => 'Efectivo',
            'status' => 'completado',
        ]);

        $completo = Onboarding::for($this->company->fresh());

        $this->assertTrue($completo->isComplete());
        $this->assertFalse($completo->faltaElPrimerCobro());
    }

    /** Y el recuadro del escritorio sigue visible mientras falte ese paso. */
    public function test_el_recuadro_de_arranque_sigue_visible_hasta_el_primer_cobro(): void
    {
        $equipo = $this->equipo('LAV-101', 'rentada');

        $cliente = $this->company->customers()->create([
            'name' => 'Ana', 'phone' => '1', 'email' => 'ana3@x.com',
        ]);

        $this->company->rentals()->create([
            'customer_id' => $cliente->id,
            'washing_machine_id' => $equipo->id,
            'start_date' => now()->subWeek()->toDateString(),
            'end_date' => now()->addWeek()->toDateString(),
            'status' => 'activa',
        ]);

        $this->actingAs($this->admin);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('propietario'));
        \Filament\Facades\Filament::setTenant($this->company->fresh(), true);

        $this->assertTrue(\App\Filament\Widgets\OnboardingWidget::canView());
    }

    /** Y el paso lleva a algún lado real. */
    public function test_el_paso_del_cobro_lleva_a_una_pantalla(): void
    {
        $this->actingAs($this->admin);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('propietario'));
        \Filament\Facades\Filament::setTenant($this->company, true);

        $url = (new \App\Filament\Widgets\OnboardingWidget())->urlFor('cobro');

        $this->assertStringContainsString('/propietario/', $url);
    }
}
