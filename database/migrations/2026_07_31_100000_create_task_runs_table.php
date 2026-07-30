<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cuándo corrió cada tarea programada y si salió bien.
 *
 * No había nada que avisara cuando una tarea se cae. El respaldo tiene su
 * monitor, pero todo lo demás moría en silencio: marcar vencidas, mandar
 * recordatorios, los correos de ciclo de vida, borrar los demos.
 *
 * Y no es hipotético: el script de limpieza de temporales falló 48 semanas
 * seguidas por un salto de línea en su primera línea, y nadie se enteró hasta que
 * alguien fue a mirar.
 *
 * El peor caso es marcar vencidas. Si se muere, nadie aparece como vencido: los
 * avisos salen vacíos, la cola de recolección sale vacía, la cobranza sale limpia.
 * Una semana sin morosos y una semana con el sistema roto se ven idénticas, y eso
 * es justo lo que las vuelve peligrosas.
 *
 * Va en una tabla y no en caché a propósito: `optimize:clear` corre en cada
 * despliegue y se llevaría el historial completo, que es lo único con que se
 * detecta una tarea que dejó de correr.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_runs', function (Blueprint $table) {
            $table->id();
            $table->string('tarea')->index();
            $table->boolean('ok');
            $table->text('mensaje')->nullable();
            $table->timestamp('corrio_en');
            $table->timestamps();

            // La consulta de siempre es "la última corrida de esta tarea".
            $table->index(['tarea', 'corrio_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_runs');
    }
};
