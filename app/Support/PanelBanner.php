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
                '#7c3aed',
                'Estás en un <strong>demo</strong>: los datos son de ejemplo y se borran solos en 24 horas.',
                '/propietario/registrar',
                'Crear mi cuenta real'
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
