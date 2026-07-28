# Contactar hoy — diseño

Fecha: 2026-07-28

## Problema

En producción hay **102 prospectos**: 89 de scraping y 13 de Facebook. De ellos, 94
tienen teléfono y 89 tienen ciudad. **Ninguno ha sido contactado nunca**: los 102 siguen
en estado `nuevo` y `last_contacted_at` está vacío en todos.

No es que falte la herramienta. `ProspectiveClientResource` ya tiene un botón que abre
WhatsApp con un mensaje escrito, normaliza el teléfono y marca al prospecto como
contactado. Nadie lo ha presionado.

Lo que falla es el flujo: una tabla de 94 renglones dentro del menú de Administrador no
invita a trabajarla. Y el mensaje que trae ("¿Te gustaría conocer más?") pide permiso en
vez de enseñar algo — y no incluye el demo, que es el argumento más fuerte y es nuevo.

El envío automático no es opción: `prospects:contact` corre cada hora sin poder enviar
nada, porque no hay ni un solo correo entre los 102 y el token de WhatsApp está vacío.
Configurar la API de WhatsApp costaría por conversación, que es justo lo que el negocio
no quiere.

## Los mensajes

Tres plantillas, todas con el demo al frente y firmadas "Omar, Renta Fácil".
`{nombre}` se sustituye con el nombre del prospecto.

**Primer contacto**

```
Hola {nombre} 👋

¿Rentas lavadoras? Hicimos una app para negocios como el tuyo, para dejar la
libreta: sabes dónde está cada máquina, quién la trae y cuánto te debe, y te
avisa cuando se vencen.

Míralo funcionando ahorita, sin registrarte ni dar tus datos:
https://rentafacil.tu-app.co/demo

Si te late, son 15 días gratis.
— Omar, Renta Fácil
```

**Seguimiento**

```
Hola {nombre}, ¿alcanzaste a abrir el demo?

https://rentafacil.tu-app.co/demo

Si prefieres te lo enseño en una llamada de 5 minutos, tú dime.
— Omar, Renta Fácil
```

**Solo el demo**

```
Hola {nombre}, te paso el demo de Renta Fácil para que le muevas por dentro:
https://rentafacil.tu-app.co/demo

Son datos de ejemplo, puedes crear y borrar lo que quieras. Se borra solo en 24 horas.
— Omar, Renta Fácil
```

La URL del demo sale de `config('app.url')`, para que no quede escrita a mano y siga
funcionando en local.

## La pantalla

Una página **"Contactar hoy"** en el grupo Administrador, ruta `contactar`.

- **Arriba:** cuántos prospectos faltan por contactar y un selector de ciudad, para
  trabajar por zona.
- **La ficha del prospecto en turno:** nombre, negocio, ciudad, teléfono y fuente.
- **Selector de plantilla** y un botón grande **Abrir WhatsApp**, que abre `wa.me` en
  otra pestaña con el mensaje ya escrito.
- **Cinco botones de cierre:** Le interesó · Agendé demo · No le interesó · Ya es
  cliente · Saltar. Cada uno guarda el estado y trae al siguiente.

Abrir WhatsApp marca al prospecto como `contactado` y sella `last_contacted_at`, igual
que ya hace el botón del recurso.

"Saltar" no cambia el estado: solo pasa al siguiente en esta sesión.

Cuando no queden pendientes, la pantalla lo dice y no muestra ficha.

## Componentes

**Se crean:**

| Archivo | Responsabilidad |
|---|---|
| `app/Support/ProspectOutreach.php` | A quién sigue contactar, las tres plantillas ya sustituidas y el teléfono normalizado a formato `wa.me`. Sin Filament. |
| `app/Filament/Pages/Prospeccion.php` | La pantalla: estado de la cola y las acciones. |
| `resources/views/filament/pages/prospeccion.blade.php` | Su vista. |
| `tests/Unit/ProspectOutreachTest.php` | Orden de la cola, plantillas y normalización del teléfono. |
| `tests/Feature/ProspeccionPageTest.php` | Que la pantalla abra y que calificar avance al siguiente. |

**Se modifica:** `app/Filament/Resources/ProspectiveClientResource.php`, para que su botón
de WhatsApp use las mismas plantillas y la misma normalización en vez de la línea que hoy
tiene incrustada.

### Normalización del teléfono

Hoy vive en una sola línea ilegible dentro del recurso: quita todo lo que no sea dígito y
antepone `52` si quedan 10. Se mueve a `ProspectOutreach::whatsappNumber()` con esa misma
regla, y desde ahí la usan la pantalla y el recurso.

## Pruebas

- La cola trae primero a los `nuevo` y deja fuera a los `cliente` y `no_interesado`.
- Filtrando por ciudad, solo salen los de esa ciudad.
- Cada plantilla sustituye el nombre e incluye la liga del demo.
- Un teléfono de 10 dígitos queda con `52` al frente; uno que ya trae `52` no se duplica;
  los guiones y espacios se van.
- La pantalla abre sin error y muestra al prospecto en turno.
- Calificar a un prospecto guarda su estado y trae al siguiente.
- Sin pendientes, la pantalla lo dice y no truena.

## Fuera de alcance

- Enviar mensajes automáticamente.
- Editar las plantillas desde el panel.
- Importar más prospectos o hacer scraping.
- Tocar el comando `prospects:contact`, que se revisará aparte.
