<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Rental;
use App\Models\User;
use App\Models\WashingMachine;
use App\Support\AccountStatement;
use App\Support\AvisosDelDia;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Recargos por atraso y avisos del día.
 *
 * El sistema sabía al día cuánto llevaba atrasado cada quien, pero atrasarse
 * salía gratis. Y el botón de WhatsApp obligaba a buscar cliente por cliente.
 *
 * Lo que más importa aquí es la NO regresión: con el recargo en cero, el adeudo
 * de las 160 rentas que ya existen tiene que salir idéntico al de siempre.
 */
class RecargosYAvisosTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
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

        $user = User::create(['name' => 'Dueño', 'email' => 'd@x.com', 'password' => bcrypt('s')]);
        $user->assignRole(Role::findOrCreate('propietario', 'web'));
        $this->company->members()->attach($user);
        $this->actingAs($user);

        Filament::setCurrentPanel(Filament::getPanel('propietario'));
        Filament::setTenant($this->company, true);

        $this->cliente = $this->company->customers()->create([
            'name' => 'Juan Pérez', 'email' => 'c@x.mx', 'phone' => '6681234567',
        ]);
    }

    private function equipo(string $kind = 'lavadora'): WashingMachine
    {
        return $this->company->washingMachines()->create([
            'machine_code' => 'LAV-' . fake()->unique()->numberBetween(100, 999),
            'brand' => 'Mabe', 'kind' => $kind, 'status' => 'rentada',
        ]);
    }

    private function renta(int $diasVencida, array $extra = []): Rental
    {
        return $this->company->rentals()->create(array_merge([
            'customer_id' => $this->cliente->id,
            'washing_machine_id' => $this->equipo()->id,
            'start_date' => now()->subMonths(3)->toDateString(),
            'end_date' => now()->subDays($diasVencida)->toDateString(),
            'status' => $diasVencida > 0 ? 'vencida' : 'activa',
        ], $extra));
    }

    private function recargo(float $monto, string $tipo = 'fijo', int $gracia = 0): void
    {
        $this->company->settings->update([
            'late_fee_amount' => $monto,
            'late_fee_type' => $tipo,
            'late_fee_grace_days' => $gracia,
        ]);
        $this->company->refresh();
    }

    private function deuda(Rental $renta): \App\Support\RentalDebt
    {
        return app(AccountStatement::class)->forRental($renta->fresh(), $this->company->fresh()->settings);
    }

    // --- Recargos ---

    /**
     * LA prueba que importa: sin configurar recargo, el adeudo sale exactamente
     * como salía antes de que esta función existiera.
     */
    public function test_sin_recargo_configurado_el_adeudo_no_cambia(): void
    {
        $deuda = $this->deuda($this->renta(14));

        $this->assertSame(500.0, $deuda->amount, '14 días vencida, periodo de 7, precio 250.');
        $this->assertSame(0.0, $deuda->lateFee);
        $this->assertFalse($deuda->hasLateFee());
    }

    public function test_un_recargo_fijo_se_cobra_por_periodo_vencido(): void
    {
        $this->recargo(50);

        $deuda = $this->deuda($this->renta(14));

        // 2 periodos × 250 de renta + 2 × 50 de recargo.
        $this->assertSame(100.0, $deuda->lateFee);
        $this->assertSame(600.0, $deuda->amount);
        $this->assertSame(500.0, $deuda->rentOnly());
    }

    public function test_un_recargo_en_porcentaje_se_calcula_sobre_lo_vencido(): void
    {
        $this->recargo(10, 'porcentaje');

        $deuda = $this->deuda($this->renta(14));

        // 10% de los 500 vencidos.
        $this->assertSame(50.0, $deuda->lateFee);
        $this->assertSame(550.0, $deuda->amount);
    }

    /** Quien paga dos días tarde no debería llevarse un recargo. */
    public function test_los_dias_de_gracia_perdonan_el_atraso_corto(): void
    {
        $this->recargo(50, 'fijo', gracia: 3);

        $this->assertSame(0.0, $this->deuda($this->renta(2))->lateFee);
        // Pasada la gracia, el recargo entra completo.
        $this->assertSame(50.0, $this->deuda($this->renta(5))->lateFee);
    }

    /** Un recargo sobre un precio propio usa ese precio, no el de la empresa. */
    public function test_el_porcentaje_respeta_el_precio_de_la_renta(): void
    {
        $this->recargo(10, 'porcentaje');

        $deuda = $this->deuda($this->renta(7, ['price' => 400]));

        $this->assertSame(40.0, $deuda->lateFee);
        $this->assertSame(440.0, $deuda->amount);
    }

    public function test_una_renta_al_corriente_no_lleva_recargo(): void
    {
        $this->recargo(50);

        $renta = $this->company->rentals()->create([
            'customer_id' => $this->cliente->id,
            'washing_machine_id' => $this->equipo()->id,
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'status' => 'activa',
        ]);

        $this->assertSame(0.0, $this->deuda($renta)->amount);
        $this->assertSame(0.0, $this->deuda($renta)->lateFee);
    }

    /** El recargo entra al total del estado de cuenta, no queda colgando. */
    public function test_el_recargo_se_suma_al_total_del_cliente(): void
    {
        $this->recargo(50);
        $this->renta(14);

        $estado = app(AccountStatement::class)->forCustomer($this->cliente->fresh());

        $this->assertSame(600.0, $estado->total);
    }

    // --- Avisos ---

    public function test_avisa_de_los_vencidos_y_de_los_que_vencen_pronto(): void
    {
        $this->renta(6);                                      // vencido
        $this->renta(-2);                                     // vence en 2 días
        $this->renta(-30);                                    // todavía lejos

        $avisos = AvisosDelDia::for($this->company);

        $this->assertCount(2, $avisos->avisos, 'El de 30 días no debería entrar.');
        $this->assertCount(1, $avisos->vencidos());
        $this->assertCount(1, $avisos->porVencer());
    }

    /** Lo ya vencido va primero: es lo que urge. */
    public function test_los_vencidos_salen_primero(): void
    {
        $this->renta(-1);
        $this->renta(10);

        $primero = AvisosDelDia::for($this->company)->porUrgencia()->first();

        $this->assertTrue($primero->vencida);
    }

    /** Sin teléfono no hay a dónde mandar el aviso. */
    public function test_un_cliente_sin_telefono_no_entra_a_la_cola(): void
    {
        $sinTelefono = $this->company->customers()->create([
            'name' => 'Sin teléfono', 'email' => 's@x.mx', 'phone' => null,
        ]);

        $this->company->rentals()->create([
            'customer_id' => $sinTelefono->id,
            'washing_machine_id' => $this->equipo()->id,
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->subDays(5)->toDateString(),
            'status' => 'vencida',
        ]);

        $this->assertCount(0, AvisosDelDia::for($this->company)->avisos);
    }

    public function test_el_mensaje_del_vencido_trae_el_saldo_y_no_regaña(): void
    {
        $this->renta(14);

        $aviso = AvisosDelDia::for($this->company)->vencidos()->first();

        $mensaje = $aviso->mensaje();

        $this->assertStringContainsString('Juan Pérez', $mensaje);
        $this->assertStringContainsString('$500.00', $mensaje);
        $this->assertStringContainsString('Lavandería', $mensaje);
        $this->assertStringNotContainsString('!', $mensaje, 'Un mensaje agresivo hace que dejen de contestar.');
    }

    public function test_el_mensaje_dice_secadora_cuando_es_secadora(): void
    {
        $this->company->rentals()->create([
            'customer_id' => $this->cliente->id,
            'washing_machine_id' => $this->equipo('secadora')->id,
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->subDays(5)->toDateString(),
            'status' => 'vencida',
        ]);

        $mensaje = AvisosDelDia::for($this->company)->vencidos()->first()->mensaje();

        $this->assertStringContainsString('secadora', $mensaje);
        $this->assertStringNotContainsString('lavadora', $mensaje);
    }

    /** rawurlencode y no urlencode: WhatsApp muestra los "+" tal cual. */
    public function test_la_liga_de_whatsapp_no_manda_los_espacios_como_mas(): void
    {
        $this->renta(5);

        $url = AvisosDelDia::for($this->company)->vencidos()->first()->whatsappUrl();

        $this->assertStringStartsWith('https://wa.me/', $url);
        $this->assertStringContainsString('%20', $url);
        $this->assertStringNotContainsString('+', $url);
    }

    public function test_la_pantalla_abre_y_lista_a_quien_avisarle(): void
    {
        $this->renta(5);

        $this->get("/propietario/{$this->company->id}/avisos")
            ->assertOk()
            ->assertSee('Juan Pérez')
            ->assertSee('Mandar');
    }

    public function test_sin_nadie_a_quien_avisar_la_pantalla_lo_dice(): void
    {
        $this->get("/propietario/{$this->company->id}/avisos")
            ->assertOk()
            ->assertSee('No hay nadie a quien avisarle hoy');
    }

    /** Avisar es parte del trabajo del cobrador. */
    public function test_el_cobrador_tambien_ve_los_avisos(): void
    {
        $cobrador = User::create(['name' => 'Beto', 'email' => 'b@x.com', 'password' => bcrypt('s')]);
        $cobrador->assignRole(Role::findOrCreate('cobrador', 'web'));
        $this->company->members()->attach($cobrador);

        $this->actingAs($cobrador)
            ->get("/propietario/{$this->company->id}/avisos")
            ->assertOk();
    }
}
