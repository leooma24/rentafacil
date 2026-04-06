<x-mail::message>
Hola **{{ $user->name }}** 🎉

**¡Bienvenido a Renta Fácil!** Tu cuenta está lista y tienes **15 días gratis** con todas las funciones.

---

## Tu guía rápida para empezar

Sigue estos 4 pasos y en menos de 10 minutos estarás gestionando tu negocio:

### Paso 1: Configura tu empresa
Entra a **Configuración** y establece tu precio de renta y los días que cubre cada pago.

### Paso 2: Agrega tus lavadoras
Ve a **Mis Lavadoras** → **Crear** y registra cada máquina con su código, marca y modelo.

### Paso 3: Registra tus clientes
Ve a **Mis Clientes** → **Crear** y agrega nombre, teléfono y dirección de cada cliente.

### Paso 4: Crea tu primera renta
Ve a **Mis Rentas** → **Crear**, selecciona el cliente, la lavadora y las fechas. ¡Listo!

---

## ¿Qué más puedes hacer?

- **Cobrar por WhatsApp** — En la tabla de rentas, clic en "WhatsApp" para enviar recordatorio
- **Generar contratos PDF** — Clic en "Contrato" en cualquier renta
- **Ver tus ingresos** — El dashboard te muestra gráficas en tiempo real
- **Planificar rutas** — Selecciona los clientes a visitar y te calcula la mejor ruta

<x-mail::button :url="'https://rentafacil.tu-app.co/propietario'" color="primary">
Entrar a mi panel
</x-mail::button>

---

## ¿Necesitas ayuda?

No estás solo. Estamos aquí para ayudarte:

<x-mail::button :url="'https://wa.me/6682493398?text=Hola%2C%20acabo%20de%20registrarme%20en%20Renta%20Fácil%20y%20necesito%20ayuda'" color="success">
Escríbenos por WhatsApp
</x-mail::button>

Te ayudamos a configurar todo si lo necesitas. Sin costo.

¡Mucho éxito con tu negocio!<br>
**El equipo de Renta Fácil**
</x-mail::message>
