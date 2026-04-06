<x-mail::message>
Hola **{{ $user->name }}**,

Tu prueba gratuita de Renta Fácil termina en **{{ $daysLeft }} días**.

Durante este tiempo has podido gestionar tus lavadoras, cobrar más fácil y ahorrar tiempo. **¿Te imaginas volver a la libreta?**

Para seguir con todas las funciones, elige tu plan:

<x-mail::button :url="$planUrl" color="primary">
Elegir mi plan
</x-mail::button>

Planes desde **$149/mes** — menos de lo que pierdes en una renta no cobrada.

¿Dudas? Escríbenos:

<x-mail::button :url="'https://wa.me/6682493398?text=Hola%2C%20quiero%20renovar%20mi%20plan%20de%20Renta%20Fácil'" color="success">
Hablar por WhatsApp
</x-mail::button>

**El equipo de Renta Fácil**
</x-mail::message>
