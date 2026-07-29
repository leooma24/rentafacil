<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los papeles del cliente: INE, comprobante de domicilio, referencias.
 *
 * La tabla customers tiene nombre, correo y teléfono. Nada más. Y esos papeles son
 * justo lo que permite recuperar un aparato cuando alguien se muda con él.
 *
 * Tabla aparte y no columnas en customers: son varios por cliente, se agregan con
 * el tiempo y cada uno tiene su fecha y quién lo subió.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            // Se conserva si la persona que lo subió deja el equipo.
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');
            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_documents');
    }
};
