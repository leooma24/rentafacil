<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\Package;
use App\Models\Rental;
use App\Models\User;
use App\Models\WashingMachine;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Entrega con evidencia y documentos del cliente.
 *
 * Existía "Recoger" pero no "Entregar": sin foto ni acuse no hay con qué responder
 * al "así me la entregaste". Y los clientes sólo guardaban nombre, correo y
 * teléfono: no había dónde poner la INE, que es lo que permite recuperar un
 * aparato cuando alguien se muda con él.
 */
class EntregaYDocumentosTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $dueno;
    private Customer $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('public');
        // Las fotos viven en el disco privado: la carpeta pública se sirve tal
        // cual en /storage/... y las bajaba cualquiera con la liga.
        Storage::fake('privado');

        if (! Package::find(1)) {
            Package::forceCreate([
                'id' => 1, 'name' => 'Pro', 'max_clients' => 500, 'max_washers' => 500, 'price' => 999,
            ]);
        }

        $this->company = Company::create(['name' => 'Lavandería', 'phone' => '1', 'email' => 'l@x.com']);
        $this->company->settings()->create(['price' => 250, 'days_per_payment' => 7]);

        $this->dueno = User::create(['name' => 'Dueño', 'email' => 'd@x.com', 'password' => bcrypt('s')]);
        $this->dueno->assignRole(Role::findOrCreate('propietario', 'web'));
        $this->dueno->givePermissionTo([
            Permission::findOrCreate('view_any_washing::machine', 'web'),
            Permission::findOrCreate('view_any_customer', 'web'),
        ]);
        $this->company->members()->attach($this->dueno);
        $this->actingAs($this->dueno);

        Filament::setCurrentPanel(Filament::getPanel('propietario'));
        Filament::setTenant($this->company, true);

        $this->cliente = $this->company->customers()->create([
            'name' => 'Cliente', 'email' => 'c@x.mx', 'phone' => '1',
        ]);
    }

    private function equipoRentado(string $codigo = 'LAV-001', array $extra = []): array
    {
        $equipo = $this->company->washingMachines()->create([
            'machine_code' => $codigo, 'brand' => 'Mabe', 'status' => 'rentada',
        ]);

        $renta = $this->company->rentals()->create(array_merge([
            'customer_id' => $this->cliente->id,
            'washing_machine_id' => $equipo->id,
            'start_date' => now()->subDays(3)->toDateString(),
            'end_date' => now()->addDays(4)->toDateString(),
            'status' => 'activa',
        ], $extra));

        return [$equipo, $renta];
    }

    // --- Entrega ---

    public function test_una_renta_nueva_nace_sin_entregar(): void
    {
        [, $renta] = $this->equipoRentado();

        $this->assertFalse($renta->isDelivered());
        $this->assertTrue($renta->needsDelivery());
    }

    /**
     * A las 160 rentas que ya existen no se les va a pedir una entrega que
     * ocurrió antes de que la app supiera registrarlas.
     */
    public function test_una_renta_ya_cerrada_no_pide_entrega(): void
    {
        [, $renta] = $this->equipoRentado('LAV-002', ['status' => 'completada']);

        $this->assertFalse($renta->needsDelivery());
    }

    public function test_registrar_la_entrega_guarda_fotos_notas_y_fecha(): void
    {
        [$equipo, $renta] = $this->equipoRentado();

        Livewire::test(
            \App\Filament\Resources\WashingMachineResource\Pages\ListWashingMachines::class,
            ['tenant' => $this->company],
        )
            ->assertOk()
            ->callTableAction('entregar', $equipo, [
                'delivery_photos' => [UploadedFile::fake()->image('tambor.jpg')],
                'delivery_notes' => 'Funcionando, sin golpes.',
            ]);

        $renta->refresh();

        $this->assertTrue($renta->isDelivered());
        $this->assertFalse($renta->needsDelivery());
        $this->assertSame('Funcionando, sin golpes.', $renta->delivery_notes);
        $this->assertCount(1, $renta->delivery_photos);
        Storage::disk('privado')->assertExists($renta->delivery_photos[0]);
    }

    /** Se puede entregar sin fotos, pero la app lo dice en vez de callarse. */
    public function test_se_puede_entregar_sin_fotos(): void
    {
        [$equipo, $renta] = $this->equipoRentado();

        Livewire::test(
            \App\Filament\Resources\WashingMachineResource\Pages\ListWashingMachines::class,
            ['tenant' => $this->company],
        )->callTableAction('entregar', $equipo, ['delivery_notes' => 'De prisa.']);

        $this->assertTrue($renta->fresh()->isDelivered());
        $this->assertSame([], $renta->fresh()->delivery_photos);
    }

    /**
     * Las fotos de recolección son las que le dan sentido a las de entrega: sin
     * el después, el antes no compara contra nada.
     */
    public function test_al_recoger_se_guardan_las_fotos_de_como_lo_devolvieron(): void
    {
        [$equipo, $renta] = $this->equipoRentado();

        Livewire::test(
            \App\Filament\Resources\WashingMachineResource\Pages\ListWashingMachines::class,
            ['tenant' => $this->company],
        )->callTableAction('pick_up', $equipo, [
            'pickup_photos' => [UploadedFile::fake()->image('devuelta.jpg')],
        ]);

        $renta->refresh();

        $this->assertSame('completada', $renta->status);
        $this->assertCount(1, $renta->pickup_photos);
        Storage::disk('privado')->assertExists($renta->pickup_photos[0]);
    }

    // --- Documentos del cliente ---

    public function test_se_guarda_la_ine_del_cliente(): void
    {
        $documento = $this->cliente->documents()->create([
            'type' => 'ine',
            'file_path' => 'documentos-clientes/ine.jpg',
        ]);

        $this->assertSame('INE / identificación', $documento->typeLabel());
        $this->assertSame($this->dueno->id, $documento->uploaded_by, 'No quedó anotado quién lo subió.');
        $this->assertCount(1, $this->cliente->fresh()->documents);
    }

    /**
     * Borrar el registro tiene que llevarse el archivo: dejar la identificación de
     * alguien tirada en el disco sin que nadie sepa que está ahí es peor que no
     * haberla guardado.
     */
    public function test_borrar_el_documento_borra_el_archivo(): void
    {
        // Disco privado: estos papeles no se sirven por el navegador.
        Storage::fake('local');
        Storage::disk('local')->put('documentos-clientes/ine.jpg', 'contenido');

        $documento = $this->cliente->documents()->create([
            'type' => 'ine',
            'file_path' => 'documentos-clientes/ine.jpg',
        ]);

        Storage::disk('local')->assertExists('documentos-clientes/ine.jpg');

        $documento->delete();

        Storage::disk('local')->assertMissing('documentos-clientes/ine.jpg');
    }

    public function test_borrar_al_cliente_se_lleva_sus_documentos(): void
    {
        $this->cliente->documents()->create(['type' => 'ine', 'file_path' => 'x.jpg']);

        $this->cliente->forceDelete();

        $this->assertSame(0, CustomerDocument::count());
    }

    /** Un cobrador no tiene por qué ver la identificación de nadie. */
    public function test_el_cobrador_no_ve_los_documentos_del_cliente(): void
    {
        $cobrador = User::create(['name' => 'Beto', 'email' => 'b@x.com', 'password' => bcrypt('s')]);
        $cobrador->assignRole(Role::findOrCreate('cobrador', 'web'));
        $this->company->members()->attach($cobrador);

        $this->actingAs($cobrador);

        $this->assertFalse(
            \App\Filament\Resources\CustomerResource\RelationManagers\DocumentsRelationManager::canViewForRecord(
                $this->cliente,
                \App\Filament\Resources\CustomerResource\Pages\EditCustomer::class
            )
        );
    }

    public function test_el_dueno_si_los_ve(): void
    {
        $this->assertTrue(
            \App\Filament\Resources\CustomerResource\RelationManagers\DocumentsRelationManager::canViewForRecord(
                $this->cliente,
                \App\Filament\Resources\CustomerResource\Pages\EditCustomer::class
            )
        );
    }
}
