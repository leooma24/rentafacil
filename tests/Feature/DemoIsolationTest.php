<?php

namespace Tests\Feature;

use App\Http\Controllers\PlanCheckoutController;
use App\Models\Company;
use App\Models\Package;
use App\Services\DemoCompanyBuilder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DemoIsolationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * CompanyObserver asigna package_id 1 a toda empresa nueva y el AUTO_INCREMENT
     * de MySQL no se reinicia entre tests, así que el id va forzado.
     */
    private function seedPackage(): void
    {
        Package::forceCreate([
            'id' => 1, 'name' => 'Pro', 'max_clients' => 500, 'max_washers' => 500, 'price' => 999,
        ]);
    }

    public function test_los_comandos_programados_no_tocan_empresas_demo(): void
    {
        Mail::fake();
        $this->seedPackage();

        // WhatsAppService tipa su token como string y revienta si viene null.
        config([
            'services.whatsapp.token' => 'token-de-prueba',
            'services.whatsapp.phone_number_id' => '000',
        ]);

        $company = (new DemoCompanyBuilder())->build();
        $activasAntes = $company->rentals()->where('status', 'activa')->count();

        $this->artisan('rentals:mark-overdue')->assertSuccessful();
        $this->artisan('rentals:send-reminders')->assertSuccessful();
        $this->artisan('users:check-inactive')->assertSuccessful();
        $this->artisan('users:lifecycle-emails')->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertSame(
            $activasAntes,
            $company->rentals()->where('status', 'activa')->count(),
            'Un comando programado modificó rentas de una empresa demo.'
        );
    }

    /**
     * La ruta de checkout depende de que Filament haya resuelto el tenant, cosa
     * que solo pasa dentro del panel, así que el guard se prueba directo sobre
     * el controlador con el tenant puesto a mano.
     */
    public function test_una_empresa_demo_no_puede_llegar_a_stripe(): void
    {
        $this->seedPackage();

        $company = (new DemoCompanyBuilder())->build();
        $package = Package::first();

        $this->actingAs($company->members()->first());
        Filament::setTenant($company, true);

        $response = app(PlanCheckoutController::class)->checkout($package);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/propietario/registrar', $response->getTargetUrl());
    }

    public function test_cleanup_borra_solo_las_demos_vencidas(): void
    {
        $this->seedPackage();

        $vencida = (new DemoCompanyBuilder())->build();
        $vencida->update(['demo_expires_at' => now()->subHour()]);
        $vencidaId = $vencida->id;
        $usuarioVencido = $vencida->members()->first()->id;

        $viva = (new DemoCompanyBuilder())->build();

        $real = Company::create(['name' => 'Real', 'phone' => '9', 'email' => 'real@x.com']);

        $this->artisan('demo:cleanup')->assertSuccessful();

        $this->assertDatabaseMissing('companies', ['id' => $vencidaId]);
        $this->assertDatabaseMissing('users', ['id' => $usuarioVencido]);
        $this->assertDatabaseMissing('washing_machines', ['company_id' => $vencidaId]);
        $this->assertDatabaseMissing('payments', ['company_id' => $vencidaId]);

        $this->assertDatabaseHas('companies', ['id' => $viva->id]);
        $this->assertDatabaseHas('companies', ['id' => $real->id]);
        $this->assertGreaterThan(0, $viva->washingMachines()->count());
    }
}
