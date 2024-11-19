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
        Schema::table('washing_machines', function (Blueprint $table) {
            //
            $table->string('serial_number')->nullable();
            $table->string('purchase_date')->nullable();
            $table->string('purchase_price')->nullable();
            $table->string('type')->nullable();
            $table->string('color')->nullable();
            $table->float('load_capacity')->nullable();
            $table->integer('height')->nullable(); // in cm
            $table->integer('width')->nullable(); // in cm
            $table->integer('depth')->nullable(); // in cm
            $table->float('weight')->nullable(); // in kg
            $table->integer('motor_power')->nullable(); // in W
            $table->integer('spin_speed')->nullable(); // in RPM
            $table->string('energy_consumption')->nullable(); // Rating: A++, etc.
            $table->string('motor_type')->nullable(); // Inverter, traditional
            $table->integer('washing_program_count')->default(0);
            $table->json('available_temperatures'); // ['Cold', '30°C', '40°C']
            $table->integer('noise_level'); // in decibels
            $table->integer('water_efficiency'); // in liters/cycle
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('washing_machines_tables', function (Blueprint $table) {
            //
        });
    }
};
