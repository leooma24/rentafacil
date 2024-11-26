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
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status', ['abierta', 'en_progreso', 'cerrada'])->default('abierta');
            $table->enum('priority', ['baja', 'media', 'alta'])->default('media');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('washing_machine_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('type', ['mecánica', 'eléctrica', 'software', 'otra'])->default('otra');
            $table->dateTime('resolved_at')->nullable();
            $table->text('comments')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
