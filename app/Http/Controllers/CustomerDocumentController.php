<?php

namespace App\Http\Controllers;

use App\Models\CustomerDocument;
use App\Support\Acceso;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Entrega los papeles del cliente comprobando quién los pide.
 *
 * Antes vivían en storage/app/public, que se sirve tal cual en /storage/..., así
 * que una identificación oficial la bajaba cualquiera con la liga y sin sesión.
 * Ahora viven en el disco privado y pasan por aquí.
 */
class CustomerDocumentController extends Controller
{
    public function show(CustomerDocument $document): StreamedResponse
    {
        $usuario = auth()->user();

        // Tres candados: hay sesión, es dueño, y el cliente es de SU empresa.
        // El tercero es el que importa: sin él, un dueño podría pedir el
        // documento de un cliente de otra lavandería cambiando el número.
        abort_unless($usuario && Acceso::soloDueno(), 403);

        $empresa = $document->customer?->company_id;

        abort_unless(
            $empresa && $usuario->companies()->whereKey($empresa)->exists(),
            403
        );

        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->response(
            $document->file_path,
            $document->original_name ?: basename($document->file_path),
            ['Content-Disposition' => 'inline'],
        );
    }
}
