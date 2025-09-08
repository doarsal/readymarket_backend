<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Models\Order;
use App\Services\WhatsAppNotificationService;

class InvoiceErrorNotificationService
{
    /**
     * Send error notification when invoice generation fails
     */
    public function sendInvoiceErrorNotification(Order $order, string $errorMessage, array $errorDetails = [], array $receiverData = []): void
    {
        try {
            $recipientEmails = env('MICROSOFT_ERROR_NOTIFICATION_EMAIL', 'salvador.rodriguez@readymind.ms');

            // Convert comma-separated emails to array
            $emailList = array_map('trim', explode(',', $recipientEmails));

            // Load necessary relationships
            $order->load(['user', 'items.product']);

            // Send email notification to each recipient
            foreach ($emailList as $email) {
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    Mail::send('emails.invoice-error', [
                        'order' => $order,
                        'errorMessage' => $errorMessage,
                        'errorDetails' => $errorDetails,
                        'receiverData' => $receiverData,
                        'timestamp' => now()->format('Y-m-d H:i:s')
                    ], function ($message) use ($email, $order) {
                        $message->to($email)
                               ->subject('🧾🚨 Error en Facturación - Orden ' . $order->order_number);
                    });

                    Log::info("Invoice error notification email sent to {$email} for order {$order->id}");
                }
            }

            // Send WhatsApp notification
            $whatsappService = new WhatsAppNotificationService();
            $whatsappService->sendInvoiceErrorNotification($order, $errorMessage, $errorDetails, $receiverData);

        } catch (\Exception $e) {
            Log::error("Failed to send invoice error notification: " . $e->getMessage());
        }
    }
}
