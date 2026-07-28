<?php

namespace App\Services;

use App\Models\Address;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Convierte una dirección escrita en coordenadas.
 *
 * Usa Nominatim, el buscador de OpenStreetMap: es gratis y no pide llave, que
 * es lo que importa cuando el dueño no quiere más gastos fijos.
 *
 * Ojo con la privacidad: buscar una dirección la manda a un servidor ajeno.
 * Por eso aquí no hay ningún proceso que recorra la base solo: siempre lo
 * dispara el dueño, sobre las direcciones que él escoge.
 *
 * La política de uso de Nominatim pide identificarse y no pasar de una
 * consulta por segundo. Las dos cosas se respetan.
 */
class Geocoder
{
    private const URL = 'https://nominatim.openstreetmap.org/search';

    /** Su política pide un máximo de una consulta por segundo. */
    public const PAUSA_ENTRE_CONSULTAS = 1;

    /** @return array{lat: float, lng: float}|null */
    public function buscar(string $direccion): ?array
    {
        if (trim($direccion) === '') {
            return null;
        }

        try {
            $respuesta = Http::withHeaders([
                    // Nominatim rechaza a quien no se identifica.
                    'User-Agent' => 'RentaFacil/1.0 (' . config('app.url') . ')',
                ])
                ->timeout(8)
                ->get(self::URL, [
                    'q' => $direccion,
                    'format' => 'json',
                    'limit' => 1,
                    'countrycodes' => 'mx',
                ]);
        } catch (\Throwable $e) {
            Log::warning('Geocoder: no se pudo consultar', ['error' => $e->getMessage()]);

            return null;
        }

        if (! $respuesta->successful()) {
            return null;
        }

        $primero = $respuesta->json()[0] ?? null;

        if (! $primero || ! isset($primero['lat'], $primero['lon'])) {
            return null;
        }

        return ['lat' => (float) $primero['lat'], 'lng' => (float) $primero['lon']];
    }

    /**
     * Busca y guarda la ubicación de una dirección ya capturada.
     *
     * Devuelve false cuando no se encontró, para que quien llama pueda decirlo
     * en lugar de dejar al dueño esperando algo que no pasó.
     */
    public function ubicar(Address $direccion): bool
    {
        foreach ($this->intentos($direccion) as $indice => $texto) {
            if ($indice > 0) {
                sleep(self::PAUSA_ENTRE_CONSULTAS);
            }

            $coordenadas = $this->buscar($texto);

            if ($coordenadas) {
                $direccion->forceFill([
                    'latitude' => $coordenadas['lat'],
                    'longitude' => $coordenadas['lng'],
                ])->save();

                return true;
            }
        }

        return false;
    }

    /**
     * La dirección en una línea, como la entiende el buscador.
     *
     * Va de lo particular a lo general y sin el número interior, que a un mapa
     * no le dice nada y sí le estorba.
     */
    public function texto(Address $direccion): string
    {
        $partes = array_filter([
            trim("{$direccion->street} {$direccion->number}"),
            $this->colonia($direccion),
            $direccion->postal_code,
            $direccion->city,
            $direccion->state?->nombre,
            'México',
        ], fn ($parte) => filled($parte));

        return implode(', ', $partes);
    }

    /**
     * Las direcciones que se le van a probar al buscador, de la más completa a
     * la más general.
     *
     * Una dirección capturada a mano casi nunca está como el mapa la espera:
     * "Av. Álvaro Obregón 100, Primer Cuadro (Centro), 81200, Los Mochis" no
     * devuelve nada, y esa misma sin la colonia cae justo en el punto. Antes de
     * darla por perdida se prueba quitándole lo que sobra.
     *
     * @return array<int, string>
     */
    public function intentos(Address $direccion): array
    {
        $calle = trim("{$direccion->street} {$direccion->number}");
        $estado = $direccion->state?->nombre;

        $armar = fn (array $partes) => implode(
            ', ',
            array_filter($partes, fn ($parte) => filled($parte))
        );

        return array_values(array_unique(array_filter([
            $this->texto($direccion),
            // Sin colonia: es la parte que más se escribe distinto a como viene
            // en el mapa.
            $armar([$calle, $direccion->postal_code, $direccion->city, $estado, 'México']),
            // Sin código postal ni colonia, por si el CP está mal capturado.
            $armar([$calle, $direccion->city, $estado, 'México']),
            // Aquí se acaba. Caer al centro de la ciudad pondría a todos los
            // clientes en el mismo punto: el planificador los daría por
            // ubicados y trazaría una ruta que no lleva a ninguna casa.
        ], fn ($texto) => filled($texto))));
    }

    /**
     * El nombre de la colonia sin lo que va entre paréntesis.
     *
     * El catálogo trae nombres como "Primer Cuadro (Centro)" y el buscador no
     * los reconoce con el paréntesis encima.
     */
    private function colonia(Address $direccion): ?string
    {
        $nombre = $direccion->neighborhood?->nombre;

        if (! filled($nombre)) {
            return null;
        }

        return trim(preg_replace('/\s*\([^)]*\)/', '', $nombre)) ?: null;
    }
}
