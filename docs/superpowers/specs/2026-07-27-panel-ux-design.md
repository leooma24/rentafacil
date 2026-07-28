# Panel del dueño: arreglos y rediseño del escritorio — diseño

Fecha: 2026-07-27

## Problema

El panel funciona, pero se siente amateur y lento de usar. Dos cosas distintas:

1. **Datos mal mostrados** que hacen dudar de todo lo demás: fechas en formato
   anglosajón, un promedio de atraso en negativo, una columna de cliente vacía.
2. **Un escritorio de 13 bloques sin jerarquía** que informa pero no deja actuar: ves
   quién te debe, pero para cobrarle tienes que salir, ir a Rentas, buscar al cliente y
   hacer scroll horizontal hasta encontrar el botón.

## Parte A — Los arreglos

### Fechas

Las listas usan `->date()`, que con el locale actual imprime `jul. 7, 2026`. En México
eso se lee 7 de julio, pero invita a confundirlo con el 7 de julio vs julio 7. Todas las
columnas y campos de fecha pasan a `d/m/Y` (y `d/m/Y H:i` donde hoy hay `dateTime()`).

Afecta a `RentalResource`, `PaymentResource`, `MaintenanceResource`, `IncidentResource` y
`CustomerResource`.

### Promedio de días de atraso en negativo

`app/Filament/Widgets/BusinessAnalyticsWidget.php:32` calcula:

```php
now()->diffInDays($r->end_date)
```

En Laravel 11 esa diferencia viene **con signo**, y como `end_date` ya pasó, el resultado
es negativo: el escritorio muestra "−10.7 días de atraso". Se invierte el sentido de la
resta para medir de la fecha vencida hacia hoy.

### Columna de cliente vacía en rentas vencidas

`WashingMachine::activeRental()` es:

```php
$this->hasOne(Rental::class)->whereIn('status', ['activa', 'vencida'])->latestOfMany();
```

`latestOfMany()` arma una subconsulta `MAX(id)` **sin aplicar el `whereIn`**. Si la
lavadora tuvo antes una renta finalizada con id más alto, la subconsulta elige esa, el
filtro externo la descarta y la relación queda en nulo. Por eso el widget de rentas
vencidas muestra el código de la lavadora pero ni cliente ni fecha.

Se corrige aplicando el filtro dentro de la subconsulta con `ofMany`.

### Estatus en minúsculas

Los badges muestran `activa`, `vencida`, `completado`. Se capitalizan en todas las
listas, sin tocar los valores guardados en la base de datos.

## Parte B — El escritorio

De 13 bloques a 5. El criterio: **la primera pantalla contesta "¿qué tengo que hacer
hoy?" y deja hacerlo ahí mismo.**

### 1. Hoy

Tres números grandes, cada uno clicable hacia su lista ya filtrada:

- **Por cobrar** — el total que deben los clientes (de `AccountStatement`).
- **Vencidas** — cuántas rentas están vencidas.
- **Vencen esta semana** — cuántas vencen en los próximos 7 días.

### 2. A quién cobrar

Una sola tabla con lo vencido y lo que vence pronto, ordenada por urgencia (la más
atrasada primero). Columnas: cliente, lavadora, días de atraso o que faltan, cuánto debe
esa renta. Y **las acciones de WhatsApp y Extender Renta en la misma fila**.

Esta tabla reemplaza a tres bloques que hoy dicen lo mismo en pedazos: *Rentas Vencidas*,
*Rentas por Vencer* y *Clientes con adeudo*.

Para el monto por renta, `AccountStatement` expone un método nuevo `forRental()`. La
regla de cálculo no cambia: es la misma que ya usa el estado de cuenta.

### 3. El dinero

Ingresos del mes con su comparativo contra el mes anterior, y la gráfica de ingresos
mensuales.

### 4. Estado del negocio (plegado)

Ocupación, lavadoras por estado, estado de rentas y rentabilidad por lavadora. Presente,
pero sin competirle a lo accionable.

### Lo que sale del escritorio

- **Últimos Clientes** y **Últimas Lavadoras** se eliminan: son las mismas listas del
  menú, cortadas a cinco.
- **Calendario de rentas** se muda a su propia página en el menú.
- **Actividad reciente** se muda a su propia página.

Ambos son para consultar, no para arrancar el día.

### Cómo se controla qué aparece

El panel hoy descubre widgets automáticamente y Filament los pone todos en el escritorio.
Se crea `App\Filament\Pages\Dashboard` (extendiendo el de Filament) con un `getWidgets()`
explícito, para que el orden y el contenido del escritorio sean una decisión visible en
el código y no un efecto secundario del descubrimiento automático.

## Componentes

**Se crean:**

| Archivo | Responsabilidad |
|---|---|
| `app/Filament/Widgets/TodayStats.php` | Los tres números de "Hoy", cada uno con su liga. |
| `app/Filament/Widgets/CollectionsWidget.php` | La tabla "A quién cobrar" con sus acciones. |
| `app/Filament/Pages/Dashboard.php` | Define qué widgets van en el escritorio y en qué orden. |
| `app/Filament/Pages/Calendario.php` | Aloja el calendario de rentas. |
| `app/Filament/Pages/Bitacora.php` | Aloja la actividad reciente. |
| `tests/Unit/PanelDataFixesTest.php` | Los tres arreglos de datos. |
| `tests/Feature/DashboardTest.php` | Qué widgets tiene el escritorio y qué trae la tabla de cobranza. |

**Se modifican:**

| Archivo | Cambio |
|---|---|
| `app/Models/WashingMachine.php` | `activeRental` con el filtro dentro de la subconsulta. |
| `app/Filament/Widgets/BusinessAnalyticsWidget.php` | Sentido de la resta de días. |
| `app/Support/AccountStatement.php` | Método público `forRental()`. |
| `app/Filament/Resources/RentalResource.php` | Formato de fecha y estatus capitalizado. |
| `app/Filament/Resources/PaymentResource.php` | Formato de fecha y estatus capitalizado. |
| `app/Filament/Resources/MaintenanceResource.php` | Formato de fecha y estatus capitalizado. |
| `app/Filament/Resources/IncidentResource.php` | Formato de fecha y estatus capitalizado. |
| `app/Filament/Resources/CustomerResource.php` | Formato de fecha. |
| `app/Providers/Filament/AdminPanelProvider.php` | Registrar el Dashboard propio y las dos páginas. |

**Se eliminan:** `app/Filament/Widgets/LatestCustomers.php`,
`app/Filament/Widgets/LatestWashingMachines.php`.

**Se conservan sin cambio de lógica:** `OverdueRentalsWidget`, `RentDueWashingMachines` y
`CustomersWithDebtWidget` quedan fuera del escritorio al fundirse en `CollectionsWidget`;
sus archivos se eliminan junto con los dos anteriores para no dejar código muerto.

## Pruebas

**De los arreglos**, que es donde hay lógica real:

- El promedio de días de atraso de una renta vencida hace 10 días da 10, no −10.
- `activeRental` devuelve la renta activa aunque la lavadora tenga una renta
  `completada` con id más alto — el caso que hoy la deja en nulo.
- `AccountStatement::forRental()` da el mismo monto que la línea correspondiente de
  `forCustomer()`.

**Del escritorio:**

- El escritorio declara exactamente los widgets previstos, y no los eliminados.
- La tabla de cobranza trae las rentas vencidas y las que vencen dentro de 7 días, la
  más atrasada primero, y deja fuera las que vencen después y las completadas.

## Fuera de alcance

- La capa de identidad visual (tipografía, paleta, densidad) y los nombres de menú:
  eso es la parte C, que va aparte.
- Reordenar las acciones por fila en la lista de Rentas: también parte C.
- Cualquier cambio al sitio público.
