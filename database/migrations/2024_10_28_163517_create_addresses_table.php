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
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained();
            $table->string('street')->nullable(); // Calle
            $table->string('number')->nullable(); // Número
            $table->string('interior_number')->nullable(); // Número interior
            $table->string('city');
            $table->foreignId('neighborhood_id')->nullable()->constrained('colonias'); // Delegación o municipio
            $table->foreignId('township_id')->nullable()->constrained('municipios'); // Ciudad
            $table->foreignId('state_id')->nullable()->constrained('estados'); // Estado
            $table->foreignId('country_id')->nullable()->constrained('paises'); // País
            $table->string('postal_code')->nullable(); // Código postal
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
