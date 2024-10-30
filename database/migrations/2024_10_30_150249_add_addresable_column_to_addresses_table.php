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
        Schema::table('addresses', function (Blueprint $table) {
            //
            $table->dropForeign('addresses_customer_id_foreign');
            $table->dropIndex('addresses_customer_id_foreign');
            $table->dropColumn('customer_id');
            $table->morphs('addressable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            //
            $table->foreignId('customer_id')->constrained();
            $table->dropMorphs('addressable');
        });
    }
};
