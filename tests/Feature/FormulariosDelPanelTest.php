<?php

namespace Tests\Feature;

use App\Filament\Resources\PaymentResource;
use App\Filament\Resources\PaymentResource\Pages\CreatePayment;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Rental;
use App\Models\User;
use App\Models\WashingMachine;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Los formularios de mantenimiento, incidencia, gasto y pago.
 *
 * Se comprueba lo que cambia el resultado, no que la pantalla cargue: que el
 * pago registrado desde Pagos mueva la fecha igual que el botón Cobrar, y que
 * el costo de un mantenimiento se pueda anotar.
 */
class FormulariosDelPanelTest extends TestCase
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
            Permission::findOrCreate('view_any_payment', 'web'),
            Permission::findOrCreate('create_payment', 'web'),
            Permission::findOrCreate('view_any_maintenance', 'web'),
            Permission::findOrCreate('create_maintenance', 'web'),
        ]);
        $this->company->members()->attach($this->dueno);
        $this->actingAs($this->dueno);

        Filament::setCurrentPanel(Filament::getPanel('propietario'));
        Filament::setTenant($this->company, true);
    }

    private function renta(?float $precio = null, int $venceEnDias = 7): Rental
    {
        $equipo = $this->company->washingMachines()->create([
            'machine_code' => 'LAV-' . uniqid(),
            'kind' => 'lavadora',
            'brand' => 'Mabe',
            'status' => 'rentada',
        ]);

        $cliente = $this->company->customers()->create([
            'name' => 'Ana Beltrán',
            'phone' => '6681234567',
            'email' => 'ana' . uniqid() . '@x.com',
        ]);

        return $this->company->rentals()->create([
            'customer_id' => $cliente->id,
            'washing_machine_id' => $equipo->id,
            'start_date' => now()->subWeeks(2)->toDateString(),
            'end_date' => now()->addDays($venceEnDias)->toDateString(),
            'status' => 'activa',
            'price' => $precio,
        ]);
    }

    /**
     * ESTO ES LO QUE ESTABA ROTO.
     *
     * El alta desde Pagos creaba la fila y ya: el dinero quedaba registrado
     * pero la fecha de la renta no se movía, así que el cliente que acababa de
     * pagar seguía saliendo como moroso en Avisos y en su estado de cuenta.
     */
    public function test_un_pago_registrado_desde_pagos_le_recorre_la_fecha_a_la_renta(): void
    {
        $renta = $this->renta();
        $antes = \Carbon\Carbon::parse($renta->end_date);

        Livewire::test(CreatePayment::class)
            ->fillForm([
                'rental_id' => $renta->id,
                'amount' => 250,
                'payment_date' => today()->toDateString(),
                'payment_method' => 'Efectivo',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $renta->refresh();

        $this->assertSame(
            $antes->copy()->addDays(7)->toDateString(),
            \Carbon\Carbon::parse($renta->end_date)->toDateString(),
            'El pago se registró pero la renta siguió venciendo el mismo día.'
        );

        $pago = Payment::where('rental_id', $renta->id)->firstOrFail();
        $this->assertSame('completado', $pago->status);
        $this->assertTrue((bool) $pago->applied, 'El cobro completo quedó marcado como abono.');
        $this->assertSame($this->company->id, $pago->company_id);
        $this->assertSame($this->dueno->id, $pago->collected_by);
    }

    /** Y si no alcanza para el periodo, queda como abono y NO mueve la fecha. */
    public function test_un_pago_incompleto_queda_como_abono_sin_mover_la_fecha(): void
    {
        $renta = $this->renta();
        $antes = \Carbon\Carbon::parse($renta->end_date)->toDateString();

        Livewire::test(CreatePayment::class)
            ->fillForm([
                'rental_id' => $renta->id,
                'amount' => 100,
                'payment_date' => today()->toDateString(),
                'payment_method' => 'Efectivo',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $renta->refresh();

        $this->assertSame($antes, \Carbon\Carbon::parse($renta->end_date)->toDateString());

        $pago = Payment::where('rental_id', $renta->id)->firstOrFail();
        $this->assertFalse((bool) $pago->applied);
        $this->assertSame(100.0, \App\Support\Abonos::creditFor($renta));
    }

    /** El precio propio de la renta manda sobre el de la empresa. */
    public function test_el_cobro_respeta_el_precio_de_esa_renta(): void
    {
        $renta = $this->renta(precio: 300);
        $antes = \Carbon\Carbon::parse($renta->end_date)->toDateString();

        // 250 es el precio de la empresa, pero esta renta se pactó en 300.
        Livewire::test(CreatePayment::class)
            ->fillForm([
                'rental_id' => $renta->id,
                'amount' => 250,
                'payment_date' => today()->toDateString(),
                'payment_method' => 'Efectivo',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(
            $antes,
            \Carbon\Carbon::parse($renta->refresh()->end_date)->toDateString(),
            'Se le dieron 250 a una renta de 300 y aun así se le recorrió la fecha.'
        );
    }

    /**
     * El selector pedía teclear el número interno de la renta, que nadie se
     * sabe de memoria.
     */
    public function test_las_rentas_se_escogen_por_cliente_y_equipo(): void
    {
        $renta = $this->renta();

        $empresa = $this->company;
        $opciones = (fn () => self::rentasParaCobrar($empresa))
            ->call(new PaymentResource());

        $this->assertArrayHasKey($renta->id, $opciones);
        $this->assertStringContainsString('Ana Beltrán', $opciones[$renta->id]);
        $this->assertStringContainsString($renta->washingMachine->machine_code, $opciones[$renta->id]);
        $this->assertStringContainsString('Lavadora', $opciones[$renta->id]);
    }

    /** Las rentas cerradas no salen: no se le cobra a quien ya devolvió. */
    public function test_las_rentas_cerradas_no_aparecen_para_cobrar(): void
    {
        $renta = $this->renta();
        $renta->update(['status' => 'completada']);

        $empresa = $this->company;
        $opciones = (fn () => self::rentasParaCobrar($empresa))
            ->call(new PaymentResource());

        $this->assertArrayNotHasKey($renta->id, $opciones);
    }

    /**
     * cost y status existían en la tabla y no en el formulario: no había forma
     * de anotar lo que costó una reparación ni de marcarla terminada.
     */
    public function test_el_formulario_de_mantenimiento_pide_costo_y_estatus(): void
    {
        $equipo = $this->company->washingMachines()->create([
            'machine_code' => 'LAV-900',
            'kind' => 'lavadora',
            'status' => 'disponible',
        ]);

        Livewire::test(\App\Filament\Resources\MaintenanceResource\Pages\CreateMaintenance::class)
            ->fillForm([
                'washing_machine_id' => $equipo->id,
                'technician_name' => 'Luis Herrera',
                'maintenance_type' => 'correctivo',
                'status' => 'completado',
                'description' => 'Cambio de banda del motor.',
                'start_date' => now()->subDays(2)->toDateString(),
                'end_date' => now()->subDay()->toDateString(),
                'cost' => 780,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $mantenimiento = $this->company->maintenances()->firstOrFail();

        $this->assertSame(780.0, (float) $mantenimiento->cost);
        $this->assertSame('completado', $mantenimiento->status);
    }

    /** Y no deja que salga del taller antes de haber entrado. */
    public function test_el_mantenimiento_no_puede_terminar_antes_de_empezar(): void
    {
        $equipo = $this->company->washingMachines()->create([
            'machine_code' => 'LAV-901',
            'kind' => 'lavadora',
            'status' => 'disponible',
        ]);

        Livewire::test(\App\Filament\Resources\MaintenanceResource\Pages\CreateMaintenance::class)
            ->fillForm([
                'washing_machine_id' => $equipo->id,
                'technician_name' => 'Luis Herrera',
                'maintenance_type' => 'correctivo',
                'status' => 'completado',
                'description' => 'Prueba.',
                'start_date' => now()->toDateString(),
                'end_date' => now()->subWeek()->toDateString(),
            ])
            ->call('create')
            ->assertHasFormErrors(['end_date']);
    }
}
