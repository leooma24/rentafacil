<?php

namespace App\Services;

use App\Models\Rental;
use Stripe\StripeClient;

class StripePaymentLinkService
{
    protected StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    public function createPaymentLink(Rental $rental): ?string
    {
        $settings = $rental->company->settings;

        if (!$settings || !$settings->price) {
            return null;
        }

        $session = $this->stripe->checkout->sessions->create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'mxn',
                    'product_data' => [
                        'name' => "Pago de Renta - Lavadora {$rental->washingMachine->machine_code}",
                        'description' => "Cliente: {$rental->customer->name}",
                    ],
                    'unit_amount' => (int) ($settings->price * 100),
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => url('/') . '?payment=success&rental=' . $rental->id,
            'cancel_url' => url('/') . '?payment=cancelled',
            'metadata' => [
                'rental_id' => $rental->id,
                'company_id' => $rental->company_id,
                'type' => 'one_time_payment',
            ],
        ]);

        return $session->url;
    }
}
