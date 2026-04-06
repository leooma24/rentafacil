<x-mail::message>
Hola **{{ $user->name }}**,

Tu prueba gratuita ha terminado. Pero no te preocupes, **tu información sigue guardada** y puedes reactivar tu cuenta en cualquier momento.

Durante tu prueba pudiste:
- Gestionar tus lavadoras sin papel
- Cobrar más rápido
- Tener todo organizado en un solo lugar

**¿Vas a dejar que todo eso se pierda?**

Elige un plan y sigue gestionando tu negocio como un profesional:

<x-mail::button :url="$planUrl" color="primary">
Elegir mi plan ahora
</x-mail::button>

Planes desde **$149/mes**. También puedes pagar por transferencia SPEI.

**El equipo de Renta Fácil**
</x-mail::message>
