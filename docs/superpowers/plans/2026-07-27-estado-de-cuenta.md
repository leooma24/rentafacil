# Estado de cuenta por cliente — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Que el dueño vea de un golpe cuánto le debe cada cliente, quién le debe más y cuánto tiene por cobrar en total, sin capturar nada nuevo.

**Architecture:** Toda la regla de cálculo vive en `App\Support\AccountStatement`, una clase sin dependencias de Filament ni de HTTP que deriva el adeudo de datos que ya existen: la fecha de vencimiento de cada renta, el último pago y la configuración de precio de la empresa. Nada se guarda en base de datos, así que el saldo nunca se desincroniza. La interfaz (una pantalla, una columna, un filtro y dos widgets) solo consume esa clase.

**Tech Stack:** Laravel 11 (esqueleto legacy), Filament 3.3 con multi-tenancy sobre `Company`, PHPUnit, MySQL.

**Spec:** `docs/superpowers/specs/2026-07-27-estado-de-cuenta-design.md`

---

## Contexto del código que vas a tocar

Cosas que te van a morder si no las sabes:

- **El negocio es prepago.** `ExtendRentAction` cobra y empuja `end_date` N días
  (`app/Filament/Resources/RentalResource/Actions/ExtendRentAction.php:82`). Por eso el
  adeudo se deriva de `end_date`, no de una tabla de cargos.
- **El precio no vive en la renta.** Está en `Setting` por empresa (`price`,
  `days_per_payment`), pero en "Extender Renta" ambos se capturan a mano cada vez. Por
  eso el precio del adeudo sale del último pago de esa renta.
- **`CompanyObserver` le asigna `package_id => 1` a toda empresa nueva**
  (`app/Observers/CompanyObserver.php:12`). En los tests, **el paquete con id 1 tiene
  que existir antes** de crear cualquier `Company`, y el `AUTO_INCREMENT` de MySQL no se
  reinicia entre tests, así que el id va forzado con `forceCreate`.
- **`RentalObserver` manda correo en la primera renta de una empresa.** En los tests usa
  `Mail::fake()` o truena por configuración de correo.
- **Valores exactos de estado** (son enums en MySQL): `rentals.status` es `activa`,
  `vencida`, `completada`, `cancelada`. `payments.status` es texto libre; los valores en
  uso son `completado`, `pendiente` y `fallido`.
- **La base de pruebas es `flavadoras_testing`**, ya configurada en `phpunit.xml`. Si no
  existe, créala:
  `C:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysql.exe -h 127.0.0.1 -u root -e "CREATE DATABASE IF NOT EXISTS flavadoras_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"`
- **`ViewCustomer.php` existe pero no está registrado** en `getPages()` de
  `CustomerResource`. No lo uses ni lo borres; la pantalla nueva es aparte.

---

## Estructura de archivos

**Se crean:**

| Archivo | Responsabilidad |
|---|---|
| `app/Support/RentalDebt.php` | Lo que debe **una** renta: periodos vencidos, precio aplicado y monto. |
| `app/Support/Statement.php` | El estado de cuenta de **un** cliente: total, desde cuándo debe, detalle por renta y si fue calculable. |
| `app/Support/AccountStatement.php` | La regla. Único lugar donde se calcula un adeudo. |
| `app/Filament/Resources/CustomerResource/Pages/AccountStatementPage.php` | La pantalla; solo muestra lo que le da `AccountStatement`. |
| `resources/views/filament/resources/customer-resource/pages/account-statement.blade.php` | La vista de esa pantalla. |
| `app/Filament/Widgets/CustomersWithDebtWidget.php` | Tabla "Clientes con adeudo" del escritorio. |
| `resources/views/filament/widgets/customers-with-debt.blade.php` | La vista de ese widget. |
| `tests/Unit/AccountStatementTest.php` | La regla, probada aislada. |
| `tests/Feature/AccountStatementPageTest.php` | La pantalla y el filtro. |

**Se modifican:**

| Archivo | Cambio |
|---|---|
| `app/Filament/Resources/CustomerResource.php` | Columna "Debe", filtro "Solo con adeudo", botón "Estado de cuenta" y registro de la página. |
| `app/Filament/Widgets/PaymentStats.php` | Recuadro "Total por cobrar". |

---

## Task 1: Objetos de valor y el caso "al corriente"

**Files:**
- Create: `app/Support/RentalDebt.php`, `app/Support/Statement.php`, `app/Support/AccountStatement.php`
- Test: `tests/Unit/AccountStatementTest.php`

- [ ] **Step 1: Escribir el test que falla**

Crea `tests/Unit/AccountStatementTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Rental;
use App\Support\AccountStatement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AccountStatementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // RentalObserver manda correo en la primera renta de una empresa.
        Mail::fake();
    }

    /**
     * CompanyObserver asigna package_id 1 a toda empresa nueva y el AUTO_INCREMENT
     * de MySQL no se reinicia entre tests, así que el id va forzado.
     */
    private function makeCompany(float $price = 250, int $days = 7): Company
    {
        if (! Package::find(1)) {
            Package::forceCreate([
                'id' => 1, 'name' => 'Pro', 'max_clients' => 500, 'max_washers' => 500, 'price' => 999,
            ]);
        }

        $company = Company::create(['name' => 'Lavandería', 'phone' => '1', 'email' => 'l@x.com']);

        if ($price > 0 && $days > 0) {
            $company->settings()->create(['price' => $price, 'days_per_payment' => $days]);
        }

        return $company->fresh();
    }

    private function makeCustomer(Company $company, string $name = 'Juan Pérez'): Customer
    {
        return $company->customers()->create([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '', $name)) . uniqid() . '@ejemplo.mx',
            'phone' => '6681234567',
        ]);
    }

    private function makeRental(
        Company $company,
        Customer $customer,
        string $endDate,
        string $status = 'activa'
    ): Rental {
        $machine = $company->washingMachines()->create([
            'machine_code' => 'LAV-' . str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT),
            'brand' => 'Mabe',
            'status' => 'rentada',
        ]);

        return $company->rentals()->create([
            'customer_id' => $customer->id,
            'washing_machine_id' => $machine->id,
            'start_date' => now()->subMonths(2)->toDateString(),
            'end_date' => $endDate,
            'status' => $status,
        ]);
    }

    public function test_un_cliente_al_corriente_no_debe_nada(): void
    {
        $company = $this->makeCompany();
        $customer = $this->makeCustomer($company);
        $this->makeRental($company, $customer, now()->addDays(5)->toDateString());

        $statement = (new AccountStatement())->forCustomer($customer);

        $this->assertTrue($statement->calculable);
        $this->assertSame(0.0, $statement->total);
        $this->assertNull($statement->owingSince);
        $this->assertCount(1, $statement->lines);
        $this->assertSame(0, $statement->lines[0]->overduePeriods);
    }
}
```

- [ ] **Step 2: Correr el test para verificar que falla**

Run: `php artisan test --filter=test_un_cliente_al_corriente_no_debe_nada`
Expected: FAIL — `Class "App\Support\AccountStatement" not found`.

- [ ] **Step 3: Crear los objetos de valor**

Crea `app/Support/RentalDebt.php`:

```php
<?php

namespace App\Support;

use App\Models\Rental;

/**
 * Lo que debe una renta en particular.
 */
class RentalDebt
{
    public function __construct(
        public readonly Rental $rental,
        public readonly int $overduePeriods,
        public readonly float $price,
        public readonly float $amount,
    ) {
    }
}
```

Crea `app/Support/Statement.php`:

```php
<?php

namespace App\Support;

use App\Models\Customer;
use Carbon\Carbon;

/**
 * Estado de cuenta de un cliente.
 *
 * `calculable` es false cuando la empresa no tiene precio o periodo configurado.
 * En ese caso el total es 0 pero NO significa que no deba: significa que no se
 * puede saber, y la interfaz debe decirlo así en vez de mostrar un cero falso.
 */
class Statement
{
    /** @param RentalDebt[] $lines */
    public function __construct(
        public readonly Customer $customer,
        public readonly float $total,
        public readonly ?Carbon $owingSince,
        public readonly array $lines,
        public readonly bool $calculable,
    ) {
    }

    public function hasDebt(): bool
    {
        return $this->calculable && $this->total > 0;
    }
}
```

- [ ] **Step 4: Crear el servicio con lo mínimo**

Crea `app/Support/AccountStatement.php`:

```php
<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Rental;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Calcula cuánto debe un cliente.
 *
 * El negocio es prepago: al pagar, ExtendRentAction empuja el end_date de la renta.
 * Por eso el adeudo se deriva de qué tan atrás quedó ese end_date, sin guardar saldos
 * en ningún lado y sin riesgo de que se desincronicen.
 */
class AccountStatement
{
    private const ACTIVE_STATUSES = ['activa', 'vencida'];

    public function forCustomer(Customer $customer): Statement
    {
        $rentals = $customer->rentals()
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->with(['payments', 'washingMachine'])
            ->get();

        return $this->buildStatement($customer, $rentals, $customer->company?->settings);
    }

    /**
     * @param Collection<int, Rental> $rentals
     */
    private function buildStatement(Customer $customer, Collection $rentals, ?Setting $settings): Statement
    {
        $daysPerPeriod = (int) ($settings->days_per_payment ?? 0);
        $defaultPrice = (float) ($settings->price ?? 0);

        if ($daysPerPeriod <= 0 || $defaultPrice <= 0) {
            return new Statement($customer, 0.0, null, [], false);
        }

        $lines = [];
        $total = 0.0;
        $owingSince = null;

        foreach ($rentals as $rental) {
            $line = $this->debtFor($rental, $daysPerPeriod, $defaultPrice);
            $lines[] = $line;
            $total += $line->amount;

            if ($line->amount > 0) {
                $end = Carbon::parse($rental->end_date)->startOfDay();
                if ($owingSince === null || $end->lt($owingSince)) {
                    $owingSince = $end;
                }
            }
        }

        return new Statement($customer, $total, $owingSince, $lines, true);
    }

    private function debtFor(Rental $rental, int $daysPerPeriod, float $defaultPrice): RentalDebt
    {
        $price = $this->priceFor($rental, $defaultPrice);
        $end = Carbon::parse($rental->end_date)->startOfDay();
        $today = Carbon::today();

        if ($end->gte($today)) {
            return new RentalDebt($rental, 0, $price, 0.0);
        }

        $daysOverdue = $end->diffInDays($today);
        $periods = (int) ceil($daysOverdue / $daysPerPeriod);

        return new RentalDebt($rental, $periods, $price, $periods * $price);
    }

    private function priceFor(Rental $rental, float $defaultPrice): float
    {
        $last = $rental->payments
            ->where('status', 'completado')
            ->sortBy([['payment_date', 'desc'], ['id', 'desc']])
            ->first();

        return $last && $last->amount > 0 ? (float) $last->amount : $defaultPrice;
    }
}
```

- [ ] **Step 5: Correr el test**

Run: `php artisan test --filter=test_un_cliente_al_corriente_no_debe_nada`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Support tests/Unit/AccountStatementTest.php
git commit -m "feat: add account statement calculation for up-to-date customers"
```

---

## Task 2: Periodos vencidos, con periodo empezado cobrado

La regla del negocio: si la renta es semanal y lleva 10 días vencida, ya entró a la
segunda semana, así que debe dos.

**Files:**
- Test: `tests/Unit/AccountStatementTest.php` (añadir)

- [ ] **Step 1: Escribir los tests que fallan**

Añade a la clase:

```php
    public function test_diez_dias_vencido_con_semana_de_250_debe_500(): void
    {
        $company = $this->makeCompany(250, 7);
        $customer = $this->makeCustomer($company);
        $this->makeRental($company, $customer, now()->subDays(10)->toDateString(), 'vencida');

        $statement = (new AccountStatement())->forCustomer($customer);

        $this->assertSame(500.0, $statement->total);
        $this->assertSame(2, $statement->lines[0]->overduePeriods);
        $this->assertSame(
            now()->subDays(10)->toDateString(),
            $statement->owingSince->toDateString()
        );
    }

    public function test_siete_dias_exactos_cobra_un_solo_periodo(): void
    {
        $company = $this->makeCompany(250, 7);
        $customer = $this->makeCustomer($company);
        $this->makeRental($company, $customer, now()->subDays(7)->toDateString(), 'vencida');

        $this->assertSame(250.0, (new AccountStatement())->forCustomer($customer)->total);
    }

    public function test_un_solo_dia_vencido_ya_cobra_el_periodo_completo(): void
    {
        $company = $this->makeCompany(250, 7);
        $customer = $this->makeCustomer($company);
        $this->makeRental($company, $customer, now()->subDay()->toDateString(), 'vencida');

        $this->assertSame(250.0, (new AccountStatement())->forCustomer($customer)->total);
    }
```

- [ ] **Step 2: Correr los tests**

Run: `php artisan test --filter=AccountStatementTest`
Expected: PASS los cuatro. La implementación de la Task 1 ya cubre esta regla; estos
tests la fijan para que nadie la cambie sin darse cuenta.

- [ ] **Step 3: Commit**

```bash
git add tests/Unit/AccountStatementTest.php
git commit -m "test: pin overdue period rounding rule"
```

---

## Task 3: El precio sale del último pago de la renta

**Files:**
- Test: `tests/Unit/AccountStatementTest.php` (añadir)

- [ ] **Step 1: Escribir los tests que fallan**

Añade a la clase:

```php
    public function test_usa_el_precio_del_ultimo_pago_y_no_el_de_configuracion(): void
    {
        $company = $this->makeCompany(250, 7);
        $customer = $this->makeCustomer($company);
        $rental = $this->makeRental($company, $customer, now()->subDays(7)->toDateString(), 'vencida');

        // Tarifa especial: al cliente se le ha cobrado $200, no los $250 del negocio.
        $rental->payments()->create([
            'company_id' => $company->id,
            'amount' => 300,
            'payment_date' => now()->subDays(30)->toDateString(),
            'payment_method' => 'Efectivo',
            'status' => 'completado',
        ]);
        $rental->payments()->create([
            'company_id' => $company->id,
            'amount' => 200,
            'payment_date' => now()->subDays(14)->toDateString(),
            'payment_method' => 'Efectivo',
            'status' => 'completado',
        ]);

        $statement = (new AccountStatement())->forCustomer($customer);

        $this->assertSame(200.0, $statement->lines[0]->price);
        $this->assertSame(200.0, $statement->total);
    }

    public function test_ignora_los_pagos_que_no_estan_completados(): void
    {
        $company = $this->makeCompany(250, 7);
        $customer = $this->makeCustomer($company);
        $rental = $this->makeRental($company, $customer, now()->subDays(7)->toDateString(), 'vencida');

        $rental->payments()->create([
            'company_id' => $company->id,
            'amount' => 999,
            'payment_date' => now()->subDay()->toDateString(),
            'payment_method' => 'Efectivo',
            'status' => 'fallido',
        ]);

        $this->assertSame(250.0, (new AccountStatement())->forCustomer($customer)->total);
    }
```

- [ ] **Step 2: Correr los tests**

Run: `php artisan test --filter=AccountStatementTest`
Expected: PASS. Si `test_usa_el_precio_del_ultimo_pago` falla con 300, revisa que
`priceFor` ordene por `payment_date` descendente y desempate por `id`.

- [ ] **Step 3: Commit**

```bash
git add tests/Unit/AccountStatementTest.php
git commit -m "test: pin special-rate pricing from last completed payment"
```

---

## Task 4: Sin configuración, no calculable

**Files:**
- Test: `tests/Unit/AccountStatementTest.php` (añadir)

- [ ] **Step 1: Escribir los tests que fallan**

```php
    public function test_sin_configuracion_el_adeudo_no_es_calculable_en_vez_de_cero(): void
    {
        $company = $this->makeCompany(0, 0); // sin Setting
        $customer = $this->makeCustomer($company);
        $this->makeRental($company, $customer, now()->subDays(30)->toDateString(), 'vencida');

        $statement = (new AccountStatement())->forCustomer($customer);

        $this->assertFalse($statement->calculable);
        $this->assertFalse($statement->hasDebt());
        $this->assertSame(0.0, $statement->total);
    }

    public function test_con_precio_en_cero_tampoco_es_calculable(): void
    {
        $company = $this->makeCompany(0, 0);
        $company->settings()->create(['price' => 0, 'days_per_payment' => 7]);
        $customer = $this->makeCustomer($company->fresh());
        $this->makeRental($company, $customer, now()->subDays(30)->toDateString(), 'vencida');

        $this->assertFalse((new AccountStatement())->forCustomer($customer->fresh())->calculable);
    }
```

- [ ] **Step 2: Correr los tests**

Run: `php artisan test --filter=AccountStatementTest`
Expected: PASS.

- [ ] **Step 3: Commit**

```bash
git add tests/Unit/AccountStatementTest.php
git commit -m "test: pin non-calculable statement when pricing is unset"
```

---

## Task 5: Varias rentas y estados que no cuentan

**Files:**
- Test: `tests/Unit/AccountStatementTest.php` (añadir)

- [ ] **Step 1: Escribir los tests que fallan**

```php
    public function test_suma_las_rentas_del_cliente_y_toma_la_fecha_mas_vieja(): void
    {
        $company = $this->makeCompany(250, 7);
        $customer = $this->makeCustomer($company);
        $this->makeRental($company, $customer, now()->subDays(10)->toDateString(), 'vencida'); // $500
        $this->makeRental($company, $customer, now()->subDays(3)->toDateString(), 'vencida');  // $250
        $this->makeRental($company, $customer, now()->addDays(5)->toDateString(), 'activa');   // $0

        $statement = (new AccountStatement())->forCustomer($customer);

        $this->assertSame(750.0, $statement->total);
        $this->assertCount(3, $statement->lines);
        $this->assertSame(
            now()->subDays(10)->toDateString(),
            $statement->owingSince->toDateString()
        );
    }

    public function test_las_rentas_completadas_y_canceladas_no_cuentan(): void
    {
        $company = $this->makeCompany(250, 7);
        $customer = $this->makeCustomer($company);
        $this->makeRental($company, $customer, now()->subDays(60)->toDateString(), 'completada');
        $this->makeRental($company, $customer, now()->subDays(60)->toDateString(), 'cancelada');

        $statement = (new AccountStatement())->forCustomer($customer);

        $this->assertSame(0.0, $statement->total);
        $this->assertCount(0, $statement->lines);
    }
```

- [ ] **Step 2: Correr los tests**

Run: `php artisan test --filter=AccountStatementTest`
Expected: PASS.

- [ ] **Step 3: Commit**

```bash
git add tests/Unit/AccountStatementTest.php
git commit -m "test: pin multi-rental totals and excluded statuses"
```

---

## Task 6: Estados de cuenta de toda la empresa

**Files:**
- Modify: `app/Support/AccountStatement.php`
- Test: `tests/Unit/AccountStatementTest.php` (añadir)

- [ ] **Step 1: Escribir el test que falla**

```php
    public function test_for_company_devuelve_solo_a_quienes_deben_de_mayor_a_menor(): void
    {
        $company = $this->makeCompany(250, 7);

        $alCorriente = $this->makeCustomer($company, 'Al Corriente');
        $this->makeRental($company, $alCorriente, now()->addDays(5)->toDateString());

        $debePoco = $this->makeCustomer($company, 'Debe Poco');
        $this->makeRental($company, $debePoco, now()->subDays(3)->toDateString(), 'vencida');

        $debeMucho = $this->makeCustomer($company, 'Debe Mucho');
        $this->makeRental($company, $debeMucho, now()->subDays(20)->toDateString(), 'vencida');

        $statements = (new AccountStatement())->forCompany($company);

        $this->assertCount(2, $statements);
        $this->assertSame('Debe Mucho', $statements[0]->customer->name);
        $this->assertSame(750.0, $statements[0]->total);
        $this->assertSame('Debe Poco', $statements[1]->customer->name);
        $this->assertSame(250.0, $statements[1]->total);
    }
```

- [ ] **Step 2: Correr el test para verificar que falla**

Run: `php artisan test --filter=test_for_company_devuelve_solo_a_quienes_deben`
Expected: FAIL — `Call to undefined method App\Support\AccountStatement::forCompany()`.

- [ ] **Step 3: Implementar**

En `app/Support/AccountStatement.php`, añade este método público debajo de
`forCustomer()`:

```php
    /**
     * Los clientes que deben, de mayor a menor.
     *
     * Arranca del conjunto chico (rentas activas o vencidas con end_date pasado) en vez
     * de recorrer a todos los clientes, y trae las relaciones de una vez para no hacer
     * una consulta por cliente.
     *
     * @return Collection<int, Statement>
     */
    public function forCompany(Company $company): Collection
    {
        $settings = $company->settings;

        $customers = Customer::where('company_id', $company->id)
            ->whereHas('rentals', fn ($query) => $query
                ->whereIn('status', self::ACTIVE_STATUSES)
                ->whereDate('end_date', '<', Carbon::today()))
            ->with(['rentals' => fn ($query) => $query
                ->whereIn('status', self::ACTIVE_STATUSES)
                ->with(['payments', 'washingMachine'])])
            ->get();

        return $customers
            ->map(fn (Customer $customer) => $this->buildStatement($customer, $customer->rentals, $settings))
            ->filter(fn (Statement $statement) => $statement->hasDebt())
            ->sortByDesc(fn (Statement $statement) => $statement->total)
            ->values();
    }

    public function totalForCompany(Company $company): float
    {
        return (float) $this->forCompany($company)->sum(fn (Statement $statement) => $statement->total);
    }
```

- [ ] **Step 4: Correr los tests**

Run: `php artisan test --filter=AccountStatementTest`
Expected: PASS los diez.

- [ ] **Step 5: Commit**

```bash
git add app/Support/AccountStatement.php tests/Unit/AccountStatementTest.php
git commit -m "feat: list company customers with outstanding balance"
```

---

## Task 7: La pantalla de estado de cuenta

**Files:**
- Create: `app/Filament/Resources/CustomerResource/Pages/AccountStatementPage.php`, `resources/views/filament/resources/customer-resource/pages/account-statement.blade.php`
- Modify: `app/Filament/Resources/CustomerResource.php` (getPages)
- Test: `tests/Feature/AccountStatementPageTest.php`

- [ ] **Step 1: Escribir el test que falla**

Crea `tests/Feature/AccountStatementPageTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Rental;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AccountStatementPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function makeCompany(): Company
    {
        if (! Package::find(1)) {
            Package::forceCreate([
                'id' => 1, 'name' => 'Pro', 'max_clients' => 500, 'max_washers' => 500, 'price' => 999,
            ]);
        }

        $company = Company::create(['name' => 'Lavandería', 'phone' => '1', 'email' => 'l@x.com']);
        $company->settings()->create(['price' => 250, 'days_per_payment' => 7]);

        return $company->fresh();
    }

    public function test_la_pantalla_muestra_el_saldo_del_cliente(): void
    {
        $company = $this->makeCompany();
        $user = \App\Models\User::create([
            'name' => 'Dueño', 'email' => 'dueno@x.com', 'password' => bcrypt('secret'),
        ]);
        $company->members()->attach($user);

        $customer = $company->customers()->create([
            'name' => 'Juan Pérez', 'email' => 'juan@ejemplo.mx', 'phone' => '6681234567',
        ]);

        $machine = $company->washingMachines()->create([
            'machine_code' => 'LAV-001', 'brand' => 'Mabe', 'status' => 'rentada',
        ]);

        $company->rentals()->create([
            'customer_id' => $customer->id,
            'washing_machine_id' => $machine->id,
            'start_date' => now()->subMonths(2)->toDateString(),
            'end_date' => now()->subDays(10)->toDateString(),
            'status' => 'vencida',
        ]);

        $this->actingAs($user)
            ->get("/propietario/{$company->id}/clientes/{$customer->id}/estado-de-cuenta")
            ->assertOk()
            ->assertSee('Juan Pérez')
            ->assertSee('LAV-001')
            ->assertSee('500.00');
    }
}
```

- [ ] **Step 2: Correr el test para verificar que falla**

Run: `php artisan test --filter=test_la_pantalla_muestra_el_saldo_del_cliente`
Expected: FAIL — 404.

- [ ] **Step 3: Crear la página**

Crea `app/Filament/Resources/CustomerResource/Pages/AccountStatementPage.php`:

```php
<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Models\Payment;
use App\Support\AccountStatement;
use App\Support\Statement;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;

class AccountStatementPage extends Page
{
    use InteractsWithRecord;

    protected static string $resource = CustomerResource::class;

    protected static string $view = 'filament.resources.customer-resource.pages.account-statement';

    protected static ?string $title = 'Estado de cuenta';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getStatement(): Statement
    {
        return app(AccountStatement::class)->forCustomer($this->record);
    }

    /** @return Collection<int, Payment> */
    public function getPayments(): Collection
    {
        return Payment::whereIn('rental_id', $this->record->rentals()->pluck('id'))
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->get();
    }
}
```

- [ ] **Step 4: Crear la vista**

Crea `resources/views/filament/resources/customer-resource/pages/account-statement.blade.php`:

```blade
@php
    $statement = $this->getStatement();
    $payments = $this->getPayments();
@endphp

<x-filament-panels::page>
    @if (! $statement->calculable)
        <x-filament::section>
            <div class="text-sm">
                <p class="font-semibold">No se puede calcular el adeudo.</p>
                <p class="text-gray-500">
                    Falta configurar el precio de renta y cada cuántos días se cobra.
                    Ve a Configuración y captúralos para que aparezcan los saldos.
                </p>
            </div>
        </x-filament::section>
    @else
        <x-filament::section>
            <div class="flex flex-wrap items-baseline justify-between gap-4">
                <div>
                    <p class="text-sm text-gray-500">Saldo de {{ $this->record->name }}</p>
                    <p @class([
                        'text-4xl font-bold',
                        'text-danger-600' => $statement->total > 0,
                        'text-success-600' => $statement->total <= 0,
                    ])>
                        ${{ number_format($statement->total, 2) }}
                    </p>
                </div>
                <div class="text-sm text-gray-500">
                    @if ($statement->owingSince)
                        Debe desde el {{ $statement->owingSince->format('d/m/Y') }}
                    @else
                        Está al corriente
                    @endif
                </div>
            </div>
        </x-filament::section>

        <x-filament::section heading="Lavadoras que trae">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-gray-500">
                        <tr>
                            <th class="py-2 pr-4">Código</th>
                            <th class="py-2 pr-4">Desde</th>
                            <th class="py-2 pr-4">Pagado hasta</th>
                            <th class="py-2 pr-4">Periodos vencidos</th>
                            <th class="py-2">Debe</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($statement->lines as $line)
                            <tr class="border-t border-gray-100 dark:border-gray-800">
                                <td class="py-2 pr-4">{{ $line->rental->washingMachine?->machine_code ?? '—' }}</td>
                                <td class="py-2 pr-4">{{ \Carbon\Carbon::parse($line->rental->start_date)->format('d/m/Y') }}</td>
                                <td class="py-2 pr-4">{{ \Carbon\Carbon::parse($line->rental->end_date)->format('d/m/Y') }}</td>
                                <td class="py-2 pr-4">{{ $line->overduePeriods }}</td>
                                <td @class(['py-2 font-semibold', 'text-danger-600' => $line->amount > 0])>
                                    ${{ number_format($line->amount, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-4 text-gray-500">No trae lavadoras rentadas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif

    <x-filament::section heading="Historial de pagos">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-gray-500">
                    <tr>
                        <th class="py-2 pr-4">Fecha</th>
                        <th class="py-2 pr-4">Monto</th>
                        <th class="py-2 pr-4">Método</th>
                        <th class="py-2 pr-4">Referencia</th>
                        <th class="py-2">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($payments as $payment)
                        <tr class="border-t border-gray-100 dark:border-gray-800">
                            <td class="py-2 pr-4">{{ $payment->payment_date ? \Carbon\Carbon::parse($payment->payment_date)->format('d/m/Y') : '—' }}</td>
                            <td class="py-2 pr-4">${{ number_format((float) $payment->amount, 2) }}</td>
                            <td class="py-2 pr-4">{{ $payment->payment_method ?? '—' }}</td>
                            <td class="py-2 pr-4">{{ $payment->reference ?? '—' }}</td>
                            <td class="py-2">{{ ucfirst($payment->status ?? '—') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-4 text-gray-500">Todavía no ha pagado nada.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
```

- [ ] **Step 5: Registrar la ruta**

En `app/Filament/Resources/CustomerResource.php`, reemplaza el método `getPages()`:

```php
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }
```

por:

```php
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
            'estado-de-cuenta' => Pages\AccountStatementPage::route('/{record}/estado-de-cuenta'),
        ];
    }
```

- [ ] **Step 6: Correr el test**

Run: `php artisan test --filter=test_la_pantalla_muestra_el_saldo_del_cliente`
Expected: PASS. Si sale 403, revisa que el usuario esté ligado a la empresa con
`$company->members()->attach($user)`; Filament resuelve el tenant desde ahí.

- [ ] **Step 7: Commit**

```bash
git add app/Filament/Resources/CustomerResource.php app/Filament/Resources/CustomerResource/Pages/AccountStatementPage.php resources/views/filament/resources/customer-resource/pages/account-statement.blade.php tests/Feature/AccountStatementPageTest.php
git commit -m "feat: add customer account statement page"
```

---

## Task 8: Columna, filtro y botón en Mis Clientes

**Files:**
- Modify: `app/Filament/Resources/CustomerResource.php`
- Test: `tests/Feature/AccountStatementPageTest.php` (añadir)

- [ ] **Step 1: Escribir el test que falla**

Añade a `AccountStatementPageTest`:

```php
    public function test_el_filtro_de_adeudo_deja_fuera_a_los_que_estan_al_corriente(): void
    {
        $company = $this->makeCompany();

        $alCorriente = $company->customers()->create([
            'name' => 'Al Corriente', 'email' => 'ok@ejemplo.mx', 'phone' => '1',
        ]);
        $moroso = $company->customers()->create([
            'name' => 'El Moroso', 'email' => 'moroso@ejemplo.mx', 'phone' => '2',
        ]);

        foreach ([[$alCorriente, now()->addDays(5)], [$moroso, now()->subDays(10)]] as $i => [$customer, $endDate]) {
            $machine = $company->washingMachines()->create([
                'machine_code' => 'LAV-00' . ($i + 1), 'brand' => 'Mabe', 'status' => 'rentada',
            ]);
            $company->rentals()->create([
                'customer_id' => $customer->id,
                'washing_machine_id' => $machine->id,
                'start_date' => now()->subMonths(2)->toDateString(),
                'end_date' => $endDate->toDateString(),
                'status' => 'activa',
            ]);
        }

        $conAdeudo = \App\Models\Customer::where('company_id', $company->id)
            ->whereHas('rentals', fn ($q) => $q
                ->whereIn('status', ['activa', 'vencida'])
                ->whereDate('end_date', '<', \Carbon\Carbon::today()))
            ->pluck('name');

        $this->assertSame(['El Moroso'], $conAdeudo->all());
    }
```

- [ ] **Step 2: Correr el test**

Run: `php artisan test --filter=test_el_filtro_de_adeudo_deja_fuera`
Expected: PASS. Este test fija la consulta que va a usar el filtro antes de meterla a la
interfaz, donde probarla es mucho más caro.

- [ ] **Step 3: Añadir la columna**

En `app/Filament/Resources/CustomerResource.php`, dentro de `->columns([...])`, después
de la columna `phone`:

```php
                Tables\Columns\TextColumn::make('debt')
                    ->label('Debe')
                    // El saldo se calcula, no vive en la base de datos, así que esta
                    // columna no se puede ordenar. Para ver quién debe más está el
                    // widget "Clientes con adeudo" del escritorio.
                    ->state(fn (Customer $record) => app(\App\Support\AccountStatement::class)
                        ->forCustomer($record)->total)
                    ->formatStateUsing(fn ($state) => '$' . number_format((float) $state, 2))
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'gray')
                    ->weight(fn ($state) => $state > 0 ? 'bold' : 'normal'),
```

- [ ] **Step 4: Añadir el filtro**

En el mismo archivo, reemplaza:

```php
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
```

por:

```php
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\Filter::make('con_adeudo')
                    ->label('Solo con adeudo')
                    ->query(fn (\Illuminate\Database\Eloquent\Builder $query) => $query
                        ->whereHas('rentals', fn ($rentals) => $rentals
                            ->whereIn('status', ['activa', 'vencida'])
                            ->whereDate('end_date', '<', \Carbon\Carbon::today()))),
            ])
```

- [ ] **Step 5: Añadir el botón**

En el mismo archivo, reemplaza:

```php
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ])
```

por:

```php
            ->actions([
                Tables\Actions\Action::make('estado_de_cuenta')
                    ->label('Estado de cuenta')
                    ->icon('heroicon-o-banknotes')
                    ->color('warning')
                    ->url(fn (Customer $record) => static::getUrl('estado-de-cuenta', ['record' => $record])),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ])
```

- [ ] **Step 6: Correr toda la suite**

Run: `php artisan test`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Filament/Resources/CustomerResource.php tests/Feature/AccountStatementPageTest.php
git commit -m "feat: surface customer debt in the customers list"
```

---

## Task 9: Total por cobrar y clientes con adeudo en el escritorio

**Files:**
- Create: `app/Filament/Widgets/CustomersWithDebtWidget.php`, `resources/views/filament/widgets/customers-with-debt.blade.php`
- Modify: `app/Filament/Widgets/PaymentStats.php`

- [ ] **Step 1: Añadir el recuadro del total**

En `app/Filament/Widgets/PaymentStats.php`, dentro de `getStats()`, después de la línea
que calcula `$overdueRentals`:

```php
        $totalOwed = app(\App\Support\AccountStatement::class)->totalForCompany($tenant);
```

Y en el array que devuelve, después del `Stat::make('Pagos Pendientes', ...)`:

```php
            Stat::make('Total por Cobrar', '$' . number_format($totalOwed, 2))
                ->description('Suma de lo que deben tus clientes')
                ->color($totalOwed > 0 ? 'danger' : 'success'),
```

- [ ] **Step 2: Crear el widget**

Crea `app/Filament/Widgets/CustomersWithDebtWidget.php`:

```php
<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\CustomerResource;
use App\Support\AccountStatement;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

class CustomersWithDebtWidget extends Widget
{
    protected static string $view = 'filament.widgets.customers-with-debt';

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    /** @return Collection<int, \App\Support\Statement> */
    public function getStatements(): Collection
    {
        return app(AccountStatement::class)
            ->forCompany(Filament::getTenant())
            ->take(10);
    }

    public function statementUrl($statement): string
    {
        return CustomerResource::getUrl('estado-de-cuenta', ['record' => $statement->customer]);
    }
}
```

- [ ] **Step 3: Crear la vista del widget**

Crea `resources/views/filament/widgets/customers-with-debt.blade.php`:

```blade
@php
    $statements = $this->getStatements();
@endphp

<x-filament-widgets::widget>
    <x-filament::section heading="Clientes con adeudo">
        @if ($statements->isEmpty())
            <p class="text-sm text-gray-500">Nadie te debe. Todos tus clientes están al corriente.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-gray-500">
                        <tr>
                            <th class="py-2 pr-4">Cliente</th>
                            <th class="py-2 pr-4">Debe desde</th>
                            <th class="py-2 pr-4">Lavadoras</th>
                            <th class="py-2">Debe</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($statements as $statement)
                            <tr class="border-t border-gray-100 dark:border-gray-800">
                                <td class="py-2 pr-4">
                                    <a href="{{ $this->statementUrl($statement) }}"
                                       class="text-primary-600 hover:underline">
                                        {{ $statement->customer->name }}
                                    </a>
                                </td>
                                <td class="py-2 pr-4">
                                    {{ $statement->owingSince?->format('d/m/Y') ?? '—' }}
                                </td>
                                <td class="py-2 pr-4">{{ count($statement->lines) }}</td>
                                <td class="py-2 font-semibold text-danger-600">
                                    ${{ number_format($statement->total, 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
```

El widget se registra solo: `AdminPanelProvider` ya hace
`discoverWidgets(in: app_path('Filament/Widgets'))`.

- [ ] **Step 4: Correr toda la suite**

Run: `php artisan test`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Widgets resources/views/filament/widgets
git commit -m "feat: show total owed and top debtors on the dashboard"
```

---

## Task 10: Verificación en el navegador

- [ ] **Step 1: Levantar el servidor**

```bash
php artisan serve --port=8199
```

- [ ] **Step 2: Entrar al demo y revisar**

Abre `http://127.0.0.1:8199/demo`. El sandbox trae dos rentas vencidas (6 y 15 días de
atraso) con pagos de $250 y semana de 7 días, así que los números esperados son:

- La renta vencida hace 6 días → 1 periodo → **$250**.
- La renta vencida hace 15 días → 3 periodos → **$750**.
- **Total por cobrar del negocio: $1,000.**

Comprueba a ojo:

1. En el Escritorio aparece "Total por Cobrar" con **$1,000.00**.
2. Abajo, "Clientes con adeudo" lista dos clientes, el de $750 primero.
3. En Mis Clientes, la columna "Debe" muestra los montos en rojo y el resto en $0.00.
4. El filtro "Solo con adeudo" deja exactamente dos clientes.
5. El botón "Estado de cuenta" abre la pantalla con el saldo, la tabla de lavadoras y
   el historial de pagos.

- [ ] **Step 3: Comprobar el caso sin configuración**

```bash
php artisan tinker --execute="\$c = \App\Models\Company::demo()->first(); \$c->settings()->update(['price' => 0]);"
```

Recarga el estado de cuenta de un cliente de ese demo: debe decir "No se puede calcular
el adeudo" y **no** $0.00. Después deshaz el cambio:

```bash
php artisan tinker --execute="\$c = \App\Models\Company::demo()->first(); \$c->settings()->update(['price' => 250]);"
```

- [ ] **Step 4: Limpiar los demos de prueba**

```bash
php artisan tinker --execute="\App\Models\Company::demo()->update(['demo_expires_at' => now()->subHour()]);"
php artisan demo:cleanup
```

- [ ] **Step 5: Commit final si quedó algo suelto**

```bash
git status
```
