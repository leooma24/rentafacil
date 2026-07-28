# Móvil, fase 2: cobrar, entregar y recoger — diseño

Fecha: 2026-07-28

## Problema

Los tres movimientos del día se hacen desde el celular, muchas veces parado en la puerta
del cliente, y los tres piden más de lo necesario.

**Cobrar** (`ExtendRentAction`) muestra seis campos aunque cinco ya vienen con valor: la
fecha es hoy, el precio y los días salen de Configuración, el método es Efectivo y el
estado es Completado. El caso normal es confirmar, no capturar.

**Entregar** (`RentAction`) pide cinco campos:

- El cliente es un desplegable sin buscador y **sin `required()`**. Como
  `rentals.customer_id` no admite nulos, enviar el formulario sin cliente truena con un
  error de base de datos en la cara del usuario.
- Las dos fechas se escogen a mano, sin valor por omisión.
- Pide elegir estatus entre activa, completada y cancelada. Nadie entrega una lavadora
  marcándola como cancelada.

**Recoger** tiene un problema de negocio, no de interfaz.
`WashingMachineResource.php:318` solo muestra "Recoger Lavadora" cuando la renta está
**vencida**. Si el cliente está al corriente y devuelve la máquina —el caso del buen
cliente— la única acción disponible es "Cancelar Renta", que la marca como `cancelada`.

Esa renta se cumplió. Marcarla cancelada ensucia la gráfica de Estado de Rentas y deja un
historial que dice que el trato se rompió. En producción hoy hay 15 completadas y 1
cancelada, así que el daño es chico, pero crece con cada cliente que devuelve a tiempo.

Además, la condición de ambas acciones pregunta por el estatus `en_mantenimiento`, que no
existe: el valor del enum es `mantenimiento`. Esa mitad de la condición nunca se cumple.

## Cobrar de un toque

La acción deja de abrir un formulario y pasa a abrir una confirmación que resume lo que
va a pasar:

> **Cobrar $250 · 7 días · Efectivo**
> Se registra hoy y la renta se extiende al 06/08/2026.

Debajo, una sección plegada **"Cambiar monto o método"** con los mismos campos de hoy,
para la excepción. El camino normal es confirmar.

Si no hay precio o periodo configurado, la acción sigue avisando y ligando a Preferencias
como hasta ahora.

## Entregar sin fricción

- **Cliente**: buscador (`searchable`) y **obligatorio**.
- **Fecha de inicio**: hoy por omisión.
- **Fecha de fin**: calculada sumando el periodo de `Setting::days_per_payment` a la
  fecha de inicio. Si no hay configuración, quince días.
- **Estatus**: se elimina del formulario. Toda entrega nace `activa`.
- **Notas**: se queda, opcional.

De cinco campos a uno obligatorio.

## Recoger de verdad

**Recoger Lavadora** pasa a estar visible cuando la renta está `activa` **o** `vencida`.
Marca la renta como **completada**, le pone la fecha de fin de hoy y deja la lavadora
`disponible`.

**Cancelar Renta** se queda como acción aparte, para el caso real de cancelación, y marca
`cancelada`.

Ambas condiciones dejan de mencionar `en_mantenimiento` y usan `mantenimiento`.

## Componentes

**Se modifican:**

| Archivo | Cambio |
|---|---|
| `app/Filament/Resources/RentalResource/Actions/ExtendRentAction.php` | Confirmación con resumen y campos plegados. |
| `app/Filament/Resources/WashingMachineResource/Actions/ExtendRentAction.php` | Lo mismo, es la gemela usada desde Lavadoras. |
| `app/Filament/Resources/WashingMachineResource/Actions/RentAction.php` | Cliente buscable y obligatorio, fechas por omisión, fuera el estatus. |
| `app/Filament/Resources/WashingMachineResource.php` | Recoger visible en activa y vencida, marca completada; se corrige `en_mantenimiento`. |

**Se crea:** `app/Support/RentalTerms.php`, que responde el precio y el periodo vigentes
de una empresa y la fecha de fin calculada. Lo usan las tres acciones en vez de repetir la
lectura de `Setting` y el `addDays` en cada una.

## Pruebas

Sobre la lógica, que es donde está el riesgo:

- `RentalTerms` devuelve el precio y el periodo de la empresa, y quince días cuando no
  hay configuración.
- Cobrar extiende la fecha de fin por el periodo y registra un pago por el monto.
- Cobrar una renta vencida la regresa a activa.
- Entregar crea la renta como `activa`, con fin calculado, y deja la lavadora `rentada`.
- Entregar sin cliente no pasa: la validación lo detiene antes de tocar la base.
- Recoger una renta **activa** la deja `completada` y la lavadora `disponible`.
- Recoger una renta **vencida** hace lo mismo.
- Cancelar deja la renta `cancelada`.

## Fuera de alcance

- La barra lateral que se abre sola en celular.
- Recibo por WhatsApp al cobrar y estado de cuenta compartible (fase 3).
- Cobros parciales y recargos.
