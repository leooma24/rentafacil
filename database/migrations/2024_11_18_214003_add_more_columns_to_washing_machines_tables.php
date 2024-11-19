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
            $table->integer('washing_program_count')->nullable()->default(0);
            $table->json('available_temperatures')->nullable(); // ['Cold', '30°C', '40°C']
            $table->integer('noise_level')->nullable(); // in decibels
            $table->integer('water_efficiency')->nullable(); // in liters/cycle
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('washing_machines_tables', function (Blueprint $table) {
            //
            $table->dropColumn('serial_number');
            $table->dropColumn('purchase_date');
            $table->dropColumn('purchase_price');
            $table->dropColumn('type');
            $table->dropColumn('color');
            $table->dropColumn('load_capacity');
            $table->dropColumn('height');
            $table->dropColumn('width');
            $table->dropColumn('depth');
            $table->dropColumn('weight');
            $table->dropColumn('motor_power');
            $table->dropColumn('spin_speed');
            $table->dropColumn('energy_consumption');
            $table->dropColumn('motor_type');
            $table->dropColumn('washing_program_count');
            $table->dropColumn('available_temperatures');
            $table->dropColumn('noise_level');
            $table->dropColumn('water_efficiency');
        });
    }
};
