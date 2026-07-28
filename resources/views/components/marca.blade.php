{{--
    El logotipo del panel, en SVG para que salga nítido a cualquier tamaño.

    La marca es geométrica a propósito: un aro que sugiere el tambor de la
    lavadora, con el punto de carga arriba. La ilustración anterior tenía
    contornos gruesos y degradados que a 4 rem se leían como clip-art.

    currentColor en el texto para que se adapte al tema claro y oscuro.
--}}
<div class="flex items-center gap-2.5">
    <svg width="34" height="34" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <rect width="40" height="40" rx="11" fill="#06b6d4"/>
        {{-- El tambor --}}
        <circle cx="20" cy="22" r="9.5" stroke="#fff" stroke-width="2.6"/>
        {{-- El agua dentro, insinuada con un arco --}}
        <path d="M12.4 24.3c2 0 2-2.2 4-2.2s2 2.2 4 2.2 2-2.2 4-2.2 2 2.2 3.4 2.2"
              stroke="#fff" stroke-width="2.2" stroke-linecap="round" opacity=".75"/>
        {{-- El panel de control --}}
        <circle cx="28.4" cy="10.6" r="1.9" fill="#fff"/>
        <rect x="10" y="9" width="8.4" height="3.2" rx="1.6" fill="#fff" opacity=".85"/>
    </svg>

    <span style="font-size: 1.05rem; font-weight: 700; letter-spacing: -.015em; line-height: 1; color: currentColor;">
        Renta<span style="color: #06b6d4;">Fácil</span>
    </span>
</div>
