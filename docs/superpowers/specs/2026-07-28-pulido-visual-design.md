# Pulido visual del panel — diseño

Fecha: 2026-07-28

## Problema

El panel ya funciona bien, pero sigue viéndose como una plantilla configurada, no como
un producto. Cuatro cosas concretas, en orden de cuánto restan:

**El logotipo.** `public/img/logo.png` es una ilustración de lavadora con contornos
gruesos, degradados y burbujas sueltas. A los 4 rem del panel se lee como clip-art, y sale
en cada pantalla y en la de acceso, que es la primera impresión de todo el que se registra.

**El escritorio no tiene hilo.** Ocho widgets apilados sin jerarquía visible: los tres
números de hoy, la tabla de cobranza, los del dinero, los del estado del negocio. El orden
está pensado pero no se ve; parece una pila de tarjetas.

**El ancho.** En monitor de 1440 px las tablas se estiran de borde a borde y la lectura se
pierde.

**La barra del demo** es morado brillante y pelea con el cyan de la marca.

## El logotipo

Un logotipo propio en SVG: una marca geométrica —un aro que sugiere el tambor, con un
punto de carga— junto a "Renta Fácil" en la tipografía del panel. Nítido a cualquier
tamaño, en el cyan de la marca, y pesa unos cientos de bytes.

Se guarda como `resources/views/components/marca.blade.php` y se pasa a
`brandLogo()`, que acepta una vista.

`public/img/logo.png` **no se borra**: se sigue usando en el PWA, en las etiquetas de
compartir y en los PDF, y queda disponible por si se prefiere volver a él.

## Las secciones del escritorio

Entre los widgets se insertan títulos que cuentan la pantalla:

- **Hoy** — antes de los tres números y la tabla de cobranza.
- **El dinero** — antes de ingresos y la gráfica.
- **Estado del negocio** — antes de lavadoras, estado de rentas, rentabilidad y análisis.

Se resuelve con un widget muy chico, `SectionHeading`, que solo dibuja un título y una
línea. Se registra tres veces en el `Dashboard` con textos distintos, en vez de crear tres
clases iguales.

No lleva tarjeta ni fondo: es un rótulo, y una tarjeta lo volvería un bloque más.

## El ancho de contenido

`->maxContentWidth(MaxWidth::SevenExtraLarge)` en el panel. En pantallas grandes el
contenido se centra en vez de estirarse; en pantallas chicas no cambia nada.

## La barra del demo

Pasa del morado `#7c3aed` al azul pizarra `#0f172a`, que es el mismo tono oscuro de las
páginas públicas del recibo. Deja de competir con el cyan y se lee como parte del
producto.

## Componentes

**Se crean:**

| Archivo | Responsabilidad |
|---|---|
| `resources/views/components/marca.blade.php` | El logotipo en SVG. |
| `app/Filament/Widgets/SectionHeading.php` | El rótulo de sección, parametrizable. |
| `resources/views/filament/widgets/section-heading.blade.php` | Su vista. |
| `tests/Feature/PulidoVisualTest.php` | Marca, secciones y ancho. |

**Se modifican:**

| Archivo | Cambio |
|---|---|
| `app/Providers/Filament/AdminPanelProvider.php` | La marca nueva y el ancho de contenido. |
| `app/Filament/Pages/Dashboard.php` | Los tres rótulos entre los widgets. |
| `app/Support/PanelBanner.php` | El color de la barra del demo. |

## Pruebas

- El panel declara la vista de marca y un ancho de contenido acotado.
- El escritorio declara los tres rótulos en el orden previsto, entre los widgets
  correctos.
- El rótulo dibuja el texto que se le pasa.
- La barra del demo ya no usa el morado.
- El escritorio sigue abriendo sin error.

Y a ojo, en el navegador: que la marca se vea nítida en el panel y en la pantalla de
acceso, y que en 1440 px el contenido quede centrado.

## Fuera de alcance

- Compilar un tema propio de Tailwind. Se puede, pero mete un paso de compilación al
  despliegue, y ya salió dos veces que las clases que Filament no compila no existen.
- Rediseñar formularios.
- El panel del cliente y el sitio público.
