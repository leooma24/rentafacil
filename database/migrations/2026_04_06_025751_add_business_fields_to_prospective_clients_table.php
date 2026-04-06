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
        Schema::table('prospective_clients', function (Blueprint $table) {
            $table->string('city')->nullable()->after('phone');
            $table->string('business_name')->nullable()->after('city');
            $table->integer('num_washers')->nullable()->after('business_name');
            $table->foreignId('converted_user_id')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('converted_at')->nullable()->after('converted_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('prospective_clients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('converted_user_id');
            $table->dropColumn(['city', 'business_name', 'num_washers', 'converted_at']);
        });
    }
};
