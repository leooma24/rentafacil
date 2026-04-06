<x-mail::message>
Hola **{{ $user->name }}**,

Ya llevas 2 semanas usando Renta Fácil y nos encantaría saber **cómo te ha ido**.

Solo toma 30 segundos. Dinos del **1 al 10**, ¿qué tan probable es que recomiendes Renta Fácil a otro propietario de lavadoras?

<div style="text-align: center; margin: 20px 0;">
<span style="color: #64748b; font-size: 13px;">Nada probable</span>
&nbsp;&nbsp;
@for($i = 1; $i <= 10; $i++)
<a href="https://wa.me/6682493398?text=Mi%20calificación%20de%20Renta%20Fácil%20es%20{{ $i }}%2F10.%20{{ $i >= 8 ? 'Me%20gusta%20mucho.' : ($i >= 5 ? 'Está%20bien%20pero%20podría%20mejorar.' : 'Necesito%20ayuda.') }}" style="display: inline-block; width: 32px; height: 32px; line-height: 32px; text-align: center; border-radius: 6px; margin: 2px; font-weight: 700; font-size: 14px; text-decoration: none; {{ $i >= 9 ? 'background: #dcfce7; color: #16a34a;' : ($i >= 7 ? 'background: #fef9c3; color: #a16207;' : 'background: #fee2e2; color: #dc2626;') }}">{{ $i }}</a>
@endfor
&nbsp;&nbsp;
<span style="color: #64748b; font-size: 13px;">Muy probable</span>
</div>

Al hacer clic se abre WhatsApp con tu respuesta. **Es anónimo y nos ayuda a mejorar.**

---

**¿Algo que quieras que mejoremos?** Escríbenos directo:

<x-mail::button :url="'https://wa.me/6682493398?text=Hola%2C%20tengo%20una%20sugerencia%20para%20Renta%20Fácil%3A%20'" color="success">
Enviar sugerencia
</x-mail::button>

Gracias por confiar en nosotros,<br>
**El equipo de Renta Fácil**
</x-mail::message>
