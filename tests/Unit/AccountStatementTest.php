<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Rental;
use App\Support\AccountStatement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AccountStatementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // RentalObserver manda correo en la primera renta de una empresa.
        Mail::fake();
    }

    /**
     * CompanyObserver asigna package_id 1 a toda empresa nueva y el AUTO_INCREMENT
     * de MySQL no se reinicia entre tests, así que el id va forzado.
     */
    private function makeCompany(float $price = 250, int $days = 7): Company
    {
        if (! Package::find(1)) {
            Package::forceCreate([
                'id' => 1, 'name' => 'Pro', 'max_clients' => 500, 'max_washers' => 500, 'price' => 999,
            ]);
        }

        $company = Company::create(['name' => 'Lavandería', 'phone' => '1', 'email' => 'l@x.com']);

        if ($price > 0 && $days > 0) {
            $company->settings()->create(['price' => $price, 'days_per_payment' => $days]);
        }

        return $company->fresh();
    }

    private function makeCustomer(Company $company, string $name = 'Juan Pérez'): Customer
    {
        return $company->customers()->create([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '', $name)) . uniqid() . '@ejemplo.mx',
            'phone' => '6681234567',
        ]);
    }

    private function makeRental(
        Company $company,
        Customer $customer,
        string $endDate,
        string $status = 'activa'
    ): Rental {
        $machine = $company->washingMachines()->create([
            'machine_code' => 'LAV-' . str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT),
            'brand' => 'Mabe',
            'status' => 'rentada',
        ]);

        return $company->rentals()->create([
            'customer_id' => $customer->id,
            'washing_machine_id' => $machine->id,
            'start_date' => now()->subMonths(2)->toDateString(),
            'end_date' => $endDate,
            'status' => $status,
        ]);
    }

    public function test_un_cliente_al_corriente_no_debe_nada(): void
    {
        $company = $this->makeCompany();
        $customer = $this->makeCustomer($company);
        $this->makeRental($company, $customer, now()->addDays(5)->toDateString());

        $statement = (new AccountStatement())->forCustomer($customer);

        $this->assertTrue($statement->calculable);
        $this->assertSame(0.0, $statement->total);
        $this->assertNull($statement->owingSince);
        $this->assertCount(1, $statement->lines);
        $this->assertSame(0, $statement->lines[0]->overduePeriods);
    }

    public function test_diez_dias_vencido_con_semana_de_250_debe_500(): void
    {
        $company = $this->makeCompany(250, 7);
        $customer = $this->makeCustomer($company);
        $this->makeRental($company, $customer, now()->subDays(10)->toDateString(), 'vencida');

        $statement = (new AccountStatement())->forCustomer($customer);

        $this->assertSame(500.0, $statement->total);
        $this->assertSame(2, $statement->lines[0]->overduePeriods);
        $this->assertSame(
            now()->subDays(10)->toDateString(),
            $statement->owingSince->toDateString()
        );
    }

    public function test_siete_dias_exactos_cobra_un_solo_periodo(): void
    {
        $company = $this->makeCompany(250, 7);
        $customer = $this->makeCustomer($company);
        $this->makeRental($company, $customer, now()->subDays(7)->toDateString(), 'vencida');

        $this->assertSame(250.0, (new AccountStatement())->forCustomer($customer)->total);
    }

    public function test_un_solo_dia_vencido_ya_cobra_el_periodo_completo(): void
    {
        $company = $this->makeCompany(250, 7);
        $customer = $this->makeCustomer($company);
        $this->makeRental($company, $customer, now()->subDay()->toDateString(), 'vencida');

        $this->assertSame(250.0, (new AccountStatement())->forCustomer($customer)->total);
    }

    public function test_usa_el_precio_del_ultimo_pago_y_no_el_de_configuracion(): void
    {
        $company = $this->makeCompany(250, 7);
        $customer = $this->makeCustomer($company);
        $rental = $this->makeRental($company, $customer, now()->subDays(7)->toDateString(), 'vencida');

        // Tarifa especial: al cliente se le ha cobrado $200, no los $250 del negocio.
        $rental->payments()->create([
            'company_id' => $company->id,
            'amount' => 300,
            'payment_date' => now()->subDays(30)->toDateString(),
            'payment_method' => 'Efectivo',
            'status' => 'completado',
        ]);
        $rental->payments()->create([
            'company_id' => $company->id,
            'amount' => 200,
            'payment_date' => now()->subDays(14)->toDateString(),
            'payment_method' => 'Efectivo',
            'status' => 'completado',
        ]);

        $statement = (new AccountStatement())->forCustomer($customer);

        $this->assertSame(200.0, $statement->lines[0]->price);
        $this->assertSame(200.0, $statement->total);
    }

    public function test_ignora_los_pagos_que_no_estan_completados(): void
    {
        $company = $this->makeCompany(250, 7);
        $customer = $this->makeCustomer($company);
        $rental = $this->makeRental($company, $customer, now()->subDays(7)->toDateString(), 'vencida');

        $rental->payments()->create([
            'company_id' => $company->id,
            'amount' => 999,
            'payment_date' => now()->subDay()->toDateString(),
            'payment_method' => 'Efectivo',
            'status' => 'fallido',
        ]);

        $this->assertSame(250.0, (new AccountStatement())->forCustomer($customer)->total);
    }

    public function test_sin_configuracion_el_adeudo_no_es_calculable_en_vez_de_cero(): void
    {
        $company = $this->makeCompany(0, 0); // sin Setting
        $customer = $this->makeCustomer($company);
        $this->makeRental($company, $customer, now()->subDays(30)->toDateString(), 'vencida');

        $statement = (new AccountStatement())->forCustomer($customer);

        $this->assertFalse($statement->calculable);
        $this->assertFalse($statement->hasDebt());
        $this->assertSame(0.0, $statement->total);
    }

    public function test_con_precio_en_cero_tampoco_es_calculable(): void
    {
        $company = $this->makeCompany(0, 0);
        $company->settings()->create(['price' => 0, 'days_per_payment' => 7]);
        $customer = $this->makeCustomer($company->fresh());
        $this->makeRental($company, $customer, now()->subDays(30)->toDateString(), 'vencida');

        $this->assertFalse((new AccountStatement())->forCustomer($customer->fresh())->calculable);
    }

    public function test_suma_las_rentas_del_cliente_y_toma_la_fecha_mas_vieja(): void
    {
        $company = $this->makeCompany(250, 7);
        $customer = $this->makeCustomer($company);
        $this->makeRental($company, $customer, now()->subDays(10)->toDateString(), 'vencida'); // $500
        $this->makeRental($company, $customer, now()->subDays(3)->toDateString(), 'vencida');  // $250
        $this->makeRental($company, $customer, now()->addDays(5)->toDateString(), 'activa');   // $0

        $statement = (new AccountStatement())->forCustomer($customer);

        $this->assertSame(750.0, $statement->total);
        $this->assertCount(3, $statement->lines);
        $this->assertSame(
            now()->subDays(10)->toDateString(),
            $statement->owingSince->toDateString()
        );
    }

    public function test_las_rentas_completadas_y_canceladas_no_cuentan(): void
    {
        $company = $this->makeCompany(250, 7);
        $customer = $this->makeCustomer($company);
        $this->makeRental($company, $customer, now()->subDays(60)->toDateString(), 'completada');
        $this->makeRental($company, $customer, now()->subDays(60)->toDateString(), 'cancelada');

        $statement = (new AccountStatement())->forCustomer($customer);

        $this->assertSame(0.0, $statement->total);
        $this->assertCount(0, $statement->lines);
    }

    public function test_for_company_devuelve_solo_a_quienes_deben_de_mayor_a_menor(): void
    {
        $company = $this->makeCompany(250, 7);

        $alCorriente = $this->makeCustomer($company, 'Al Corriente');
        $this->makeRental($company, $alCorriente, now()->addDays(5)->toDateString());

        $debePoco = $this->makeCustomer($company, 'Debe Poco');
        $this->makeRental($company, $debePoco, now()->subDays(3)->toDateString(), 'vencida');

        $debeMucho = $this->makeCustomer($company, 'Debe Mucho');
        $this->makeRental($company, $debeMucho, now()->subDays(20)->toDateString(), 'vencida');

        $statements = (new AccountStatement())->forCompany($company);

        $this->assertCount(2, $statements);
        $this->assertSame('Debe Mucho', $statements[0]->customer->name);
        $this->assertSame(750.0, $statements[0]->total);
        $this->assertSame('Debe Poco', $statements[1]->customer->name);
        $this->assertSame(250.0, $statements[1]->total);
    }
}
