<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('prospective_clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');  // Nombre del cliente potencial
            $table->string('email')->unique();  // Email único para contacto
            $table->string('phone')->nullable();  // Teléfono opcional
            $table->string('source')->nullable();  // Fuente de contacto, e.g., redes sociales, formulario web
            $table->text('notes')->nullable();  // Notas sobre el cliente, intereses, etc.
            $table->timestamp('last_contacted_at')->nullable();  // Fecha de último contacto
            $table->enum('status', ['nuevo', 'contactado', 'interesado', 'no_interesado', 'cliente'])->default('nuevo');  // Estado del cliente potencial
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prospective_clients');
    }
};
