<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * La carpeta de archivos subidos no debe ejecutar PHP.
 *
 * No es teoría: en febrero aparecieron ahí dos archivos que nadie subió desde la
 * app — uno con enlaces ocultos a sitios de apuestas y otro con código para
 * ejecutar cosas en el servidor. Alguien los bloqueó a mano en el servidor, pero
 * esa protección no estaba en el repositorio: un despliegue que sobrescribiera
 * el archivo reabría el hueco sin que nadie se enterara.
 *
 * Esta prueba existe para que no vuelva a pasar en silencio.
 */
class ProteccionDeSubidasTest extends TestCase
{
    public function test_la_carpeta_de_subidas_no_ejecuta_php(): void
    {
        $ruta = storage_path('app/public/.htaccess');

        $this->assertFileExists($ruta, 'Falta el .htaccess que impide ejecutar PHP en las subidas.');

        $contenido = file_get_contents($ruta);

        $this->assertMatchesRegularExpression('/FilesMatch.*\\\\\.php/i', $contenido);
        $this->assertStringContainsString('Deny from all', $contenido);
    }

    /** Y va versionado, porque si no, no sobrevive al siguiente despliegue. */
    public function test_ese_htaccess_esta_versionado(): void
    {
        $ignore = file_get_contents(storage_path('app/public/.gitignore'));

        $this->assertStringContainsString(
            '!.htaccess',
            $ignore,
            'El .htaccess está ignorado por git y se perdería al desplegar.'
        );
    }

    public function test_el_htaccess_publico_bloquea_los_nombres_conocidos(): void
    {
        $contenido = file_get_contents(public_path('.htaccess'));

        // Los dos que aparecieron, más los nombres típicos de este tipo de archivo.
        foreach (['bnn_', 'rip', 'shell', 'cmd', 'eval'] as $nombre) {
            $this->assertStringContainsString(
                $nombre,
                $contenido,
                "El .htaccess público dejó de bloquear «{$nombre}»."
            );
        }

        $this->assertStringContainsString('Deny from all', $contenido);
    }

    /** Las cabeceras de seguridad también vivían sólo en el servidor. */
    public function test_las_cabeceras_de_seguridad_estan_versionadas(): void
    {
        $contenido = file_get_contents(public_path('.htaccess'));

        foreach ([
            'X-Content-Type-Options',
            'X-Frame-Options',
            'Referrer-Policy',
        ] as $cabecera) {
            $this->assertStringContainsString($cabecera, $contenido);
        }
    }
}
