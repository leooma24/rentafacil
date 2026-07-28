# Demo en vivo — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Un botón público en el landing que, sin pedir datos, mete al visitante a un sandbox desechable del panel real de Renta Fácil, ya poblado con datos de ejemplo realistas, que se borra solo a las 24 horas.

**Architecture:** Un servicio `DemoCompanyBuilder` genera al vuelo una `Company` marcada `is_demo` con usuario propio y datos calculados relativos a `now()`. Un `DemoController` la crea, autentica al visitante y lo manda al panel de Filament. Un comando `demo:cleanup` horario borra las vencidas. Tres blindajes evitan que el sandbox toque correos reales, Stripe o se use para abusar del servidor.

**Tech Stack:** Laravel 11 (esqueleto legacy: `app/Console/Kernel.php`, `app/Http/Kernel.php`), Filament 3.3 con multi-tenancy sobre `Company`, PHPUnit, MySQL.

**Spec:** `docs/superpowers/specs/2026-07-27-demo-en-vivo-design.md`

---

## Contexto del código que vas a tocar

Cosas que no son obvias y te van a morder si no las sabes:

- **El tenant es `Company`.** El panel vive en `/propietario/{company_id}`. `Filament::getTenant()` devuelve la `Company`. Un `User` se asocia por la tabla pivote `company_user` vía `$company->members()->attach($user)`.
- **`Customer::address()` está muerto.** La migración `2024_10_30_150249_add_addresable_column_to_addresses_table.php` borró la columna `customer_id` de `addresses` y la volvió polimórfica. Usa **siempre** `$customer->addresses()->create([...])` (morphMany), nunca `$customer->address()`.
- **Las tablas de geografía tienen nombre en español.** `Country` → `paises`, `State` → `estados`, `Township` → `municipios`, `Neighborhood` → `colonias`. Su única columna de texto es `nombre`.
- **Las lavadoras no guardan precio de renta.** El precio vive en `Setting` (`price`, `days_per_payment`) por empresa. Los ingresos salen de `payments.amount` con `status = 'completado'`.
- **Valores de estado exactos** (son enums en MySQL, no inventes otros):
  - `washing_machines.status`: `disponible`, `rentada`, `mantenimiento`, `vendida`, `fuera_de_servicio`
  - `rentals.status`: `activa`, `vencida`, `completada`, `cancelada`
  - `payments.status`: `completado`, `pendiente`
  - `maintenances.maintenance_type`: `preventivo`, `correctivo`
  - `incidents.status`: `abierta`, `en_progreso`, `cerrada` · `priority`: `baja`, `media`, `alta` · `type`: `mecánica`, `eléctrica`, `software`, `otra`
- **Los modelos principales usan `SoftDeletes`** (migración `2026_04_05_195149`). Para borrar de verdad en el cleanup necesitas `forceDelete()`.
- **El scheduler está en `app/Console/Kernel.php`**, no en `routes/console.php`.

---

## Estructura de archivos

**Se crean:**

| Archivo | Responsabilidad |
|---|---|
| `database/migrations/2026_07_27_000000_add_demo_flags.php` | Columnas `is_demo`/`demo_expires_at` en `companies` e `is_demo` en `users`. |
| `app/Services/DemoCompanyBuilder.php` | Genera la empresa demo completa. Sin dependencias de HTTP, para poder probarla aislada. |
| `app/Http/Controllers/DemoController.php` | Pantalla de espera + creación del sandbox y login. Delgado: solo orquesta. |
| `app/Support/PanelBanner.php` | Decide qué barra mostrar arriba del panel (demo / prueba / plan vencido). Extraído del closure inline de `AdminPanelProvider`. |
| `app/Console/Commands/CleanupDemos.php` | Borra en duro las empresas demo vencidas. |
| `resources/views/demo/preparando.blade.php` | Pantalla "Preparando tu demo…". |
| `tests/Unit/DemoCompanyBuilderTest.php` | Contenido y frescura de los datos generados. |
| `tests/Unit/PanelBannerTest.php` | Qué barra corresponde a cada estado de empresa. |
| `tests/Feature/DemoAccessTest.php` | Rutas, login, rate limit y botones en el landing. |
| `tests/Feature/DemoIsolationTest.php` | Cleanup, comandos programados y Stripe ignoran/protegen las demo. |

**Se modifican:**

| Archivo | Cambio |
|---|---|
| `phpunit.xml` | Apuntar los tests a una base de datos separada. |
| `app/Models/Company.php` | `$fillable`, `$casts` y scopes `demo()` / `expiredDemos()`. |
| `app/Models/User.php` | `$fillable` y `$casts` para `is_demo`. |
| `app/Providers/Filament/AdminPanelProvider.php:92-115` | El closure del render hook delega en `PanelBanner`. |
| `app/Http/Controllers/PlanCheckoutController.php` | Guard contra tenants demo. |
| `app/Console/Commands/SendRentalReminders.php` | Filtro `is_demo = false`. |
| `app/Console/Commands/MarkRentalsAsOverdue.php` | Filtro `is_demo = false`. |
| `app/Console/Commands/CheckInactiveUsers.php` | Filtro `is_demo = false`. |
| `app/Console/Commands/UserLifecycleEmails.php` | Filtro `is_demo = false`. |
| `app/Console/Kernel.php` | Registrar `demo:cleanup` cada hora. |
| `routes/web.php` | Rutas `/demo` y `/demo/iniciar`. |
| `resources/views/livewire/banner.blade.php:9` | Botón de demo en el hero. |
| `resources/views/components/layouts/app.blade.php:138` | Link de demo en el menú. |
| `resources/views/livewire/show-home.blade.php:264` | Botón de demo en el CTA de cierre. |

---

## Task 1: Base de datos de pruebas aislada

Ahora mismo `phpunit.xml` no define conexión, así que los tests correrían contra `flavadoras`, la base de desarrollo. Cualquier test con `RefreshDatabase` la borraría. Esto va primero.

No usamos SQLite en memoria porque la migración `add_addresable_column_to_addresses_table` hace `dropForeign` + `dropIndex` por nombre, que SQLite no soporta igual que MySQL. Usamos una base MySQL aparte.

**Files:**
- Modify: `phpunit.xml`
- Test: `tests/Feature/DemoAccessTest.php`

- [ ] **Step 1: Crear la base de datos de pruebas**

```bash
mysql -u root -e "CREATE DATABASE IF NOT EXISTS flavadoras_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Si `mysql` no está en el PATH en Laragon, usa la ruta completa, por ejemplo:
`C:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysql.exe -u root -e "..."`

Esperado: sin salida y sin error.

- [ ] **Step 2: Apuntar PHPUnit a esa base**

En `phpunit.xml`, dentro del bloque `<php>`, reemplaza las dos líneas comentadas de SQLite:

```xml
        <!-- <env name="DB_CONNECTION" value="sqlite"/> -->
        <!-- <env name="DB_DATABASE" value=":memory:"/> -->
```

por:

```xml
        <env name="DB_CONNECTION" value="mysql"/>
        <env name="DB_DATABASE" value="flavadoras_testing"/>
```

- [ ] **Step 3: Escribir el test de humo**

Crea `tests/Feature/DemoAccessTest.php`:

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_las_migraciones_corren_en_la_base_de_pruebas(): void
    {
        $this->assertSame('flavadoras_testing', config('database.connections.mysql.database'));
        $this->assertTrue(\Schema::hasTable('companies'));
    }
}
```

- [ ] **Step 4: Correr el test**

Run: `php artisan test --filter=test_las_migraciones_corren_en_la_base_de_pruebas`
Expected: PASS. Si falla por conexión, revisa que la base exista y que `DB_USERNAME`/`DB_PASSWORD` de `.env` sirvan para ella.

- [ ] **Step 5: Confirmar que la base de desarrollo sigue intacta**

Run: `php artisan tinker --execute="echo \App\Models\Company::count();"`
Expected: imprime el número de empresas reales que ya tenías (no 0 por sorpresa).

- [ ] **Step 6: Commit**

```bash
git add phpunit.xml tests/Feature/DemoAccessTest.php
git commit -m "test: isolate test suite in its own database"
```

---

## Task 2: Marcar empresas y usuarios como demo

**Files:**
- Create: `database/migrations/2026_07_27_000000_add_demo_flags.php`
- Modify: `app/Models/Company.php`, `app/Models/User.php`
- Test: `tests/Unit/DemoCompanyBuilderTest.php`

- [ ] **Step 1: Escribir el test que falla**

Crea `tests/Unit/DemoCompanyBuilderTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoCompanyBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_los_scopes_separan_demos_vencidas_de_las_vivas(): void
    {
        $real = Company::create(['name' => 'Real', 'phone' => '1', 'email' => 'r@x.com']);
        $viva = Company::create([
            'name' => 'Demo viva', 'phone' => '2', 'email' => 'v@x.com',
            'is_demo' => true, 'demo_expires_at' => now()->addHours(5),
        ]);
        $vencida = Company::create([
            'name' => 'Demo vencida', 'phone' => '3', 'email' => 'x@x.com',
            'is_demo' => true, 'demo_expires_at' => now()->subHour(),
        ]);

        $this->assertEqualsCanonicalizing(
            [$viva->id, $vencida->id],
            Company::demo()->pluck('id')->all()
        );
        $this->assertSame([$vencida->id], Company::expiredDemos()->pluck('id')->all());
        $this->assertFalse($real->fresh()->is_demo);
    }
}
```

- [ ] **Step 2: Correr el test para verificar que falla**

Run: `php artisan test --filter=test_los_scopes_separan_demos`
Expected: FAIL — `Column not found: 'is_demo'`.

- [ ] **Step 3: Crear la migración**

Crea `database/migrations/2026_07_27_000000_add_demo_flags.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('is_demo')->default(false)->index();
            $table->timestamp('demo_expires_at')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_demo')->default(false)->index();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['is_demo', 'demo_expires_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_demo');
        });
    }
};
```

- [ ] **Step 4: Actualizar los modelos**

En `app/Models/Company.php`, reemplaza el bloque `$fillable`:

```php
    protected $fillable = [
        'name',
        'address',
        'phone',
        'email',
    ];
```

por:

```php
    protected $fillable = [
        'name',
        'address',
        'phone',
        'email',
        'is_demo',
        'demo_expires_at',
    ];

    protected $casts = [
        'is_demo' => 'boolean',
        'demo_expires_at' => 'datetime',
    ];

    public function scopeDemo($query)
    {
        return $query->where('is_demo', true);
    }

    public function scopeExpiredDemos($query)
    {
        return $query->where('is_demo', true)->where('demo_expires_at', '<', now());
    }
```

En `app/Models/User.php`, añade `'is_demo'` al final del array `$fillable` y, dentro del método `casts()` existente (o del array `$casts`, según cómo esté declarado en el archivo), añade la línea `'is_demo' => 'boolean',`.

- [ ] **Step 5: Correr la migración y el test**

Run: `php artisan migrate && php artisan test --filter=test_los_scopes_separan_demos`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_07_27_000000_add_demo_flags.php app/Models/Company.php app/Models/User.php tests/Unit/DemoCompanyBuilderTest.php
git commit -m "feat: add demo flags to companies and users"
```

---

## Task 3: DemoCompanyBuilder — empresa, usuario y plan

**Files:**
- Create: `app/Services/DemoCompanyBuilder.php`
- Test: `tests/Unit/DemoCompanyBuilderTest.php` (añadir)

- [ ] **Step 1: Escribir el test que falla**

Añade a `tests/Unit/DemoCompanyBuilderTest.php` (dentro de la clase, y añade los `use` de `App\Models\Package` y `App\Services\DemoCompanyBuilder` arriba):

```php
    private function seedPackage(): void
    {
        Package::create(['name' => 'Pro', 'max_clients' => 500, 'max_washers' => 500, 'price' => 999]);
    }

    public function test_construye_una_empresa_demo_con_usuario_y_plan(): void
    {
        $this->seedPackage();

        $company = (new DemoCompanyBuilder())->build();

        $this->assertTrue($company->is_demo);
        $this->assertNotNull($company->demo_expires_at);
        $this->assertEqualsWithDelta(
            DemoCompanyBuilder::LIFETIME_HOURS,
            now()->diffInHours($company->demo_expires_at, false),
            1
        );

        $user = $company->members()->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->is_demo);
        $this->assertStringStartsWith('demo+', $user->email);

        $this->assertTrue($company->hasActivePackage());
        $this->assertSame(250.0, (float) $company->settings->price);
        $this->assertSame(7, (int) $company->settings->days_per_payment);
    }
```

- [ ] **Step 2: Correr el test para verificar que falla**

Run: `php artisan test --filter=test_construye_una_empresa_demo_con_usuario`
Expected: FAIL — `Class "App\Services\DemoCompanyBuilder" not found`.

- [ ] **Step 3: Crear el servicio con lo mínimo**

Crea `app/Services/DemoCompanyBuilder.php`:

```php
<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Package;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoCompanyBuilder
{
    /** Horas que vive un sandbox antes de que demo:cleanup lo borre. */
    public const LIFETIME_HOURS = 24;

    public function build(): Company
    {
        $expiresAt = now()->addHours(self::LIFETIME_HOURS);

        $company = Company::create([
            'name' => 'Lavandería Demo',
            'phone' => '6680000000',
            'email' => 'contacto@lavanderiademo.mx',
            'is_demo' => true,
            'demo_expires_at' => $expiresAt,
        ]);

        $user = User::create([
            'name' => 'Visitante Demo',
            'email' => 'demo+' . Str::uuid() . '@rentafacil.local',
            'password' => Hash::make(Str::random(32)),
            'is_demo' => true,
        ]);

        $company->members()->attach($user);

        // Mismo criterio que RegisterCompany: el paquete más caro disponible.
        $package = Package::orderByDesc('price')->first();
        if ($package) {
            $company->companyPackage()->create([
                'package_id' => $package->id,
                'start_date' => now(),
                'end_date' => $expiresAt,
            ]);
        }

        $company->settings()->create([
            'price' => 250,
            'days_per_payment' => 7,
        ]);

        return $company;
    }
}
```

- [ ] **Step 4: Correr el test**

Run: `php artisan test --filter=test_construye_una_empresa_demo_con_usuario`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/DemoCompanyBuilder.php tests/Unit/DemoCompanyBuilderTest.php
git commit -m "feat: build demo company with user, plan and settings"
```

---

## Task 4: DemoCompanyBuilder — lavadoras y clientes

14 lavadoras: 10 `rentada` (las ocuparán 8 rentas activas y 2 vencidas en la Task 5), 2 `disponible`, 1 `mantenimiento`, 1 `fuera_de_servicio`.

**Files:**
- Modify: `app/Services/DemoCompanyBuilder.php`
- Test: `tests/Unit/DemoCompanyBuilderTest.php` (añadir)

- [ ] **Step 1: Escribir el test que falla**

```php
    public function test_genera_catorce_lavadoras_y_veinte_clientes_con_direccion(): void
    {
        $this->seedPackage();

        $company = (new DemoCompanyBuilder())->build();

        $machines = $company->washingMachines;
        $this->assertCount(14, $machines);
        $this->assertSame(10, $machines->where('status', 'rentada')->count());
        $this->assertSame(2, $machines->where('status', 'disponible')->count());
        $this->assertSame(1, $machines->where('status', 'mantenimiento')->count());
        $this->assertSame(1, $machines->where('status', 'fuera_de_servicio')->count());
        $this->assertSame('LAV-001', $machines->sortBy('machine_code')->first()->machine_code);
        $this->assertTrue($machines->every(fn ($m) => $m->purchase_price > 0));

        $customers = $company->customers;
        $this->assertCount(20, $customers);
        $this->assertTrue($customers->every(fn ($c) => $c->addresses()->exists()));
        $this->assertTrue($customers->every(fn ($c) => filled($c->phone)));
    }
```

- [ ] **Step 2: Correr el test para verificar que falla**

Run: `php artisan test --filter=test_genera_catorce_lavadoras`
Expected: FAIL — `Failed asserting that actual size 0 matches expected size 14`.

- [ ] **Step 3: Implementar**

En `app/Services/DemoCompanyBuilder.php`, añade estos `use` arriba:

```php
use App\Models\Country;
use App\Models\Customer;
use App\Models\Neighborhood;
use App\Models\State;
use App\Models\Township;
use App\Models\WashingMachine;
```

Y dentro de `build()`, justo antes de `return $company;`:

```php
        $this->createMachines($company);
        $this->createCustomers($company);
```

Añade estos métodos privados a la clase:

```php
    private const MACHINE_MODELS = [
        ['brand' => 'Whirlpool', 'model' => '7MWTW1602BM', 'capacity' => '16 kg', 'price' => 8900],
        ['brand' => 'Mabe', 'model' => 'LMA6123PBAB0', 'capacity' => '12 kg', 'price' => 7200],
        ['brand' => 'LG', 'model' => 'WT16DSB', 'capacity' => '16 kg', 'price' => 10500],
        ['brand' => 'Easy', 'model' => 'LEA77114CBAB01', 'capacity' => '17 kg', 'price' => 6800],
        ['brand' => 'Samsung', 'model' => 'WA17T6260BW', 'capacity' => '17 kg', 'price' => 11200],
    ];

    /** 10 rentadas, 2 disponibles, 1 en mantenimiento, 1 fuera de servicio. */
    private const MACHINE_STATUSES = [
        'rentada', 'rentada', 'rentada', 'rentada', 'rentada',
        'rentada', 'rentada', 'rentada', 'rentada', 'rentada',
        'disponible', 'disponible', 'mantenimiento', 'fuera_de_servicio',
    ];

    private function createMachines(Company $company): void
    {
        foreach (self::MACHINE_STATUSES as $index => $status) {
            $spec = self::MACHINE_MODELS[$index % count(self::MACHINE_MODELS)];

            $company->washingMachines()->create([
                'machine_code' => sprintf('LAV-%03d', $index + 1),
                'brand' => $spec['brand'],
                'model' => $spec['model'],
                'status' => $status,
                'serial_number' => 'SN' . str_pad((string) (1000 + $index), 6, '0', STR_PAD_LEFT),
                'purchase_date' => now()->subMonths(6 + $index)->toDateString(),
                'purchase_price' => $spec['price'],
                'type' => 'automatica',
                'color' => 'blanco',
                'load_capacity' => $spec['capacity'],
            ]);
        }
    }

    private const CUSTOMER_NAMES = [
        'María González', 'Juan Pérez', 'Guadalupe Ramírez', 'José Luis Torres',
        'Ana Beltrán', 'Carlos Medina', 'Rosa Elena Ochoa', 'Miguel Ángel Cota',
        'Patricia Sandoval', 'Jesús Armando Ruiz', 'Leticia Camacho', 'Ramón Ibarra',
        'Verónica Zazueta', 'Francisco Javier León', 'Silvia Angulo', 'Alfonso Castro',
        'Norma Alicia Peña', 'Hugo Valenzuela', 'Claudia Iribe', 'Sergio Bojórquez',
    ];

    private const STREETS = [
        'Av. Álvaro Obregón', 'Calle Rosales', 'Blvd. Pedro Infante', 'Calle Ángel Flores',
        'Av. Universitarios', 'Calle Juan Carrasco', 'Blvd. Madero', 'Calle Ruperto Paliza',
    ];

    private function createCustomers(Company $company): void
    {
        $geo = $this->geography();

        foreach (self::CUSTOMER_NAMES as $index => $name) {
            $customer = $company->customers()->create([
                'name' => $name,
                'email' => 'cliente' . ($index + 1) . '@ejemplo.mx',
                'phone' => '668' . str_pad((string) (1000000 + $index * 37), 7, '0', STR_PAD_LEFT),
                'address' => self::STREETS[$index % count(self::STREETS)] . ' #' . (100 + $index * 7),
            ]);

            $customer->addresses()->create([
                'street' => self::STREETS[$index % count(self::STREETS)],
                'number' => (string) (100 + $index * 7),
                'city' => 'Los Mochis',
                'postal_code' => '8120' . ($index % 10),
                'country_id' => $geo['country']->id,
                'state_id' => $geo['state']->id,
                'township_id' => $geo['township']->id,
                'neighborhood_id' => $geo['neighborhood']->id,
            ]);
        }
    }

    /**
     * Las tablas de geografía pueden venir vacías en una instalación limpia,
     * así que reutilizamos lo que haya o sembramos un juego mínimo.
     */
    private function geography(): array
    {
        $country = Country::first() ?? Country::create(['nombre' => 'México']);
        $state = State::first() ?? State::create(['nombre' => 'Sinaloa']);
        $township = Township::first() ?? Township::create([
            'nombre' => 'Ahome',
            'estado_id' => $state->id,
        ]);
        $neighborhood = Neighborhood::first() ?? Neighborhood::create([
            'nombre' => 'Centro',
            'ciudad' => 'Los Mochis',
            'municipio_id' => $township->id,
            'asentamiento' => 'Colonia',
            'codigo_postal' => '81200',
        ]);

        return compact('country', 'state', 'township', 'neighborhood');
    }
```

- [ ] **Step 4: Correr el test**

Run: `php artisan test --filter=test_genera_catorce_lavadoras`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/DemoCompanyBuilder.php tests/Unit/DemoCompanyBuilderTest.php
git commit -m "feat: seed demo machines and customers"
```

---

## Task 5: DemoCompanyBuilder — rentas y pagos

Esta es la parte que hace que el demo se vea vivo. Todas las fechas cuelgan de `now()`.

- 8 rentas `activa` sobre las máquinas 1-8. Dos vencen pronto (en 2 y en 5 días) para poblar el calendario y los recordatorios.
- 2 rentas `vencida` sobre las máquinas 9-10, con 6 y 15 días de atraso, para `OverdueRentalsWidget`.
- 15 rentas `completada` repartidas en los últimos 6 meses, reusando máquinas, para que haya historial.
- Pagos semanales de 250 en estado `completado` desde el inicio de cada renta hasta hoy (o hasta su fin), que es de donde salen `MonthlyRevenueChart` y `MachineProfitabilityWidget`.

**Files:**
- Modify: `app/Services/DemoCompanyBuilder.php`
- Test: `tests/Unit/DemoCompanyBuilderTest.php` (añadir)

- [ ] **Step 1: Escribir el test que falla**

```php
    public function test_genera_rentas_activas_vencidas_y_completadas_con_pagos(): void
    {
        $this->seedPackage();

        $company = (new DemoCompanyBuilder())->build();
        $rentals = $company->rentals;

        $this->assertSame(8, $rentals->where('status', 'activa')->count());
        $this->assertSame(2, $rentals->where('status', 'vencida')->count());
        $this->assertSame(15, $rentals->where('status', 'completada')->count());

        // Hay al menos una renta que vence dentro de los próximos 7 días.
        $this->assertTrue(
            $rentals->where('status', 'activa')->contains(
                fn ($r) => \Carbon\Carbon::parse($r->end_date)->between(now(), now()->addDays(7))
            ),
            'Ninguna renta activa vence esta semana; el calendario se vería vacío.'
        );

        // Las vencidas están realmente atrasadas.
        $this->assertTrue(
            $rentals->where('status', 'vencida')->every(
                fn ($r) => \Carbon\Carbon::parse($r->end_date)->lt(now())
            )
        );

        $payments = \App\Models\Payment::where('company_id', $company->id)->get();
        $this->assertGreaterThan(50, $payments->count());
        $this->assertTrue($payments->every(fn ($p) => $p->status === 'completado'));
        $this->assertTrue($payments->every(fn ($p) => (float) $p->amount === 250.0));

        // Hay historial repartido en al menos 5 meses distintos.
        $months = $payments->map(fn ($p) => \Carbon\Carbon::parse($p->payment_date)->format('Y-m'))->unique();
        $this->assertGreaterThanOrEqual(5, $months->count());
    }
```

- [ ] **Step 2: Correr el test para verificar que falla**

Run: `php artisan test --filter=test_genera_rentas_activas_vencidas`
Expected: FAIL — se esperaban 8 rentas activas y hay 0.

- [ ] **Step 3: Implementar**

En `build()`, después de `$this->createCustomers($company);` y antes del `return`:

```php
        $this->createRentals($company);
```

Añade a la clase (y el `use Carbon\Carbon;` arriba):

```php
    private function createRentals(Company $company): void
    {
        $machines = $company->washingMachines()->orderBy('machine_code')->get();
        $customers = $company->customers()->orderBy('id')->get();

        // 8 activas sobre las máquinas 1-8. Las dos primeras vencen esta semana.
        $activeEndsInDays = [2, 5, 21, 34, 48, 60, 75, 92];
        foreach ($activeEndsInDays as $i => $endsIn) {
            $start = now()->subWeeks(3 + $i * 2)->startOfDay();
            $rental = $company->rentals()->create([
                'customer_id' => $customers[$i]->id,
                'washing_machine_id' => $machines[$i]->id,
                'start_date' => $start->toDateString(),
                'end_date' => now()->addDays($endsIn)->toDateString(),
                'status' => 'activa',
                'notes' => 'Renta de ejemplo generada para la demo.',
            ]);
            $this->createWeeklyPayments($company, $rental, $start, now());
        }

        // 2 vencidas sobre las máquinas 9-10, con 6 y 15 días de atraso.
        foreach ([6, 15] as $j => $lateDays) {
            $index = 8 + $j;
            $start = now()->subWeeks(10 + $j * 4)->startOfDay();
            $end = now()->subDays($lateDays)->startOfDay();
            $rental = $company->rentals()->create([
                'customer_id' => $customers[$index]->id,
                'washing_machine_id' => $machines[$index]->id,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'status' => 'vencida',
                'notes' => 'Cliente con pago atrasado.',
            ]);
            // Deja de pagar justo cuando se atrasó.
            $this->createWeeklyPayments($company, $rental, $start, $end);
        }

        // 15 completadas en los últimos 6 meses, reusando máquinas y clientes.
        for ($k = 0; $k < 15; $k++) {
            $start = now()->subDays(175 - $k * 10)->startOfDay();
            $end = (clone $start)->addDays(45);
            if ($end->gte(now())) {
                $end = now()->subDays(3)->startOfDay();
            }
            $rental = $company->rentals()->create([
                'customer_id' => $customers[$k % $customers->count()]->id,
                'washing_machine_id' => $machines[$k % $machines->count()]->id,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'status' => 'completada',
                'notes' => 'Renta finalizada.',
            ]);
            $this->createWeeklyPayments($company, $rental, $start, $end);
        }
    }

    private function createWeeklyPayments(Company $company, $rental, Carbon $from, Carbon $until): void
    {
        $date = (clone $from);
        $n = 0;

        while ($date->lte($until)) {
            $company->payments_direct()->create([
                'rental_id' => $rental->id,
                'amount' => 250,
                'payment_date' => $date->toDateString(),
                'payment_method' => $n % 3 === 0 ? 'transferencia' : 'efectivo',
                'reference' => 'DEMO-' . $rental->id . '-' . ($n + 1),
                'status' => 'completado',
            ]);
            $date->addDays(7);
            $n++;
        }
    }
```

`Company` tiene `payments()` como `hasManyThrough`, que no permite `create()`. Añade en `app/Models/Company.php`, junto a las demás relaciones, una relación directa:

```php
    public function payments_direct(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
```

y el `use App\Models\Payment;` no hace falta (mismo namespace), pero sí asegúrate de que `HasMany` ya esté importado en ese archivo (lo está).

- [ ] **Step 4: Correr el test**

Run: `php artisan test --filter=test_genera_rentas_activas_vencidas`
Expected: PASS.

- [ ] **Step 5: Correr toda la suite del builder**

Run: `php artisan test --filter=DemoCompanyBuilderTest`
Expected: PASS (4 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Services/DemoCompanyBuilder.php app/Models/Company.php tests/Unit/DemoCompanyBuilderTest.php
git commit -m "feat: seed demo rentals and payment history"
```

---

## Task 6: DemoCompanyBuilder — mantenimientos e incidencias

**Files:**
- Modify: `app/Services/DemoCompanyBuilder.php`
- Test: `tests/Unit/DemoCompanyBuilderTest.php` (añadir)

- [ ] **Step 1: Escribir el test que falla**

```php
    public function test_genera_mantenimientos_e_incidencias(): void
    {
        $this->seedPackage();

        $company = (new DemoCompanyBuilder())->build();

        $maintenances = $company->maintenances;
        $this->assertCount(4, $maintenances);
        $this->assertTrue($maintenances->every(fn ($m) => $m->cost > 0));
        $this->assertTrue($maintenances->every(
            fn ($m) => in_array($m->maintenance_type, ['preventivo', 'correctivo'], true)
        ));

        $incidents = $company->incidents;
        $this->assertCount(3, $incidents);
        $this->assertEqualsCanonicalizing(
            ['abierta', 'en_progreso', 'cerrada'],
            $incidents->pluck('status')->all()
        );
    }
```

- [ ] **Step 2: Correr el test para verificar que falla**

Run: `php artisan test --filter=test_genera_mantenimientos_e_incidencias`
Expected: FAIL — se esperaban 4 mantenimientos y hay 0.

- [ ] **Step 3: Implementar**

En `build()`, antes del `return`:

```php
        $this->createMaintenanceAndIncidents($company);
```

Añade a la clase:

```php
    private function createMaintenanceAndIncidents(Company $company): void
    {
        $machines = $company->washingMachines()->orderBy('machine_code')->get();
        $user = $company->members()->first();

        $maintenances = [
            ['type' => 'preventivo', 'desc' => 'Limpieza general y revisión de mangueras.', 'cost' => 350, 'daysAgo' => 12, 'status' => 'completado'],
            ['type' => 'correctivo', 'desc' => 'Cambio de banda del motor.', 'cost' => 780, 'daysAgo' => 34, 'status' => 'completado'],
            ['type' => 'correctivo', 'desc' => 'Reemplazo de bomba de desagüe.', 'cost' => 1250, 'daysAgo' => 58, 'status' => 'completado'],
            ['type' => 'preventivo', 'desc' => 'Revisión programada del tablero.', 'cost' => 400, 'daysAgo' => 1, 'status' => 'pendiente'],
        ];

        foreach ($maintenances as $i => $m) {
            $start = now()->subDays($m['daysAgo']);
            $company->maintenances()->create([
                'washing_machine_id' => $machines[$i + 10]->id ?? $machines[$i]->id,
                'technician_name' => ['Luis Herrera', 'Óscar Payán', 'Luis Herrera', 'Óscar Payán'][$i],
                'start_date' => $start->toDateString(),
                'end_date' => $m['status'] === 'completado' ? $start->copy()->addDay()->toDateString() : null,
                'maintenance_type' => $m['type'],
                'description' => $m['desc'],
                'cost' => $m['cost'],
                'status' => $m['status'],
            ]);
        }

        $incidents = [
            ['title' => 'La lavadora no centrifuga', 'status' => 'abierta', 'priority' => 'alta', 'type' => 'mecánica', 'resolved' => false],
            ['title' => 'Fuga de agua en la manguera', 'status' => 'en_progreso', 'priority' => 'media', 'type' => 'mecánica', 'resolved' => false],
            ['title' => 'No enciende el panel', 'status' => 'cerrada', 'priority' => 'alta', 'type' => 'eléctrica', 'resolved' => true],
        ];

        foreach ($incidents as $i => $inc) {
            $company->incidents()->create([
                'title' => $inc['title'],
                'description' => 'Reporte de ejemplo generado para la demo.',
                'status' => $inc['status'],
                'priority' => $inc['priority'],
                'type' => $inc['type'],
                'user_id' => $user?->id,
                'washing_machine_id' => $machines[$i]->id,
                'resolved_at' => $inc['resolved'] ? now()->subDays(4) : null,
            ]);
        }
    }
```

- [ ] **Step 4: Correr el test**

Run: `php artisan test --filter=test_genera_mantenimientos_e_incidencias`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/DemoCompanyBuilder.php tests/Unit/DemoCompanyBuilderTest.php
git commit -m "feat: seed demo maintenance records and incidents"
```

---

## Task 7: Rutas, controlador y límite por IP

**Files:**
- Create: `app/Http/Controllers/DemoController.php`, `resources/views/demo/preparando.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/DemoAccessTest.php` (añadir)

- [ ] **Step 1: Escribir los tests que fallan**

Añade a `tests/Feature/DemoAccessTest.php` (con `use App\Models\Company;` y `use App\Models\Package;` arriba):

```php
    private function seedPackage(): void
    {
        Package::create(['name' => 'Pro', 'max_clients' => 500, 'max_washers' => 500, 'price' => 999]);
    }

    public function test_la_pantalla_de_espera_responde(): void
    {
        $this->get('/demo')->assertOk()->assertSee('Preparando tu demo');
    }

    public function test_iniciar_demo_crea_sandbox_y_autentica(): void
    {
        $this->seedPackage();

        $response = $this->postJson('/demo/iniciar');

        $response->assertOk()->assertJsonStructure(['url']);
        $this->assertAuthenticated();

        $company = Company::demo()->first();
        $this->assertNotNull($company);
        $this->assertStringContainsString("/propietario/{$company->id}", $response->json('url'));
        $this->assertTrue(auth()->user()->companies->contains($company));
        $this->assertGreaterThan(0, $company->washingMachines()->count());
    }

    public function test_el_limite_por_ip_corta_al_sexto_intento(): void
    {
        $this->seedPackage();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/demo/iniciar')->assertOk();
        }

        $this->postJson('/demo/iniciar')->assertStatus(429);
    }
```

- [ ] **Step 2: Correr los tests para verificar que fallan**

Run: `php artisan test --filter=DemoAccessTest`
Expected: FAIL — 404 en `/demo`.

- [ ] **Step 3: Crear el controlador**

Crea `app/Http/Controllers/DemoController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Services\DemoCompanyBuilder;
use Illuminate\Support\Facades\Auth;

class DemoController extends Controller
{
    public function index()
    {
        return view('demo.preparando');
    }

    public function create(DemoCompanyBuilder $builder)
    {
        $company = $builder->build();

        Auth::login($company->members()->first());

        return response()->json([
            'url' => url("/propietario/{$company->id}"),
        ]);
    }
}
```

- [ ] **Step 4: Crear la pantalla de espera**

Crea `resources/views/demo/preparando.blade.php`:

```blade
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>Preparando tu demo — Renta Fácil</title>
    <style>
        body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center;
               font-family:Roboto,system-ui,sans-serif; background:#0f172a; color:#fff; text-align:center; }
        .spinner { width:48px; height:48px; margin:0 auto 24px; border:4px solid rgba(255,255,255,.2);
                   border-top-color:#06b6d4; border-radius:50%; animation:spin 1s linear infinite; }
        @keyframes spin { to { transform:rotate(360deg); } }
        h1 { font-size:22px; margin:0 0 8px; }
        p { color:#94a3b8; margin:0; font-size:15px; }
        .error { color:#fca5a5; margin-top:16px; display:none; }
    </style>
</head>
<body>
    <div>
        <div class="spinner"></div>
        <h1>Preparando tu demo…</h1>
        <p>Estamos creando un negocio de ejemplo solo para ti.</p>
        <p class="error" id="error">No pudimos crear el demo. Recarga la página para intentarlo de nuevo.</p>
    </div>

    <script>
        fetch('{{ route('demo.create') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
        })
        .then(function (res) {
            if (!res.ok) { throw new Error('failed'); }
            return res.json();
        })
        .then(function (data) { window.location = data.url; })
        .catch(function () { document.getElementById('error').style.display = 'block'; });
    </script>
</body>
</html>
```

- [ ] **Step 5: Registrar las rutas**

En `routes/web.php`, añade `use App\Http\Controllers\DemoController;` con los demás `use`, y estas rutas después de `Route::get('/contratar/{package}', ShowPackage::class);`:

```php
Route::get('/demo', [DemoController::class, 'index'])->name('demo.start');
Route::post('/demo/iniciar', [DemoController::class, 'create'])
    ->middleware('throttle:5,60')
    ->name('demo.create');
```

- [ ] **Step 6: Correr los tests**

Run: `php artisan test --filter=DemoAccessTest`
Expected: PASS (4 tests).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/DemoController.php resources/views/demo/preparando.blade.php routes/web.php tests/Feature/DemoAccessTest.php
git commit -m "feat: add demo entry route with rate limiting"
```

---

## Task 8: Barra de demo dentro del panel

El closure del render hook en `AdminPanelProvider` ya mezcla tres casos (prueba activa, plan vencido, nada). Añadir un cuarto ahí adentro lo vuelve ilegible y no se puede probar. Lo extraemos a una clase.

**Files:**
- Create: `app/Support/PanelBanner.php`, `tests/Unit/PanelBannerTest.php`
- Modify: `app/Providers/Filament/AdminPanelProvider.php:92-115`

- [ ] **Step 1: Escribir el test que falla**

Crea `tests/Unit/PanelBannerTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\Package;
use App\Support\PanelBanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelBannerTest extends TestCase
{
    use RefreshDatabase;

    private function company(array $attrs, ?int $trialDays): Company
    {
        $company = Company::create(array_merge(
            ['name' => 'X', 'phone' => '1', 'email' => 'x@x.com'],
            $attrs
        ));

        if ($trialDays !== null) {
            $package = Package::create(['name' => 'Pro', 'max_clients' => 9, 'max_washers' => 9, 'price' => 1]);
            $company->companyPackage()->create([
                'package_id' => $package->id,
                'start_date' => now(),
                'end_date' => now()->addDays($trialDays),
            ]);
        }

        return $company->fresh();
    }

    public function test_empresa_demo_ve_la_barra_de_demo(): void
    {
        $company = $this->company(
            ['is_demo' => true, 'demo_expires_at' => now()->addHours(24)],
            10
        );

        $html = PanelBanner::for($company);

        $this->assertStringContainsString('demo', strtolower($html));
        $this->assertStringContainsString('/propietario/registrar', $html);
        $this->assertStringNotContainsString('Prueba gratuita', $html);
    }

    public function test_empresa_en_prueba_ve_el_aviso_de_prueba(): void
    {
        $company = $this->company([], 10);

        $this->assertStringContainsString('Prueba gratuita', PanelBanner::for($company));
    }

    public function test_empresa_sin_plan_vigente_ve_el_aviso_de_expirado(): void
    {
        $company = $this->company([], -1);

        $this->assertStringContainsString('expirado', PanelBanner::for($company));
    }

    public function test_sin_tenant_no_hay_barra(): void
    {
        $this->assertSame('', PanelBanner::for(null));
    }
}
```

- [ ] **Step 2: Correr el test para verificar que falla**

Run: `php artisan test --filter=PanelBannerTest`
Expected: FAIL — `Class "App\Support\PanelBanner" not found`.

- [ ] **Step 3: Crear la clase**

Crea `app/Support/PanelBanner.php`:

```php
<?php

namespace App\Support;

use App\Models\Company;

class PanelBanner
{
    public static function for(?Company $tenant): string
    {
        if (! $tenant) {
            return '';
        }

        if ($tenant->is_demo) {
            return self::bar(
                '#7c3aed',
                'Estás en un <strong>demo</strong>: los datos son de ejemplo y se borran solos en 24 horas.',
                '/propietario/registrar',
                'Crear mi cuenta real'
            );
        }

        $planUrl = "/propietario/{$tenant->id}/mi-plan";

        if ($tenant->isOnTrial()) {
            $days = $tenant->trialDaysLeft();
            $color = $days <= 3 ? '#ef4444' : ($days <= 7 ? '#f59e0b' : '#06b6d4');

            return self::bar(
                $color,
                "Prueba gratuita: te quedan <strong>{$days} días</strong>.",
                $planUrl,
                'Elegir un plan'
            );
        }

        if (! $tenant->hasActivePackage()) {
            return self::bar(
                '#ef4444',
                'Tu plan ha expirado.',
                $planUrl,
                'Contratar plan para continuar'
            );
        }

        return '';
    }

    private static function bar(string $color, string $message, string $url, string $linkText): string
    {
        return "<div style=\"background:{$color};color:#fff;text-align:center;padding:8px 16px;font-size:14px;font-weight:600;\">"
            . $message
            . " <a href=\"{$url}\" style=\"color:#fff;text-decoration:underline;margin-left:8px;\">{$linkText}</a>"
            . '</div>';
    }
}
```

- [ ] **Step 4: Conectar el render hook**

En `app/Providers/Filament/AdminPanelProvider.php`, reemplaza todo el bloque que va desde `->renderHook('panels::body.start', function () {` hasta el `})` que lo cierra (líneas 92-115 aprox., el que contiene los avisos de prueba y de plan expirado) por:

```php
            ->renderHook('panels::body.start', function () {
                if (auth()->user()?->hasRole('super_admin')) {
                    return '';
                }

                return new HtmlString(
                    \App\Support\PanelBanner::for(\Filament\Facades\Filament::getTenant())
                );
            })
```

- [ ] **Step 5: Correr los tests**

Run: `php artisan test --filter=PanelBannerTest`
Expected: PASS (4 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Support/PanelBanner.php tests/Unit/PanelBannerTest.php app/Providers/Filament/AdminPanelProvider.php
git commit -m "refactor: extract panel banner and add demo bar"
```

---

## Task 9: Blindar los comandos programados

Sin esto, cada sandbox creado le pide al servidor mandar correos diarios a direcciones inventadas.

**Files:**
- Modify: `app/Console/Commands/SendRentalReminders.php`, `app/Console/Commands/MarkRentalsAsOverdue.php`, `app/Console/Commands/CheckInactiveUsers.php`, `app/Console/Commands/UserLifecycleEmails.php`
- Test: `tests/Feature/DemoIsolationTest.php`

- [ ] **Step 1: Escribir el test que falla**

Crea `tests/Feature/DemoIsolationTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Services\DemoCompanyBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DemoIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function seedPackage(): void
    {
        Package::create(['name' => 'Pro', 'max_clients' => 500, 'max_washers' => 500, 'price' => 999]);
    }

    public function test_los_comandos_programados_no_tocan_empresas_demo(): void
    {
        Mail::fake();
        $this->seedPackage();

        $company = (new DemoCompanyBuilder())->build();
        $activasAntes = $company->rentals()->where('status', 'activa')->count();

        $this->artisan('rentals:mark-overdue')->assertSuccessful();
        $this->artisan('rentals:send-reminders')->assertSuccessful();
        $this->artisan('users:check-inactive')->assertSuccessful();
        $this->artisan('users:lifecycle-emails')->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertSame(
            $activasAntes,
            $company->rentals()->where('status', 'activa')->count(),
            'Un comando programado modificó rentas de una empresa demo.'
        );
    }
}
```

- [ ] **Step 2: Correr el test para verificar que falla**

Run: `php artisan test --filter=test_los_comandos_programados_no_tocan`
Expected: FAIL — se enviaron correos y/o cambió el conteo de rentas activas.

- [ ] **Step 3: Filtrar en los cuatro comandos**

En `app/Console/Commands/SendRentalReminders.php`, a **cada** una de las dos consultas `Rental::with([...])` (líneas 26 y 54 aprox.), encadena antes del `->get()`:

```php
            ->whereHas('company', fn ($q) => $q->where('is_demo', false))
```

En `app/Console/Commands/MarkRentalsAsOverdue.php`, a la consulta `Rental::where('status', 'activa')` (línea 30 aprox.), encadena antes del `->get()`:

```php
                                ->whereHas('company', fn ($q) => $q->where('is_demo', false))
```

En `app/Console/Commands/CheckInactiveUsers.php`, a la consulta `User::where('created_at', ...)` (línea 19 aprox.), añade antes del `->get()`:

```php
            ->where('is_demo', false)
```

En `app/Console/Commands/UserLifecycleEmails.php` hay cuatro consultas: tres que arrancan de `Company` y una de `User` (líneas 45, 69, 92 y 119 aprox.). A las de `Company` añádeles `->where('is_demo', false)`; a la de `User`, también `->where('is_demo', false)`. Ejemplo para la de la línea 92:

```php
        $companies = Company::with('members')
            ->where('is_demo', false)
            ->whereHas('companyPackage', function ($q) {
```

- [ ] **Step 4: Correr el test**

Run: `php artisan test --filter=test_los_comandos_programados_no_tocan`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands tests/Feature/DemoIsolationTest.php
git commit -m "fix: exclude demo companies from scheduled emails"
```

---

## Task 10: Blindar el checkout de Stripe

**Files:**
- Modify: `app/Http/Controllers/PlanCheckoutController.php`
- Test: `tests/Feature/DemoIsolationTest.php` (añadir)

- [ ] **Step 1: Escribir el test que falla**

```php
    public function test_una_empresa_demo_no_puede_llegar_a_stripe(): void
    {
        $this->seedPackage();

        $company = (new DemoCompanyBuilder())->build();
        $package = Package::first();

        $this->actingAs($company->members()->first())
            ->get("/plan/{$package->id}/checkout")
            ->assertRedirect('/propietario/registrar');
    }
```

- [ ] **Step 2: Correr el test para verificar que falla**

Run: `php artisan test --filter=test_una_empresa_demo_no_puede_llegar_a_stripe`
Expected: FAIL — intenta llamar a Stripe y truena por credenciales, o devuelve 302 a otra URL.

- [ ] **Step 3: Añadir el guard**

En `app/Http/Controllers/PlanCheckoutController.php`, dentro de `checkout()`, justo después del bloque `if (!$tenant) { abort(403); }`:

```php
        if ($tenant->is_demo) {
            return redirect('/propietario/registrar');
        }
```

- [ ] **Step 4: Correr el test**

Run: `php artisan test --filter=test_una_empresa_demo_no_puede_llegar_a_stripe`
Expected: PASS.

Nota: si el test falla porque Filament no resuelve el tenant fuera del panel, cambia el guard para leer el tenant de la sesión igual que ya lo hace el resto del controlador; no toques la firma del método.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/PlanCheckoutController.php tests/Feature/DemoIsolationTest.php
git commit -m "fix: block Stripe checkout for demo tenants"
```

---

## Task 11: Comando demo:cleanup

**Files:**
- Create: `app/Console/Commands/CleanupDemos.php`
- Modify: `app/Console/Kernel.php`
- Test: `tests/Feature/DemoIsolationTest.php` (añadir)

- [ ] **Step 1: Escribir el test que falla**

```php
    public function test_cleanup_borra_solo_las_demos_vencidas(): void
    {
        $this->seedPackage();

        $vencida = (new DemoCompanyBuilder())->build();
        $vencida->update(['demo_expires_at' => now()->subHour()]);
        $vencidaId = $vencida->id;
        $usuarioVencido = $vencida->members()->first()->id;

        $viva = (new DemoCompanyBuilder())->build();

        $real = \App\Models\Company::create(['name' => 'Real', 'phone' => '9', 'email' => 'real@x.com']);

        $this->artisan('demo:cleanup')->assertSuccessful();

        $this->assertDatabaseMissing('companies', ['id' => $vencidaId]);
        $this->assertDatabaseMissing('users', ['id' => $usuarioVencido]);
        $this->assertDatabaseMissing('washing_machines', ['company_id' => $vencidaId]);
        $this->assertDatabaseMissing('payments', ['company_id' => $vencidaId]);

        $this->assertDatabaseHas('companies', ['id' => $viva->id]);
        $this->assertDatabaseHas('companies', ['id' => $real->id]);
        $this->assertGreaterThan(0, $viva->washingMachines()->count());
    }
```

- [ ] **Step 2: Correr el test para verificar que falla**

Run: `php artisan test --filter=test_cleanup_borra_solo_las_demos_vencidas`
Expected: FAIL — `Command "demo:cleanup" is not defined`.

- [ ] **Step 3: Crear el comando**

Crea `app/Console/Commands/CleanupDemos.php`:

```php
<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupDemos extends Command
{
    protected $signature = 'demo:cleanup';

    protected $description = 'Borra las empresas demo cuya vigencia ya venció, junto con todos sus datos.';

    public function handle(): int
    {
        $companies = Company::expiredDemos()->get();

        foreach ($companies as $company) {
            DB::transaction(function () use ($company) {
                $userIds = $company->members()->pluck('users.id');

                // Los pagos cuelgan de rentas, así que van primero.
                DB::table('payments')->where('company_id', $company->id)->delete();
                DB::table('rentals')->where('company_id', $company->id)->delete();
                DB::table('maintenances')->where('company_id', $company->id)->delete();
                DB::table('incidents')->where('company_id', $company->id)->delete();

                $customerIds = DB::table('customers')->where('company_id', $company->id)->pluck('id');
                DB::table('addresses')
                    ->where('addressable_type', \App\Models\Customer::class)
                    ->whereIn('addressable_id', $customerIds)
                    ->delete();
                DB::table('customers')->where('company_id', $company->id)->delete();

                DB::table('washing_machines')->where('company_id', $company->id)->delete();
                DB::table('settings')->where('company_id', $company->id)->delete();
                DB::table('company_package')->where('company_id', $company->id)->delete();
                DB::table('company_user')->where('company_id', $company->id)->delete();

                DB::table('companies')->where('id', $company->id)->delete();

                User::whereIn('id', $userIds)->where('is_demo', true)->forceDelete();
            });
        }

        $this->info("Demos borradas: {$companies->count()}");

        return self::SUCCESS;
    }
}
```

Usamos `DB::table()->delete()` a propósito: los modelos tienen `SoftDeletes` y aquí queremos borrado real, sin dejar filas huérfanas marcadas.

Si el nombre de alguna tabla pivote no coincide (`company_package`, `company_user`), verifícalo con:
`php artisan tinker --execute="print_r(\Illuminate\Support\Facades\Schema::getTableListing());"`

- [ ] **Step 4: Registrar en el scheduler**

En `app/Console/Kernel.php`, dentro de `schedule()`, después de la línea de `backup:run`:

```php
        $schedule->command('demo:cleanup')->hourly();
```

- [ ] **Step 5: Correr el test**

Run: `php artisan test --filter=test_cleanup_borra_solo_las_demos_vencidas`
Expected: PASS.

- [ ] **Step 6: Verificar que el scheduler lo ve**

Run: `php artisan schedule:list`
Expected: aparece `demo:cleanup` con frecuencia horaria.

- [ ] **Step 7: Commit**

```bash
git add app/Console/Commands/CleanupDemos.php app/Console/Kernel.php tests/Feature/DemoIsolationTest.php
git commit -m "feat: add hourly demo cleanup command"
```

---

## Task 12: Botones en el landing

El CTA de cierre hoy tiene un botón "Solicitar Demo" que abre WhatsApp. Lo reemplazamos por el demo en vivo: un sandbox que se abre al instante le gana a pedir una cita, y el canal de WhatsApp sigue disponible en el widget de chat del sitio.

**Files:**
- Modify: `resources/views/livewire/banner.blade.php:9`, `resources/views/components/layouts/app.blade.php:138`, `resources/views/livewire/show-home.blade.php:264`
- Test: `tests/Feature/DemoAccessTest.php` (añadir)

- [ ] **Step 1: Escribir el test que falla**

```php
    public function test_la_home_ofrece_el_demo(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Ver demo en vivo');
        $response->assertSee('href="/demo"', false);
    }
```

- [ ] **Step 2: Correr el test para verificar que falla**

Run: `php artisan test --filter=test_la_home_ofrece_el_demo`
Expected: FAIL — no encuentra "Ver demo en vivo".

- [ ] **Step 3: Botón en el hero**

En `resources/views/livewire/banner.blade.php`, reemplaza:

```blade
                    <a href="/propietario/registrar" class="btn btn-primary btn-lg">Probar 15 Días Gratis</a>
                    <a href="#como-funciona" class="btn btn-outline btn-lg">Ver cómo funciona</a>
```

por:

```blade
                    <a href="/propietario/registrar" class="btn btn-primary btn-lg">Probar 15 Días Gratis</a>
                    <a href="/demo" class="btn btn-outline btn-lg">▶ Ver demo en vivo</a>
```

Y en el bloque `banner-trust` justo debajo, reemplaza el primer item:

```blade
                    <div class="banner-trust-item">
                        <i class="fas fa-check-circle"></i>
                        <span>Sin tarjeta de crédito</span>
                    </div>
```

por:

```blade
                    <div class="banner-trust-item">
                        <i class="fas fa-check-circle"></i>
                        <span>Sin tarjeta · Demo sin registro</span>
                    </div>
```

- [ ] **Step 4: Link en el menú**

En `resources/views/components/layouts/app.blade.php`, dentro del `<nav class="menu">`, antes del `<li>` de "Acceder":

```blade
                                <li><a href="/demo">Ver demo</a></li>
```

- [ ] **Step 5: Botón en el CTA de cierre**

En `resources/views/livewire/show-home.blade.php`, reemplaza:

```blade
            <a href="https://wa.me/6682493398?text=Quiero%20una%20demo%20de%20Renta%20Fácil" target="_blank" class="btn btn-outline btn-lg">Solicitar Demo</a>
```

por:

```blade
            <a href="/demo" class="btn btn-outline btn-lg">▶ Ver demo en vivo</a>
```

- [ ] **Step 6: Correr el test**

Run: `php artisan test --filter=test_la_home_ofrece_el_demo`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add resources/views/livewire/banner.blade.php resources/views/components/layouts/app.blade.php resources/views/livewire/show-home.blade.php tests/Feature/DemoAccessTest.php
git commit -m "feat: surface live demo link across the landing page"
```

---

## Task 13: Verificación completa

- [ ] **Step 1: Correr toda la suite**

Run: `php artisan test`
Expected: todos los tests en verde. Si `tests/Feature/ExampleTest.php` o `tests/Unit/ExampleTest.php` fallan por algo preexistente, anótalo pero no lo arregles aquí.

- [ ] **Step 2: Probar el flujo real en el navegador**

```bash
php artisan serve
```

Abre `http://127.0.0.1:8000`, haz clic en "▶ Ver demo en vivo" y comprueba a ojo:

1. Aparece la pantalla "Preparando tu demo…" y en 1-3 s entras al panel.
2. Arriba se ve la barra morada de demo con el botón "Crear mi cuenta real".
3. El dashboard muestra números distintos de cero: rentas activas, rentas vencidas, ingresos.
4. `Rentabilidad por Lavadora` lista máquinas con ingresos.
5. La gráfica de ingresos mensuales tiene varias barras, no una.
6. El calendario de rentas muestra eventos.
7. Puedes crear un cliente nuevo sin que te bloquee un límite de plan.

- [ ] **Step 3: Confirmar el borrado**

```bash
php artisan tinker --execute="\App\Models\Company::demo()->latest('id')->first()->update(['demo_expires_at' => now()->subHour()]);"
php artisan demo:cleanup
```

Expected: imprime `Demos borradas: 1`. Vuelve a cargar el panel del demo: debe echarte fuera.

- [ ] **Step 4: Commit final si quedó algo suelto**

```bash
git status
```

Si no hay cambios pendientes, no hay nada que commitear.
