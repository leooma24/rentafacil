<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Los papeles del cliente no se sirven por el navegador.
 *
 * Se descubrió probando: storage/app/public se sirve tal cual en /storage/...,
 * y el .htaccess de ahí sólo bloquea archivos .php. Todo lo demás —incluidas las
 * identificaciones oficiales que se empezaron a guardar— lo bajaba cualquiera
 * con la liga, sin sesión.
 *
 * Ahora viven en el disco privado y salen por una ruta que comprueba quién las
 * pide.
 */
class PrivacidadDeArchivosTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $dueno;
    private Customer $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');

        if (! Package::find(1)) {
            Package::forceCreate([
                'id' => 1, 'name' => 'Pro', 'max_clients' => 500, 'max_washers' => 500, 'price' => 999,
            ]);
        }

        $this->company = Company::create(['name' => 'Lavandería', 'phone' => '1', 'email' => 'l@x.com']);

        $this->dueno = User::create(['name' => 'Dueño', 'email' => 'd@x.com', 'password' => bcrypt('s')]);
        $this->dueno->assignRole(Role::findOrCreate('propietario', 'web'));
        $this->company->members()->attach($this->dueno);

        $this->cliente = $this->company->customers()->create([
            'name' => 'Cliente', 'email' => 'c@x.mx', 'phone' => '1',
        ]);
    }

    private function documento(?Customer $de = null): CustomerDocument
    {
        $de ??= $this->cliente;

        Storage::disk('local')->put('documentos-clientes/ine.jpg', 'contenido de la ine');

        return $de->documents()->create([
            'type' => 'ine',
            'file_path' => 'documentos-clientes/ine.jpg',
            'original_name' => 'ine.jpg',
        ]);
    }

    /** El caso que motivó todo: sin sesión, nada. */
    public function test_sin_sesion_no_se_puede_ver_un_documento(): void
    {
        $documento = $this->documento();

        // Manda a iniciar sesión, no truena. La ruta 'login' no existía y
        // cualquier ruta protegida respondía 500.
        $this->get(route('documentos.ver', $documento))
            ->assertRedirect(route('login'));
    }

    /** Y esa ruta lleva al acceso del panel, no a un callejón sin salida. */
    public function test_la_ruta_de_acceso_lleva_al_panel(): void
    {
        $this->get(route('login'))
            ->assertRedirect('/propietario/login');
    }

    public function test_el_dueño_si_puede_ver_los_de_sus_clientes(): void
    {
        $documento = $this->documento();

        $this->actingAs($this->dueno)
            ->get(route('documentos.ver', $documento))
            ->assertOk();
    }

    /**
     * El candado que de verdad importa: sin él, un dueño podría pedir el
     * documento de un cliente de otra lavandería cambiando el número en la liga.
     */
    public function test_un_dueño_no_alcanza_los_documentos_de_otra_empresa(): void
    {
        $otra = Company::create(['name' => 'Otra', 'phone' => '2', 'email' => 'o@x.com']);
        $ajeno = $otra->customers()->create([
            'name' => 'Ajeno', 'email' => 'a@x.mx', 'phone' => '1',
        ]);

        $documento = $this->documento($ajeno);

        $this->actingAs($this->dueno)
            ->get(route('documentos.ver', $documento))
            ->assertForbidden();
    }

    /** Un cobrador no tiene por qué ver la identificación de nadie. */
    public function test_el_cobrador_no_puede_ver_documentos(): void
    {
        $cobrador = User::create(['name' => 'Beto', 'email' => 'b@x.com', 'password' => bcrypt('s')]);
        $cobrador->assignRole(Role::findOrCreate('cobrador', 'web'));
        $this->company->members()->attach($cobrador);

        $documento = $this->documento();

        $this->actingAs($cobrador)
            ->get(route('documentos.ver', $documento))
            ->assertForbidden();
    }

    public function test_un_documento_cuyo_archivo_ya_no_esta_da_404_y_no_truena(): void
    {
        $documento = $this->cliente->documents()->create([
            'type' => 'ine',
            'file_path' => 'documentos-clientes/no-existe.jpg',
        ]);

        $this->actingAs($this->dueno)
            ->get(route('documentos.ver', $documento))
            ->assertNotFound();
    }

    /**
     * Los archivos van al disco privado, no al público. Si alguien vuelve a
     * apuntarlos al público, esta prueba lo dice.
     */
    public function test_los_documentos_se_guardan_en_el_disco_privado(): void
    {
        $manager = file_get_contents(app_path(
            'Filament/Resources/CustomerResource/RelationManagers/DocumentsRelationManager.php'
        ));

        $this->assertStringContainsString("->disk('local')", $manager);
        $this->assertStringContainsString("->visibility('private')", $manager);
        $this->assertStringNotContainsString("Storage::disk('public')->url", $manager);
    }

    /** Lo mismo para el Excel de importación, que trae la lista de clientes. */
    public function test_las_importaciones_no_quedan_en_una_carpeta_publica(): void
    {
        foreach ([
            'Filament/Resources/CustomerResource.php',
            'Filament/Resources/WashingMachineResource.php',
        ] as $archivo) {
            $codigo = file_get_contents(app_path($archivo));

            $this->assertStringNotContainsString(
                "storage_path('app/public/' . \$data['file'])",
                $codigo,
                "{$archivo} sigue leyendo la importación desde la carpeta pública."
            );
            $this->assertStringContainsString("->directory('importaciones')", $codigo);
        }
    }

    /** Y se borra en cuanto se leyó: no tiene por qué quedarse guardado. */
    public function test_el_excel_se_borra_despues_de_importar(): void
    {
        foreach ([
            'Filament/Resources/CustomerResource.php',
            'Filament/Resources/WashingMachineResource.php',
        ] as $archivo) {
            $this->assertStringContainsString(
                "->delete(\$data['file'])",
                file_get_contents(app_path($archivo)),
                "{$archivo} deja el Excel guardado después de importarlo."
            );
        }
    }
}
