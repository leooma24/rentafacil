<x-mail::message>
¡Felicidades **{{ $user->name }}**! 🎉

Acabas de crear tu primera renta en Renta Fácil:

- **Lavadora:** {{ $machineName }}
- **Cliente:** {{ $customerName }}

**¡Así de fácil!** Ya estás gestionando tu negocio como un profesional.

---

**¿Qué sigue?** Aquí van 3 cosas que puedes hacer ahora:

1. **Agrega más lavadoras** para tener todo tu inventario controlado
2. **Configura tus precios** en Configuración para poder extender rentas con un clic
3. **Genera el contrato PDF** de esta renta desde la tabla de "Mis Rentas"

<x-mail::button :url="'https://rentafacil.tu-app.co/propietario'" color="primary">
Ir a mi panel
</x-mail::button>

¡Sigue así!<br>
**El equipo de Renta Fácil**
</x-mail::message>
