<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class WhatsAppNotificationService
{
    private $graphToken;
    private $phoneId;
    private $verifyToken;

    public function __construct()
    {
        $this->graphToken = env('WHATSAPP_GRAPH_TOKEN');
        $this->phoneId = env('WHATSAPP_PHONE_ID');
        $this->verifyToken = env('WHATSAPP_VERIFY_TOKEN');
    }

    /**
     * Send WhatsApp message for Microsoft Partner Center errors
     */
    public function sendMicrosoftErrorNotification($order, string $errorMessage, array $errorDetails = [], array $microsoftErrorDetails = []): void
    {
        try {
            $phoneNumbers = env('WHATSAPP_NOTIFICATION_NUMBER');

            if (!$phoneNumbers || !$this->graphToken || !$this->phoneId) {
                Log::warning('WhatsApp configuration incomplete');
                return;
            }

            // Convert comma-separated numbers to array
            $phoneList = array_map('trim', explode(',', $phoneNumbers));

            // Format message for WhatsApp
            $message = $this->formatMicrosoftErrorMessage($order, $errorMessage, $errorDetails, $microsoftErrorDetails);

            // Send to each phone number
            foreach ($phoneList as $phoneNumber) {
                if (!empty($phoneNumber)) {
                    try {
                        $this->sendMessage($phoneNumber, $message);
                        Log::info("WhatsApp notification sent to {$phoneNumber} for Microsoft error - Order: {$order->order_number}");
                    } catch (Exception $sendException) {
                        Log::error("Failed to send WhatsApp to {$phoneNumber}: " . $sendException->getMessage());
                        // Continue with next number even if one fails
                    }
                }
            }

        } catch (Exception $e) {
            Log::error("Failed to send WhatsApp notification: " . $e->getMessage());
        }
    }

    /**
     * Send a WhatsApp message using template
     */
    private function sendMessage(string $phoneNumber, string $message): void
    {
        $url = "https://graph.facebook.com/v18.0/{$this->phoneId}/messages";

        // Try using the approved template first
        $templatePayload = [
            'messaging_product' => 'whatsapp',
            'to' => $phoneNumber,
            'type' => 'template',
            'template' => [
                'name' => 'info_ai_transcripcion',
                'language' => [
                    'code' => 'es'
                ],
                'components' => [
                    [
                        'type' => 'header',
                        'parameters' => [
                            [
                                'type' => 'image',
                                'image' => [
                                    'link' => 'https://readymind.mx/flyer_rs.png'
                                ]
                            ]
                        ]
                    ],
                    [
                        'type' => 'body',
                        'parameters' => [
                            [
                                'type' => 'text',
                                'text' => $message
                            ]
                        ]
                    ]
                ]
            ]
        ];

        Log::info("WhatsApp template message attempt", [
            'phone' => $phoneNumber,
            'template' => 'info_ai_transcripcion',
            'message_length' => strlen($message)
        ]);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->graphToken,
            'Content-Type' => 'application/json'
        ])->post($url, $templatePayload);

        Log::info("Template response", [
            'phone' => $phoneNumber,
            'status' => $response->status(),
            'body' => $response->body()
        ]);

        // If template fails, fallback to regular text message
        if (!$response->successful()) {
            Log::warning("Template message failed, trying regular text", [
                'phone' => $phoneNumber,
                'template_error' => $response->body()
            ]);

            // Format message for text fallback to mimic template
            $formattedMessage = "Has recibido un correo con el siguiente mensaje:\n\n" . $message;

            $textPayload = [
                'messaging_product' => 'whatsapp',
                'to' => $phoneNumber,
                'type' => 'text',
                'text' => [
                    'body' => $formattedMessage
                ]
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->graphToken,
                'Content-Type' => 'application/json'
            ])->post($url, $textPayload);

            Log::info("Text fallback response", [
                'phone' => $phoneNumber,
                'status' => $response->status(),
                'used_format' => 'text_with_template_format'
            ]);
        } else {
            Log::info("Template message successful", [
                'phone' => $phoneNumber,
                'used_format' => 'approved_template'
            ]);
        }        if (!$response->successful()) {
            throw new Exception('WhatsApp API error: ' . $response->body());
        }

        Log::info("WhatsApp message sent successfully", [
            'phone' => $phoneNumber,
            'response' => $response->json()
        ]);
    }

    /**
     * Format the Microsoft error message for WhatsApp
     */
    private function formatMicrosoftErrorMessage($order, string $errorMessage, array $errorDetails, array $microsoftErrorDetails): string
    {
        $message = "🚨 *NO SE PROCESO PEDIDO EN READYMARKET* 🚨\n\n";

        $message .= "📋 *ORDEN:* {$order->order_number}\n";
        $message .= "💰 *TOTAL:* $" . number_format($order->total_amount, 2) . "\n";

        if ($order->user) {
            $message .= "👤 *CLIENTE:* {$order->user->name}\n";
            $message .= "📧 *EMAIL:* {$order->user->email}\n";
            if ($order->user->phone) {
                $message .= "📞 *TELÉFONO:* {$order->user->phone}\n";
            }
        }

        if ($order->microsoftAccount) {
            $message .= "🔑 *Microsoft ID:* {$order->microsoftAccount->microsoft_id}\n";
            $message .= "🌐 *DOMINIO:* {$order->microsoftAccount->domain_concatenated}\n";
        }

        $message .= "\n❌ *ERROR DE MICROSOFT:*\n";

        if (isset($microsoftErrorDetails['error_code'])) {
            $message .= "📄 *Código:* {$microsoftErrorDetails['error_code']}\n";
        }

        if (isset($microsoftErrorDetails['description'])) {
            $message .= "📝 *Descripción:* {$microsoftErrorDetails['description']}\n";
        }

        if (isset($microsoftErrorDetails['http_status'])) {
            $message .= "🌐 *HTTP Status:* {$microsoftErrorDetails['http_status']}\n";
        }

        $message .= "\n🛒 *PRODUCTOS:*\n";
        if ($order->cartItems && $order->cartItems->count() > 0) {
            foreach ($order->cartItems as $index => $item) {
                $productName = $item->product->ProductTitle ?? $item->product->SkuTitle ?? 'Producto no disponible';
                $message .= ($index + 1) . ". {$productName} (x{$item->quantity})\n";
            }
        } else {
            $message .= "Sin productos disponibles\n";
        }

        $message .= "\n⏰ *Fecha:* " . now()->format('d/m/Y H:i:s');
        $message .= "\n\n🔧 *Acción requerida:* Revisar Microsoft Partner Center";

        return $message;
    }

    /**
     * Test WhatsApp connection
     */
    public function testConnection(): bool
    {
        try {
            $phoneNumbers = env('WHATSAPP_NOTIFICATION_NUMBER');
            $phoneList = array_map('trim', explode(',', $phoneNumbers));

            $testMessage = "🧪 Test de conexión WhatsApp - Readymarket\n\nFecha: " . now()->format('d/m/Y H:i:s');

            foreach ($phoneList as $phoneNumber) {
                if (!empty($phoneNumber)) {
                    $this->sendMessage($phoneNumber, $testMessage);
                    Log::info("WhatsApp test message sent to {$phoneNumber}");
                }
            }

            return true;

        } catch (Exception $e) {
            Log::error("WhatsApp test failed: " . $e->getMessage());
            return false;
        }
    }
}
