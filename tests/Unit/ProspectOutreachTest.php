<?php

namespace Tests\Unit;

use App\Models\ProspectiveClient;
use App\Support\ProspectOutreach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ProspectOutreachTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function makeProspect(array $attrs = []): ProspectiveClient
    {
        return ProspectiveClient::create(array_merge([
            'name' => 'Don Chuy',
            'phone' => '6681234567',
            'city' => 'Los Mochis',
            'status' => 'nuevo',
            'source' => 'web_scraping',
        ], $attrs));
    }

    public function test_la_cola_deja_fuera_a_los_cerrados_y_a_los_que_no_tienen_telefono(): void
    {
        $nuevo = $this->makeProspect(['name' => 'Nuevo']);
        $this->makeProspect(['name' => 'Ya cliente', 'status' => 'cliente']);
        $this->makeProspect(['name' => 'No quiso', 'status' => 'no_interesado']);
        $this->makeProspect(['name' => 'Sin tel', 'phone' => null]);

        $cola = ProspectOutreach::queue();

        $this->assertCount(1, $cola);
        $this->assertSame($nuevo->id, $cola->first()->id);
    }

    public function test_los_nuevos_van_antes_que_los_ya_contactados(): void
    {
        $contactado = $this->makeProspect([
            'name' => 'Contactado', 'status' => 'contactado', 'last_contacted_at' => now()->subDay(),
        ]);
        $nuevo = $this->makeProspect(['name' => 'Nuevo']);

        $cola = ProspectOutreach::queue();

        $this->assertSame($nuevo->id, $cola[0]->id);
        $this->assertSame($contactado->id, $cola[1]->id);
    }

    public function test_se_puede_filtrar_por_ciudad(): void
    {
        $this->makeProspect(['name' => 'De Mochis', 'city' => 'Los Mochis']);
        $this->makeProspect(['name' => 'De Culiacán', 'city' => 'Culiacán']);

        $cola = ProspectOutreach::queue('Culiacán');

        $this->assertCount(1, $cola);
        $this->assertSame('De Culiacán', $cola->first()->name);
        $this->assertSame(2, ProspectOutreach::pendingCount());
        $this->assertSame(1, ProspectOutreach::pendingCount('Culiacán'));
    }

    public function test_un_numero_de_diez_digitos_recibe_la_lada_de_mexico(): void
    {
        $this->assertSame('526681234567', ProspectOutreach::whatsappNumber('6681234567'));
    }

    public function test_un_numero_que_ya_trae_lada_no_se_duplica(): void
    {
        $this->assertSame('526681234567', ProspectOutreach::whatsappNumber('526681234567'));
    }

    public function test_los_guiones_espacios_y_parentesis_se_van(): void
    {
        $this->assertSame('526681234567', ProspectOutreach::whatsappNumber('(668) 123-45-67'));
        $this->assertSame('526681234567', ProspectOutreach::whatsappNumber('+52 668 123 4567'));
    }

    public function test_cada_plantilla_lleva_el_nombre_y_la_liga_del_demo(): void
    {
        $prospect = $this->makeProspect(['name' => 'Doña Mary']);
        $demo = rtrim(config('app.url'), '/') . '/demo';

        foreach (array_keys(ProspectOutreach::PLANTILLAS) as $plantilla) {
            $mensaje = ProspectOutreach::message($prospect, $plantilla);

            $this->assertStringContainsString('Doña Mary', $mensaje, "La plantilla {$plantilla} no saluda por nombre.");
            $this->assertStringContainsString($demo, $mensaje, "La plantilla {$plantilla} no lleva el demo.");
            $this->assertStringContainsString('Renta Fácil', $mensaje);
        }
    }

    public function test_la_liga_de_whatsapp_lleva_el_numero_y_el_mensaje(): void
    {
        $prospect = $this->makeProspect(['name' => 'Don Chuy', 'phone' => '6681234567']);

        $url = ProspectOutreach::whatsappUrl($prospect, 'primero');

        $this->assertStringStartsWith('https://wa.me/526681234567?text=', $url);
        $this->assertStringContainsString(urlencode('Don Chuy'), $url);
    }

    public function test_las_ciudades_salen_de_los_prospectos_pendientes(): void
    {
        $this->makeProspect(['city' => 'Los Mochis']);
        $this->makeProspect(['city' => 'Culiacán']);
        $this->makeProspect(['city' => 'Guasave', 'status' => 'cliente']);

        $ciudades = ProspectOutreach::cities();

        $this->assertContains('Los Mochis', $ciudades);
        $this->assertContains('Culiacán', $ciudades);
        $this->assertNotContains('Guasave', $ciudades, 'Una ciudad sin pendientes no debería aparecer.');
    }
}
