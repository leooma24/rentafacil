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
        Schema::create('maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade'); // Referencia a la tabla de empresas (si aplica)
            $table->foreignId('washing_machine_id')->constrained()->onDelete('cascade'); // Referencia a la tabla de lavadoras
            $table->string('technician_name'); // Nombre del técnico responsable
            $table->date('start_date'); // Fecha de inicio del mantenimiento
            $table->date('end_date')->nullable(); // Fecha de finalización (puede ser nula hasta que se complete)
            $table->string('maintenance_type'); // Tipo de mantenimiento: 'preventivo', 'correctivo', etc.
            $table->text('description')->nullable(); // Descripción del mantenimiento
            $table->decimal('cost', 10, 2)->nullable(); // Costo del mantenimiento
            $table->enum('status', ['programada', 'en_progreso', 'completado'])->default('programada'); // Estado del mantenimiento
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenances');
    }
};
