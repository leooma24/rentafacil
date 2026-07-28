# Móvil, fase 1: listas usables con el pulgar — diseño

Fecha: 2026-07-28

## Problema

Medido en producción con un viewport de 390 px de ancho:

- La tabla de Rentas mide **802 px**. Quedan **459 px fuera de pantalla**, y ahí está la
  columna de acciones: **el botón de Cobrar es inalcanzable sin arrastrar la tabla de
  lado**. Es la acción más frecuente del negocio y la que se usa en la calle.
- El escritorio mide **4,599 px de alto**: cinco pantallas y media de scroll, porque las
  nueve tarjetas de un solo número se apilan de una en una.
- La barra lateral se abre sola y cubre la pantalla al entrar. Se comprobó con el
  almacenamiento del navegador limpio y **quitando** `sidebarCollapsibleOnDesktop()`:
  ocurre igual, así que es el comportamiento de fábrica de Filament y no algo que se
  introdujo en los cambios recientes. Queda fuera de esta fase.

El panel está hecho para escritorio, y el celular es donde más se va a usar.

## Enfoque

Se esconden en celular las columnas que no son urgentes, con `visibleFrom('md')`. La
tabla sigue siendo tabla, el escritorio no cambia, y el cambio es reversible columna por
columna.

Se descartó convertir los renglones en tarjetas con `Split`/`Stack`: se ve mejor en
celular, pero elimina la fila de encabezados y cambia el ordenamiento **también en
escritorio**, que se acaba de aprobar. Si al usarlo hace falta, es el siguiente paso
sobre este mismo trabajo.

Un motivo extra para este camino: las clases de Tailwind que Filament no usa no entran en
su CSS compilado — ya pasó con un botón que salía blanco sobre blanco. `visibleFrom` es
API de Filament y no necesita ninguna clase nueva.

## Qué se ve en celular

| Pantalla | Se queda | Se esconde hasta tableta |
|---|---|---|
| **Rentas** | Cliente, Lavadora, Estatus y las acciones | Fecha de inicio y fecha de fin |
| **Clientes** | Nombre, Debe y las acciones | Correo y teléfono |
| **Lavadoras** | Código, Estatus, Cliente y las acciones | Marca, modelo, estatus de renta, fecha de inicio y de fin |

En Rentas el cálculo queda en unos 365 px de los 390 disponibles, con el botón dentro.

Las columnas ya ocultas por omisión (`toggleable(isToggledHiddenByDefault: true)`) no se
tocan.

## Las tarjetas de números

Hoy el escritorio tiene nueve tarjetas de un solo dato. Se consolidan en cuatro, pasando
el detalle a la descripción de cada una:

- **Lavadoras** — el total, y debajo "10 rentadas · 2 libres · 1 en mantenimiento".
  Reemplaza a las cuatro tarjetas de `StatsOverview`.
- **Ingresos del Mes** — sin cambio, con su comparativo.
- **Total por Cobrar** — sin cambio.
- **Rentas** — las activas, y debajo "2 vencidas · 0 pagos pendientes". Reemplaza a las
  tres tarjetas sueltas de rentas y pagos pendientes.

Menos scroll en el teléfono y menos ruido en la computadora.

**Lo que esto no hace:** en celular las tarjetas se seguirán apilando de una en una.
Ponerlas de dos en dos requiere clases de rejilla que no están en el CSS compilado. El
camino es tener menos tarjetas, no forzarlas a dos columnas.

## Componentes

**Se modifican:**

| Archivo | Cambio |
|---|---|
| `app/Filament/Resources/RentalResource.php` | `visibleFrom('md')` en las dos fechas. |
| `app/Filament/Resources/CustomerResource.php` | `visibleFrom('md')` en correo y teléfono. |
| `app/Filament/Resources/WashingMachineResource.php` | `visibleFrom('md')` en marca, modelo, estatus de renta y las dos fechas. |
| `app/Filament/Widgets/StatsOverview.php` | Las cuatro tarjetas de lavadoras se vuelven una. |
| `app/Filament/Widgets/PaymentStats.php` | Rentas activas, vencidas y pagos pendientes se vuelven una. |

No se crean archivos nuevos.

## Pruebas

Automáticas:

- Cada columna que debe esconderse en celular declara `visibleFrom('md')`, y las que se
  quedan no lo declaran.
- `StatsOverview` devuelve una sola tarjeta y su descripción menciona las rentadas, las
  disponibles y las que están en mantenimiento.
- `PaymentStats` devuelve tres tarjetas y la de rentas menciona las vencidas.
- Las tres listas siguen abriendo sin error.

A ojo, en el navegador a 390 px de ancho, y este es el criterio de aceptación:

- La tabla **no se desborda**: su ancho de contenido cabe en la pantalla.
- El botón de la acción principal queda **dentro** de la pantalla.
- El alto del escritorio baja de forma apreciable respecto a los 4,599 px medidos.

## Fuera de alcance

- Convertir los renglones en tarjetas.
- La barra lateral que se abre sola en celular.
- Los flujos de cobrar, entregar y recoger (fase 2).
- El portal del cliente (fase 3).
