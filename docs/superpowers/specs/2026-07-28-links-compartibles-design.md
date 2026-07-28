# Recibo y estado de cuenta compartibles — diseño

Fecha: 2026-07-28

## Problema

El dueño no tiene forma de darle a su cliente un comprobante ni de contestarle "¿cuánto
debo?" sin sacar cuentas por chat.

El recibo en PDF ya existe (`ContractController::receipt`), pero su ruta está detrás del
middleware `auth`: solo el dueño puede abrirla, así que tendría que descargar el archivo y
mandarlo a mano por WhatsApp.

El portal del cliente **no lo puede usar nadie**: solo existen los roles `propietario` y
`super_admin`, `Customer` no tiene `user_id` —los clientes no son usuarios— y no se ha
creado ni un ticket desde que existe. Y el mercado tampoco lo pide: quien renta una
lavadora no va a crear cuenta ni recordar contraseña.

Lo que sí funciona con esa gente es un link de WhatsApp que abre y ya.

## Cómo se comparte sin cuenta

Ligas firmadas de Laravel (`URL::signedRoute`): el link lleva una firma que el servidor
verifica. No se puede adivinar el de otro cliente ni cambiarle el identificador para ver
datos ajenos; si se altera un carácter, la ruta responde 403.

- **El recibo no caduca.** Es el comprobante de algo que ya pasó.
- **El estado de cuenta caduca a los 30 días.** El saldo cambia, y un link viejo que diga
  "no debes nada" sería peor que no tener link.

## Lo que ve el cliente

Páginas pensadas para el teléfono, sin menús ni login, con su propio HTML y CSS en línea:
no dependen del CSS compilado de Filament, que no incluye las clases que no usa.

**Recibo**: el nombre del negocio, "Recibo de pago", el monto en grande, la fecha, el
método, la lavadora y hasta cuándo queda cubierta la renta. Con un botón para descargar el
PDF que ya se genera hoy.

**Estado de cuenta**: el nombre del cliente, cuánto debe en grande, desde cuándo, y la
lista de lavadoras que trae con lo que debe cada una. Si está al corriente, lo dice.

Ambas páginas llevan `noindex` para que no acaben en buscadores.

## Lo que ve el dueño

Al terminar de cobrar, la notificación de éxito trae un botón **"Mandar recibo"** que abre
WhatsApp con el mensaje ya escrito:

```
Hola {cliente}, recibimos tu pago de $250.00. Aquí está tu comprobante:
{liga}

Tu renta queda cubierta hasta el 06/08/2026.
— Renta Fácil
```

En la pantalla de estado de cuenta, un botón **"Mandar por WhatsApp"**:

```
Hola {cliente}, este es tu estado de cuenta con nosotros:
{liga}

Debes $750.00 desde el 13/07/2026.
— Renta Fácil
```

Cuando el cliente está al corriente, el mensaje lo dice en vez del adeudo.

El número se arma con `ProspectOutreach::whatsappNumber()`, la misma regla que ya se usa
para prospectos, para no tener dos formas distintas de normalizar un teléfono mexicano.

## Componentes

**Se crean:**

| Archivo | Responsabilidad |
|---|---|
| `app/Support/ShareableLinks.php` | Arma las ligas firmadas y los mensajes de WhatsApp. Sin Filament ni HTTP. |
| `app/Http/Controllers/PublicDocumentController.php` | Muestra el recibo y el estado de cuenta. Solo resuelve y renderiza; la firma la valida el middleware. |
| `resources/views/publico/recibo.blade.php` | La página del recibo. |
| `resources/views/publico/estado-de-cuenta.blade.php` | La página del estado de cuenta. |
| `tests/Feature/LinksCompartiblesTest.php` | Firma, caducidad y contenido. |

**Se modifican:**

| Archivo | Cambio |
|---|---|
| `routes/web.php` | Las dos rutas públicas con middleware `signed`. |
| `app/Filament/Resources/RentalResource/Actions/ExtendRentAction.php` | Botón "Mandar recibo" en la notificación de éxito. |
| `app/Filament/Resources/WashingMachineResource/Actions/ExtendRentAction.php` | Lo mismo, es la gemela. |
| `app/Filament/Resources/CustomerResource/Pages/AccountStatementPage.php` | Botón "Mandar por WhatsApp" en el encabezado. |

El PDF sigue saliendo de `ContractController::receipt`, que se queda como está para el
dueño; la página pública liga a su propia ruta firmada de descarga.

## Pruebas

Lo que más importa, porque ahí van datos de clientes:

- Un link **sin firma** responde 403.
- Un link con la **firma alterada** responde 403.
- Un link de estado de cuenta **caducado** responde 403.
- Un link válido de recibo muestra el monto, la fecha y la lavadora.
- Un link válido de estado de cuenta muestra el saldo y las lavadoras del cliente.
- Un cliente al corriente ve que está al corriente, no un saldo en cero sin contexto.
- Los mensajes de WhatsApp llevan el nombre, el monto y la liga.
- El número se normaliza igual que en prospectos.

## Fuera de alcance

- Dar cuentas a los clientes o revivir el portal.
- Mandar el mensaje automáticamente: se abre WhatsApp y el dueño le da enviar, como en
  todo lo demás.
- Compartir el contrato.
