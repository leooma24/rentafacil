<?php

namespace App\Services;

use App\Models\Rental;
use App\Models\Setting;
use Stripe\StripeClient;

class StripeRecurringService
{
    protected StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    public function createSubscriptionForRental(Rental $rental): ?string
    {
        $customer = $rental->customer;
        $settings = $rental->company->settings;

        if (!$settings || !$settings->price) {
            return null;
        }

        // Create or get Stripe customer
        $stripeCustomer = $this->stripe->customers->create([
            'name' => $customer->name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'metadata' => [
                'rental_id' => $rental->id,
                'company_id' => $rental->company_id,
            ],
        ]);

        // Create a price for this rental
        $price = $this->stripe->prices->create([
            'unit_amount' => (int) ($settings->price * 100),
            'currency' => 'mxn',
            'recurring' => [
                'interval' => 'day',
                'interval_count' => $settings->days_per_payment,
            ],
            'product_data' => [
                'name' => "Renta Lavadora {$rental->washingMachine->machine_code}",
            ],
        ]);

        // Create checkout session for subscription
        $session = $this->stripe->checkout->sessions->create([
            'customer' => $stripeCustomer->id,
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price' => $price->id,
                'quantity' => 1,
            ]],
            'mode' => 'subscription',
            'success_url' => url('/') . '?payment=success',
            'cancel_url' => url('/') . '?payment=cancelled',
            'metadata' => [
                'rental_id' => $rental->id,
                'company_id' => $rental->company_id,
            ],
        ]);

        // Store stripe customer id
        $rental->update(['notes' => ($rental->notes ? $rental->notes . "\n" : '') . "Stripe: {$stripeCustomer->id}"]);

        return $session->url;
    }
}
