<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Rental;
use App\Models\WashingMachine;
use App\Support\Abonos;
use App\Support\AccountStatement;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AbonosTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->company = $this->prepararEmpresa();
    }

    private function prepararEmpresa(float $precio = 250, int $dias = 7): Company
    {
        if (! Package::find(1)) {
            Package::forceCreate([
                'id' => 1, 'name' => 'Pro', 'max_clients' => 500, 'max_washers' => 500, 'price' => 999,
            ]);
        }

        $company = Company::create(['name' => 'Lavandería', 'phone' => '1', 'email' => 'l@x.com']);
        $company->settings()->create(['price' => $precio, 'days_per_payment' => $dias]);

        return $company->fresh();
    }

    private function makeCustomer(): Customer
    {
        return $this->company->customers()->create([
            'name' => 'Juan Pérez', 'email' => 'juan' . uniqid() . '@x.mx', 'phone' => '1',
        ]);
    }

    /** Una renta vencida hace $dias días, o vigente si el número es negativo. */
    private function makeRental(int $diasVencida = 5, ?Customer $customer = null): Rental
    {
        $machine = $this->company->washingMachines()->create([
            'machine_code' => 'LAV-' . uniqid(), 'brand' => 'Mabe', 'status' => 'rentada',
        ]);

        return $this->company->rentals()->create([
            'customer_id' => ($customer ?? $this->makeCustomer())->id,
            'washing_machine_id' => $machine->id,
            'start_date' => now()->subMonths(2)->toDateString(),
            'end_date' => now()->subDays($diasVencida)->toDateString(),
            'status' => $diasVencida > 0 ? 'vencida' : 'activa',
        ]);
    }

    private function saldo(Customer $customer): float
    {
        return (new AccountStatement())->forCustomer($customer->fresh())->total;
    }

    public function test_un_abono_baja_el_saldo_sin_liquidarlo(): void
    {
        $cliente = $this->makeCustomer();
        $renta = $this->makeRental(5, $cliente);

        $this->assertSame(250.0, $this->saldo($cliente));

        Abonos::register($renta, 150);

        $this->assertSame(100.0, $this->saldo($cliente), 'Debía $250 y abonó $150.');
    }

    public function test_el_abono_no_mueve_la_fecha_de_vencimiento(): void
    {
        $renta = $this->makeRental(5);
        $finAntes = $renta->end_date;

        Abonos::register($renta, 150);

        $this->assertSame(
            Carbon::parse($finAntes)->toDateString(),
            Carbon::parse($renta->fresh()->end_date)->toDateString(),
            'El abono todavía no compra tiempo.'
        );
        $this->assertSame(150.0, Abonos::creditFor($renta->fresh()));
    }

    public function test_dos_abonos_que_completan_el_periodo_lo_extienden_una_vez(): void
    {
        $cliente = $this->makeCustomer();
        $renta = $this->makeRental(5, $cliente);
        $finAntes = Carbon::parse($renta->end_date);

        Abonos::register($renta, 150);
        Abonos::register($renta->fresh(), 100);

        $renta = $renta->fresh();

        $this->assertSame(
            $finAntes->copy()->addDays(7)->toDateString(),
            Carbon::parse($renta->end_date)->toDateString(),
            'Al completar $250 la renta debe extenderse una semana.'
        );
        $this->assertSame(0.0, Abonos::creditFor($renta), 'Los abonos quedaron consumidos.');
        $this->assertSame(0.0, $this->saldo($cliente));
    }

    public function test_un_abono_que_cubre_dos_periodos_extiende_dos_y_deja_el_sobrante(): void
    {
        $renta = $this->makeRental(12); // dos periodos vencidos
        $finAntes = Carbon::parse($renta->end_date);

        Abonos::register($renta, 600); // dos periodos de $250 y sobran $100

        $renta = $renta->fresh();

        $this->assertSame(
            $finAntes->copy()->addDays(14)->toDateString(),
            Carbon::parse($renta->end_date)->toDateString()
        );
        $this->assertSame(100.0, Abonos::creditFor($renta), 'El sobrante queda a favor.');
    }

    public function test_un_abono_mayor_a_la_deuda_no_deja_el_saldo_en_negativo(): void
    {
        $cliente = $this->makeCustomer();
        $renta = $this->makeRental(5, $cliente);

        Abonos::register($renta, 1000);

        $this->assertGreaterThanOrEqual(0.0, $this->saldo($cliente));
    }

    public function test_los_pagos_que_ya_existian_cuentan_como_aplicados(): void
    {
        $cliente = $this->makeCustomer();
        $renta = $this->makeRental(5, $cliente);

        // Como los que crea ExtendRentAction: sin tocar 'applied'.
        $renta->payments()->create([
            'company_id' => $this->company->id,
            'amount' => 250,
            'payment_date' => now()->subDays(12)->toDateString(),
            'payment_method' => 'Efectivo',
            'status' => 'completado',
        ]);

        $this->assertSame(0.0, Abonos::creditFor($renta->fresh()), 'Un pago normal no es un abono.');
        $this->assertSame(250.0, $this->saldo($cliente), 'El saldo no debe cambiar por un pago viejo.');
    }

    public function test_un_abono_no_se_confunde_con_la_tarifa_del_cliente(): void
    {
        $cliente = $this->makeCustomer();
        $renta = $this->makeRental(5, $cliente);

        // Tarifa especial de $200, ya aplicada.
        $renta->payments()->create([
            'company_id' => $this->company->id,
            'amount' => 200,
            'payment_date' => now()->subDays(12)->toDateString(),
            'payment_method' => 'Efectivo',
            'status' => 'completado',
            'applied' => true,
        ]);

        Abonos::register($renta->fresh(), 50);

        $linea = (new AccountStatement())->forCustomer($cliente->fresh())->lines[0];

        $this->assertSame(200.0, $linea->price, 'La tarifa sigue siendo $200, no los $50 del abono.');
        $this->assertSame(150.0, $linea->amount, '$200 de adeudo menos $50 abonados.');
    }

    public function test_el_estado_de_cuenta_reporta_lo_abonado_y_lo_que_falta(): void
    {
        $cliente = $this->makeCustomer();
        $renta = $this->makeRental(5, $cliente);

        Abonos::register($renta, 150);

        $linea = (new AccountStatement())->forCustomer($cliente->fresh())->lines[0];

        $this->assertTrue($linea->hasCredit());
        $this->assertSame(150.0, $linea->credit);
        $this->assertSame(100.0, $linea->missingForNextPeriod());
    }

    public function test_abonar_a_una_renta_vencida_la_regresa_a_activa_al_completarla(): void
    {
        $renta = $this->makeRental(5);

        Abonos::register($renta, 250);

        $this->assertSame('activa', $renta->fresh()->status);
    }

    public function test_sin_precio_configurado_el_abono_se_registra_pero_no_extiende(): void
    {
        $company = Company::create(['name' => 'Sin config', 'phone' => '1', 'email' => 'sc@x.com']);
        $customer = $company->customers()->create(['name' => 'Ana', 'email' => 'ana@x.mx', 'phone' => '1']);
        $machine = $company->washingMachines()->create([
            'machine_code' => 'LAV-X', 'brand' => 'Mabe', 'status' => 'rentada',
        ]);
        $renta = $company->rentals()->create([
            'customer_id' => $customer->id,
            'washing_machine_id' => $machine->id,
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->subDays(5)->toDateString(),
            'status' => 'vencida',
        ]);
        $finAntes = $renta->end_date;

        $resultado = Abonos::register($renta, 500);

        $this->assertSame(0, $resultado['periodos']);
        $this->assertSame(
            Carbon::parse($finAntes)->toDateString(),
            Carbon::parse($renta->fresh()->end_date)->toDateString()
        );
        $this->assertSame(500.0, Abonos::creditFor($renta->fresh()));
    }
}
