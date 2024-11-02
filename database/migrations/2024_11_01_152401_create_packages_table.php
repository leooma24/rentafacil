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
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');            // Nombre del paquete
            $table->integer('max_clients');     // Máximo de clientes permitidos en el paquete
            $table->integer('max_washers');     // Máximo de lavadoras permitidas en el paquete
            $table->decimal('price', 8, 2);     // Costo del paquete
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
