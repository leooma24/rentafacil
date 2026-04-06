<x-mail::message>
Hola **{{ $user->name }}**,

Notamos que creaste tu cuenta en Renta Fácil pero todavía no has agregado tus lavadoras. **¿Está todo bien?**

Sabemos que empezar algo nuevo puede parecer complicado, pero en realidad es muy sencillo. Solo necesitas:

1. **Agregar tus lavadoras** (código, marca y modelo)
2. **Registrar tus clientes** (nombre y teléfono)
3. **Crear tus rentas** (quién tiene qué lavadora)

**Y listo.** El sistema se encarga del resto.

---

**¿Estás batallando con algo?** No te preocupes, te ayudamos:

<x-mail::button :url="'https://wa.me/6682493398?text=Hola%2C%20me%20registré%20en%20Renta%20Fácil%20pero%20ocupo%20ayuda%20para%20configurar'" color="success">
Pedir ayuda por WhatsApp
</x-mail::button>

Te podemos ayudar a configurar todo en una llamada rápida de 10 minutos. **Sin costo.**

---

Recuerda que tu prueba gratuita sigue activa. No dejes pasar la oportunidad de organizar tu negocio.

<x-mail::button :url="'https://rentafacil.tu-app.co/propietario'" color="primary">
Entrar a mi panel
</x-mail::button>

Un abrazo,<br>
**El equipo de Renta Fácil**
</x-mail::message>
