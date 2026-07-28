<?php

namespace App\Support;

use App\Models\ProspectiveClient;
use Illuminate\Support\Collection;

/**
 * A quién le toca, qué se le manda y a qué número.
 *
 * El envío es manual a propósito: se abre WhatsApp con el mensaje ya escrito y
 * la persona le da enviar. La API de WhatsApp cobra por conversación y aquí no
 * hay ni un solo correo entre los prospectos, así que automatizar no es opción.
 */
class ProspectOutreach
{
    /** Estados que ya no hay que volver a tocar. */
    private const CERRADOS = ['cliente', 'no_interesado'];

    public const PLANTILLAS = [
        'primero' => 'Primer contacto',
        'seguimiento' => 'Seguimiento',
        'demo' => 'Solo el demo',
    ];

    /**
     * Los que faltan por contactar: primero los nuevos, y dentro de cada grupo
     * los más viejos, para que nadie se quede al fondo para siempre.
     *
     * @return Collection<int, ProspectiveClient>
     */
    public static function queue(?string $ciudad = null): Collection
    {
        return ProspectiveClient::query()
            ->whereNotIn('status', self::CERRADOS)
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->when($ciudad, fn ($q) => $q->where('city', $ciudad))
            ->orderByRaw("CASE WHEN status = 'nuevo' THEN 0 ELSE 1 END")
            ->orderBy('last_contacted_at')
            ->orderBy('id')
            ->get();
    }

    public static function pendingCount(?string $ciudad = null): int
    {
        return self::queue($ciudad)->count();
    }

    /** Las ciudades que tienen prospectos pendientes. */
    public static function cities(): array
    {
        return ProspectiveClient::query()
            ->whereNotIn('status', self::CERRADOS)
            ->whereNotNull('city')
            ->where('city', '!=', '')
            ->distinct()
            ->orderBy('city')
            ->pluck('city', 'city')
            ->all();
    }

    /**
     * El número como lo quiere wa.me: solo dígitos, con lada de México si venía
     * de 10. Un número que ya trae 52 no se duplica.
     */
    public static function whatsappNumber(?string $phone): string
    {
        $digitos = preg_replace('/\D/', '', (string) $phone);

        if (strlen($digitos) === 10) {
            return '52' . $digitos;
        }

        return $digitos;
    }

    public static function message(ProspectiveClient $prospect, string $plantilla): string
    {
        $nombre = trim((string) $prospect->name) ?: 'qué tal';
        $demo = rtrim(config('app.url'), '/') . '/demo';

        return match ($plantilla) {
            'seguimiento' => <<<TXT
                Hola {$nombre}, ¿alcanzaste a abrir el demo?

                {$demo}

                Si prefieres te lo enseño en una llamada de 5 minutos, tú dime.
                — Omar, Renta Fácil
                TXT,

            'demo' => <<<TXT
                Hola {$nombre}, te paso el demo de Renta Fácil para que le muevas por dentro:
                {$demo}

                Son datos de ejemplo, puedes crear y borrar lo que quieras. Se borra solo en 24 horas.
                — Omar, Renta Fácil
                TXT,

            default => <<<TXT
                Hola {$nombre} 👋

                ¿Rentas lavadoras? Hicimos una app para negocios como el tuyo, para dejar la libreta: sabes dónde está cada máquina, quién la trae y cuánto te debe, y te avisa cuando se vencen.

                Míralo funcionando ahorita, sin registrarte ni dar tus datos:
                {$demo}

                Si te late, son 15 días gratis.
                — Omar, Renta Fácil
                TXT,
        };
    }

    /** La liga que abre WhatsApp con el mensaje ya escrito. */
    public static function whatsappUrl(ProspectiveClient $prospect, string $plantilla): string
    {
        // rawurlencode y no urlencode: este último manda los espacios como "+"
        // y WhatsApp los muestra tal cual, así que el mensaje le llegaba al
        // prospecto lleno de signos de más.
        return 'https://wa.me/' . self::whatsappNumber($prospect->phone)
            . '?text=' . rawurlencode(self::message($prospect, $plantilla));
    }
}
