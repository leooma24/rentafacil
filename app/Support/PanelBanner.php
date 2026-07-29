<?php

namespace App\Support;

use App\Models\Company;

/**
 * Decide qué barra se muestra arriba del panel del propietario.
 */
class PanelBanner
{
    public static function for(?Company $tenant): string
    {
        if (! $tenant) {
            return '';
        }

        if ($tenant->is_demo) {
            return self::bar(
                // Azul pizarra, el mismo de las páginas públicas. El morado
                // anterior peleaba con el cyan de la marca.
                '#0f172a',
                'Estás en un <strong>demo</strong>: los datos son de ejemplo y se borran solos en 24 horas.',
                '/propietario/registrar',
                'Crear mi cuenta real'
            );
        }

        // Sin precio configurado, cobrar truena y el estado de cuenta no puede
        // calcular. Rompe cosas en silencio, así que va antes que lo del plan.
        if (Onboarding::for($tenant)->needsPrice()) {
            return self::bar(
                '#f59e0b',
                'Te falta configurar tu <strong>precio de renta</strong>. Sin eso no puedes registrar cobros.',
                "/propietario/{$tenant->id}/configuracion",
                'Configurarlo ahora'
            );
        }

        $planUrl = "/propietario/{$tenant->id}/mi-plan";

        if ($tenant->isOnTrial()) {
            $days = $tenant->trialDaysLeft();
            $color = $days <= 3 ? '#ef4444' : ($days <= 7 ? '#f59e0b' : '#06b6d4');

            return self::bar(
                $color,
                "Prueba gratuita: te quedan <strong>{$days} días</strong>.",
                $planUrl,
                'Elegir un plan'
            );
        }

        // El gratuito no vence. Lo que lo acota es el cupo, así que sólo se le
        // habla cuando de verdad se llenó, y ahí la barra es una oferta y no un
        // regaño: es justo la señal de que ya lo está usando.
        if ($tenant->isOnFreePlan()) {
            $uso = PlanUsage::for($tenant);

            if ($uso->isMaxedOut()) {
                return self::bar(
                    '#f59e0b',
                    "Llegaste al tope del plan gratuito (<strong>{$uso->machinesLabel()} equipos</strong>). Súbete de plan para seguir dando de alta.",
                    $planUrl,
                    'Ver los planes'
                );
            }

            return '';
        }

        if (! $tenant->hasActivePackage()) {
            return self::bar(
                '#ef4444',
                'Tu plan ha expirado.',
                $planUrl,
                'Contratar plan para continuar'
            );
        }

        return '';
    }

    private static function bar(string $color, string $message, string $url, string $linkText): string
    {
        return "<div style=\"background:{$color};color:#fff;text-align:center;padding:8px 16px;font-size:14px;font-weight:600;\">"
            . $message
            . " <a href=\"{$url}\" style=\"color:#fff;text-decoration:underline;margin-left:8px;\">{$linkText}</a>"
            . '</div>';
    }
}
