# Plan efectivo y visibilidad de límites — diseño

Fecha: 2026-07-27

## Problema

Al registrarse, una empresa recibe **dos** planes:

- `CompanyObserver::created` (`app/Observers/CompanyObserver.php:12`) le asigna
  `package_id => 1` (**Gratuito**: 3 lavadoras, 3 clientes) por un mes.
- `RegisterCompany::handleRegistration` le asigna el paquete más caro (**Ilimitado**)
  por 15 días, que es la prueba que anuncia el sitio.

`Company::companyPackage()` es un `hasOne` sin orden, así que devuelve **el de id más
bajo**: siempre el Gratuito. El plan Ilimitado existe en la base pero no lo usa nadie.

**Consecuencia en producción:** durante sus "15 días gratis con todas las funciones", el
cliente está topado en 3 lavadoras y 3 clientes. Verificado: 4 empresas con dos filas en
`company_package`, todas con Gratuito ganándole a Ilimitado.

Además, hoy no hay forma de ver desde el panel qué plan tiene cada cliente ni si ya llegó
a su límite, que es justo el dato para saber a quién ofrecerle subir de plan.

## Parte 1 — El plan efectivo

### Quitar la asignación del observer

`CompanyObserver::created` deja de crear un `CompanyPackage`. `RegisterCompany` ya asigna
la prueba, y es el único lugar que debe decidir con qué plan arranca una empresa.

### El último asignado gana

`Company::companyPackage()` pasa de `hasOne(CompanyPackage::class)` a la misma relación
con `latestOfMany()`, es decir el de id más alto. Si alguna vez vuelven a existir dos
filas, gana la más reciente, que siempre es la intención de quien la creó.

Con ese solo cambio las 4 empresas afectadas quedan correctas:

| Empresa | Antes (Gratuito) | Después (Ilimitado) | Resultado |
|---|---|---|---|
| 14, 15, 16 | vigente al 07-08 ago | vencido el 22-24 jul | Muestran "plan expirado" y el botón de contratar. Correcto: su prueba terminó. |
| 21 | vigente al 28 ago | vigente al 12 ago | Recupera acceso completo durante su prueba. Correcto. |

### Limpiar las filas de más

Una migración borra, por cada empresa con más de una fila en `company_package`, todas
menos la de id más alto. Deja un solo plan por empresa y evita que cualquier reporte
futuro cuente doble.

La migración es de datos y no tiene `down()` que restaure: las filas borradas son las que
el observer creó de más y no representan nada que el negocio quiera conservar.

## Parte 2 — Plan y límites en Usuarios

En `UserResource` (grupo Administrador, no acotado por tenant), tres columnas nuevas:

- **Plan** — badge con el nombre del paquete y su estado: verde si está vigente, ámbar si
  es prueba con los días restantes, rojo si venció, gris si no tiene plan.
- **Lavadoras** — `14 / 20`, en rojo cuando alcanzó o superó el límite.
- **Clientes** — `18 / 20`, igual.

Más un filtro **"Ya topó su límite"** que deja solo a quienes alcanzaron el tope de
lavadoras o de clientes: la lista de a quién llamarle para subirlo de plan.

Un `User` pertenece a varias `Company` por tabla pivote. En la práctica todos tienen una;
si alguno tuviera más, las columnas muestran "varias empresas" en vez de escoger una y
mentir.

### Dónde vive el cálculo

Una clase `App\Support\PlanUsage` responde, para una empresa: qué paquete tiene, si está
vigente, cuántas lavadoras y clientes usa contra su tope, y si ya topó. Sin dependencias
de Filament, para poder probarla aislada. `UserResource` solo la consume.

`Company` ya tiene `canAddMoreClients()` y `canAddMoreWashingMachines()`, que siguen
siendo la fuente de verdad para bloquear altas; `PlanUsage` es para **mostrar**, y se
apoya en el mismo `companyPackage`.

## Pruebas

**Del plan efectivo:**

- Registrar una empresa deja **una sola** fila en `company_package`.
- Con dos filas, `companyPackage` devuelve la de id más alto.
- La migración deja una sola fila por empresa y conserva la de id más alto.
- Una empresa con la prueba vigente reporta `hasActivePackage()` verdadero con el paquete
  de la prueba, no con Gratuito.

**De la vista:**

- `PlanUsage` reporta el nombre del paquete, el uso y los topes correctos.
- Una empresa con 3 de 3 lavadoras se marca como topada; con 2 de 3, no.
- Una empresa sin plan se reporta como sin plan, no como topada.
- El filtro "Ya topó su límite" deja fuera a quien todavía tiene cupo.

## Fuera de alcance

- Que la prueba caiga automáticamente a Gratuito al vencer.
- Cambiar los límites o precios de los paquetes.
- Notificar al cliente cuando se acerca a su tope.
