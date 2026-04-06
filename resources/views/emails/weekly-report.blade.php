<x-mail::message>
Hola **{{ $user->name }}**, aquí está tu resumen de la semana:

<x-mail::table>
| Métrica | Valor |
|:---|---:|
| Ingresos cobrados | **${{ number_format($revenue, 2) }}** |
| Rentas activas | **{{ $activeRentals }}** |
| Rentas vencidas | **{{ $overdueRentals }}** |
| Total lavadoras | **{{ $totalMachines }}** |
</x-mail::table>

@if($overdueRentals > 0)
⚠️ Tienes **{{ $overdueRentals }} rentas vencidas**. Entra a tu panel para enviar recordatorios de cobro.
@else
✅ **¡Todo en orden!** No tienes rentas vencidas esta semana.
@endif

<x-mail::button :url="'https://rentafacil.tu-app.co/propietario'" color="primary">
Ver mi dashboard
</x-mail::button>

¡Que tengas una excelente semana!<br>
**El equipo de Renta Fácil**
</x-mail::message>
