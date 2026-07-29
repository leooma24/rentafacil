<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El historial de cambios de equipo dentro de una misma renta.
 *
 * Si una lavadora se descompone y se le lleva otra al mismo cliente, hasta ahora
 * había que cancelar la renta y crear otra: se perdía el historial de pagos y el
 * saldo del cliente arrancaba de cero.
 *
 * Tabla propia y no sólo cambiar washing_machine_id: eso último borra de dónde
 * venía, y justamente lo que se quiere saber es qué equipo tuvo antes y por qué
 * se le cambió.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_machine_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_machine_id')->constrained('washing_machines');
            $table->foreignId('to_machine_id')->constrained('washing_machines');
            // Se conserva si la persona que lo hizo deja el equipo.
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('rental_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_machine_changes');
    }
};
