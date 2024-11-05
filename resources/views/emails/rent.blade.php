<x-mail::message>
# Renta Fácil - Notificación de servicio de renta

Hola, {{ $data['nombre'] }}.

{{ $data['mensaje'] }}

Gracias, <br />
{{ config('app.name') }}
</x-mail::message>
