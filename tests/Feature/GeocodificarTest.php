<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Package;
use App\Models\User;
use App\Services\Geocoder;
use App\Services\UbicarClientes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Ubicar clientes en el mapa.
 *
 * En producción había 71 direcciones capturadas y ninguna con coordenadas,
 * porque la latitud había que escribirla de memoria. Sin coordenadas el
 * planificador de rutas no puede trazar nada.
 *
 * Las pruebas simulan el servicio de mapas: no se sale a internet desde aquí.
 */
class GeocodificarTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Role::findOrCreate('super_admin', 'web');

        if (! Package::find(1)) {
            Package::forceCreate([
                'id' => 1, 'name' => 'Pro', 'max_clients' => 500, 'max_washers' => 500, 'price' => 999,
            ]);
        }

        $this->company = Company::create(['name' => 'Lavandería', 'phone' => '1', 'email' => 'l@x.com']);

        $user = User::create(['name' => 'Dueño', 'email' => 'd@x.com', 'password' => bcrypt('s')]);
        $user->assignRole('super_admin');
        // Shield corre con define_via_gate en false: super_admin no se salta las
        // políticas, así que el permiso va explícito.
        $user->givePermissionTo(
            \Spatie\Permission\Models\Permission::findOrCreate('view_any_customer', 'web')
        );
        $this->company->members()->attach($user);
        $this->actingAs($user);
    }

    private function clienteCon(?float $lat = null, string $email = 'c@x.mx'): Customer
    {
        $cliente = $this->company->customers()->create([
            'name' => 'Cliente', 'email' => $email, 'phone' => '1',
        ]);

        $cliente->addresses()->create([
            'street' => 'Av. Álvaro Obregón',
            'number' => '1200',
            'city' => 'Culiacán',
            'postal_code' => '80000',
            'latitude' => $lat,
            'longitude' => $lat === null ? null : -107.39,
        ]);

        return $cliente;
    }

    private function respuestaConCoordenadas(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                ['lat' => '24.8049', 'lon' => '-107.3939'],
            ]),
        ]);
    }

    public function test_encuentra_las_coordenadas_de_una_direccion(): void
    {
        $this->respuestaConCoordenadas();

        $coordenadas = app(Geocoder::class)->buscar('Av. Álvaro Obregón 1200, Culiacán');

        $this->assertSame(24.8049, $coordenadas['lat']);
        $this->assertSame(-107.3939, $coordenadas['lng']);
    }

    public function test_devuelve_nulo_cuando_no_encuentra_nada(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([])]);

        $this->assertNull(app(Geocoder::class)->buscar('Calle que no existe 999'));
    }

    /** Si el servicio se cae, la app no se cae con él. */
    public function test_aguanta_que_el_servicio_de_mapas_falle(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response('', 503)]);

        $this->assertNull(app(Geocoder::class)->buscar('Av. Obregón 1200'));
    }

    public function test_guarda_la_ubicacion_en_la_direccion(): void
    {
        $this->respuestaConCoordenadas();

        $direccion = $this->clienteCon()->addresses->first();

        $this->assertFalse($direccion->hasCoordinates());
        $this->assertTrue(app(Geocoder::class)->ubicar($direccion));
        $this->assertTrue($direccion->fresh()->hasCoordinates());
    }

    /**
     * Cero no es una ubicación: es el punto nulo en el Atlántico, y una ruta
     * trazada hacia allá manda al dueño al mar.
     */
    public function test_cero_no_cuenta_como_ubicacion(): void
    {
        $direccion = new Address(['latitude' => 0, 'longitude' => 0]);

        $this->assertFalse($direccion->hasCoordinates());
    }

    public function test_la_direccion_se_manda_al_buscador_de_lo_particular_a_lo_general(): void
    {
        $direccion = $this->clienteCon()->addresses->first();

        $texto = app(Geocoder::class)->texto($direccion);

        $this->assertStringStartsWith('Av. Álvaro Obregón 1200', $texto);
        $this->assertStringEndsWith('México', $texto);
        $this->assertStringContainsString('Culiacán', $texto);
    }

    /**
     * Salió con los datos del demo: "Av. Álvaro Obregón 100, Primer Cuadro
     * (Centro), 81200, Los Mochis" no devuelve nada, y esa misma sin la colonia
     * cae justo en el punto.
     */
    public function test_si_la_direccion_completa_no_pega_se_prueba_sin_colonia(): void
    {
        $colonia = \App\Models\Neighborhood::create([
            'nombre' => 'Primer Cuadro (Centro)',
            'ciudad' => 'Los Mochis',
            'asentamiento' => 'Colonia',
            'codigo_postal' => '81200',
        ]);

        $direccion = $this->clienteCon()->addresses->first();
        $direccion->update(['neighborhood_id' => $colonia->id]);

        $intentos = app(Geocoder::class)->intentos($direccion->fresh());

        // El paréntesis se cae del primer intento y la colonia del segundo.
        $this->assertStringContainsString('Primer Cuadro,', $intentos[0]);
        $this->assertStringNotContainsString('(', $intentos[0]);
        $this->assertStringNotContainsString('Primer Cuadro', $intentos[1]);
    }

    /**
     * Caer al centro de la ciudad pondría a todos los clientes en el mismo
     * punto: el planificador los daría por ubicados y trazaría una ruta que no
     * lleva a ninguna casa.
     */
    public function test_nunca_se_conforma_con_el_centro_de_la_ciudad(): void
    {
        $direccion = $this->clienteCon()->addresses->first();

        foreach (app(Geocoder::class)->intentos($direccion) as $intento) {
            $this->assertStringContainsString(
                'Av. Álvaro Obregón',
                $intento,
                "El intento «{$intento}» ya no apunta al domicilio."
            );
        }
    }

    public function test_insiste_con_una_version_mas_simple_antes_de_rendirse(): void
    {
        Http::fakeSequence()
            ->push([])                                      // con colonia: nada
            ->push([['lat' => '25.7859', 'lon' => '-108.9914']]); // sin colonia: pega

        $direccion = $this->clienteCon()->addresses->first();

        // Sin pausa entre intentos la prueba no se lleva un segundo de más.
        $this->assertTrue(app(Geocoder::class)->ubicar($direccion));
        $this->assertTrue($direccion->fresh()->hasCoordinates());
    }

    public function test_ubica_a_varios_clientes_de_un_jalon(): void
    {
        $this->respuestaConCoordenadas();

        $clientes = collect([
            $this->clienteCon(email: 'a@x.mx'),
            $this->clienteCon(email: 'b@x.mx'),
        ]);

        app(UbicarClientes::class)->paraTodos($clientes);

        foreach ($clientes as $cliente) {
            $this->assertTrue($cliente->fresh()->addresses->first()->hasCoordinates());
        }
    }

    /** No se vuelve a preguntar por quien ya está ubicado: sería tráfico regalado. */
    public function test_no_vuelve_a_buscar_a_quien_ya_esta_ubicado(): void
    {
        $this->respuestaConCoordenadas();

        $cliente = $this->clienteCon(lat: 24.8049);

        app(UbicarClientes::class)->paraTodos(collect([$cliente]));

        Http::assertNothingSent();
    }

    /** La acción tiene que existir en la pantalla, no sólo el servicio detrás. */
    public function test_la_lista_de_clientes_ubica_a_los_seleccionados(): void
    {
        $this->respuestaConCoordenadas();

        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('propietario'));
        \Filament\Facades\Filament::setTenant($this->company, true);

        $cliente = $this->clienteCon();

        \Livewire\Livewire::test(
            \App\Filament\Resources\CustomerResource\Pages\ListCustomers::class,
            ['tenant' => $this->company],
        )
            ->assertOk()
            ->callTableBulkAction('ubicar_en_el_mapa', [$cliente]);

        $this->assertTrue($cliente->fresh()->addresses->first()->hasCoordinates());
    }

    /**
     * La tanda va acotada porque el servicio pide una consulta por segundo: sin
     * tope, seleccionar 100 clientes tumbaría la petición por tiempo de espera.
     */
    public function test_la_tanda_esta_acotada_y_avisa_cuantos_quedan(): void
    {
        $this->respuestaConCoordenadas();

        $clientes = collect(range(1, UbicarClientes::MAXIMO_POR_TANDA + 3))
            ->map(fn (int $i) => $this->clienteCon(email: "c{$i}@x.mx"));

        // Sin pausa: aquí se mide el tope de la tanda, no la cortesía con el servicio.
        (new UbicarClientes(app(Geocoder::class), pausa: 0))->paraTodos($clientes);

        $ubicados = $clientes->filter(
            fn (Customer $c) => $c->fresh()->addresses->first()->hasCoordinates()
        )->count();

        $this->assertSame(UbicarClientes::MAXIMO_POR_TANDA, $ubicados);
    }
}
