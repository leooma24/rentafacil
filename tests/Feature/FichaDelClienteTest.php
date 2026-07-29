<?php

namespace Tests\Feature;

use App\Filament\Resources\CustomerResource;
use App\Models\Company;
use App\Models\Package;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * El recuadro "Cómo va" de la ficha del cliente.
 *
 * Quien abre la ficha de alguien casi siempre viene con una pregunta —"¿este ya
 * me pagó?", "¿qué trae?"— y la ficha sólo tenía nombre, correo y teléfono.
 */
class FichaDelClienteTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

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
    }

    private function cliente(string $nombre = 'Ana Beltrán')
    {
        return $this->company->customers()->create([
            'name' => $nombre,
            'phone' => '6681234567',
            'email' => strtolower(str_replace(' ', '', $nombre)) . '@x.com',
        ]);
    }

    private function equipo(string $codigo, string $kind = 'lavadora')
    {
        return $this->company->washingMachines()->create([
            'machine_code' => $codigo,
            'kind' => $kind,
            'brand' => 'Mabe',
            'status' => 'rentada',
        ]);
    }

    public function test_dice_que_trae_y_hasta_cuando_esta_cubierto(): void
    {
        $cliente = $this->cliente();

        $this->company->rentals()->create([
            'customer_id' => $cliente->id,
            'washing_machine_id' => $this->equipo('LAV-001')->id,
            'start_date' => now()->subWeeks(4)->toDateString(),
            'end_date' => now()->addDays(10)->toDateString(),
            'status' => 'activa',
        ]);

        $resumen = CustomerResource::comoVaElCliente($cliente->fresh());

        $this->assertStringContainsString('LAV-001', $resumen);
        $this->assertStringContainsString('Lavadora', $resumen);
        $this->assertStringContainsString(now()->addDays(10)->format('d/m/Y'), $resumen);
        $this->assertStringContainsString('al corriente', $resumen);
        $this->assertStringNotContainsString('rf-cfg-resumen-falta', $resumen);
    }

    /** Lo que se viene a preguntar el 90% de las veces. */
    public function test_marca_en_rojo_al_que_debe_y_dice_cuanto(): void
    {
        $cliente = $this->cliente('Jesús Ruiz');

        // Vencida hace tres semanas y sin un solo pago.
        $this->company->rentals()->create([
            'customer_id' => $cliente->id,
            'washing_machine_id' => $this->equipo('LAV-002')->id,
            'start_date' => now()->subWeeks(6)->toDateString(),
            'end_date' => now()->subWeeks(3)->toDateString(),
            'status' => 'vencida',
        ]);

        $resumen = CustomerResource::comoVaElCliente($cliente->fresh());

        $this->assertStringContainsString('Debe', $resumen);
        $this->assertStringContainsString('rf-cfg-resumen-falta', $resumen);
        $this->assertStringNotContainsString('al corriente', $resumen);
    }

    /** Y no inventa una fecha ni un saldo cuando no hay nada que decir. */
    public function test_el_cliente_sin_rentas_no_inventa_nada(): void
    {
        $resumen = CustomerResource::comoVaElCliente($this->cliente('Nuevo Cliente'));

        $this->assertStringContainsString('No trae nada rentado', $resumen);
        $this->assertStringNotContainsString('Debe', $resumen);
        $this->assertStringNotContainsString('Cubierto hasta', $resumen);
    }

    /** Con dos equipos, la fecha que manda es la más próxima a vencerse. */
    public function test_con_dos_equipos_manda_la_fecha_mas_cercana(): void
    {
        $cliente = $this->cliente('Rosa Ochoa');

        foreach ([['LAV-003', 30], ['SEC-001', 5]] as [$codigo, $dias]) {
            $this->company->rentals()->create([
                'customer_id' => $cliente->id,
                'washing_machine_id' => $this->equipo(
                    $codigo,
                    str_starts_with($codigo, 'SEC') ? 'secadora' : 'lavadora'
                )->id,
                'start_date' => now()->subWeeks(2)->toDateString(),
                'end_date' => now()->addDays($dias)->toDateString(),
                'status' => 'activa',
            ]);
        }

        $resumen = CustomerResource::comoVaElCliente($cliente->fresh());

        $this->assertStringContainsString('LAV-003', $resumen);
        $this->assertStringContainsString('SEC-001', $resumen);
        $this->assertStringContainsString('Secadora', $resumen);
        $this->assertStringContainsString(now()->addDays(5)->format('d/m/Y'), $resumen);
        $this->assertStringNotContainsString(now()->addDays(30)->format('d/m/Y'), $resumen);
    }

    /**
     * La pestaña de rentas salía rotulada "Rentals": era la única palabra en
     * inglés que veía el cliente en toda la ficha.
     */
    public function test_la_pestana_de_rentas_esta_en_espanol(): void
    {
        $this->assertSame(
            'Rentas',
            \App\Filament\Resources\CustomerResource\RelationManagers\WashingMachinesRelationManager::getTitle(
                $this->cliente(),
                'edit'
            )
        );
    }
}
