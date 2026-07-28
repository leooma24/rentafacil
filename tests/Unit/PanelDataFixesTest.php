<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Rental;
use App\Models\WashingMachine;
use App\Support\AccountStatement;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PanelDataFixesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function makeCompany(): Company
    {
        if (! Package::find(1)) {
            Package::forceCreate([
                'id' => 1, 'name' => 'Pro', 'max_clients' => 500, 'max_washers' => 500, 'price' => 999,
            ]);
        }

        $company = Company::create(['name' => 'Lavandería', 'phone' => '1', 'email' => 'l@x.com']);
        $company->settings()->create(['price' => 250, 'days_per_payment' => 7]);

        return $company->fresh();
    }

    private function makeCustomer(Company $company): Customer
    {
        return $company->customers()->create([
            'name' => 'Juan Pérez',
            'email' => 'juan' . uniqid() . '@ejemplo.mx',
            'phone' => '6681234567',
        ]);
    }

    private function makeMachine(Company $company, string $code): WashingMachine
    {
        return $company->washingMachines()->create([
            'machine_code' => $code, 'brand' => 'Mabe', 'status' => 'rentada',
        ]);
    }

    private function makeRental(
        Company $company,
        Customer $customer,
        WashingMachine $machine,
        string $endDate,
        string $status
    ): Rental {
        return $company->rentals()->create([
            'customer_id' => $customer->id,
            'washing_machine_id' => $machine->id,
            'start_date' => now()->subMonths(3)->toDateString(),
            'end_date' => $endDate,
            'status' => $status,
        ]);
    }

    public function test_el_promedio_de_dias_de_atraso_es_positivo(): void
    {
        $company = $this->makeCompany();
        $customer = $this->makeCustomer($company);
        $machine = $this->makeMachine($company, 'LAV-001');
        $this->makeRental($company, $customer, $machine, now()->subDays(10)->toDateString(), 'vencida');

        $overdue = $company->rentals()->where('status', 'vencida')->get();

        // Misma expresión que usa el widget.
        $avg = round($overdue->avg(
            fn ($r) => Carbon::parse($r->end_date)->startOfDay()->diffInDays(now()->startOfDay())
        ), 1);

        $this->assertSame(10.0, $avg, 'El atraso debe medirse de la fecha vencida hacia hoy.');
        $this->assertGreaterThan(0, $avg);
    }

    public function test_active_rental_ignora_las_rentas_ya_finalizadas_con_id_mayor(): void
    {
        $company = $this->makeCompany();
        $customer = $this->makeCustomer($company);
        $machine = $this->makeMachine($company, 'LAV-001');

        // La renta viva se crea primero (id menor)...
        $activa = $this->makeRental(
            $company, $customer, $machine, now()->subDays(6)->toDateString(), 'vencida'
        );

        // ...y después una finalizada de la misma lavadora, con id mayor.
        $this->makeRental(
            $company, $customer, $machine, now()->subDays(2)->toDateString(), 'completada'
        );

        $this->assertNotNull(
            $machine->fresh()->activeRental,
            'La lavadora tiene una renta vencida; activeRental no debería venir en nulo.'
        );
        $this->assertSame($activa->id, $machine->fresh()->activeRental->id);
        $this->assertSame('Juan Pérez', $machine->fresh()->activeRental->customer->name);
    }

    public function test_for_rental_da_lo_mismo_que_la_linea_del_estado_de_cuenta(): void
    {
        $company = $this->makeCompany();
        $customer = $this->makeCustomer($company);
        $machine = $this->makeMachine($company, 'LAV-001');
        $rental = $this->makeRental(
            $company, $customer, $machine, now()->subDays(10)->toDateString(), 'vencida'
        );

        $service = new AccountStatement();

        $porRenta = $service->forRental($rental);
        $delEstadoDeCuenta = $service->forCustomer($customer)->lines[0];

        $this->assertSame(500.0, $porRenta->amount);
        $this->assertSame($delEstadoDeCuenta->amount, $porRenta->amount);
        $this->assertSame($delEstadoDeCuenta->overduePeriods, $porRenta->overduePeriods);
    }

    public function test_for_rental_sin_configuracion_devuelve_cero(): void
    {
        $company = $this->makeCompany();
        $company->settings()->update(['price' => 0]);
        $customer = $this->makeCustomer($company);
        $machine = $this->makeMachine($company, 'LAV-001');
        $rental = $this->makeRental(
            $company, $customer, $machine, now()->subDays(10)->toDateString(), 'vencida'
        );

        $this->assertSame(0.0, (new AccountStatement())->forRental($rental->fresh())->amount);
    }
}
