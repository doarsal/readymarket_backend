<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PurchaseConfirmationEmailService
{
    /**
     * Send purchase confirmation email to customer
     */
    public function sendCustomerConfirmation($order, $microsoftAccount = null, $paymentData = null): bool
    {
        try {
            $customerEmail = $order->user->email;

            // Datos para la plantilla de correo del cliente
            $emailData = [
                'order' => $order,
                'customer' => $order->user,
                'microsoft_account' => $microsoftAccount,
                'billing_info' => $order->billingInformation,
                'items' => $order->items,
                'paymentData' => $paymentData, // Incluir datos de la transacción
                'is_customer_email' => true
            ];

            // Enviar correo al cliente
            Mail::send('emails.purchase-confirmation-customer', $emailData, function ($message) use ($customerEmail, $order) {
                $message->to($customerEmail)
                    ->subject("Confirmación de Compra - Pedido {$order->order_number}");
            });

            Log::info('Purchase confirmation email sent to customer', [
                'order_number' => $order->order_number,
                'customer_email' => $customerEmail,
                'payment_reference' => $paymentData['reference'] ?? null
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send purchase confirmation email to customer', [
                'order_number' => $order->order_number,
                'error' => $e->getMessage(),
                'payment_data' => $paymentData
            ]);
            return false;
        }
    }

    /**
     * Send purchase confirmation email to admin emails
     */
    public function sendAdminConfirmation($order, $microsoftAccount = null, $paymentData = null): bool
    {
        try {
            $adminEmails = env('PURCHASE_CONFIRMATION_NOTIFICATION_EMAIL');

            if (!$adminEmails) {
                Log::warning('No admin emails configured for purchase confirmation');
                return false;
            }

            // Convert comma-separated emails to array
            $emailList = array_map('trim', explode(',', $adminEmails));

            // Datos para la plantilla de correo de admin
            $emailData = [
                'order' => $order,
                'customer' => $order->user,
                'microsoft_account' => $microsoftAccount,
                'billing_info' => $order->billingInformation,
                'items' => $order->items,
                'paymentData' => $paymentData, // Incluir datos de la transacción
                'is_customer_email' => false
            ];

            // Enviar a cada email de admin
            foreach ($emailList as $adminEmail) {
                if (!empty($adminEmail)) {
                    try {
                        Mail::send('emails.purchase-confirmation-admin', $emailData, function ($message) use ($adminEmail, $order) {
                            $message->to($adminEmail)
                                ->subject("Nueva Compra - Pedido {$order->order_number}");
                        });

                        Log::info('Purchase confirmation email sent to admin', [
                            'order_number' => $order->order_number,
                            'admin_email' => $adminEmail,
                            'payment_reference' => $paymentData['reference'] ?? null
                        ]);
                    } catch (\Exception $sendException) {
                        Log::error('Failed to send purchase confirmation email to admin', [
                            'order_number' => $order->order_number,
                            'admin_email' => $adminEmail,
                            'error' => $sendException->getMessage(),
                            'payment_data' => $paymentData
                        ]);
                        // Continue with next email even if one fails
                    }
                }
            }

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send purchase confirmation emails to admins', [
                'order_number' => $order->order_number,
                'error' => $e->getMessage(),
                'payment_data' => $paymentData
            ]);
            return false;
        }
    }

    /**
     * Send both customer and admin confirmations
     */
    public function sendAllConfirmations($order, $microsoftAccount = null, $paymentData = null): bool
    {
        $customerSent = $this->sendCustomerConfirmation($order, $microsoftAccount, $paymentData);
        $adminSent = $this->sendAdminConfirmation($order, $microsoftAccount, $paymentData);

        return $customerSent && $adminSent;
    }
}
