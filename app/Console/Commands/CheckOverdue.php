<?php

namespace App\Console\Commands;

use App\Models\Rental;
use Illuminate\Console\Command;

class CheckOverdue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-overdue';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        //
        Rental::where('end_date', '<', now())
            ->whereIn('status', ['activa', 'en_mantenimiento'])
            ->update(['status' => 'vencida']);
    }
}
