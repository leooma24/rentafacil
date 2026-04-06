<x-mail::message>
Hola **{{ $user->name }}** 💡

## Tip de la semana: {{ $tipTitle }}

{!! $tipBody !!}

<x-mail::button :url="'https://rentafacil.tu-app.co/propietario'" color="primary">
Probarlo ahora
</x-mail::button>

¿Quieres aprender más trucos? Escríbenos por WhatsApp y te ayudamos.

**El equipo de Renta Fácil**
</x-mail::message>
