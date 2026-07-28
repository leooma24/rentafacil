<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Rental;
use App\Models\WashingMachine;
use App\Support\ShareableLinks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LinksCompartiblesTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->company = $this->prepararEmpresa();
    }

    private function prepararEmpresa(): Company
    {
        if (! Package::find(1)) {
            Package::forceCreate([
                'id' => 1, 'name' => 'Pro', 'max_clients' => 500, 'max_washers' => 500, 'price' => 999,
            ]);
        }

        $company = Company::create([
            'name' => 'Lavandería del Valle', 'phone' => '1', 'email' => 'l@x.com',
        ]);
        $company->settings()->create(['price' => 250, 'days_per_payment' => 7]);

        return $company->fresh();
    }

    private function makeCustomer(): Customer
    {
        return $this->company->customers()->create([
            'name' => 'María González',
            'email' => 'maria' . uniqid() . '@x.mx',
            'phone' => '6681234567',
        ]);
    }

    private function makeRental(Customer $customer, string $endDate, string $status = 'activa'): Rental
    {
        $machine = $this->company->washingMachines()->create([
            'machine_code' => 'LAV-001', 'brand' => 'Mabe', 'status' => 'rentada',
        ]);

        return $this->company->rentals()->create([
            'customer_id' => $customer->id,
            'washing_machine_id' => $machine->id,
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => $endDate,
            'status' => $status,
        ]);
    }

    private function makePayment(Rental $rental): Payment
    {
        return $rental->payments()->create([
            'company_id' => $this->company->id,
            'amount' => 250,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Efectivo',
            'status' => 'completado',
        ]);
    }

    // --- Lo que más importa: que nadie vea lo que no es suyo ---

    public function test_el_recibo_sin_firma_no_se_muestra(): void
    {
        $pago = $this->makePayment($this->makeRental($this->makeCustomer(), now()->addDays(7)->toDateString()));

        $this->get("/recibo/{$pago->id}")->assertForbidden();
    }

    public function test_el_recibo_con_la_firma_alterada_no_se_muestra(): void
    {
        $pago = $this->makePayment($this->makeRental($this->makeCustomer(), now()->addDays(7)->toDateString()));

        $liga = ShareableLinks::receiptUrl($pago);

        $this->get($liga . 'x')->assertForbidden();
    }

    public function test_no_se_puede_espiar_cambiando_el_numero_del_recibo(): void
    {
        $mio = $this->makePayment($this->makeRental($this->makeCustomer(), now()->addDays(7)->toDateString()));
        $ajeno = $this->makePayment($this->makeRental($this->makeCustomer(), now()->addDays(7)->toDateString()));

        // Tomar la liga propia y cambiarle el id por el del vecino.
        $liga = str_replace("/recibo/{$mio->id}", "/recibo/{$ajeno->id}", ShareableLinks::receiptUrl($mio));

        $this->get($liga)->assertForbidden();
    }

    public function test_el_estado_de_cuenta_sin_firma_no_se_muestra(): void
    {
        $cliente = $this->makeCustomer();

        $this->get("/estado-de-cuenta/{$cliente->id}")->assertForbidden();
    }

    public function test_un_estado_de_cuenta_caducado_deja_de_servir(): void
    {
        $cliente = $this->makeCustomer();
        $liga = ShareableLinks::statementUrl($cliente);

        $this->travel(ShareableLinks::DIAS_ESTADO_DE_CUENTA + 1)->days();

        $this->get($liga)->assertForbidden();
    }

    // --- Lo que sí debe verse ---

    public function test_el_recibo_firmado_muestra_el_pago(): void
    {
        $cliente = $this->makeCustomer();
        $renta = $this->makeRental($cliente, now()->addDays(7)->toDateString());
        $pago = $this->makePayment($renta);

        $this->get(ShareableLinks::receiptUrl($pago))
            ->assertOk()
            ->assertSee('Recibo de pago')
            ->assertSee('250.00')
            ->assertSee('María González')
            ->assertSee('LAV-001')
            ->assertSee('Lavandería del Valle')
            ->assertSee(now()->addDays(7)->format('d/m/Y'));
    }

    public function test_el_estado_de_cuenta_firmado_muestra_el_saldo(): void
    {
        $cliente = $this->makeCustomer();
        $this->makeRental($cliente, now()->subDays(10)->toDateString(), 'vencida');

        $this->get(ShareableLinks::statementUrl($cliente->fresh()))
            ->assertOk()
            ->assertSee('Estado de cuenta')
            ->assertSee('María González')
            ->assertSee('500.00')
            ->assertSee('LAV-001');
    }

    public function test_un_cliente_al_corriente_ve_que_esta_al_corriente(): void
    {
        $cliente = $this->makeCustomer();
        $this->makeRental($cliente, now()->addDays(10)->toDateString());

        $this->get(ShareableLinks::statementUrl($cliente->fresh()))
            ->assertOk()
            ->assertSee('Estás al corriente')
            ->assertDontSee('Desde el');
    }

    // --- Los mensajes ---

    public function test_el_mensaje_del_recibo_lleva_monto_liga_y_vigencia(): void
    {
        $cliente = $this->makeCustomer();
        $renta = $this->makeRental($cliente, now()->addDays(7)->toDateString());
        $pago = $this->makePayment($renta);

        $mensaje = ShareableLinks::receiptMessage($pago->fresh('rental'));

        $this->assertStringContainsString('María González', $mensaje);
        $this->assertStringContainsString('$250.00', $mensaje);
        $this->assertStringContainsString('/recibo/' . $pago->id, $mensaje);
        $this->assertStringContainsString(now()->addDays(7)->format('d/m/Y'), $mensaje);
    }

    public function test_el_mensaje_del_estado_de_cuenta_dice_cuanto_debe(): void
    {
        $cliente = $this->makeCustomer();
        $this->makeRental($cliente, now()->subDays(10)->toDateString(), 'vencida');

        $mensaje = ShareableLinks::statementMessage($cliente->fresh());

        $this->assertStringContainsString('María González', $mensaje);
        $this->assertStringContainsString('$500.00', $mensaje);
        $this->assertStringContainsString('/estado-de-cuenta/' . $cliente->id, $mensaje);
    }

    public function test_el_mensaje_de_quien_esta_al_corriente_no_le_inventa_deuda(): void
    {
        $cliente = $this->makeCustomer();
        $this->makeRental($cliente, now()->addDays(10)->toDateString());

        $mensaje = ShareableLinks::statementMessage($cliente->fresh());

        $this->assertStringContainsString('al corriente', $mensaje);
        $this->assertStringNotContainsString('Debes', $mensaje);
    }

    public function test_la_liga_de_whatsapp_normaliza_el_numero_igual_que_en_prospectos(): void
    {
        $url = ShareableLinks::whatsappUrl('668 123 4567', 'Hola');

        $this->assertStringStartsWith('https://wa.me/526681234567?text=', $url);
    }
}
