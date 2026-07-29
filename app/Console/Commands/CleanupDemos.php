<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CleanupDemos extends Command
{
    protected $signature = 'demo:cleanup';

    protected $description = 'Borra las empresas demo cuya vigencia ya venció, junto con todos sus datos.';

    public function handle(): int
    {
        $companies = Company::expiredDemos()->get();

        foreach ($companies as $company) {
            DB::transaction(function () use ($company) {
                $userIds = $company->members()->pluck('users.id');

                // Antes que nada: los papeles y las fotos del demo son archivos
                // de verdad en disco y ningún borrado en cascada los alcanza.
                // Va primero porque las rutas viven en las filas que estamos a
                // punto de borrar.
                $this->borrarArchivos($company->id);

                // Borrado real con DB::table: los modelos usan SoftDeletes y aquí
                // no queremos dejar filas marcadas como basura.
                DB::table('payments')->where('company_id', $company->id)->delete();
                DB::table('rentals')->where('company_id', $company->id)->delete();
                DB::table('maintenances')->where('company_id', $company->id)->delete();
                DB::table('incidents')->where('company_id', $company->id)->delete();

                $customerIds = DB::table('customers')->where('company_id', $company->id)->pluck('id');

                DB::table('addresses')
                    ->where('addressable_type', Customer::class)
                    ->whereIn('addressable_id', $customerIds)
                    ->delete();
                DB::table('customers')->where('company_id', $company->id)->delete();

                DB::table('washing_machines')->where('company_id', $company->id)->delete();
                DB::table('settings')->where('company_id', $company->id)->delete();
                DB::table('company_package')->where('company_id', $company->id)->delete();
                DB::table('company_user')->where('company_id', $company->id)->delete();

                DB::table('companies')->where('id', $company->id)->delete();

                User::whereIn('id', $userIds)->where('is_demo', true)->forceDelete();

                // model_has_roles es polimórfica y por eso no tiene llave
                // foránea a users: nadie limpia estos renglones al borrar al
                // usuario, y se van acumulando uno por cada demo.
                DB::table('model_has_roles')
                    ->where('model_type', User::class)
                    ->whereIn('model_id', $userIds)
                    ->delete();
            });
        }

        $this->info("Demos borradas: {$companies->count()}");

        return self::SUCCESS;
    }

    private function borrarArchivos(int $companyId): void
    {
        $documentos = DB::table('customer_documents')
            ->whereIn('customer_id', function ($query) use ($companyId) {
                $query->select('id')->from('customers')->where('company_id', $companyId);
            })
            ->pluck('file_path');

        foreach ($documentos as $ruta) {
            Storage::disk('local')->delete($ruta);
        }

        $fotos = DB::table('rentals')
            ->where('company_id', $companyId)
            ->whereNotNull('delivery_photos')
            ->pluck('delivery_photos')
            ->concat(
                DB::table('rentals')
                    ->where('company_id', $companyId)
                    ->whereNotNull('pickup_photos')
                    ->pluck('pickup_photos')
            );

        foreach ($fotos as $json) {
            foreach ((array) json_decode((string) $json, true) as $ruta) {
                if (is_string($ruta) && $ruta !== '') {
                    Storage::disk('privado')->delete($ruta);
                }
            }
        }
    }
}
