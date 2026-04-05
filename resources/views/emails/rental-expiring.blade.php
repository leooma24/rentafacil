<x-mail::message>
# Hola {{ $customer->name }}

Tu renta de lavadora está por vencer.

**Detalles de la renta:**
- **Lavadora:** {{ $machine->machine_code }} - {{ $machine->brand }} {{ $machine->model }}
- **Fecha de vencimiento:** {{ \Carbon\Carbon::parse($rental->end_date)->format('d/m/Y') }}
- **Días restantes:** {{ $daysLeft }}

Para renovar tu renta, contacta a tu proveedor o realiza tu pago antes de la fecha de vencimiento.

<x-mail::button :url="''" color="primary">
Contactar
</x-mail::button>

Gracias por tu preferencia,<br>
{{ config('app.name') }}
</x-mail::message>
