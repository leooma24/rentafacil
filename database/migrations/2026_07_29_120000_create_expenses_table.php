<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que el negocio gasta.
 *
 * Hasta ahora sólo se registraba el costo de los mantenimientos, así que
 * "Ingresos del Mes" se leía como ganancia cuando no lo es: falta la gasolina
 * de salir a cobrar, los sueldos, las refacciones y la renta del local. Un
 * rentador que no sabe cuánto gasta no sabe si gana.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            // Quién lo registró. Se queda si la persona se va del equipo.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category');
            $table->string('description');
            $table->decimal('amount', 10, 2);
            $table->date('expense_date');
            $table->string('payment_method')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Los reportes siempre preguntan por empresa y rango de fechas.
            $table->index(['company_id', 'expense_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
