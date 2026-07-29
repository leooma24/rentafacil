<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyPackage;
use App\Models\Package;
use App\Support\PlanUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * El periodo de prueba y la rotación del registro.
 *
 * isOnTrial() tenía los 15 días escritos a mano, así que una prueba de 30 dejaba
 * de contarse como prueba a la mitad: la cuenta seguía funcionando pero el
 * letrero de "prueba, X días" se apagaba.
 */
class PruebaYBitacoraTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        if (! Package::find(1)) {
            Package::forceCreate([
                'id' => 1, 'name' => 'Ilimitado', 'max_clients' => 500, 'max_washers' => 500, 'price' => 999,
            ]);
        }
    }

    private function empresa(): Company
    {
        $company = Company::create(['name' => 'Lavandería', 'phone' => '1', 'email' => 'l' . uniqid() . '@x.com']);

        // CompanyObserver ya le asigna uno; se limpia para controlar el caso.
        $company->companyPackages()->delete();

        return $company->fresh();
    }

    private function periodo(Company $company, int $dias, ?int $empezoHace = null): CompanyPackage
    {
        return $company->companyPackages()->create([
            'package_id' => 1,
            'start_date' => now()->subDays($empezoHace ?? 0),
            'end_date' => now()->subDays($empezoHace ?? 0)->addDays($dias),
        ]);
    }

    public function test_una_prueba_de_quince_dias_cuenta_como_prueba(): void
    {
        $company = $this->empresa();
        $this->periodo($company, 15);

        $this->assertTrue($company->fresh()->isOnTrial());
    }

    /**
     * El caso que motivó el arreglo: con el plazo escrito a mano en 15, una
     * prueba de 30 dejaba de contarse el día 16.
     */
    public function test_una_prueba_de_treinta_dias_sigue_contando_pasado_el_dia_quince(): void
    {
        $company = $this->empresa();
        $this->periodo($company, 30, empezoHace: 20);

        $company = $company->fresh();

        $this->assertTrue($company->isOnTrial(), 'Dejó de contarse como prueba a los 20 días.');
        // 9 y no 10: quedan 9.99 días y diffInDays trunca. Es como ha contado
        // siempre; aquí sólo se comprueba que la cuenta siga viva.
        $this->assertSame(9, $company->trialDaysLeft());
    }

    public function test_una_prueba_vencida_ya_no_cuenta(): void
    {
        $company = $this->empresa();
        $this->periodo($company, 15, empezoHace: 30);

        $this->assertFalse($company->fresh()->isOnTrial());
    }

    /** Al pagar se registra otro periodo, y ese ya no es prueba. */
    public function test_en_cuanto_paga_deja_de_ser_prueba(): void
    {
        $company = $this->empresa();
        $this->periodo($company, 15, empezoHace: 10);

        $this->assertTrue($company->fresh()->isOnTrial());

        $this->periodo($company, 365);

        $company = $company->fresh();

        $this->assertFalse($company->isOnTrial(), 'Un periodo pagado no debería contar como prueba.');
        $this->assertTrue($company->hasActivePackage());
    }

    /** El letrero que ve el dueño en la lista de usuarios. */
    public function test_el_letrero_dice_los_dias_que_le_quedan(): void
    {
        $company = $this->empresa();
        $this->periodo($company, 30, empezoHace: 20);

        $uso = PlanUsage::for($company->fresh());

        $this->assertSame('Ilimitado · prueba, 9 días', $uso->planLabel());
        $this->assertSame('warning', $uso->planColor());
    }

    /**
     * laravel.log llevaba 16 MB acumulados desde abril y nadie lo miraba: los
     * errores de hoy quedaban enterrados bajo los de hace cuatro meses.
     */
    public function test_el_registro_rota_por_dia(): void
    {
        $canales = config('logging.channels.stack.channels');

        $this->assertContains('daily', $canales, 'El registro sigue en un solo archivo.');
        $this->assertNotContains('single', $canales);
        $this->assertSame(14, config('logging.channels.daily.days'));
    }

    /**
     * Los respaldos se guardan 7 días y ya.
     *
     * La configuración de fábrica del paquete guarda todo 7 días y de ahí
     * adelgaza: uno diario por 16 días, uno semanal por 8 semanas y uno mensual
     * por 4 meses. Con eso quedaban respaldos de hace medio año ocupando disco.
     */
    public function test_los_respaldos_se_guardan_siete_dias_y_no_mas(): void
    {
        $estrategia = config('backup.cleanup.default_strategy');

        $this->assertSame(7, $estrategia['keep_all_backups_for_days']);

        foreach (['keep_daily_backups_for_days', 'keep_weekly_backups_for_weeks',
                  'keep_monthly_backups_for_months', 'keep_yearly_backups_for_years'] as $clave) {
            $this->assertSame(
                0,
                $estrategia[$clave],
                "{$clave} volvió a su valor de fábrica y se van a acumular respaldos viejos."
            );
        }
    }

    /** Y el respaldo incluye los archivos subidos, no sólo la base. */
    public function test_el_respaldo_incluye_los_archivos_que_sube_la_gente(): void
    {
        $incluye = config('backup.backup.source.files.include');

        $this->assertContains(
            storage_path('app/public'),
            $incluye,
            'Las fotos de entrega y las identificaciones de clientes no se respaldarían.'
        );
    }
}
