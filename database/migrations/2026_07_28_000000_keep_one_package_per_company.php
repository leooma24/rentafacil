<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Deja un solo plan por empresa.
 *
 * CompanyObserver le asignaba el paquete Gratuito a toda empresa nueva, además del
 * que ya le daba RegisterCompany, así que quedaban dos filas y se aplicaba la
 * equivocada. Ya se quitó esa asignación; aquí se limpia lo que dejó.
 *
 * Se conserva la fila de id más alto, que es la que el modelo considera vigente.
 */
return new class extends Migration
{
    public function up(): void
    {
        $sobrantes = DB::table('company_package')
            ->select('company_id', DB::raw('MAX(id) as ultimo'))
            ->groupBy('company_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($sobrantes as $fila) {
            DB::table('company_package')
                ->where('company_id', $fila->company_id)
                ->where('id', '<', $fila->ultimo)
                ->delete();
        }
    }

    public function down(): void
    {
        // Las filas borradas eran duplicados que el observer creaba de más;
        // no representan nada que el negocio quiera restaurar.
    }
};
