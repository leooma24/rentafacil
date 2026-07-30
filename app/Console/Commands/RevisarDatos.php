<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\DatosIncoherentesNotification;
use App\Support\RevisionDeDatos;
use Illuminate\Console\Command;

/**
 * Revisa que los datos no se contradigan.
 *
 * Las incoherencias de estado no revientan nada: el aparato simplemente no
 * aparece para rentar, y el dueño se entera el día que le hace falta y no lo
 * encuentra. En una sola sesión de trabajo aparecieron tres de esas, y las tres se
 * encontraron a mano.
 */
class RevisarDatos extends Command
{
    protected $signature = 'datos:revisar {--empresa= : Revisar sólo una empresa}';

    protected $description = 'Busca equipos y rentas cuyos estados se contradicen.';

    public function handle(): int
    {
        if ($id = $this->option('empresa')) {
            $empresa = \App\Models\Company::find($id);

            if (! $empresa) {
                $this->error("No existe la empresa {$id}.");

                return self::FAILURE;
            }

            $revisiones = collect([RevisionDeDatos::for($empresa)])
                ->filter(fn (RevisionDeDatos $r) => $r->hay())
                ->values();
        } else {
            $revisiones = RevisionDeDatos::todasLasCuentas();
        }

        if ($revisiones->isEmpty()) {
            $this->info('Todo coherente: ningún equipo ni renta se contradice.');

            return self::SUCCESS;
        }

        foreach ($revisiones as $revision) {
            $this->newLine();
            $this->warn($revision->empresa->name . ' — ' . $revision->cuantos()
                . ($revision->cuantos() === 1 ? ' cosa por revisar' : ' cosas por revisar'));

            foreach ($revision->hallazgos as $hallazgo) {
                $this->line("  {$hallazgo->equipo}: {$hallazgo->que_pasa}");
            }
        }

        $total = $revisiones->sum(fn (RevisionDeDatos $r) => $r->cuantos());

        // Se avisa a quien opera la plataforma, no al dueño de cada lavandería: a
        // él ya le sale en sus pendientes del día lo que le toca arreglar, con el
        // botón para hacerlo. Esto es para saber si el problema es de uno o de
        // todos, que es una pregunta distinta.
        foreach (User::role('super_admin')->get() as $admin) {
            $admin->notify(new DatosIncoherentesNotification($revisiones->count(), $total));
        }

        $this->newLine();
        $this->warn("{$total} en total, en {$revisiones->count()} cuentas.");

        return self::SUCCESS;
    }
}
