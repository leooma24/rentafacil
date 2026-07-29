<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Rental;
use App\Models\RentalMachineChange;
use App\Models\User;
use App\Models\WashingMachine;
use App\Support\AccountStatement;
use App\Support\CambioDeEquipo;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Cambio de aparato y equipo extraviado.
 *
 * Si una lavadora se descomponía y se le llevaba otra al cliente, había que
 * cancelar la renta y crear otra: se perdían los pagos y el saldo arrancaba de
 * cero. Y un equipo del que el cliente se adueñaba volvía al inventario como
 * disponible, inflando la ocupación.
 */
class CambioDeEquipoTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $dueno;
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

        $this->dueno = User::create(['name' => 'Dueño', 'email' => 'd@x.com', 'password' => bcrypt('s')]);
        $this->dueno->assignRole(Role::findOrCreate('propietario', 'web'));
        $this->dueno->givePermissionTo(
            Permission::findOrCreate('view_any_washing::machine', 'web')
        );
        $this->company->members()->attach($this->dueno);
        $this->actingAs($this->dueno);

        Filament::setCurrentPanel(Filament::getPanel('propietario'));
        Filament::setTenant($this->company, true);

        $this->cliente = $this->company->customers()->create([
            'name' => 'Cliente', 'email' => 'c@x.mx', 'phone' => '1',
        ]);
    }

    private function equipo(string $codigo, string $estado = 'disponible'): WashingMachine
    {
        return $this->company->washingMachines()->create([
            'machine_code' => $codigo, 'brand' => 'Mabe', 'status' => $estado,
        ]);
    }

    private function renta(WashingMachine $equipo, array $extra = []): Rental
    {
        return $this->company->rentals()->create(array_merge([
            'customer_id' => $this->cliente->id,
            'washing_machine_id' => $equipo->id,
            'start_date' => now()->subMonths(2)->toDateString(),
            'end_date' => now()->subDays(14)->toDateString(),
            'status' => 'vencida',
            'price' => 300,
            'deposit' => 500,
            'delivered_at' => now()->subMonths(2),
        ], $extra));
    }

    // --- Cambio de equipo ---

    /** Lo que importa: al cliente no se le mueve nada más que el aparato. */
    public function test_cambiar_el_equipo_conserva_pagos_saldo_precio_y_deposito(): void
    {
        $viejo = $this->equipo('LAV-001', 'rentada');
        $nuevo = $this->equipo('LAV-002');
        $renta = $this->renta($viejo);

        $renta->payments()->create([
            'company_id' => $this->company->id,
            'amount' => 300,
            'payment_date' => now()->subMonth()->toDateString(),
            'payment_method' => 'Efectivo',
            'status' => 'completado',
        ]);

        $adeudoAntes = app(AccountStatement::class)->forRental($renta)->amount;

        app(CambioDeEquipo::class)->ejecutar($renta, $nuevo, 'falla', 'Ya no centrifugaba.');

        $renta->refresh();

        $this->assertSame($nuevo->id, $renta->washing_machine_id);
        $this->assertSame('vencida', $renta->status, 'La renta no debió cerrarse.');
        $this->assertSame('300.00', $renta->price);
        $this->assertSame('500.00', $renta->deposit);
        $this->assertCount(1, $renta->payments, 'Se perdieron los pagos.');
        $this->assertSame($adeudoAntes, app(AccountStatement::class)->forRental($renta->fresh())->amount);
        $this->assertSame(1, Rental::count(), 'Se creó una renta nueva en vez de mover la existente.');
    }

    public function test_el_equipo_que_se_descompuso_se_va_a_mantenimiento(): void
    {
        $viejo = $this->equipo('LAV-001', 'rentada');
        $nuevo = $this->equipo('LAV-002');

        app(CambioDeEquipo::class)->ejecutar($this->renta($viejo), $nuevo, 'falla');

        $this->assertSame('mantenimiento', $viejo->fresh()->status);
        $this->assertSame('rentada', $nuevo->fresh()->status);
    }

    /** Si lo pidió el cliente, el que se retira está bien y vuelve al inventario. */
    public function test_si_lo_pidio_el_cliente_el_viejo_queda_disponible(): void
    {
        $viejo = $this->equipo('LAV-001', 'rentada');
        $nuevo = $this->equipo('LAV-002');

        app(CambioDeEquipo::class)->ejecutar($this->renta($viejo), $nuevo, 'peticion');

        $this->assertSame('disponible', $viejo->fresh()->status);
    }

    public function test_queda_el_historial_de_a_donde_venia(): void
    {
        $viejo = $this->equipo('LAV-001', 'rentada');
        $nuevo = $this->equipo('LAV-002');
        $renta = $this->renta($viejo);

        $cambio = app(CambioDeEquipo::class)->ejecutar($renta, $nuevo, 'falla', 'Ya no centrifugaba.');

        $this->assertSame($viejo->id, $cambio->from_machine_id);
        $this->assertSame($nuevo->id, $cambio->to_machine_id);
        $this->assertSame('Se descompuso', $cambio->reasonLabel());
        $this->assertSame($this->dueno->id, $cambio->changed_by);
        $this->assertSame('Ya no centrifugaba.', $cambio->notes);
    }

    /** Es otro aparato: hace falta la foto de cómo se dejó éste. */
    public function test_el_cambio_vuelve_a_pedir_la_entrega(): void
    {
        $viejo = $this->equipo('LAV-001', 'rentada');
        $renta = $this->renta($viejo);

        $this->assertTrue($renta->isDelivered());

        app(CambioDeEquipo::class)->ejecutar($renta, $this->equipo('LAV-002'), 'falla');

        $this->assertTrue($renta->fresh()->needsDelivery());
    }

    public function test_no_se_puede_cambiar_por_un_equipo_ya_rentado(): void
    {
        $viejo = $this->equipo('LAV-001', 'rentada');
        $ocupado = $this->equipo('LAV-002', 'rentada');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no está disponible');

        app(CambioDeEquipo::class)->ejecutar($this->renta($viejo), $ocupado, 'falla');
    }

    public function test_no_se_puede_cambiar_por_el_mismo(): void
    {
        $equipo = $this->equipo('LAV-001', 'rentada');

        $this->expectException(\RuntimeException::class);

        app(CambioDeEquipo::class)->ejecutar($this->renta($equipo), $equipo, 'falla');
    }

    /** Si algo truena a medias, el cliente no puede quedarse sin equipo asignado. */
    public function test_un_cambio_fallido_no_deja_nada_a_medias(): void
    {
        $viejo = $this->equipo('LAV-001', 'rentada');
        $ocupado = $this->equipo('LAV-002', 'rentada');
        $renta = $this->renta($viejo);

        try {
            app(CambioDeEquipo::class)->ejecutar($renta, $ocupado, 'falla');
        } catch (\RuntimeException) {
            // esperado
        }

        $this->assertSame($viejo->id, $renta->fresh()->washing_machine_id);
        $this->assertSame('rentada', $viejo->fresh()->status);
        $this->assertSame(0, RentalMachineChange::count());
    }

    public function test_se_cambia_desde_la_pantalla(): void
    {
        $viejo = $this->equipo('LAV-001', 'rentada');
        $nuevo = $this->equipo('LAV-002');
        $renta = $this->renta($viejo);

        Livewire::test(
            \App\Filament\Resources\WashingMachineResource\Pages\ListWashingMachines::class,
            ['tenant' => $this->company],
        )
            ->assertOk()
            ->callTableAction('cambiar_equipo', $viejo, [
                'to_machine_id' => $nuevo->id,
                'reason' => 'falla',
                'notes' => 'Se quemó el motor.',
            ]);

        $this->assertSame($nuevo->id, $renta->fresh()->washing_machine_id);
        $this->assertSame(1, RentalMachineChange::count());
    }

    // --- Equipo extraviado ---

    public function test_marcar_extraviado_lo_saca_del_inventario(): void
    {
        $equipo = $this->equipo('LAV-001', 'rentada');
        $this->renta($equipo);

        Livewire::test(
            \App\Filament\Resources\WashingMachineResource\Pages\ListWashingMachines::class,
            ['tenant' => $this->company],
        )->callTableAction('marcar_extraviado', $equipo, [
            'notes' => 'Se mudó sin avisar.',
        ]);

        $this->assertSame('extraviada', $equipo->fresh()->status);
    }

    /**
     * El adeudo se deriva de qué tan atrás quedó end_date, así que cerrar la
     * renta lo borraría del estado de cuenta justo cuando más se quiere cobrar.
     */
    public function test_el_extravio_no_borra_lo_que_el_cliente_debe(): void
    {
        $equipo = $this->equipo('LAV-001', 'rentada');
        $renta = $this->renta($equipo);

        $adeudoAntes = app(AccountStatement::class)->forCustomer($this->cliente)->total;
        $this->assertGreaterThan(0, $adeudoAntes);

        Livewire::test(
            \App\Filament\Resources\WashingMachineResource\Pages\ListWashingMachines::class,
            ['tenant' => $this->company],
        )->callTableAction('marcar_extraviado', $equipo, ['notes' => 'Se mudó.']);

        $this->assertSame('vencida', $renta->fresh()->status, 'La renta no debió cerrarse.');
        $this->assertSame(
            $adeudoAntes,
            app(AccountStatement::class)->forCustomer($this->cliente->fresh())->total,
            'El adeudo desapareció al marcar el equipo como extraviado.'
        );
        $this->assertStringContainsString('extraviado', $renta->fresh()->notes);
    }

    /** Un aparato que ya no está no debe hundir la ocupación. */
    public function test_un_equipo_extraviado_no_cuenta_en_la_ocupacion(): void
    {
        $this->equipo('LAV-001', 'rentada');
        $this->equipo('LAV-002', 'extraviada');
        $this->equipo('SEC-001', 'rentada')->update(['kind' => 'secadora']);

        $stats = (fn () => $this->getStats())->call(
            new \App\Filament\Resources\WashingMachineResource\Widgets\LavadorasStats()
        );

        $ocupacion = collect($stats)->firstWhere(fn ($s) => $s->getLabel() === 'Ocupación');

        $this->assertSame('100%', (string) $ocupacion->getValue());
        $this->assertSame('1/1 lavadoras · 1/1 secadoras', (string) $ocupacion->getDescription());
    }

    /**
     * El equipo que se retira al taller se queda con su orden abierta.
     *
     * Antes quedaba marcado en mantenimiento sin nada que lo explicara: no
     * aparecía para rentar, la pantalla de mantenimientos no decía por qué, y no
     * había con qué darlo por terminado para volverlo a colocar.
     */
    public function test_el_equipo_que_se_va_al_taller_queda_con_su_orden_abierta(): void
    {
        $anterior = $this->equipo('LAV-800', 'rentada');
        $renta = $this->renta($anterior, ['status' => 'activa', 'end_date' => now()->addDays(5)->toDateString()]);
        $nuevo = $this->equipo('LAV-900');

        app(\App\Support\CambioDeEquipo::class)->ejecutar($renta, $nuevo, 'falla', 'No centrifugaba.');

        $this->assertSame('mantenimiento', $anterior->fresh()->status);

        $orden = \App\Models\Maintenance::where('washing_machine_id', $anterior->id)->first();

        $this->assertNotNull($orden, 'Se mandó al taller sin abrirle orden.');
        $this->assertSame('programada', $orden->status);
        $this->assertStringContainsString('centrifugaba', $orden->description);

        // Y por lo tanto no sale como "parado sin orden abierta".
        $claves = collect(\App\Support\PendientesDelDia::for($this->company)->pendientes)->pluck('clave');
        $this->assertFalse($claves->contains('parados'));
    }
}
