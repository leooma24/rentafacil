# Estado de cuenta por cliente — diseño

Fecha: 2026-07-27

## Problema

RentaFácil registra pagos sueltos, pero en ningún lado dice **cuánto debe un cliente**.
El dueño tiene que abrir la renta, mirar la fecha de vencimiento, sacar la cuenta de
cabeza y confiar en su memoria. Es justo lo que la app promete quitarle.

No existe hoy: ni saldo, ni adeudo, ni estado de cuenta, ni un lugar donde ver quién
debe. Se verificó buscando `saldo`, `balance`, `adeudo` y `debe` en `app/`.

## Cómo funciona el negocio (base del cálculo)

El modelo es **prepago**. La acción "Extender Renta"
(`app/Filament/Resources/RentalResource/Actions/ExtendRentAction.php`) cobra un monto y
empuja el `end_date` de la renta N días. De ahí sale todo:

- `end_date` en el futuro → el cliente está pagado hasta esa fecha.
- `end_date` en el pasado → debe los periodos que van de esa fecha a hoy.

Precio y días por periodo viven en `Setting` (por empresa), pero **en "Extender Renta"
ambos se capturan a mano cada vez**, con el ajuste de la empresa solo como valor por
omisión. Por eso un cliente puede traer tarifa especial sin que exista campo para ella.

## La regla

Por cada renta del cliente con estado `activa` o `vencida`:

1. Si `end_date >= hoy` → al corriente, adeudo $0.
2. Si `end_date < hoy`:
   - `diasVencidos = días entre end_date y hoy`
   - `periodos = ceil(diasVencidos / diasPorPago)` — **periodo empezado se cobra**
   - `adeudo = periodos × precio`

Donde:

- **precio** = monto del último pago con estado `completado` de esa renta. Si la renta
  nunca tuvo un pago completado, el `price` de `Setting`. Esto respeta tarifas
  especiales sin capturarlas en otro lado.
- **diasPorPago** = `days_per_payment` de `Setting`.

El saldo del cliente es la suma de sus rentas. Las rentas `completada` y `cancelada`
no cuentan.

Ejemplo: Juan trae dos lavadoras. Una venció hace 10 días con último pago de $250 y
semana de 7 días → `ceil(10/7) = 2` periodos → $500. La otra está al corriente → $0.
**Juan debe $500.**

### Sin configuración

Si la empresa no tiene `Setting`, o su `price` o `days_per_payment` vienen vacíos o en
cero, el adeudo es **no calculable**, no cero. La interfaz muestra "Configura tu precio
para ver adeudos" con liga a Configuración. Un cero falso en cobranza hace que el dueño
deje de cobrarle a alguien que sí debe.

### Rentas muy vencidas

No hay tope. Una renta abandonada hace 8 meses reporta 8 meses de adeudo. Decisión
tomada a propósito: el número refleja la realidad y empuja a cerrar las rentas muertas.

## Dónde se ve

**Ficha del cliente.** Un botón "Estado de cuenta" en la lista de clientes y en el
encabezado de su edición abre una pantalla nueva en `/clientes/{id}/estado-de-cuenta`:

- Arriba: el saldo en grande y desde qué fecha debe.
- En medio: tabla de sus máquinas rentadas — código, desde cuándo la trae, pagado
  hasta, periodos vencidos y cuánto debe por esa renta.
- Abajo: su historial de pagos completo — fecha, monto, método, referencia y estado.

**Mis Clientes.** Una columna "Debe" con el monto en rojo cuando hay adeudo y en gris
cuando está en cero, más un filtro "Solo con adeudo" para barrer la cobranza del día.

**Escritorio.** Un recuadro "Total por cobrar" con la suma del negocio dentro del
widget de pagos ya existente, y un widget nuevo "Clientes con adeudo" que lista de
mayor a menor con clic directo al estado de cuenta.

## Componentes

**`App\Support\AccountStatement`.** Toda la regla vive aquí, sin saber nada de Filament
ni de HTTP, para poder probarla aislada.

- `forCustomer(Customer $customer): Statement`
- `forCompany(Company $company): Collection<Statement>` — solo los que deben

**`App\Support\Statement`.** Objeto de solo lectura con: el cliente, el saldo total, la
fecha desde la que debe, el detalle por renta y si el cálculo fue posible.

**`CustomerResource\Pages\AccountStatementPage`.** La pantalla. Solo arma y muestra lo
que le da `AccountStatement`.

**`CustomersWithDebtWidget`.** La tabla del escritorio.

Cambios menores: `CustomerResource` gana la columna, el filtro y el botón;
`PaymentStats` gana el recuadro del total por cobrar.

### Rendimiento

`forCompany` no recorre a todos los clientes. Parte de las rentas con estado `activa` o
`vencida` y `end_date < hoy` — un conjunto chico y filtrable en SQL — y de ahí sube al
cliente. La columna de la lista calcula solo sobre los clientes de la página visible.

Por eso la columna "Debe" **no es ordenable** (el saldo no vive en la base de datos);
para ordenar por quién debe más está el widget del escritorio, que sí ordena sobre el
conjunto chico de rentas vencidas.

## Pruebas

Sobre `AccountStatement`, que es donde vive el riesgo:

- Cliente con renta al corriente → $0.
- Renta vencida 10 días, semana de $250 → $500 (periodo empezado se cobra).
- Renta vencida 7 días exactos → $250 (no cobra un periodo de más).
- Renta vencida 1 día → $250.
- Dos rentas del mismo cliente suman.
- Rentas `completada` y `cancelada` no cuentan.
- Con último pago de $200 y `Setting` en $250, usa $200.
- Sin pagos previos, usa el precio de `Setting`.
- Sin `Setting` o con precio en cero → no calculable, no $0.
- `forCompany` devuelve solo a quienes deben, de mayor a menor.

De interfaz: la pantalla de estado de cuenta responde y muestra el saldo; el filtro
"Solo con adeudo" deja fuera a los que están al corriente.

## Fuera de alcance

- PDF del estado de cuenta y envío por WhatsApp.
- Abonos y pagos parciales.
- Recargos por atraso.
- Precio fijo por renta como campo propio.
- Ordenar la lista de clientes por saldo.
