<?php

namespace App\Http\Controllers;

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Entrega las fotos de entregas, recolecciones e incidencias comprobando sesión.
 *
 * Vivían en storage/app/public, que se sirve tal cual en /storage/..., así que
 * las bajaba cualquiera con la liga. Son fotos del equipo y muchas veces del
 * interior de la casa del cliente.
 *
 * La ruta valida que el archivo pertenezca a una de las carpetas conocidas y no
 * traiga saltos de directorio: sin eso, esto mismo sería la puerta para leer
 * cualquier archivo del servidor.
 */
class ArchivoPrivadoController extends Controller
{
    /** Sólo estas carpetas se sirven por aquí. */
    private const CARPETAS = ['entregas', 'recolecciones', 'incidents'];

    public function show(string $ruta): StreamedResponse
    {
        abort_unless(auth()->check(), 403);

        // Sin saltos de directorio ni rutas absolutas.
        abort_if(
            str_contains($ruta, '..') || str_starts_with($ruta, '/'),
            403,
        );

        $carpeta = explode('/', $ruta)[0] ?? '';

        abort_unless(in_array($carpeta, self::CARPETAS, true), 403);

        // Y que quien pide tenga una empresa activa en el panel: un usuario
        // suelto sin empresa no tiene por qué ver fotos de nadie.
        abort_unless(Filament::getTenant() !== null, 403);

        abort_unless(Storage::disk('privado')->exists($ruta), 404);

        return Storage::disk('privado')->response($ruta, basename($ruta), [
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
