<?php

namespace App\Listeners;

use App\Events\RentEvent;
use App\Mail\RentNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendRentNotification
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(RentEvent $event): void
    {
        //
        Mail::to($event->data['email'])->send(new RentNotification($event->data));
    }
}
