<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\Package;
use App\Support\Onboarding;
use App\Support\PanelBanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OnboardingTest extends TestCase
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

        return Company::create([
            'name' => 'Lavandería', 'phone' => '1', 'email' => 'l' . uniqid() . '@x.com',
        ])->fresh();
    }

    public function test_una_empresa_nueva_trae_los_cuatro_pasos_pendientes(): void
    {
        $onboarding = Onboarding::for($this->makeCompany());

        $this->assertCount(4, $onboarding->steps);
        $this->assertSame(4, $onboarding->pendingCount());
        $this->assertSame(0, $onboarding->doneCount());
        $this->assertFalse($onboarding->isComplete());
        $this->assertTrue($onboarding->needsPrice());
    }

    public function test_configurar_el_precio_palomea_solo_el_primer_paso(): void
    {
        $company = $this->makeCompany();
        $company->settings()->create(['price' => 250, 'days_per_payment' => 7]);

        $onboarding = Onboarding::for($company->fresh());

        $this->assertFalse($onboarding->needsPrice());
        $this->assertSame(1, $onboarding->doneCount());
        $this->assertSame(3, $onboarding->pendingCount());
        $this->assertFalse($onboarding->isComplete());
    }

    public function test_un_precio_en_cero_no_cuenta_como_configurado(): void
    {
        $company = $this->makeCompany();
        $company->settings()->create(['price' => 0, 'days_per_payment' => 7]);

        $this->assertTrue(Onboarding::for($company->fresh())->needsPrice());
    }

    public function test_con_los_cuatro_pasos_hechos_queda_completo(): void
    {
        $company = $this->makeCompany();
        $company->settings()->create(['price' => 250, 'days_per_payment' => 7]);

        $machine = $company->washingMachines()->create([
            'machine_code' => 'LAV-001', 'brand' => 'Mabe', 'status' => 'rentada',
        ]);
        $customer = $company->customers()->create([
            'name' => 'Juan', 'email' => 'juan' . uniqid() . '@x.com', 'phone' => '1',
        ]);
        $company->rentals()->create([
            'customer_id' => $customer->id,
            'washing_machine_id' => $machine->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
            'status' => 'activa',
        ]);

        $onboarding = Onboarding::for($company->fresh());

        $this->assertTrue($onboarding->isComplete());
        $this->assertSame(0, $onboarding->pendingCount());
    }

    public function test_la_barra_avisa_cuando_falta_el_precio(): void
    {
        $company = $this->makeCompany();

        $html = PanelBanner::for($company);

        $this->assertStringContainsString('precio de renta', $html);
        $this->assertStringContainsString('/configuracion', $html);
    }

    public function test_la_barra_deja_de_avisar_cuando_ya_hay_precio(): void
    {
        $company = $this->makeCompany();
        $company->settings()->create(['price' => 250, 'days_per_payment' => 7]);

        $this->assertStringNotContainsString('precio de renta', PanelBanner::for($company->fresh()));
    }

    public function test_una_empresa_demo_no_ve_el_aviso_de_precio(): void
    {
        $company = Company::create([
            'name' => 'Demo', 'phone' => '1', 'email' => 'd@x.com',
            'is_demo' => true, 'demo_expires_at' => now()->addDay(),
        ]);

        $html = PanelBanner::for($company->fresh());

        $this->assertStringContainsString('demo', strtolower($html));
        $this->assertStringNotContainsString('precio de renta', $html);
    }
}
