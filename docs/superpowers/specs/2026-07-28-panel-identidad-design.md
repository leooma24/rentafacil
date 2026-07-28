# Panel: acciones, nombres e identidad — diseño

Fecha: 2026-07-28

## Problema

El panel funciona y ya dice qué hacer, pero se sigue viendo como una plantilla:

- **La marca está mal escrita.** `AdminPanelProvider:42` pone `brandName('Renta Facíl')`,
  con el acento en la "i". Sale en todas las pantallas del dueño y en la de acceso. El
  panel del cliente sí dice "Renta Fácil". En el menú de administrador hay otro:
  "Compañias" sin acento.
- **Las acciones por fila se salen de la pantalla.** `RentalResource` tiene cinco
  acciones planas (Editar, Contrato, WhatsApp, Link de Pago, Pago Recurrente) y hay que
  hacer scroll horizontal para llegar a las últimas.
- **Los nombres del menú están mezclados**: "Mis Clientes / Mis Lavadoras / Mis Rentas"
  conviven con "Incidentes / Pagos / Mantenimientos", y el grupo "Configuración" contiene
  un ítem llamado "Configuración".
- **No se puede instalar en el celular.** `public/manifest.json` pide
  `/img/icon-192.png` y `/img/icon-512.png`, y **ninguno de los dos existe**. El landing
  promete "Desde tu celular, siempre".

## C1 — Acciones por fila

`WashingMachineResource:285` ya usa `ActionGroup` para colapsar sus acciones en un menú
de tres puntos. Se aplica el mismo patrón donde falta.

**Rentas**: la acción principal visible pasa a ser **Cobrar** (`ExtendRentAction`, que hoy
vive solo en Lavadoras aunque la lista de Rentas es donde uno va a cobrar). Al menú:
Editar, Contrato, WhatsApp, Link de Pago y Pago Recurrente.

**Clientes**: **Estado de cuenta** queda visible; Editar, Eliminar y Restaurar al menú.

## C2 — Nombres del menú

Se quitan los "Mis": **Clientes, Lavadoras, Rentas, Pagos, Mantenimientos, Incidencias**.
Además de emparejarlos, en un panel donde puede haber varios usuarios "Mis Rentas" es
engañoso: las rentas son de la empresa, no de quien mira.

El grupo "Configuración" pasa a llamarse **"Mi cuenta"** para que no contenga un ítem del
mismo nombre, y ese ítem pasa a llamarse **"Preferencias"**. El grupo queda con
Preferencias, Mi Plan, Invitar Amigos y Actividad.

"Compañias" pasa a "Compañías".

Los grupos "Gestión Principal", "Finanzas", "Servicios" y "Administrador" se quedan.

## C3 — Identidad

- **`brandName` a "Renta Fácil"**, bien escrito.
- **Tipografía Inter** en lugar de Roboto. Roboto es la fuente por omisión de Android y
  se lee genérica; Inter es la de las herramientas SaaS actuales.
- **Color primario al cyan exacto del landing** (`#06b6d4`), en vez del cyan genérico de
  Filament, para que sitio y panel se sientan la misma marca.
- **Íconos del PWA** de 192 y 512 px generados desde `public/img/logo.png`, para que la
  app se pueda instalar en el celular con su ícono.
- **Barra lateral plegable en escritorio**, para dejarle más ancho a las tablas.

## Pruebas

Lo que tiene lógica se prueba; lo que es puro ajuste visual se revisa a ojo.

- El panel se declara con el nombre "Renta Fácil" y el color `#06b6d4`.
- Existen los dos archivos de ícono que pide el manifest, y cada uno mide lo que dice.
- Ninguna etiqueta de navegación empieza con "Mis".
- La lista de Rentas ofrece la acción de cobrar.
- Las pantallas de Rentas y Clientes siguen abriendo sin error después de reagrupar sus
  acciones.

## Fuera de alcance

- Rediseñar formularios o su distribución en columnas.
- Cambiar el logo.
- Tocar el sitio público o el panel del cliente.
