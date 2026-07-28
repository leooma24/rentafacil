# Demo en vivo — diseño

Fecha: 2026-07-27

## Problema

Renta Fácil se vende por suscripción, pero hoy la única forma de ver el producto es
registrarse. Eso frena a dos públicos: el prospecto que llega al landing y no quiere
dar su correo, y el dueño del negocio (Omar) que quiere enseñar el sistema funcionando
frente a un cliente en una llamada o visita.

## Solución

Un botón público **"Ver demo en vivo"** que, sin pedir ningún dato, crea al vuelo un
sandbox desechable: una empresa demo con datos de ejemplo realistas, dentro del panel
real de Filament. El visitante puede tocar y romper lo que quiera; el sandbox se borra
solo a las 24 horas.

Decisiones tomadas al diseñar:

- **Sandbox personal, no cuenta compartida.** Cada clic genera su propia empresa, así
  dos visitantes nunca se pisan ni ven basura del anterior.
- **Datos generados al vuelo, no una plantilla clonada.** Las fechas se calculan
  contra el día de hoy, así el calendario, los morosos y las gráficas se ven vivos
  cualquier día del año, sin re-sembrar nada a mano.
- **Solo el panel del propietario.** El panel del cliente final y un tour guiado con
  globitos quedan fuera de alcance.

## Experiencia

En el hero de la home, junto a "Probar 15 Días Gratis", un botón outline
**"▶ Ver demo en vivo"** con el apoyo *"Sin registro · Datos de ejemplo"*. El mismo
link va en el menú superior y en el CTA de cierre de la home. El registro sigue siendo
el CTA principal (botón sólido); el demo es el secundario.

Al hacer clic:

1. Pantalla breve *"Preparando tu demo…"* mientras se generan los datos (1-2 s).
2. El visitante cae en `/propietario/{company}` ya autenticado, sobre una empresa
   ficticia llamada **"Lavandería Demo"**.
3. Una barra fija arriba: *"Estás en un demo — los datos son de ejemplo y se borran
   solos en 24 horas"* con botón **"Crear mi cuenta real"** que apunta a
   `/propietario/registrar`.

La barra reutiliza el mismo `renderHook` que hoy usa el aviso de prueba gratuita en
`app/Providers/Filament/AdminPanelProvider.php`. Cuando la empresa es demo, se muestra
la barra de demo **en lugar de** los avisos de "tu prueba expira" / "tu plan expiró".

## Datos del sandbox

Todas las fechas son relativas a `now()` en el momento de la generación.

| Entidad | Cantidad | Detalle |
|---|---|---|
| `WashingMachine` | 14 | Códigos `LAV-001`…`LAV-014`. Marcas reales (Whirlpool, Mabe, LG, Easy, Samsung). Estados: 10 `rentada` (8 rentas activas + 2 vencidas), 2 `disponible`, 1 `mantenimiento`, 1 `fuera_de_servicio`. Con `purchase_date` y `purchase_price` para que la utilidad por máquina cuadre. |
| `Customer` | 20 | Nombre, teléfono, correo ficticio y `Address` relacionada. |
| `Rental` activas | 8 | Una vence en 2 días y otra en 5, para que el calendario y los recordatorios tengan qué mostrar. |
| `Rental` vencidas | 2 | Con 6 y 15 días de atraso, para poblar `OverdueRentalsWidget`. |
| `Rental` finalizadas | ~15 | Repartidas en los últimos 6 meses. |
| `Payment` | derivados | Cobros semanales hacia atrás por cada renta, estado `completado`, 6 meses de historial para `MonthlyRevenueChart` y `PaymentStats`. |
| `Maintenance` | 4 | Con `cost`, para que el cálculo de utilidad reste gastos. |
| `Incident` | 3 | Una abierta, una en proceso, una resuelta. |
| `Setting` | 1 | `price` 250, `days_per_payment` 7. |
| `CompanyPackage` | 1 | Paquete de mayor capacidad, `end_date` = expiración del demo, para que ningún límite de plan estorbe. |

Las direcciones dependen de `countries` / `states` / `townships` / `neighborhoods`. El
generador toma los primeros registros disponibles; si esas tablas están vacías, crea un
juego mínimo fijo para no fallar.

## Componentes

**Migración.** Añade `is_demo` (boolean, default false) y `demo_expires_at` (timestamp
nullable) a `companies`, e `is_demo` (boolean, default false) a `users`. Nada más.

**`App\Services\DemoCompanyBuilder`.** Un método público `build(): Company`. Contiene
todo el armado de datos y no sabe nada de HTTP, así se prueba en aislamiento. Crea el
`User` demo (`demo+<uuid>@rentafacil.local`, contraseña aleatoria, `is_demo = true`),
la `Company` demo con `demo_expires_at = now()->addHours(24)`, los asocia y siembra las
entidades de la tabla de arriba. La duración vive en una constante para poder cambiarla.

**`App\Http\Controllers\DemoController`.**

- `GET /demo` (`demo.start`) — devuelve la vista de espera con el spinner.
- `POST /demo/iniciar` (`demo.create`) — llama al builder, hace `Auth::login()` y
  responde con la URL del panel para que el front redirija.

Ambas rutas limitadas a **5 demos por IP cada hora** vía middleware `throttle`.

**`demo:cleanup`.** Comando Artisan que borra en duro (los modelos usan `SoftDeletes`,
así que `forceDelete`) las empresas con `is_demo = true` y `demo_expires_at < now()`,
junto con sus usuarios y todos sus registros dependientes. Se registra en
`app/Console/Kernel.php` con `->hourly()`.

**Landing.** Botón en `resources/views/livewire/banner.blade.php` (hero), link en el
menú superior y botón en el CTA de cierre de `resources/views/livewire/show-home.blade.php`.
Las landings de ciudad, `prices` y `other-banner` quedan sin cambios en esta iteración.

## Blindajes

Un sandbox suelto dentro de la app real rompe tres cosas si no se aísla:

1. **Correos.** Los comandos programados (`rentals:send-reminders`, `rentals:mark-overdue`,
   `users:check-inactive`, `users:lifecycle-emails`) filtran por `is_demo = false`. Sin
   esto el demo dispararía correos diarios a direcciones inventadas.
2. **Cobro.** `PlanCheckoutController` rechaza empresas demo y redirige al registro real
   en vez de abrir Stripe.
3. **Abuso y basura.** Límite de 5 demos por IP por hora, más la limpieza horaria.

## Pruebas

Tests de feature:

- `POST /demo/iniciar` deja al visitante autenticado en una empresa demo, y esa empresa
  trae lavadoras, clientes, rentas activas, al menos una renta vencida y pagos.
- El sexto intento desde la misma IP dentro de la hora recibe 429.
- `demo:cleanup` borra las empresas demo vencidas **y deja intactas** las empresas reales
  y las empresas demo que aún no expiran.
- Los comandos de correo no seleccionan registros de empresas demo.

Test unitario de `DemoCompanyBuilder`: las fechas generadas son relativas a hoy (hay
rentas que vencen dentro de los próximos 7 días y rentas con atraso).

## Fuera de alcance

- Panel del cliente final (arrendatario) dentro del demo.
- Tour guiado con globitos.
- Convertir un sandbox en cuenta real conservando sus datos.
- Botón de demo en landings de ciudad, precios y `other-banner`.
