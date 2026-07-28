<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\Package;
use App\Support\PanelBanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelBannerTest extends TestCase
{
    use RefreshDatabase;

    private function company(array $attrs, ?int $trialDays): Company
    {
        // CompanyObserver le asigna package_id 1 a toda empresa nueva, así que
        // el paquete tiene que existir antes y su asignación se reemplaza aquí.
        $package = Package::forceCreate([
            'id' => 1, 'name' => 'Pro', 'max_clients' => 9, 'max_washers' => 9, 'price' => 1,
        ]);

        $company = Company::create(array_merge(
            ['name' => 'X', 'phone' => '1', 'email' => 'x@x.com'],
            $attrs
        ));

        $company->companyPackage()->delete();

        // Sin precio, la barra avisa de eso antes que de nada del plan, que es
        // justo lo que estos tests quieren comprobar.
        $company->settings()->create(['price' => 250, 'days_per_payment' => 7]);

        if ($trialDays !== null) {
            $company->companyPackage()->create([
                'package_id' => $package->id,
                'start_date' => now(),
                'end_date' => now()->addDays($trialDays),
            ]);
        }

        return $company->fresh();
    }

    public function test_empresa_demo_ve_la_barra_de_demo(): void
    {
        $company = $this->company(
            ['is_demo' => true, 'demo_expires_at' => now()->addHours(24)],
            10
        );

        $html = PanelBanner::for($company);

        $this->assertStringContainsString('demo', strtolower($html));
        $this->assertStringContainsString('/propietario/registrar', $html);
        $this->assertStringNotContainsString('Prueba gratuita', $html);
    }

    public function test_empresa_en_prueba_ve_el_aviso_de_prueba(): void
    {
        $company = $this->company([], 10);

        $this->assertStringContainsString('Prueba gratuita', PanelBanner::for($company));
    }

    public function test_empresa_sin_plan_vigente_ve_el_aviso_de_expirado(): void
    {
        $company = $this->company([], -1);

        $this->assertStringContainsString('expirado', PanelBanner::for($company));
    }

    public function test_sin_tenant_no_hay_barra(): void
    {
        $this->assertSame('', PanelBanner::for(null));
    }
}
