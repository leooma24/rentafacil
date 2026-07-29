<?php

namespace Tests\Feature;

use App\Console\Commands\CleanupDemos;
use App\Models\Company;
use App\Models\CustomerDocument;
use App\Models\Package;
use App\Services\DemoCompanyBuilder;
use App\Support\GuiaDelDemo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * La guía que recibe al visitante del demo, y que no quede basura al borrarlo.
 */
class GuiaDelDemoTest extends TestCase
{
    use RefreshDatabase;

    private function seedPackage(): void
    {
        Package::forceCreate([
            'id' => 1, 'name' => 'Pro', 'max_clients' => 500, 'max_washers' => 500, 'price' => 999,
        ]);
    }

    private function demo(): Company
    {
        $this->seedPackage();

        // Las direcciones de las pantallas se arman con el panel del
        // propietario, así que tiene que estar puesto aunque no naveguemos.
        \Filament\Facades\Filament::setCurrentPanel(
            \Filament\Facades\Filament::getPanel('propietario')
        );

        return (new DemoCompanyBuilder())->build();
    }

    /**
     * El visitante entraba, veía gráficas bonitas y se iba sin tocar un botón.
     * Lo que vende esta app —que cobrar extiende la fecha solo, que la ruta se
     * ordena sola— no se descubre mirando.
     */
    public function test_la_guia_propone_cosas_concretas_que_hacer(): void
    {
        $guia = GuiaDelDemo::for($this->demo());

        $this->assertGreaterThanOrEqual(6, $guia->total());
        $this->assertSame(0, $guia->vistos());
        $this->assertFalse($guia->termino());

        foreach ($guia->pasos as $paso) {
            $this->assertNotEmpty($paso['titulo']);
            $this->assertNotEmpty($paso['gancho']);
            $this->assertStringStartsWith('http', $paso['url'], "El paso {$paso['clave']} no lleva a ningún lado.");
        }
    }

    /**
     * "Ve a Rentas" no le dice nada a nadie. Los pasos se cuelgan de los datos
     * que este demo sí tiene, para que el visitante vea de qué se trata antes
     * de dar el clic.
     */
    public function test_los_pasos_nombran_a_los_clientes_del_demo(): void
    {
        $empresa = $this->demo();
        $guia = GuiaDelDemo::for($empresa);

        $nombres = $empresa->customers->pluck('name');
        $titulos = collect($guia->pasos)->pluck('titulo')->implode(' | ');

        $this->assertTrue(
            $nombres->contains(fn ($nombre) => str_contains($titulos, $nombre)),
            "Ningún paso nombra a un cliente de verdad: {$titulos}"
        );

        // Y el del cobrador lo nombra a él, que es de lo que más se pregunta.
        $cobrador = $empresa->members->first(fn ($u) => $u->hasRole('cobrador'));
        $this->assertStringContainsString($cobrador->name, $titulos);
    }

    public function test_el_paso_visitado_queda_marcado_y_avanza_el_siguiente(): void
    {
        $empresa = $this->demo();

        $primero = GuiaDelDemo::for($empresa)->siguiente();
        $this->assertNotNull($primero);

        GuiaDelDemo::marcarVisto($primero['clave']);

        $guia = GuiaDelDemo::for($empresa);

        $this->assertSame(1, $guia->vistos());
        $this->assertNotSame($primero['clave'], $guia->siguiente()['clave']);
        $this->assertTrue(
            collect($guia->pasos)->firstWhere('clave', $primero['clave'])['visto']
        );
    }

    public function test_la_guia_solo_se_ve_dentro_del_demo(): void
    {
        $demo = $this->demo();
        $real = Company::create(['name' => 'Real', 'phone' => '1', 'email' => 'r@x.com']);

        // setTenant exige un usuario autenticado.
        $this->actingAs($demo->members->first());

        \Filament\Facades\Filament::setTenant($real);
        $this->assertFalse(
            \App\Filament\Widgets\GuiaDemoWidget::canView(),
            'La guía del demo se le está enseñando a una cuenta real.'
        );

        \Filament\Facades\Filament::setTenant($demo);
        $this->assertTrue(\App\Filament\Widgets\GuiaDemoWidget::canView());
    }

    /**
     * Los papeles y las fotos del demo son archivos de verdad en disco, y
     * ningún borrado en cascada los alcanza: cada demo dejaba cuatro imágenes
     * ahí para siempre.
     */
    public function test_al_borrar_el_demo_no_quedan_archivos_ni_roles_sueltos(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('Sin GD no se generan las imágenes del demo.');
        }

        Storage::fake('local');
        Storage::fake('privado');

        $empresa = $this->demo();
        $usuarios = $empresa->members->pluck('id');

        $documentos = CustomerDocument::whereIn('customer_id', $empresa->customers()->select('id'))
            ->pluck('file_path');
        $fotos = $empresa->rentals
            ->filter(fn ($r) => filled($r->delivery_photos))
            ->flatMap(fn ($r) => $r->delivery_photos);

        $this->assertGreaterThan(0, $documentos->count());
        $this->assertGreaterThan(0, $fotos->count());

        $empresa->update(['demo_expires_at' => now()->subHour()]);

        $this->artisan(CleanupDemos::class)->assertSuccessful();

        $this->assertDatabaseMissing('companies', ['id' => $empresa->id]);

        foreach ($documentos as $ruta) {
            Storage::disk('local')->assertMissing($ruta);
        }

        foreach ($fotos as $ruta) {
            Storage::disk('privado')->assertMissing($ruta);
        }

        // model_has_roles es polimórfica y no tiene llave foránea a users:
        // nadie limpiaba estos renglones y se acumulaban uno por cada demo.
        $this->assertSame(
            0,
            \Illuminate\Support\Facades\DB::table('model_has_roles')
                ->where('model_type', \App\Models\User::class)
                ->whereIn('model_id', $usuarios)
                ->count()
        );
    }
}
