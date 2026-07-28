<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\Package;
use App\Support\PlanUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PlanUsageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function makePackages(): void
    {
        if (! Package::find(1)) {
            Package::forceCreate([
                'id' => 1, 'name' => 'Gratuito', 'max_clients' => 3, 'max_washers' => 3, 'price' => 0,
            ]);
        }
        if (! Package::find(2)) {
            Package::forceCreate([
                'id' => 2, 'name' => 'Ilimitado', 'max_clients' => 9999, 'max_washers' => 9999, 'price' => 899,
            ]);
        }
    }

    private function makeCompany(?int $packageId = 1, int $days = 30): Company
    {
        $this->makePackages();

        $company = Company::create(['name' => 'Lavandería', 'phone' => '1', 'email' => 'l' . uniqid() . '@x.com']);

        if ($packageId !== null) {
            $company->companyPackage()->create([
                'package_id' => $packageId,
                'start_date' => now(),
                'end_date' => now()->addDays($days),
            ]);
        }

        return $company->fresh();
    }

    private function addMachines(Company $company, int $n): void
    {
        for ($i = 0; $i < $n; $i++) {
            $company->washingMachines()->create([
                'machine_code' => 'LAV-' . uniqid(), 'brand' => 'Mabe', 'status' => 'disponible',
            ]);
        }
    }

    public function test_el_observer_ya_no_asigna_plan_al_crear_una_empresa(): void
    {
        $this->makePackages();

        $company = Company::create(['name' => 'Nueva', 'phone' => '1', 'email' => 'nueva@x.com']);

        $this->assertSame(
            0,
            $company->companyPackage()->count(),
            'Solo RegisterCompany debe decidir con qué plan arranca una empresa.'
        );
    }

    public function test_el_plan_efectivo_es_el_ultimo_asignado(): void
    {
        $company = $this->makeCompany(1, 30); // Gratuito, como lo hacía el observer

        // Después llega la prueba, igual que en RegisterCompany.
        $company->companyPackage()->create([
            'package_id' => 2,
            'start_date' => now(),
            'end_date' => now()->addDays(15),
        ]);

        $this->assertSame('Ilimitado', $company->fresh()->companyPackage->package->name);
    }

    public function test_reporta_el_plan_y_el_uso_contra_el_tope(): void
    {
        $company = $this->makeCompany(1, 30);
        $this->addMachines($company, 2);

        $usage = PlanUsage::for($company->fresh());

        $this->assertTrue($usage->hasPlan);
        $this->assertTrue($usage->isActive);
        $this->assertSame('Gratuito', $usage->planName);
        $this->assertSame('2 / 3', $usage->machinesLabel());
        $this->assertSame('0 / 3', $usage->customersLabel());
        $this->assertFalse($usage->machinesMaxed());
        $this->assertFalse($usage->isMaxedOut());
    }

    public function test_marca_topado_al_alcanzar_el_limite(): void
    {
        $company = $this->makeCompany(1, 30);
        $this->addMachines($company, 3);

        $usage = PlanUsage::for($company->fresh());

        $this->assertTrue($usage->machinesMaxed());
        $this->assertTrue($usage->isMaxedOut());
        $this->assertSame('3 / 3', $usage->machinesLabel());
    }

    public function test_una_empresa_sin_plan_no_esta_topada_sino_sin_plan(): void
    {
        $company = $this->makeCompany(null);
        $this->addMachines($company, 5);

        $usage = PlanUsage::for($company->fresh());

        $this->assertFalse($usage->hasPlan);
        $this->assertFalse($usage->isMaxedOut());
        $this->assertSame('Sin plan', $usage->planLabel());
        $this->assertSame('gray', $usage->planColor());
    }

    public function test_un_plan_vencido_se_marca_en_rojo(): void
    {
        $company = $this->makeCompany(1, -5);

        $usage = PlanUsage::for($company->fresh());

        $this->assertTrue($usage->hasPlan);
        $this->assertFalse($usage->isActive);
        $this->assertSame('Gratuito · vencido', $usage->planLabel());
        $this->assertSame('danger', $usage->planColor());
    }
}
