<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('is_demo')->default(false)->index();
            $table->timestamp('demo_expires_at')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_demo')->default(false)->index();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['is_demo', 'demo_expires_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_demo');
        });
    }
};
