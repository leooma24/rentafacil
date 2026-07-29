<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Package;
use App\Models\User;
use App\Models\WashingMachine;
use App\Support\RentabilidadDelEquipo;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Cuánto ha dejado cada equipo de verdad.
 *
 * La pantalla enseñaba una sola columna de dinero, "Ingresos Totales", que era lo
 * cobrado y nada más. Con ese número se decide qué marca volver a comprar.
 */
class RentabilidadDelEquipoTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

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
        $user->givePermissionTo(Permission::findOrCreate('view_any_washing::machine', 'web'));
        $this->company->members()->attach($user);
        $this->actingAs($user);

        Filament::setCurrentPanel(Filament::getPanel('propietario'));
        Filament::setTenant($this->company, true);
    }

    /**
     * @param float $cobrado Lo que se le ha cobrado en total a sus clientes.
     */
    private function equipo(string $codigo, ?float $compra, float $cobrado, float $mantenimiento = 0): WashingMachine
    {
        $equipo = $this->company->washingMachines()->create([
            'machine_code' => $codigo,
            'kind' => 'lavadora',
            'brand' => 'Samsung',
            'status' => 'disponible',
            'purchase_price' => $compra,
        ]);

        $cliente = $this->company->customers()->create([
            'name' => 'Cliente ' . $codigo,
            'phone' => '6681234567',
            'email' => strtolower($codigo) . '@x.com',
        ]);

        $renta = $this->company->rentals()->create([
            'customer_id' => $cliente->id,
            'washing_machine_id' => $equipo->id,
            'start_date' => now()->subYear()->toDateString(),
            'end_date' => now()->subMonth()->toDateString(),
            'status' => 'completada',
        ]);

        if ($cobrado > 0) {
            $renta->payments()->create([
                'company_id' => $this->company->id,
                'amount' => $cobrado,
                'payment_date' => now()->subMonths(2)->toDateString(),
                'payment_method' => 'Efectivo',
                'status' => 'completado',
            ]);
        }

        if ($mantenimiento > 0) {
            $this->company->maintenances()->create([
                'washing_machine_id' => $equipo->id,
                'technician_name' => 'Luis Herrera',
                'start_date' => now()->subMonths(3)->toDateString(),
                'end_date' => now()->subMonths(3)->addDay()->toDateString(),
                'maintenance_type' => 'correctivo',
                'status' => 'completado',
                'description' => 'Bomba.',
                'cost' => $mantenimiento,
            ]);
        }

        return $equipo->fresh();
    }

    /**
     * ESTE ES EL CASO QUE SALÍA AL REVÉS.
     *
     * Cobró $8,000, costó $11,200 y llevó $2,500 en reparaciones. Va perdiendo
     * $5,700 y en la pantalla aparecía hasta arriba, como la mejor del parque.
     */
    public function test_la_que_cobro_mucho_pero_costo_mas_sale_en_negativo(): void
    {
        $equipo = $this->equipo('LAV-001', compra: 11200, cobrado: 8000, mantenimiento: 2500);

        $r = RentabilidadDelEquipo::for($equipo);

        $this->assertSame(8000.0, $r->cobrado);
        $this->assertSame(13700.0, $r->gastado(), 'No está sumando las reparaciones.');
        $this->assertSame(-5700.0, $r->resultado());
        $this->assertFalse($r->yaSePago());
        // Ámbar y no rojo: lleva recuperado el 58%, va atrás pero no es un
        // desastre. El rojo se reserva para las que no han recuperado ni la
        // mitad, que son las que de verdad hay que mirar de cerca.
        $this->assertSame('warning', $r->color());
    }

    /** La que no ha recuperado ni la mitad sí sale en rojo. */
    public function test_la_que_no_recupera_ni_la_mitad_sale_en_rojo(): void
    {
        $equipo = $this->equipo('LAV-009', compra: 10000, cobrado: 1500, mantenimiento: 2000);

        $this->assertSame('danger', RentabilidadDelEquipo::for($equipo)->color());
    }

    /** Y la que sí se pagó lo dice, con lo que deja de ganancia. */
    public function test_la_que_ya_se_pago_lo_dice(): void
    {
        $equipo = $this->equipo('LAV-002', compra: 7200, cobrado: 12000, mantenimiento: 800);

        $r = RentabilidadDelEquipo::for($equipo);

        $this->assertTrue($r->yaSePago());
        $this->assertSame(4000.0, $r->resultado());
        $this->assertSame('success', $r->color());
        $this->assertStringContainsString('Ya se pagó', $r->veredicto());
        $this->assertStringContainsString('4,000.00', $r->veredicto());
    }

    /**
     * El mantenimiento es el gasto que decide en este negocio: un aparato barato
     * que se descompone cada dos meses sale más caro que uno del doble que no.
     */
    public function test_el_mantenimiento_puede_voltear_el_resultado(): void
    {
        $sinFallas = $this->equipo('LAV-003', compra: 7000, cobrado: 9000);
        $conFallas = $this->equipo('LAV-004', compra: 7000, cobrado: 9000, mantenimiento: 3500);

        $this->assertTrue(RentabilidadDelEquipo::for($sinFallas)->yaSePago());
        $this->assertFalse(
            RentabilidadDelEquipo::for($conFallas)->yaSePago(),
            'Dos aparatos con el mismo precio y el mismo cobrado salen igual, aunque uno llevó $3,500 de reparaciones.'
        );
    }

    /** Le faltan X cobros: en cobros y no en pesos, que es como se piensa. */
    public function test_dice_cuantos_cobros_le_faltan_para_pagarse(): void
    {
        $equipo = $this->equipo('LAV-005', compra: 7000, cobrado: 5000);

        $r = RentabilidadDelEquipo::for($equipo);

        // Faltan $2,000 y el periodo cuesta $250: ocho cobros.
        $this->assertSame(8, $r->periodosParaPagarse());
        $this->assertStringContainsString('8 cobros', $r->veredicto());
    }

    /**
     * Sin precio de compra no se puede decir nada, y eso NO es lo mismo que "no
     * ha dejado nada": los dos ceros se ven igual en pantalla y significan cosas
     * opuestas.
     */
    public function test_sin_precio_de_compra_lo_dice_en_vez_de_enseñar_cero(): void
    {
        $equipo = $this->equipo('LAV-006', compra: null, cobrado: 6000);

        $r = RentabilidadDelEquipo::for($equipo);

        $this->assertFalse($r->calculable());
        $this->assertFalse($r->yaSePago(), 'Sin precio de compra no se puede afirmar que ya se pagó.');
        $this->assertSame('gray', $r->color());
        $this->assertStringContainsString('Falta su precio', $r->veredicto());
    }

    /** La pantalla enseña las columnas nuevas y no sólo lo cobrado. */
    public function test_la_pantalla_enseña_lo_gastado_y_no_solo_lo_cobrado(): void
    {
        $this->equipo('LAV-007', compra: 11200, cobrado: 8000, mantenimiento: 2500);

        \Livewire\Livewire::test(\App\Filament\Widgets\MachineProfitabilityWidget::class)
            ->assertSee('Reparaciones')
            ->assertSee('Te ha dejado')
            ->assertSee('Costó')
            ->assertDontSee('Ingresos Totales');
    }

    /**
     * Las dos sumas viven en subconsultas y no en un join compartido: unir pagos
     * y mantenimientos en la misma consulta multiplica las filas y las dos cifras
     * salen infladas.
     */
    public function test_las_sumas_no_se_inflan_con_varios_pagos_y_mantenimientos(): void
    {
        $equipo = $this->equipo('LAV-008', compra: 5000, cobrado: 250, mantenimiento: 300);

        // Dos mantenimientos más y dos cobros más sobre la misma renta.
        $renta = $equipo->rentals()->first();

        foreach ([250, 250] as $monto) {
            $renta->payments()->create([
                'company_id' => $this->company->id,
                'amount' => $monto,
                'payment_date' => now()->subMonth()->toDateString(),
                'payment_method' => 'Efectivo',
                'status' => 'completado',
            ]);
        }

        foreach ([200, 100] as $costo) {
            $this->company->maintenances()->create([
                'washing_machine_id' => $equipo->id,
                'technician_name' => 'Luis Herrera',
                'start_date' => now()->subMonths(4)->toDateString(),
                'maintenance_type' => 'preventivo',
                'status' => 'completado',
                'description' => 'Revisión.',
                'cost' => $costo,
            ]);
        }

        $r = RentabilidadDelEquipo::for($equipo->fresh());

        $this->assertSame(750.0, $r->cobrado, 'Los cobros se contaron más de una vez.');
        $this->assertSame(600.0, $r->mantenimiento, 'Las reparaciones se contaron más de una vez.');
    }
}
