<?php

namespace App\Http\Controllers;

use App\Models\Package;
use Filament\Facades\Filament;
use Stripe\StripeClient;

class PlanCheckoutController extends Controller
{
    public function checkout(Package $package)
    {
        $tenant = Filament::getTenant();

        if (!$tenant) {
            abort(403);
        }

        // Un sandbox de demo nunca debe llegar a un cobro real.
        if ($tenant->is_demo) {
            return redirect('/propietario/registrar');
        }

        $stripe = new StripeClient(config('services.stripe.secret'));

        $session = $stripe->checkout->sessions->create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'mxn',
                    'product_data' => [
                        'name' => "Plan {$package->name} - Renta Fácil",
                        'description' => "Hasta {$package->max_washers} lavadoras y {$package->max_clients} clientes",
                    ],
                    'unit_amount' => (int) ($package->price * 100),
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => url("/propietario/{$tenant->id}/mi-plan?payment=success"),
            'cancel_url' => url("/propietario/{$tenant->id}/mi-plan?payment=cancelled"),
            'metadata' => [
                'type' => 'plan_purchase',
                'company_id' => $tenant->id,
                'package_id' => $package->id,
            ],
        ]);

        return redirect($session->url);
    }
}
