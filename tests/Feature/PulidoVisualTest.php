<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\SectionHeading;
use App\Models\Company;
use App\Support\PanelBanner;
use Filament\Facades\Filament;
use Filament\Support\Enums\MaxWidth;
use Filament\Widgets\WidgetConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PulidoVisualTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_el_panel_usa_el_logotipo_propio_y_acota_el_ancho(): void
    {
        $panel = Filament::getPanel('propietario');

        $this->assertSame(MaxWidth::SevenExtraLarge, $panel->getMaxContentWidth());

        // brandLogo acepta un closure; se evalúa para ver que devuelve la vista.
        $logo = $panel->getBrandLogo();
        $this->assertNotNull($logo);
        $this->assertStringContainsString('Renta', (string) $logo);
    }

    public function test_el_logotipo_es_svg_y_no_la_ilustracion_vieja(): void
    {
        $marca = (string) view('components.marca')->render();

        $this->assertStringContainsString('<svg', $marca);
        $this->assertStringContainsString('Renta', $marca);
        $this->assertStringContainsString('Fácil', $marca);
        $this->assertStringNotContainsString('logo.png', $marca);
    }

    /** El archivo viejo se conserva: lo usan el PWA, los PDF y las etiquetas de compartir. */
    public function test_la_ilustracion_anterior_se_conserva(): void
    {
        $this->assertFileExists(public_path('img/logo.png'));
    }

    public function test_el_escritorio_separa_sus_bloques_con_rotulos(): void
    {
        $widgets = (new Dashboard())->getWidgets();

        $rotulos = collect($widgets)
            ->filter(fn ($w) => $w instanceof WidgetConfiguration && $w->widget === SectionHeading::class)
            ->map(fn (WidgetConfiguration $w) => $w->getProperties()['titulo'])
            ->values()
            ->all();

        $this->assertSame(['Hoy', 'El dinero', 'Estado del negocio'], $rotulos);
    }

    public function test_el_rotulo_dibuja_el_texto_que_se_le_pasa(): void
    {
        $html = \Livewire\Livewire::test(SectionHeading::class, [
            'titulo' => 'El dinero',
            'descripcion' => 'Cómo va el mes.',
        ])->html();

        $this->assertStringContainsString('El dinero', $html);
        $this->assertStringContainsString('Cómo va el mes.', $html);
    }

    public function test_la_barra_del_demo_ya_no_es_morada(): void
    {
        $company = Company::create([
            'name' => 'Demo', 'phone' => '1', 'email' => 'd@x.com',
            'is_demo' => true, 'demo_expires_at' => now()->addDay(),
        ]);

        $html = PanelBanner::for($company->fresh());

        $this->assertStringNotContainsString('#7c3aed', $html);
        $this->assertStringContainsString('#0f172a', $html);
    }
}
