<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

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
            });
        }

        $this->info("Demos borradas: {$companies->count()}");

        return self::SUCCESS;
    }
}
