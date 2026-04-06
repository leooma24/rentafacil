<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Rental;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $endpointSecret = config('services.stripe.webhook_secret', '');

        try {
            if ($endpointSecret) {
                $event = Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
            } else {
                $event = json_decode($payload);
            }
        } catch (\Exception $e) {
            return response('Webhook error', 400);
        }

        $type = is_object($event) ? ($event->type ?? null) : null;

        if ($type === 'invoice.payment_succeeded') {
            $this->handlePaymentSucceeded($event->data->object);
        }

        if ($type === 'checkout.session.completed') {
            $session = $event->data->object;
            $metaType = $session->metadata->type ?? null;

            if ($metaType === 'one_time_payment') {
                $this->handleOneTimePayment($session);
            } elseif ($metaType === 'plan_purchase') {
                $this->handlePlanPurchase($session);
            }
        }

        return response('OK', 200);
    }

    protected function handlePaymentSucceeded($invoice): void
    {
        $metadata = $invoice->subscription_details?->metadata ?? $invoice->metadata ?? null;

        if (!$metadata || !isset($metadata->rental_id)) {
            return;
        }

        $rental = Rental::with(['customer', 'washingMachine', 'company.settings'])
            ->find($metadata->rental_id);

        if (!$rental) {
            return;
        }

        $settings = $rental->company->settings;
        $days = $settings?->days_per_payment ?? 7;
        $amount = ($invoice->amount_paid ?? 0) / 100;

        // Extend the rental
        $newEndDate = Carbon::parse($rental->end_date)->addDays($days);
        $rental->update([
            'end_date' => $newEndDate,
            'status' => 'activa',
        ]);

        // Create payment record
        $rental->payments()->create([
            'company_id' => $rental->company_id,
            'amount' => $amount,
            'payment_date' => now(),
            'payment_method' => 'Stripe Subscription',
            'reference' => $invoice->id ?? null,
            'status' => 'completado',
        ]);

        // Send WhatsApp confirmation
        if ($rental->customer?->phone) {
            $whatsapp = app(WhatsAppService::class);
            $whatsapp->sendPaymentConfirmation(
                $rental->customer->phone,
                $rental->customer->name,
                $amount,
                $newEndDate->format('d/m/Y'),
            );
        }
    }

    protected function handleOneTimePayment($session): void
    {
        $rentalId = $session->metadata->rental_id ?? null;
        if (!$rentalId) return;

        $rental = Rental::with(['customer', 'washingMachine', 'company.settings'])->find($rentalId);
        if (!$rental) return;

        $settings = $rental->company->settings;
        $days = $settings?->days_per_payment ?? 7;
        $amount = ($session->amount_total ?? 0) / 100;

        $newEndDate = Carbon::parse($rental->end_date)->addDays($days);
        $rental->update([
            'end_date' => $newEndDate,
            'status' => 'activa',
        ]);

        $rental->payments()->create([
            'company_id' => $rental->company_id,
            'amount' => $amount,
            'payment_date' => now(),
            'payment_method' => 'Stripe Link',
            'reference' => $session->id ?? null,
            'status' => 'completado',
        ]);

        if ($rental->customer?->phone) {
            $whatsapp = app(WhatsAppService::class);
            $whatsapp->sendPaymentConfirmation(
                $rental->customer->phone,
                $rental->customer->name,
                $amount,
                $newEndDate->format('d/m/Y'),
            );
        }
    }

    protected function handlePlanPurchase($session): void
    {
        $companyId = $session->metadata->company_id ?? null;
        $packageId = $session->metadata->package_id ?? null;

        if (!$companyId || !$packageId) return;

        $company = Company::find($companyId);
        if (!$company) return;

        // Update or create the company package with 30 days
        $company->companyPackage()->updateOrCreate(
            ['company_id' => $company->id],
            [
                'package_id' => $packageId,
                'start_date' => now(),
                'end_date' => now()->addDays(30),
            ]
        );
    }
}
