<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\PaymentIntent;

class PaymentController extends Controller
{
    //
    public function createPaymentIntent(Request $request)
    {
        Stripe::setApiKey(config('services.stripe.secret'));
        $package_id = $request->packageId;
        $package = \App\Models\Package::findOrFail($package_id);

        $paymentIntent = PaymentIntent::create([
            'amount' => $package->price * 100, // monto en centavos, ej. $10.00 = 1000
            'currency' => 'mxn',
            'payment_method_types' => ['card'],
        ]);

        return response()->json([
            'clientSecret' => $paymentIntent->client_secret,
        ]);
    }
}
