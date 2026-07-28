# Primeros pasos y aviso de límite — diseño

Fecha: 2026-07-28

## Problema

De las 17 empresas reales en producción:

| | |
|---|---|
| Nunca cargaron una lavadora | 11 |
| Nunca cargaron un cliente | 12 |
| Nunca crearon una renta | 12 |
| Nunca registraron un pago | 16 |
| Sin precio configurado | 13 |

Una sola cuenta usa el producto. Las demás entraron, vieron tablas vacías y se fueron.
Dos cosas concretas lo explican:

**Nada dice por dónde empezar.** Una cuenta nueva llega a un escritorio en ceros sin
ninguna indicación del orden: precio → lavadoras → clientes → renta.

**Falta el precio y nadie avisa.** Sin `Setting`, "Cobrar" truena, el estado de cuenta no
puede calcular y el escritorio reporta $0 por cobrar aunque haya adeudos. Nada crea esa
configuración al registrarse ni la pide.

**Y al topar el límite, el botón desaparece.** `ListCustomers::getHeaderActions()` y su
gemelo de lavadoras devuelven un arreglo vacío cuando ya no hay cupo. El botón de crear
simplemente deja de existir, sin aviso ni razón. El dueño concluye que la app se
descompuso y llama a soporte — justo en el momento en que estaba dispuesto a pagar más.

## Parte 1 — Primeros pasos

Un widget **"Primeros pasos"** hasta arriba del escritorio, con cuatro renglones. Cada
uno muestra si está hecho y liga a donde se hace:

1. **Configura tu precio de renta** → Preferencias
2. **Carga tus lavadoras** → Lavadoras
3. **Carga tus clientes** → Clientes
4. **Registra tu primera renta** → Rentas

Los pasos 2 y 3 mencionan que se puede importar desde Excel, que ya existe en esas
pantallas y casi nadie sabe.

El widget **se oculta cuando los cuatro pasos están hechos**, así que la cuenta que ya
opera no lo ve nunca.

No bloquea. Quien quiera mirar antes de capturar, que mire; lo que se elimina es la
pantalla en blanco sin instrucciones.

**Además**, cuando falte el precio se enciende la barra superior del panel, la misma
pieza (`App\Support\PanelBanner`) que ya avisa del demo, de la prueba por vencer y del
plan expirado. Ese caso rompe cosas en silencio, así que merece aviso propio.

Orden de la barra: el aviso de demo manda sobre todo; después el de precio faltante;
después los de prueba y plan vencido. Un demo nunca ve el aviso de precio porque el
generador ya le pone configuración.

## Parte 2 — Aviso al topar el límite

El botón de crear **siempre se dibuja**. Cuando ya no hay cupo, en vez de crear abre un
aviso:

> **Llegaste al límite de tu plan**
> Tu plan Gratuito incluye 3 lavadoras y ya tienes 3.
> Para agregar más, sube de plan.
> [Ver planes]

"Ver planes" lleva a Mi Plan.

La lógica de cupo ya vive en `App\Support\PlanUsage`. Esta parte solo la consulta para
decidir cuál de los dos botones dibujar.

## Componentes

**Se crean:**

| Archivo | Responsabilidad |
|---|---|
| `app/Support/Onboarding.php` | Los cuatro pasos de una empresa: cuáles están hechos y a dónde lleva cada uno. Sin dependencias de Filament. |
| `app/Filament/Widgets/OnboardingWidget.php` | Dibuja el checklist; se oculta si ya no hay pendientes. |
| `resources/views/filament/widgets/onboarding.blade.php` | Su vista. |
| `app/Filament/Actions/CreateWithinPlanAction.php` | Devuelve el botón de crear normal si hay cupo, o el aviso de límite si no. Lo usan Clientes y Lavadoras para no repetir código. |
| `tests/Unit/OnboardingTest.php` | Los pasos y cuándo se considera terminado. |
| `tests/Feature/PlanLimitTest.php` | Que el botón siga estando al topar el límite y explique. |

**Se modifican:**

| Archivo | Cambio |
|---|---|
| `app/Support/PanelBanner.php` | Aviso cuando falta el precio. |
| `app/Filament/Pages/Dashboard.php` | Registrar el widget al principio. |
| `app/Filament/Resources/CustomerResource/Pages/ListCustomers.php` | Usar la acción nueva en vez de devolver un arreglo vacío. |
| `app/Filament/Resources/WashingMachineResource/Pages/ListWashingMachines.php` | Lo mismo. |

## Pruebas

- Una empresa recién creada reporta los cuatro pasos pendientes.
- Con precio configurado, el primer paso queda hecho y los otros tres no.
- Con lavadoras, clientes y una renta, los cuatro quedan hechos y el widget se oculta.
- Con cupo, la lista de lavadoras ofrece crear.
- Sin cupo, **el botón sigue estando** y su aviso menciona el límite y liga a Mi Plan.
- La barra del panel avisa cuando falta el precio, y no lo hace cuando ya está puesto.
- Una empresa demo no ve el aviso de precio.

## Fuera de alcance

- Bloquear el panel hasta configurar el precio.
- Pantallas vacías con instrucciones dentro de cada listado.
- Cualquier cosa relacionada con cobrar en línea.
