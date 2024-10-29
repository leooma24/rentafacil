<?php

namespace App\Console\Commands;

use App\Models\Rental;
use Illuminate\Console\Command;

class MarkRentalsAsOverdue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rentals:mark-overdue';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Marcar rentas como vencidas si han pasado su fecha de vencimiento';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        //
        $overdueRentals = Rental::where('status', 'activa')
                                ->where('end_date', '<', now())
                                ->get();

        foreach ($overdueRentals as $rental) {
            $rental->update(['status' => 'vencida']);
        }

        $this->info('Las rentas vencidas han sido marcadas correctamente.');
    }
}
