<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Services\WhatsAppNotificationService;

class UserRegistrationNotificationService
{
    /**
     * Send notification email and WhatsApp when a new user registers
     */
    public function sendNewUserRegistrationNotification(User $user): void
    {
        // Verificar si las notificaciones de registro están habilitadas
        if (!env('SEND_USER_REGISTRATION_NOTIFICATIONS', false)) {
            Log::info('User registration notifications are disabled', ['user_id' => $user->id]);
            return;
        }

        try {
            $recipientEmails = env('USER_REGISTRATION_NOTIFICATION_EMAIL', 'salvador.rodriguez@readymind.ms');

            // Convert comma-separated emails to array
            $emailList = array_map('trim', explode(',', $recipientEmails));

            // Send email notification to each recipient
            foreach ($emailList as $email) {
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    Mail::send('emails.user-registration', [
                        'user' => $user,
                        'timestamp' => now()->format('Y-m-d H:i:s')
                    ], function ($message) use ($email, $user) {
                        $message->to($email)
                               ->subject('🎉 Nuevo usuario registrado en Readymarket - ' . $user->name);
                    });

                    Log::info("User registration notification email sent to {$email} for user {$user->id}");
                }
            }

            // Send WhatsApp notification
            $whatsappService = new WhatsAppNotificationService();
            $whatsappService->sendUserRegistrationNotification($user);

        } catch (\Exception $e) {
            Log::error("Failed to send user registration notification: " . $e->getMessage(), [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
