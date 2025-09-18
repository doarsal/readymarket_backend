<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Models\User;

class PasswordResetEmailService
{
    /**
     * Send password reset email to user
     */
    public function sendPasswordResetEmail(User $user, string $token): bool
    {
        try {
            $resetUrl = config('app.frontend_url') . '/reset-password?token=' . $token . '&email=' . urlencode($user->email);

            $emailData = [
                'user_name' => $user->first_name . ' ' . $user->last_name,
                'user_email' => $user->email,
                'reset_url' => $resetUrl,
                'token' => $token,
                'app_name' => config('app.name'),
            ];

            Mail::send('emails.password-reset', $emailData, function ($message) use ($user) {
                $message->to($user->email, $user->first_name . ' ' . $user->last_name)
                        ->subject('Restablecer Contraseña - ' . config('app.name'));
                $message->from(config('mail.from.address'), config('mail.from.name'));
            });

            Log::info('Password reset email sent successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'token_length' => strlen($token)
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send password reset email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return false;
        }
    }
}
