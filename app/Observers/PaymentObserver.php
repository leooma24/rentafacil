<?php

namespace App\Observers;

use App\Models\Payment;
use App\Notifications\PaymentReceivedNotification;

class PaymentObserver
{
    public function created(Payment $payment): void
    {
        $payment->load('company.members', 'rental.washingMachine');

        if ($payment->company) {
            $payment->company->members->each(fn ($member) =>
                $member->notify(new PaymentReceivedNotification($payment))
            );
        }
    }
}
