<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Rental;
use App\Models\User;
use App\Support\HistorialDelCliente;
use App\Support\Recoleccion;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Cómo se ha portado un cliente.
 *
 * Volverle a entregar una lavadora a quien ya te falló es el error más caro del
 * negocio: se pierde el aparato completo, no una semana de renta. Y se cometía
 * sin un solo aviso, porque la ficha del cliente sólo tenía nombre, correo y
 * teléfono.
 */
class HistorialDelClienteTest extends TestCase
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
        $user->givePermissionTo([
            Permission::findOrCreate('view_any_customer', 'web'),
            Permission::findOrCreate('view_any_rental', 'web'),
        ]);
        $this->company->members()->attach($user);
        $this->actingAs($user);

        Filament::setCurrentPanel(Filament::getPanel('propietario'));
        Filament::setTenant($this->company, true);
    }

    private function cliente(string $nombre): Customer
    {
        return $this->company->customers()->create([
            'name' => $nombre,
            'phone' => '6681234567',
            'email' => \Illuminate\Support\Str::slug($nombre) . uniqid() . '@x.com',
        ]);
    }

    private function renta(Customer $cliente, string $codigo, array $extra = []): Rental
    {
        $equipo = $this->company->washingMachines()->create([
            'machine_code' => $codigo,
            'kind' => 'lavadora',
            'brand' => 'Mabe',
            'status' => 'rentada',
        ]);

        return $this->company->rentals()->create(array_merge([
            'customer_id' => $cliente->id,
            'washing_machine_id' => $equipo->id,
            'start_date' => now()->subWeeks(10)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'status' => 'activa',
            'price' => 250,
        ], $extra));
    }

    /** Paga cada $cada días, $cuantos veces. */
    private function pagos(Rental $renta, int $cuantos, int $cada): void
    {
        for ($i = 0; $i < $cuantos; $i++) {
            $renta->payments()->create([
                'company_id' => $this->company->id,
                'amount' => 250,
                'payment_date' => now()->subDays($cada * ($cuantos - $i))->toDateString(),
                'payment_method' => 'Efectivo',
                'status' => 'completado',
            ]);
        }
    }

    /** El que no tiene historia no se juzga: no hay con qué. */
    public function test_el_cliente_sin_historia_sale_como_nuevo(): void
    {
        $historial = HistorialDelCliente::for($this->cliente('Recién Llegado'));

        $this->assertTrue($historial->esNuevo());
        $this->assertSame('Nuevo', $historial->etiqueta());
        $this->assertFalse($historial->hayQueAdvertir());
        $this->assertStringContainsString('Todavía no le has rentado', $historial->resumen());
    }

    /** El que paga cada semana en un periodo de siete días está cumplido. */
    public function test_el_que_paga_puntual_sale_como_cumplido(): void
    {
        $cliente = $this->cliente('Puntual');
        $this->pagos($this->renta($cliente, 'LAV-001'), 8, 7);

        $historial = HistorialDelCliente::for($cliente->fresh());

        $this->assertSame('Cumplido', $historial->etiqueta());
        $this->assertSame('success', $historial->color());
        $this->assertFalse($historial->pagaTarde());
        $this->assertFalse($historial->hayQueAdvertir());
    }

    /**
     * EL QUE MÁS DICE Y EL QUE MENOS SE NOTABA.
     *
     * Alguien que paga cada 11 días en un periodo de 7 no está al corriente:
     * está siempre atrás. En la pantalla se veía igual que el puntual, porque el
     * único dato visible era si debía HOY.
     */
    public function test_el_que_paga_cada_once_dias_en_periodo_de_siete_sale_atrasado(): void
    {
        $cliente = $this->cliente('Siempre Tarde');
        $this->pagos($this->renta($cliente, 'LAV-002'), 8, 11);

        $historial = HistorialDelCliente::for($cliente->fresh());

        $this->assertTrue($historial->pagaTarde());
        $this->assertSame('Se atrasa', $historial->etiqueta());
        $this->assertSame(11.0, $historial->diasEntrePagos);
        $this->assertTrue($historial->hayQueAdvertir());
        $this->assertStringContainsString('cada <strong>11 días</strong>', $historial->advertencia());
    }

    /** Un par de días de holgura no lo vuelve atrasado. */
    public function test_un_dia_de_holgura_no_lo_vuelve_atrasado(): void
    {
        $cliente = $this->cliente('Casi Puntual');
        $this->pagos($this->renta($cliente, 'LAV-003'), 8, 8);

        $this->assertFalse(
            HistorialDelCliente::for($cliente->fresh())->pagaTarde(),
            'Un día de holgura sobre siete ya lo marca como atrasado.'
        );
    }

    /**
     * ESTE ERROR SE ME ESCAPÓ Y LO CACHÉ MIRANDO LA PANTALLA.
     *
     * Un cliente que rentó, devolvió, y volvió a rentar meses después tiene un
     * hueco sin pagos en medio: no pagó porque no debía, no porque se atrasara.
     * Midiendo por encima de ese hueco, alguien que pagó puntual en las dos
     * rentas salía con un ritmo de 15.9 días sobre un periodo de 7 — un cliente
     * cumplido marcado como atrasado, que es justo el error que esta pantalla no
     * puede permitirse.
     */
    public function test_el_hueco_entre_dos_rentas_no_lo_vuelve_atrasado(): void
    {
        $cliente = $this->cliente('Volvió Después');

        // Rentó hace medio año y pagó cada semana durante seis semanas.
        $vieja = $this->renta($cliente, 'LAV-100', [
            'status' => 'completada',
            'start_date' => now()->subDays(200)->toDateString(),
            'end_date' => now()->subDays(158)->toDateString(),
        ]);

        for ($i = 0; $i < 7; $i++) {
            $vieja->payments()->create([
                'company_id' => $this->company->id,
                'amount' => 250,
                'payment_date' => now()->subDays(200 - $i * 7)->toDateString(),
                'payment_method' => 'Efectivo',
                'status' => 'completado',
            ]);
        }

        // Cuatro meses sin nada rentado. Después volvió, y otra vez puntual.
        $nueva = $this->renta($cliente, 'LAV-101', [
            'start_date' => now()->subDays(28)->toDateString(),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $nueva->payments()->create([
                'company_id' => $this->company->id,
                'amount' => 250,
                'payment_date' => now()->subDays(28 - $i * 7)->toDateString(),
                'payment_method' => 'Efectivo',
                'status' => 'completado',
            ]);
        }

        $historial = HistorialDelCliente::for($cliente->fresh());

        $this->assertSame(12, $historial->pagos);
        $this->assertSame(
            7.0,
            $historial->diasEntrePagos,
            'El hueco de cuatro meses entre las dos rentas se contó como atraso.'
        );
        $this->assertFalse($historial->pagaTarde());
        $this->assertSame('Cumplido', $historial->etiqueta());
    }

    /**
     * EL CASO QUE JUSTIFICA TODO ESTO.
     *
     * Se le recogió la lavadora quedando a deber. La próxima vez que aparezca
     * pidiendo equipo, eso tiene que estar en la pantalla.
     */
    public function test_al_que_ya_le_recogieron_un_equipo_debiendo_se_le_advierte(): void
    {
        $cliente = $this->cliente('Jesús Ruiz');
        $renta = $this->renta($cliente, 'LAV-004', [
            'status' => 'vencida',
            'end_date' => now()->subWeeks(3)->toDateString(),
        ]);

        $debia = app(Recoleccion::class)->ejecutar($renta, quedaronEnPaz: false);
        $this->assertGreaterThan(0, $debia);

        $historial = HistorialDelCliente::for($cliente->fresh());

        $this->assertTrue($historial->yaFallo());
        $this->assertSame(1, $historial->vecesQueQuedoADeber);
        $this->assertSame($debia, $historial->adeudoCongelado);
        $this->assertSame('Quedó a deber', $historial->etiqueta());
        $this->assertSame('danger', $historial->color());

        $advertencia = $historial->advertencia();
        $this->assertStringContainsString('Ya le recogiste un equipo', $advertencia);
        $this->assertStringContainsString(number_format($debia, 2), $advertencia);
        $this->assertStringContainsString('depósito', $advertencia);
    }

    /**
     * Que se lo hayan perdonado no borra que pasó: es justo lo que hay que
     * recordar la próxima vez que pida equipo.
     */
    public function test_el_perdonado_sigue_contando_como_una_falla(): void
    {
        $cliente = $this->cliente('Perdonado');
        $renta = $this->renta($cliente, 'LAV-005', [
            'status' => 'vencida',
            'end_date' => now()->subWeeks(3)->toDateString(),
        ]);

        app(Recoleccion::class)->ejecutar($renta, quedaronEnPaz: true);

        $historial = HistorialDelCliente::for($cliente->fresh());

        $this->assertTrue($historial->yaFallo());
        $this->assertSame(0.0, $historial->adeudoCongelado, 'Se le perdonó: ya no debe nada.');
        $this->assertSame('Ya falló una vez', $historial->etiqueta());
        $this->assertStringContainsString('quedaron en paz', $historial->advertencia());
    }

    /** Al que estaba al corriente cuando se le recogió no se le marca nada. */
    public function test_al_que_devolvio_estando_al_corriente_no_se_le_marca(): void
    {
        $cliente = $this->cliente('Devolvió Bien');
        $renta = $this->renta($cliente, 'LAV-006');
        $this->pagos($renta, 8, 7);

        app(Recoleccion::class)->ejecutar($renta->fresh(), quedaronEnPaz: false);

        $historial = HistorialDelCliente::for($cliente->fresh());

        $this->assertFalse($historial->yaFallo(), 'Devolvió al corriente y quedó marcado como fallido.');
        $this->assertSame(0, $historial->vecesQueQuedoADeber);
    }

    /** Y la advertencia aparece en el formulario de renta, no hay que buscarla. */
    public function test_el_formulario_de_renta_advierte_del_cliente_que_ya_fallo(): void
    {
        $cliente = $this->cliente('Jesús Ruiz');
        $renta = $this->renta($cliente, 'LAV-007', [
            'status' => 'vencida',
            'end_date' => now()->subWeeks(3)->toDateString(),
        ]);

        app(Recoleccion::class)->ejecutar($renta, quedaronEnPaz: false);

        $aviso = \App\Filament\Resources\CustomerResource::comoSeHaPortado($cliente->fresh());

        $this->assertStringContainsString('Ya le recogiste', $aviso);
        $this->assertStringContainsString('rf-cfg-resumen-falta', $aviso);
    }

    /** Del bueno también se dice algo, para poder no pedirle depósito. */
    public function test_del_cliente_cumplido_tambien_se_dice_algo(): void
    {
        $cliente = $this->cliente('Buen Cliente');
        $this->pagos($this->renta($cliente, 'LAV-008'), 10, 7);

        $aviso = \App\Filament\Resources\CustomerResource::comoSeHaPortado($cliente->fresh());

        $this->assertStringContainsString('10 cobros', $aviso);
        $this->assertStringContainsString('paga cada 7 días', $aviso);
        $this->assertStringNotContainsString('rf-cfg-resumen-falta', $aviso);
    }

    /** Con dos pagos no se juzga a nadie: no alcanza para ver un ritmo. */
    public function test_con_dos_pagos_no_se_juzga(): void
    {
        $cliente = $this->cliente('Apenas Empieza');
        $this->pagos($this->renta($cliente, 'LAV-009'), 2, 20);

        $historial = HistorialDelCliente::for($cliente->fresh());

        $this->assertNull($historial->diasEntrePagos);
        $this->assertFalse($historial->pagaTarde());
        $this->assertTrue($historial->esNuevo());
    }
}
