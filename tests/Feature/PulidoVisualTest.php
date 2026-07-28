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

    public function test_la_hoja_de_estilos_del_panel_se_carga(): void
    {
        $this->assertFileExists(public_path('css/panel.css'));

        $provider = file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));
        $this->assertStringContainsString("url('css/panel.css')", $provider);
    }

    /**
     * Filament sólo trae compiladas las rejillas de 1 a 4 columnas. Un widget
     * que pida más no emite ninguna clase y sus tarjetas se apilan a renglón
     * completo cada una, que fue justo lo que se vio en producción. La regla
     * que lo repara vive en panel.css y se reconoce por el número de hijos, así
     * que si alguien agrega o quita una tarjeta hay que ajustar la regla.
     */
    public function test_los_widgets_de_mas_de_cuatro_tarjetas_tienen_regla_de_rejilla(): void
    {
        $css = file_get_contents(public_path('css/panel.css'));

        $widget = new \App\Filament\Widgets\BusinessAnalyticsWidget();
        $columnas = (fn () => $this->getColumns())->call($widget);

        $this->assertGreaterThan(
            4,
            $columnas,
            'Si ya bajó a cuatro o menos, la regla de panel.css sobra y esta prueba también.'
        );

        $this->assertStringContainsString(
            "nth-child({$columnas})",
            $css,
            "El widget pide {$columnas} columnas y panel.css no las cubre: se apilarán."
        );
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
