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
        // Change enum to string to support more states
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE prospective_clients MODIFY COLUMN status VARCHAR(50) DEFAULT 'nuevo'");
    }

    public function down(): void
    {
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE prospective_clients MODIFY COLUMN status ENUM('nuevo','contactado','interesado','no_interesado','cliente') DEFAULT 'nuevo'");
    }
};
