<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El corte de caja de un día.
 *
 * De 641 cobros en producción, 389 son en efectivo. El dueño termina el día con
 * ese dinero en la bolsa y hasta ahora no había forma de cuadrarlo: cuánto
 * debería traer, cuánto trae de verdad, y si falta algo.
 *
 * Se guarda lo esperado además de lo contado, aunque lo esperado se pueda
 * recalcular: si mañana se corrige un cobro viejo, el corte que ya se firmó
 * tiene que seguir diciendo lo que decía ese día.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_closings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('closing_date');
            $table->decimal('expected_cash', 10, 2);
            $table->decimal('counted_cash', 10, 2);
            $table->decimal('difference', 10, 2);
            $table->unsignedInteger('payments_count');
            $table->text('notes')->nullable();
            $table->timestamps();

            // Un corte por día y por persona: si el dueño y el cobrador cierran
            // por separado, cada quien cuadra lo suyo.
            $table->unique(['company_id', 'user_id', 'closing_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_closings');
    }
};
