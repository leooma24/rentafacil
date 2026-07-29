<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Mueve las fotos que ya existen de la carpeta pública a la privada.
 *
 * Las rutas guardadas en la base son relativas ("entregas/xxx.jpg"), así que
 * mover los archivos basta: no hay que tocar ningún registro.
 *
 * Se corre una vez, al desplegar el cambio de disco. Es idempotente: si ya se
 * movieron, no hace nada.
 */
class MoverFotosAPrivado extends Command
{
    protected $signature = 'fotos:privatizar {--dry-run : Sólo dice qué movería}';

    protected $description = 'Mueve las fotos de storage/app/public a storage/app/privado';

    private const CARPETAS = ['entregas', 'recolecciones', 'incidents'];

    public function handle(): int
    {
        $origen = storage_path('app/public');
        $destino = storage_path('app/privado');
        $simulacion = $this->option('dry-run');

        $movidas = 0;

        foreach (self::CARPETAS as $carpeta) {
            $de = "{$origen}/{$carpeta}";

            if (! File::isDirectory($de)) {
                $this->line("· {$carpeta}: no existe, nada que mover");
                continue;
            }

            $archivos = File::files($de);

            if ($archivos === []) {
                $this->line("· {$carpeta}: vacía");
                continue;
            }

            $a = "{$destino}/{$carpeta}";

            if (! $simulacion) {
                File::ensureDirectoryExists($a, 0755);
            }

            foreach ($archivos as $archivo) {
                $nombre = $archivo->getFilename();

                if ($simulacion) {
                    $this->line("  movería {$carpeta}/{$nombre}");
                    $movidas++;
                    continue;
                }

                File::move($archivo->getPathname(), "{$a}/{$nombre}");
                $movidas++;
            }

            $this->info("· {$carpeta}: " . count($archivos) . ' archivos');

            // La carpeta vacía se queda: borrarla no aporta y sí puede romper
            // algo que dé por hecho que existe.
        }

        $this->newLine();
        $this->info($simulacion
            ? "Se moverían {$movidas} archivos."
            : "Listo: {$movidas} archivos fuera del alcance público.");

        return self::SUCCESS;
    }
}
