<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Package;
use App\Models\User;
use App\Support\PanoramaSaaS;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * El escritorio de quien opera RentaFácil.
 *
 * Lo que más importa aquí es que las demos NO cuenten. Una demo trae 17 equipos
 * y 200 pagos de mentiras: mezclarlas fue justo lo que hizo que las cifras se
 * vieran cuatro veces mejor de lo que están.
 */
class PanoramaSaaSTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        if (! Package::find(1)) {
            Package::forceCreate([
                'id' => 1, 'name' => 'Pro', 'max_clients' => 500, 'max_washers' => 500, 'price' => 999,
            ]);
        }

        $this->admin = User::create(['name' => 'Admin', 'email' => 'a@x.com', 'password' => bcrypt('s')]);
        $this->admin->assignRole(Role::findOrCreate('super_admin', 'web'));
    }

    private function empresa(string $nombre, bool $demo = false, ?int $creadaHace = null): Company
    {
        $company = Company::create([
            'name' => $nombre,
            'phone' => '6681234567',
            'email' => strtolower(str_replace(' ', '', $nombre)) . '@x.com',
            'is_demo' => $demo,
        ]);

        if ($creadaHace !== null) {
            $company->forceFill(['created_at' => now()->subDays($creadaHace)])->save();
        }

        return $company->fresh();
    }

    private function conEquipo(Company $company): Company
    {
        $company->washingMachines()->create([
            'machine_code' => 'LAV-' . fake()->unique()->numberBetween(100, 999),
            'brand' => 'Mabe', 'status' => 'disponible',
        ]);

        return $company;
    }

    private function conRentaYPago(Company $company, bool $conPago = true): Company
    {
        $this->conEquipo($company);

        $cliente = $company->customers()->create([
            'name' => 'C', 'email' => uniqid() . '@x.mx', 'phone' => '1',
        ]);

        $renta = $company->rentals()->create([
            'customer_id' => $cliente->id,
            'washing_machine_id' => $company->washingMachines()->first()->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
            'status' => 'activa',
        ]);

        if ($conPago) {
            $renta->payments()->create([
                'company_id' => $company->id,
                'amount' => 250,
                'payment_date' => today()->toDateString(),
                'payment_method' => 'Efectivo',
                'status' => 'completado',
            ]);
        }

        return $company;
    }

    /** LA prueba que importa: una demo no puede inflar el embudo. */
    public function test_las_demos_no_cuentan_en_ningun_paso(): void
    {
        $this->conRentaYPago($this->empresa('Demo viva', demo: true));
        $this->empresa('Real sin nada', creadaHace: 10);

        $panorama = PanoramaSaaS::actual();

        $this->assertSame(1, $panorama->registradas, 'La demo se coló en el total.');
        $this->assertSame(0, $panorama->conEquipo, 'La demo se coló en el paso de equipos.');
        $this->assertSame(0, $panorama->rentando);
        $this->assertSame(0, $panorama->cobrando);
    }

    public function test_el_embudo_cuenta_cada_paso(): void
    {
        $this->empresa('Sólo registrada', creadaHace: 10);
        $this->conEquipo($this->empresa('Cargó equipo', creadaHace: 10));
        $this->conRentaYPago($this->empresa('Renta sin cobrar', creadaHace: 10), conPago: false);
        $this->conRentaYPago($this->empresa('Cobra', creadaHace: 10));

        $panorama = PanoramaSaaS::actual();

        $this->assertSame(4, $panorama->registradas);
        $this->assertSame(3, $panorama->conEquipo);
        $this->assertSame(2, $panorama->rentando);
        $this->assertSame(1, $panorama->cobrando);

        $this->assertSame(75, $panorama->porcentaje($panorama->conEquipo));
        $this->assertSame(25, $panorama->porcentaje($panorama->cobrando));
    }

    public function test_sin_cuentas_el_porcentaje_no_truena(): void
    {
        $this->assertSame(0, PanoramaSaaS::actual()->porcentaje(0));
    }

    // --- Atoradas ---

    public function test_una_cuenta_sin_equipo_aparece_como_atorada(): void
    {
        $this->empresa('Atorada', creadaHace: 10);

        $this->assertSame(1, PanoramaSaaS::atoradas()->count());
    }

    /** Una cuenta de hoy no está atorada: va empezando. */
    public function test_una_cuenta_recien_creada_no_cuenta_como_atorada(): void
    {
        $this->empresa('Nuevita');

        $this->assertSame(0, PanoramaSaaS::atoradas()->count());
    }

    public function test_una_cuenta_que_ya_cargo_equipo_no_esta_atorada(): void
    {
        $this->conEquipo($this->empresa('Arrancó', creadaHace: 10));

        $this->assertSame(0, PanoramaSaaS::atoradas()->count());
    }

    public function test_las_demos_nunca_salen_como_atoradas(): void
    {
        $this->empresa('Demo', demo: true, creadaHace: 10);

        $this->assertSame(0, PanoramaSaaS::atoradas()->count());
    }

    /** Llegaron más lejos: cargaron equipo pero nunca cobraron. */
    public function test_las_que_cargaron_y_no_cobran_se_listan_aparte(): void
    {
        $this->conEquipo($this->empresa('A medias', creadaHace: 10));
        $this->conRentaYPago($this->empresa('Completa', creadaHace: 10));

        $sinCobrar = PanoramaSaaS::sinCobrar();

        $this->assertCount(1, $sinCobrar);
        $this->assertSame('A medias', $sinCobrar->first()->name);
    }

    // --- La pantalla ---

    public function test_solo_el_administrador_entra(): void
    {
        $empresa = $this->conEquipo($this->empresa('Lavandería'));
        $empresa->members()->attach($this->admin);

        $dueno = User::create(['name' => 'Dueño', 'email' => 'd@x.com', 'password' => bcrypt('s')]);
        $dueno->assignRole(Role::findOrCreate('propietario', 'web'));
        $empresa->members()->attach($dueno);

        $this->actingAs($dueno)
            ->get("/propietario/{$empresa->id}/escritorio")
            ->assertForbidden();
    }

    public function test_la_pantalla_abre_y_enseña_el_embudo(): void
    {
        $empresa = $this->empresa('Lavandería', creadaHace: 10);
        $empresa->members()->attach($this->admin);

        $this->actingAs($this->admin)
            ->get("/propietario/{$empresa->id}/escritorio")
            ->assertOk()
            ->assertSee('Cuentas registradas')
            ->assertSee('Cargaron equipo')
            ->assertSee('Han cobrado');
    }

    /** La lista trae el contacto, porque el objetivo es marcarles. */
    public function test_la_lista_de_atoradas_enseña_a_quien_marcarle(): void
    {
        $empresa = $this->empresa('Lavandería del Norte', creadaHace: 10);
        $empresa->members()->attach($this->admin);

        $this->actingAs($this->admin)
            ->get("/propietario/{$empresa->id}/escritorio")
            ->assertOk()
            ->assertSee('Lavandería del Norte')
            ->assertSee('6681234567');
    }

    /**
     * Una cuenta con el plan vencido que alcanzó a cargar equipos NO es lo
     * mismo que una que se registró y nunca abrió, y desde el escritorio se
     * veían igual: la de equipos no salía en ninguna lista.
     *
     * Que haya cargado sus aparatos quiere decir que ya sabe para qué sirve
     * esto, y es justamente a quien conviene marcarle.
     */
    public function test_la_cuenta_vencida_que_si_cargo_equipos_aparece_con_cuantos(): void
    {
        $vencida = $this->empresa('Lavandería El Águila', creadaHace: 90);
        $vencida->companyPackage()->create([
            'package_id' => 1,
            'start_date' => now()->subDays(60),
            'end_date' => now()->subDays(30),
        ]);

        for ($i = 0; $i < 8; $i++) {
            $vencida->washingMachines()->create([
                'machine_code' => 'AGU-' . $i,
                'brand' => 'Mabe',
                'status' => 'disponible',
            ]);
        }

        $this->assertFalse($vencida->fresh()->hasActivePackage(), 'La cuenta debía quedar vencida.');

        $usaron = PanoramaSaaS::queLoUsaron();

        $this->assertTrue(
            $usaron->contains('id', $vencida->id),
            'La cuenta vencida con ocho equipos no aparece en ningún lado.'
        );
        $this->assertSame(8, $usaron->firstWhere('id', $vencida->id)->washing_machines_count);
    }

    /** Quien nunca cargó nada no se cuela a esta lista: para eso está la otra. */
    public function test_quien_no_cargo_nada_no_aparece_entre_los_que_lo_usaron(): void
    {
        $sinNada = $this->empresa('Nunca Arrancó', creadaHace: 30);

        $this->assertFalse(PanoramaSaaS::queLoUsaron()->contains('id', $sinNada->id));
    }

    /** Y las demos siguen fuera: traen 18 equipos de mentiras cada una. */
    public function test_las_demos_no_se_cuelan_entre_los_que_lo_usaron(): void
    {
        $demo = $this->conEquipo($this->empresa('Lavandería Demo', demo: true, creadaHace: 1));

        $this->assertFalse(PanoramaSaaS::queLoUsaron()->contains('id', $demo->id));
    }

    /** Se ordenan por cuánto cargaron: arriba el que más lejos llegó. */
    public function test_los_que_mas_cargaron_salen_primero(): void
    {
        $poco = $this->conEquipo($this->empresa('Con Una', creadaHace: 20));

        $mucho = $this->empresa('Con Cinco', creadaHace: 20);
        for ($i = 0; $i < 5; $i++) {
            $mucho->washingMachines()->create([
                'machine_code' => 'CIN-' . $i,
                'brand' => 'Mabe',
                'status' => 'disponible',
            ]);
        }

        $usaron = PanoramaSaaS::queLoUsaron();

        $this->assertSame($mucho->id, $usaron->first()->id);
        $this->assertSame(1, $usaron->firstWhere('id', $poco->id)->washing_machines_count);
    }

    /** Y la pantalla lo enseña, con el número de equipos a la vista. */
    public function test_el_escritorio_enseña_cuanto_cargo_cada_quien(): void
    {
        $empresa = $this->empresa('Lavandería Del Valle', creadaHace: 30);
        $empresa->members()->attach($this->admin);
        $this->conEquipo($empresa);

        $this->actingAs($this->admin)
            ->get("/propietario/{$empresa->id}/escritorio")
            ->assertOk()
            ->assertSee('Cuánto cargó cada quien')
            ->assertSee('Lavandería Del Valle');
    }
}
