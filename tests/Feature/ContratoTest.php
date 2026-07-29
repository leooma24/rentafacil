<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Rental;
use App\Models\User;
use App\Models\WashingMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * El contrato de renta.
 *
 * Es el papel que respalda el cobro, y decía el precio general de la empresa
 * aunque a ese cliente se le cobrara otro. Tampoco mencionaba el depósito ni si
 * el aparato era lavadora o secadora.
 *
 * No tenía ninguna prueba.
 */
class ContratoTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Customer $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        if (! Package::find(1)) {
            Package::forceCreate([
                'id' => 1, 'name' => 'Pro', 'max_clients' => 500, 'max_washers' => 500, 'price' => 999,
            ]);
        }

        $this->company = Company::create(['name' => 'Lavandería', 'phone' => '1', 'email' => 'l@x.com']);
        $this->company->settings()->create(['price' => 250, 'days_per_payment' => 7]);

        $user = User::create(['name' => 'Dueño', 'email' => 'd@x.com', 'password' => bcrypt('s')]);
        $user->assignRole(Role::findOrCreate('propietario', 'web'));
        $this->company->members()->attach($user);
        $this->actingAs($user);

        $this->cliente = $this->company->customers()->create([
            'name' => 'Juan Pérez', 'email' => 'c@x.mx', 'phone' => '1',
        ]);
    }

    private function renta(array $extra = [], string $kind = 'lavadora'): Rental
    {
        $equipo = $this->company->washingMachines()->create([
            'machine_code' => 'LAV-001', 'brand' => 'Mabe', 'model' => 'X1',
            'kind' => $kind, 'status' => 'rentada',
        ]);

        return $this->company->rentals()->create(array_merge([
            'customer_id' => $this->cliente->id,
            'washing_machine_id' => $equipo->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
            'status' => 'activa',
        ], $extra));
    }

    /** El PDF se descarga; lo que importa es que traiga lo correcto adentro. */
    private function contrato(Rental $renta): string
    {
        $respuesta = $this->get(route('contract.download', $renta));
        $respuesta->assertOk();

        // Se arma la vista aparte para poder leer el texto: el PDF ya comprimido
        // no se puede inspeccionar de forma confiable.
        return view('pdf.rental-contract', [
            'rental' => $renta,
            'customer' => $renta->customer,
            'machine' => $renta->washingMachine,
            'company' => $renta->company,
            'settings' => $renta->company->settings,
            'terms' => \App\Support\RentalTerms::forRental($renta),
        ])->render();
    }

    public function test_el_contrato_usa_el_precio_de_la_renta_y_no_el_de_la_empresa(): void
    {
        $html = $this->contrato($this->renta(['price' => 400]));

        $this->assertStringContainsString('$400.00', $html);
        $this->assertStringNotContainsString('$250.00', $html, 'Salió el precio general de la empresa.');
    }

    public function test_sin_precio_propio_usa_el_de_la_empresa(): void
    {
        $this->assertStringContainsString('$250.00', $this->contrato($this->renta()));
    }

    /** Lo que el cliente dejó tiene que estar escrito: es su dinero. */
    public function test_el_deposito_aparece_en_los_datos_y_en_las_condiciones(): void
    {
        $html = $this->contrato($this->renta(['deposit' => 800]));

        $this->assertStringContainsString('Depósito en Garantía', $html);
        $this->assertStringContainsString('$800.00', $html);
        $this->assertStringContainsString('le será devuelto al entregar el equipo', $html);
    }

    public function test_sin_deposito_no_se_menciona(): void
    {
        $html = $this->contrato($this->renta());

        $this->assertStringNotContainsString('Depósito en Garantía', $html);
    }

    public function test_el_contrato_de_una_secadora_dice_secadora(): void
    {
        $html = $this->contrato($this->renta(kind: 'secadora'), );

        $this->assertStringContainsString('Contrato de Renta de Secadora', $html);
        $this->assertStringContainsString('Datos del Equipo', $html);
    }

    /** Un recargo que no está escrito en el contrato no se puede cobrar. */
    public function test_el_recargo_configurado_aparece_en_el_contrato(): void
    {
        $this->company->settings->update([
            'late_fee_amount' => 50,
            'late_fee_type' => 'fijo',
            'late_fee_grace_days' => 3,
        ]);
        $this->company->refresh();

        $html = $this->contrato($this->renta());

        $this->assertStringContainsString('Recargo por Atraso', $html);
        $this->assertStringContainsString('$50.00 MXN por periodo vencido', $html);
        $this->assertStringContainsString('3 días de gracia', $html);
    }

    public function test_sin_recargo_configurado_no_se_menciona(): void
    {
        $html = $this->contrato($this->renta());

        $this->assertStringNotContainsString('Recargo por Atraso', $html);
    }

    public function test_el_recibo_nombra_el_equipo_y_no_solo_lavadora(): void
    {
        $renta = $this->renta(kind: 'secadora');

        $pago = $renta->payments()->create([
            'company_id' => $this->company->id,
            'amount' => 250,
            'payment_date' => today()->toDateString(),
            'payment_method' => 'Efectivo',
            'status' => 'completado',
        ]);

        $html = view('pdf.payment-receipt', [
            'payment' => $pago,
            'rental' => $renta,
            'customer' => $this->cliente,
            'machine' => $renta->washingMachine,
            'company' => $this->company,
        ])->render();

        $this->assertStringContainsString('Secadora', $html);
    }
}
