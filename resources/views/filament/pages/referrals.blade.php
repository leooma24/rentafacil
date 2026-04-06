<x-filament-panels::page>
    <div style="max-width: 600px;">
        <div style="background: linear-gradient(135deg, #f0fdfa, #ecfeff); border: 2px solid #67e8f9; border-radius: 12px; padding: 32px; margin-bottom: 24px; text-align: center;">
            <div style="font-size: 48px; margin-bottom: 12px;">🎁</div>
            <h2 style="font-size: 22px; font-weight: 800; color: #0e7490; margin: 0 0 8px;">Invita y gana días gratis</h2>
            <p style="font-size: 15px; color: #64748b; margin: 0 0 20px;">Por cada amigo que se registre con tu link, <strong>ambos reciben 7 días extra gratis</strong>.</p>

            <div style="background: white; border: 2px dashed #06b6d4; border-radius: 8px; padding: 16px; margin-bottom: 16px;">
                <p style="font-size: 12px; color: #94a3b8; margin: 0 0 6px;">Tu link de referido:</p>
                <p style="font-size: 15px; font-weight: 700; color: #0f172a; margin: 0; word-break: break-all;">{{ $referralUrl }}</p>
            </div>

            <div style="display: flex; gap: 8px; justify-content: center; flex-wrap: wrap;">
                <a href="https://wa.me/?text=Te%20recomiendo%20Renta%20Fácil%20para%20gestionar%20tu%20negocio%20de%20lavadoras.%20Regístrate%20con%20mi%20link%20y%20ambos%20ganamos%207%20días%20gratis%3A%20{{ urlencode($referralUrl) }}" target="_blank"
                   style="display: inline-flex; align-items: center; gap: 6px; padding: 10px 20px; background: #25D366; color: white; border-radius: 8px; font-size: 14px; font-weight: 600; text-decoration: none;">
                    Compartir por WhatsApp
                </a>
                <button onclick="navigator.clipboard.writeText('{{ $referralUrl }}'); alert('Link copiado')"
                   style="display: inline-flex; align-items: center; gap: 6px; padding: 10px 20px; background: #0e7490; color: white; border-radius: 8px; font-size: 14px; font-weight: 600; border: none; cursor: pointer;">
                    Copiar link
                </button>
            </div>
        </div>

        <div style="background: white; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h3 style="font-size: 16px; font-weight: 700; margin: 0 0 8px;">Tus referidos: {{ $referralsCount }}</h3>
            <p style="font-size: 14px; color: #64748b; margin: 0;">
                @if($referralsCount > 0)
                    Has ganado <strong>{{ $referralsCount * 7 }} días extra</strong> gracias a tus referidos.
                @else
                    Todavía no tienes referidos. ¡Comparte tu link y gana días gratis!
                @endif
            </p>
        </div>
    </div>
</x-filament-panels::page>
