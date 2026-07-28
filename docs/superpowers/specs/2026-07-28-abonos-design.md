# Abonos — diseño

Fecha: 2026-07-28

## Problema

El negocio cobra en efectivo, en la puerta, y ahí la gente paga lo que trae. Si el
cliente llega con $150 de los $250, hoy no hay dónde registrarlo: o se cobra completo
—lo que no pasó— o no se registra nada.

Y aunque se registrara, no serviría: `AccountStatement` calcula el adeudo **solo** a
partir de la fecha de vencimiento (periodos vencidos × precio). Un pago parcial no movería
el saldo ni un peso.

## El modelo

**Un abono es un pago que todavía no compra tiempo.** Se registra contra la renta pero no
mueve su fecha de vencimiento.

Para distinguirlo se agrega `payments.applied`, un booleano con valor por omisión
verdadero: todos los pagos que existen hoy extendieron una renta, así que nacen aplicados
y nada cambia para ellos. Los abonos se guardan con `applied = false`.

**El adeudo pasa a ser:**

```
adeudo = max(0, periodos_vencidos × precio − abonos_sin_aplicar)
```

Ejemplo: renta vencida hace 5 días, semana de $250. Debe $250. Llega con $150 → el estado
de cuenta dice **$100**.

## Al completar el periodo

Cuando los abonos sin aplicar de una renta alcanzan el precio del periodo, la renta se
extiende sola y esos abonos quedan consumidos:

1. Se calcula cuántos periodos completos cubre el acumulado.
2. La fecha de fin avanza esos periodos.
3. Los abonos usados se marcan `applied = true`.
4. Si sobra dinero, el sobrante queda como abono para el siguiente periodo.

Siguiendo el ejemplo: si después trae los $100 que faltaban, el acumulado llega a $250, la
renta se extiende una semana y los dos abonos quedan aplicados. El saldo vuelve a cero sin
que nadie tenga que acordarse.

Esto vive en `App\Support\Abonos`, no dentro de la acción de Filament, para poder probarlo
solo.

## En la pantalla

**Botón "Abonar"** junto a Cobrar, en Rentas y en Lavadoras. Pide monto y método, nada
más. Al guardar avisa cuánto le falta al cliente, o que ya completó y la renta se extendió.

**Estado de cuenta**: cada renta muestra lo abonado y lo que falta. La página pública del
cliente también, para que él vea que su dinero está reconocido.

**La lista de Pagos** gana una columna que distingue el abono del cobro completo.

## Componentes

**Se crea:**

| Archivo | Responsabilidad |
|---|---|
| `database/migrations/..._add_applied_to_payments.php` | La marca `applied`, por omisión verdadera. |
| `app/Support/Abonos.php` | Registrar un abono, calcular el acumulado sin aplicar y extender la renta cuando se completa un periodo. |
| `app/Filament/Resources/RentalResource/Actions/AbonarAction.php` | El botón, en las dos listas. |
| `tests/Feature/AbonosTest.php` | El cálculo, el consumo y los límites. |

**Se modifican:**

| Archivo | Cambio |
|---|---|
| `app/Models/Payment.php` | `applied` en `$fillable` y en `$casts`. |
| `app/Support/AccountStatement.php` | Restar los abonos sin aplicar al adeudo de cada renta. |
| `app/Support/RentalDebt.php` | Guardar lo abonado, para poder mostrarlo. |
| `app/Filament/Resources/RentalResource.php` y `WashingMachineResource.php` | El botón de Abonar. |
| `app/Filament/Resources/PaymentResource.php` | Columna que distingue abono de cobro. |
| `resources/views/filament/resources/customer-resource/pages/account-statement.blade.php` | Mostrar lo abonado. |
| `resources/views/publico/estado-de-cuenta.blade.php` | Lo mismo, del lado del cliente. |

## Pruebas

- Un abono de $150 sobre una deuda de $250 deja el saldo en $100.
- El abono **no** mueve la fecha de vencimiento.
- Dos abonos que suman el periodo lo extienden una vez y quedan aplicados.
- Un abono que cubre dos periodos de golpe extiende dos y deja el sobrante como abono.
- Un abono mayor a la deuda no deja el saldo en negativo: se queda en cero.
- Los pagos que ya existían siguen contando como aplicados y ningún saldo cambia.
- Cobrar completo sigue funcionando igual: extiende y registra un pago aplicado.
- El estado de cuenta del cliente muestra lo abonado.

## Fuera de alcance

- Recargos por atraso.
- Devolver un abono o cancelarlo.
- Aplicar un abono a una renta distinta de la que se registró.
