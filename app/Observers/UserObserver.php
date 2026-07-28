<?php

namespace App\Observers;

use App\Mail\WelcomeOnboardingMail;
use App\Models\ProspectiveClient;
use App\Models\User;
use App\Notifications\NewUserRegisteredNotification;
use Illuminate\Support\Facades\Mail;

class UserObserver
{
    public function created(User $user): void
    {
        // Los usuarios de demo son desechables: ni son prospectos convertidos,
        // ni deben disparar correos de bienvenida ni avisos a los admins.
        if ($user->is_demo) {
            return;
        }

        // Auto-detect if this user was a prospect and mark as converted
        $prospect = ProspectiveClient::where('email', $user->email)
            ->orWhere(function ($q) use ($user) {
                // Match by phone if no email match
                if ($user->phone) {
                    $q->where('phone', $user->phone);
                }
            })
            ->whereNull('converted_user_id')
            ->first();

        if ($prospect) {
            $prospect->update([
                'status' => 'cliente',
                'converted_user_id' => $user->id,
                'converted_at' => now(),
            ]);
        }

        // Notify super admins
        $admins = User::role('super_admin')->get();
        foreach ($admins as $admin) {
            $admin->notify(new NewUserRegisteredNotification($user));
        }

        // Send onboarding email to the new user
        try {
            Mail::to($user->email)->send(new WelcomeOnboardingMail($user));
        } catch (\Exception $e) {
            // Don't break registration if email fails
        }
    }
}
