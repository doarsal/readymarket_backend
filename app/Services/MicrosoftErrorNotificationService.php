<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Models\Order;
use App\Services\WhatsAppNotificationService;

class MicrosoftErrorNotificationService
{
    /**
     * Send error notification email and WhatsApp when Microsoft Partner Center fails
     */
    public function sendMicrosoftErrorNotification(Order $order, string $errorMessage, array $errorDetails = [], array $microsoftErrorDetails = []): void
    {
        try {
            $recipientEmails = env('MICROSOFT_ERROR_NOTIFICATION_EMAIL', 'salvador.rodriguez@readymind.ms');

            // Convert comma-separated emails to array
            $emailList = array_map('trim', explode(',', $recipientEmails));

            // Load necessary relationships
            $order->load(['user', 'microsoftAccount', 'cartItems.product']);

            // Send email notification to each recipient
            foreach ($emailList as $email) {
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    Mail::send('emails.microsoft-error', [
                        'order' => $order,
                        'errorMessage' => $errorMessage,
                        'errorDetails' => $errorDetails,
                        'microsoftErrorDetails' => $microsoftErrorDetails,
                        'timestamp' => now()->format('Y-m-d H:i:s')
                    ], function ($message) use ($email, $order) {
                        $message->to($email)
                               ->subject('🚨 No se procesó pedido en Readymarket - Orden ' . $order->order_number);
                    });

                    Log::info("Microsoft error notification email sent to {$email} for order {$order->id}");
                }
            }

            // Send WhatsApp notification
            $whatsappService = new WhatsAppNotificationService();
            $whatsappService->sendMicrosoftErrorNotification($order, $errorMessage, $errorDetails, $microsoftErrorDetails);

        } catch (\Exception $e) {
            Log::error("Failed to send Microsoft error notification: " . $e->getMessage());
        }
    }
}
