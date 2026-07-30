<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\TareaFallidaNotification;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * El latido de las tareas programadas: cuáles corrieron, cuáles fallaron y
 * cuáles llevan demasiado sin dar señales.
 *
 * Son dos problemas distintos y hacen falta los dos:
 *
 * 1. Una tarea que TRUENA. Se avisa en el momento, porque hay una excepción que
 *    contar y alguien puede hacer algo hoy.
 * 2. Una tarea que DEJA DE CORRER. Ésa no truena: simplemente no pasa nada, y no
 *    hay excepción que atrapar. Se detecta al revés, por ausencia: si la última
 *    corrida es más vieja de lo que su horario permite, algo está mal.
 *
 * El segundo es el que muerde. El script de limpieza de temporales falló 48
 * semanas seguidas por un salto de línea en su primera línea; nunca mandó un
 * error porque nunca llegó a arrancar.
 */
class LatidoDeTareas
{
    /**
     * Cada cuántas horas se espera cada tarea, con holgura.
     *
     * La holgura ya viene incluida: una diaria se marca perdida a las 30 horas y
     * no a las 24, porque un despliegue a media noche o un servidor lento no son
     * una falla y avisar por eso enseña a ignorar los avisos.
     */
    public const ESPERADAS = [
        'rentals:mark-overdue' => ['horas' => 30, 'que_hace' => 'Marca como vencidas las rentas que se pasaron de fecha'],
        'rentals:send-reminders' => ['horas' => 30, 'que_hace' => 'Manda los recordatorios de vencimiento'],
        'users:lifecycle-emails' => ['horas' => 30, 'que_hace' => 'Manda los correos de seguimiento a las cuentas'],
        'users:check-inactive' => ['horas' => 30, 'que_hace' => 'Detecta las cuentas que se enfriaron'],
        'backup:run' => ['horas' => 30, 'que_hace' => 'Hace el respaldo de la base y los archivos'],
        'demo:cleanup' => ['horas' => 3, 'que_hace' => 'Borra los demos vencidos y sus archivos'],
    ];

    /** Queda anotado que corrió bien. */
    public static function registrar(string $tarea): void
    {
        self::anotar($tarea, true);
    }

    /**
     * Queda anotado que falló, y se avisa en el momento.
     *
     * Se avisa a los administradores de la plataforma y no al dueño de la
     * lavandería: son tareas del sistema y él no puede hacer nada con eso más
     * que preocuparse.
     */
    public static function registrarFallo(string $tarea, ?string $mensaje = null): void
    {
        self::anotar($tarea, false, $mensaje);

        Log::error("Tarea programada fallida: {$tarea}", ['mensaje' => $mensaje]);

        try {
            foreach (User::role('super_admin')->get() as $admin) {
                $admin->notify(new TareaFallidaNotification($tarea, $mensaje));
            }
        } catch (\Throwable $e) {
            // Si hasta el aviso falla, al menos queda en el log. Reventar aquí
            // dejaría al scheduler tirado y se perderían las demás tareas.
            Log::error('No se pudo avisar de la tarea fallida.', ['error' => $e->getMessage()]);
        }
    }

    private static function anotar(string $tarea, bool $ok, ?string $mensaje = null): void
    {
        DB::table('task_runs')->insert([
            'tarea' => $tarea,
            'ok' => $ok,
            'mensaje' => $mensaje ? mb_substr($mensaje, 0, 2000) : null,
            'corrio_en' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public static function ultima(string $tarea): ?object
    {
        return DB::table('task_runs')
            ->where('tarea', $tarea)
            ->orderByDesc('corrio_en')
            ->first();
    }

    /**
     * El estado de todas las tareas vigiladas.
     *
     * @return Collection<int, object>
     */
    public static function estado(): Collection
    {
        return collect(self::ESPERADAS)->map(function (array $config, string $tarea) {
            $ultima = self::ultima($tarea);
            $cuando = $ultima ? Carbon::parse($ultima->corrio_en) : null;

            // Nunca vista NO es lo mismo que perdida: puede que se acabe de
            // desplegar la vigilancia y todavía no le toque correr.
            $perdida = $cuando !== null && $cuando->lt(now()->subHours($config['horas']));

            return (object) [
                'tarea' => $tarea,
                'queHace' => $config['que_hace'],
                'cada' => $config['horas'],
                'ultima' => $cuando,
                'ok' => $ultima ? (bool) $ultima->ok : null,
                'mensaje' => $ultima->mensaje ?? null,
                'perdida' => $perdida,
                'nuncaVista' => $ultima === null,
            ];
        })->values();
    }

    /** Las que fallaron la última vez o llevan demasiado sin correr. */
    public static function conProblema(): Collection
    {
        return self::estado()->filter(
            fn (object $t) => $t->perdida || $t->ok === false
        )->values();
    }

    public static function hayProblema(): bool
    {
        return self::conProblema()->isNotEmpty();
    }

    /**
     * Borra el historial viejo.
     *
     * Sólo hace falta la última corrida de cada tarea, pero se guarda un mes para
     * poder ver un patrón —"falla los lunes"— que una sola fila no enseña.
     */
    public static function limpiarHistorial(int $dias = 30): int
    {
        return DB::table('task_runs')
            ->where('corrio_en', '<', now()->subDays($dias))
            ->delete();
    }
}
