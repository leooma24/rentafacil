<?php

namespace Tests\Feature;

use App\Filament\Pages\Prospeccion;
use App\Models\Company;
use App\Models\Package;
use App\Models\ProspectiveClient;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProspeccionPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        Role::findOrCreate('super_admin', 'web');
        Role::findOrCreate('propietario', 'web');
    }

    private function actingAsAdmin(): Company
    {
        if (! Package::find(1)) {
            Package::forceCreate([
                'id' => 1, 'name' => 'Pro', 'max_clients' => 500, 'max_washers' => 500, 'price' => 999,
            ]);
        }

        $company = Company::create(['name' => 'Sede', 'phone' => '1', 'email' => 'sede@x.com']);
        $company->settings()->create(['price' => 250, 'days_per_payment' => 7]);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@x.com', 'password' => bcrypt('secret'),
        ]);
        $admin->assignRole('super_admin');
        $company->members()->attach($admin);

        $this->actingAs($admin);
        Filament::setTenant($company->fresh(), true);

        return $company->fresh();
    }

    private function makeProspect(string $name, array $attrs = []): ProspectiveClient
    {
        return ProspectiveClient::create(array_merge([
            'name' => $name,
            'phone' => '668' . random_int(1000000, 9999999),
            'city' => 'Los Mochis',
            'status' => 'nuevo',
            'source' => 'web_scraping',
        ], $attrs));
    }

    public function test_la_pantalla_muestra_al_prospecto_en_turno(): void
    {
        $this->actingAsAdmin();
        $this->makeProspect('Don Chuy');

        Livewire::test(Prospeccion::class)
            ->assertOk()
            ->assertSee('Don Chuy')
            ->assertSee('Abrir WhatsApp');
    }

    public function test_calificar_guarda_el_estado_y_trae_al_siguiente(): void
    {
        $this->actingAsAdmin();
        $primero = $this->makeProspect('Primero');
        $segundo = $this->makeProspect('Segundo');

        $componente = Livewire::test(Prospeccion::class);
        $this->assertSame($primero->id, $componente->instance()->getProspect()->id);

        $componente->call('calificar', 'no_interesado');

        $this->assertSame('no_interesado', $primero->fresh()->status);
        $this->assertSame($segundo->id, $componente->instance()->getProspect()->id);
    }

    public function test_abrir_whatsapp_marca_como_contactado(): void
    {
        $this->actingAsAdmin();
        $prospect = $this->makeProspect('Don Chuy');

        Livewire::test(Prospeccion::class)->call('marcarContactado');

        $this->assertSame('contactado', $prospect->fresh()->status);
        $this->assertNotNull($prospect->fresh()->last_contacted_at);
    }

    public function test_saltar_no_cambia_el_estado_pero_pasa_al_siguiente(): void
    {
        $this->actingAsAdmin();
        $primero = $this->makeProspect('Primero');
        $segundo = $this->makeProspect('Segundo');

        $componente = Livewire::test(Prospeccion::class)->call('saltar');

        $this->assertSame('nuevo', $primero->fresh()->status);
        $this->assertSame($segundo->id, $componente->instance()->getProspect()->id);
    }

    public function test_sin_pendientes_la_pantalla_lo_dice_y_no_truena(): void
    {
        $this->actingAsAdmin();
        $this->makeProspect('Ya cliente', ['status' => 'cliente']);

        Livewire::test(Prospeccion::class)
            ->assertOk()
            ->assertSee('No queda nadie por contactar');
    }
}
