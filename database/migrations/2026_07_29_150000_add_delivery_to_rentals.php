<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La entrega del equipo, con evidencia.
 *
 * Existía "Recoger" pero no "Entregar": la renta nacía y ya. Sin foto ni acuse no
 * hay con qué responder al "así me la entregaste" cuando devuelven el aparato
 * golpeado, y el que pierde siempre es el dueño.
 *
 * Las fotos de recolección van en la misma migración para poder comparar el antes
 * y el después, que es lo único que hace útil a las de entrega.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->timestamp('delivered_at')->nullable()->after('deposit_returned_at');
            $table->text('delivery_notes')->nullable()->after('delivered_at');
            $table->json('delivery_photos')->nullable()->after('delivery_notes');
            $table->json('pickup_photos')->nullable()->after('delivery_photos');
        });
    }

    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->dropColumn(['delivered_at', 'delivery_notes', 'delivery_photos', 'pickup_photos']);
        });
    }
};
