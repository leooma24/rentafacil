<x-mail::message>
# Hola {{ $customer->name }}

Tu renta de lavadora ha vencido.

**Detalles de la renta:**
- **Lavadora:** {{ $machine->machine_code }} - {{ $machine->brand }} {{ $machine->model }}
- **Fecha de vencimiento:** {{ \Carbon\Carbon::parse($rental->end_date)->format('d/m/Y') }}
- **Días de atraso:** {{ $daysOverdue }}

Es importante que regularices tu situación lo antes posible para evitar cargos adicionales o la recolección del equipo.

<x-mail::button :url="''" color="error">
Contactar
</x-mail::button>

Gracias,<br>
{{ config('app.name') }}
</x-mail::message>
