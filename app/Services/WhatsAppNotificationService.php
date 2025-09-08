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
     * Send WhatsApp message for Microsoft Partner Center errors with detailed product information
     */
    public function sendMicrosoftErrorNotificationWithProducts($order, string $errorMessage, array $errorDetails = [], array $productResults = []): void
    {
        try {
            $phoneNumbers = env('WHATSAPP_NOTIFICATION_NUMBER');

            if (!$phoneNumbers || !$this->graphToken || !$this->phoneId) {
                Log::warning('WhatsApp configuration incomplete');
                return;
            }

            // Convert comma-separated numbers to array
            $phoneList = array_map('trim', explode(',', $phoneNumbers));

            // Format message for WhatsApp with product details
            $message = $this->formatMicrosoftErrorMessageWithProducts($order, $errorMessage, $errorDetails, $productResults);

            // Send to each phone number
            foreach ($phoneList as $phoneNumber) {
                if (!empty($phoneNumber)) {
                    try {
                        $this->sendMessage($phoneNumber, $message);
                        Log::info("Detailed WhatsApp notification sent to {$phoneNumber} for Microsoft error - Order: {$order->order_number}");
                    } catch (Exception $sendException) {
                        Log::error("Failed to send WhatsApp to {$phoneNumber}: " . $sendException->getMessage());
                        // Continue with next number even if one fails
                    }
                }
            }

        } catch (Exception $e) {
            Log::error("Failed to send detailed WhatsApp notification: " . $e->getMessage());
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
     * Send WhatsApp message for Microsoft account creation errors
     */
    public function sendMicrosoftAccountCreationErrorNotification($microsoftAccount, string $errorMessage, array $errorDetails = [], array $microsoftErrorDetails = []): void
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
            $message = $this->formatMicrosoftAccountCreationErrorMessage($microsoftAccount, $errorMessage, $errorDetails, $microsoftErrorDetails);

            // Send to each phone number
            foreach ($phoneList as $phoneNumber) {
                if (!empty($phoneNumber)) {
                    try {
                        $this->sendMessage($phoneNumber, $message);
                        Log::info("WhatsApp notification sent to {$phoneNumber} for Microsoft account creation error - Account: {$microsoftAccount->id}");
                    } catch (Exception $sendException) {
                        Log::error("Failed to send WhatsApp to {$phoneNumber}: " . $sendException->getMessage());
                        // Continue with next number even if one fails
                    }
                }
            }

        } catch (Exception $e) {
            Log::error("Failed to send WhatsApp account creation notification: " . $e->getMessage());
        }
    }

    /**
     * Format the Microsoft account creation error message for WhatsApp
     */
    private function formatMicrosoftAccountCreationErrorMessage($microsoftAccount, string $errorMessage, array $errorDetails, array $microsoftErrorDetails): string
    {
        $message = "🚨 *ERROR EN CREACIÓN DE CUENTA MICROSOFT* 🚨\n\n";

        // Información básica
        $message .= "� *ID LOCAL:* {$microsoftAccount->id}\n";
        $message .= "👤 *USER ID:* {$microsoftAccount->user_id}\n";
        $message .= "🔑 *MICROSOFT ID:* " . ($microsoftAccount->microsoft_id ?: 'N/A - Error en creación') . "\n\n";

        // Información de dominio
        $message .= "🌐 *DOMINIO BASE:* {$microsoftAccount->domain}\n";
        $message .= "� *DOMINIO COMPLETO:* {$microsoftAccount->domain_concatenated}\n\n";

        // Información personal
        $message .= "👤 *NOMBRE:* {$microsoftAccount->first_name}\n";
        $message .= "👤 *APELLIDO:* {$microsoftAccount->last_name}\n";
        $message .= "📧 *EMAIL:* {$microsoftAccount->email}\n";
        $message .= "📞 *TELÉFONO:* " . ($microsoftAccount->phone ?: 'N/A') . "\n\n";

        // Información de organización
        $message .= "🏢 *ORGANIZACIÓN:* {$microsoftAccount->organization}\n";
        $message .= "📍 *DIRECCIÓN:* " . ($microsoftAccount->address ?: 'N/A') . "\n";
        $message .= "🏙️ *CIUDAD:* " . ($microsoftAccount->city ?: 'N/A') . "\n";
        $message .= "🗺️ *ESTADO (CÓDIGO):* " . ($microsoftAccount->state_code ?: 'N/A') . "\n";
        $message .= "🗺️ *ESTADO (NOMBRE):* " . ($microsoftAccount->state_name ?: 'N/A') . "\n";
        $message .= "� *CÓDIGO POSTAL:* " . ($microsoftAccount->postal_code ?: 'N/A') . "\n";
        $message .= "🌎 *PAÍS (CÓDIGO):* {$microsoftAccount->country_code}\n";
        $message .= "🌎 *PAÍS (NOMBRE):* " . ($microsoftAccount->country_name ?: 'N/A') . "\n\n";

        // Información de localización
        $message .= "🗣️ *IDIOMA:* {$microsoftAccount->language_code}\n";
        $message .= "🎭 *CULTURA:* {$microsoftAccount->culture}\n\n";

        // Fechas
        $message .= "📅 *CREADO:* " . ($microsoftAccount->created_at ? $microsoftAccount->created_at->format('d/m/Y H:i:s') : 'N/A') . "\n";
        $message .= "📅 *ACTUALIZADO:* " . ($microsoftAccount->updated_at ? $microsoftAccount->updated_at->format('d/m/Y H:i:s') : 'N/A') . "\n\n";

        // Error de Microsoft
        $message .= "❌ *ERROR DE MICROSOFT:*\n";

        if (isset($microsoftErrorDetails['error_code'])) {
            $message .= "📄 *Código:* {$microsoftErrorDetails['error_code']}\n";
        }

        if (isset($microsoftErrorDetails['description'])) {
            $message .= "📝 *Descripción:* {$microsoftErrorDetails['description']}\n";
        }

        if (isset($microsoftErrorDetails['http_status'])) {
            $message .= "🌐 *HTTP Status:* {$microsoftErrorDetails['http_status']}\n";
        }

        if (isset($errorDetails['details'])) {
            $message .= "ℹ️ *Detalles:* {$errorDetails['details']}\n";
        }

        $message .= "\n⏰ *Fecha del Error:* " . now()->format('d/m/Y H:i:s');
        $message .= "\n\n🔧 *Acción requerida:* Revisar creación de cuenta en Microsoft Partner Center";

        return $message;
    }

    /**
     * Send WhatsApp message for new user registration
     */
    public function sendUserRegistrationNotification($user): void
    {
        try {
            $phoneNumbers = env('WHATSAPP_NOTIFICATION_NUMBER');

            if (!$phoneNumbers || !$this->graphToken || !$this->phoneId) {
                Log::warning('WhatsApp configuration incomplete for user registration notification');
                return;
            }

            // Convert comma-separated numbers to array
            $phoneList = array_map('trim', explode(',', $phoneNumbers));

            // Format message for WhatsApp
            $message = $this->formatUserRegistrationMessage($user);

            // Send to each phone number
            foreach ($phoneList as $phoneNumber) {
                if (!empty($phoneNumber)) {
                    try {
                        $this->sendMessage($phoneNumber, $message);
                        Log::info("WhatsApp user registration notification sent to {$phoneNumber} for user: {$user->id}");
                    } catch (Exception $sendException) {
                        Log::error("Failed to send WhatsApp user registration notification to {$phoneNumber}: " . $sendException->getMessage());
                        // Continue with next number even if one fails
                    }
                }
            }

        } catch (Exception $e) {
            Log::error("Failed to send WhatsApp user registration notification: " . $e->getMessage());
        }
    }

    /**
     * Format the user registration message for WhatsApp
     */
    private function formatUserRegistrationMessage($user): string
    {
        $message = "🎉 *NUEVO USUARIO REGISTRADO EN READYMARKET* 🎉\n\n";

        // Información básica del usuario
        $message .= "👤 *NOMBRE:* {$user->name}\n";
        $message .= "📧 *EMAIL:* {$user->email}\n";

        if ($user->phone) {
            $message .= "📞 *TELÉFONO:* {$user->phone}\n";
        }

        // Información adicional si está disponible
        if ($user->company_name) {
            $message .= "🏢 *EMPRESA:* {$user->company_name}\n";
        }

        if ($user->position) {
            $message .= "💼 *CARGO:* {$user->position}\n";
        }

        // Ubicación si está disponible
        if ($user->city || $user->state || $user->country) {
            $message .= "\n📍 *UBICACIÓN:*\n";

            if ($user->city) {
                $message .= "🏙️ Ciudad: {$user->city}\n";
            }

            if ($user->state) {
                $message .= "🗺️ Estado: {$user->state}\n";
            }

            if ($user->country) {
                $message .= "🌎 País: {$user->country}\n";
            }
        }

        // Estado del usuario
        $message .= "\n✅ *ESTADO:* " . ($user->email_verified_at ? 'Email verificado' : 'Pendiente de verificar email') . "\n";

        // Fechas
        $message .= "\n📅 *FECHA DE REGISTRO:* " . $user->created_at->format('d/m/Y H:i:s') . "\n";

        $message .= "\n🎯 *Acción sugerida:* Revisar nuevo usuario en el sistema administrativo";

        return $message;
    }

    /**
     * Send OTP verification code via WhatsApp
     */
    public function sendOTPVerification(string $phoneNumber, string $otpCode, string $userName = null): bool
    {
        try {
            if (!$this->graphToken || !$this->phoneId) {
                Log::warning('WhatsApp configuration incomplete for OTP verification');
                return false;
            }

            // Format message for OTP verification
            $message = $this->formatOTPMessage($otpCode, $userName);

            // Remove any country code duplicates and format phone
            $formattedPhone = $this->formatPhoneForWhatsApp($phoneNumber);

            // Send message using the standard sendMessage method
            $this->sendMessage($formattedPhone, $message);

            Log::info("WhatsApp OTP verification sent", [
                'phone' => $formattedPhone,
                'user_name' => $userName,
                'otp_length' => strlen($otpCode)
            ]);

            return true;

        } catch (Exception $e) {
            Log::error("Failed to send WhatsApp OTP verification", [
                'phone' => $phoneNumber,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Format the OTP verification message for WhatsApp
     */
    private function formatOTPMessage(string $otpCode, string $userName = null): string
    {
        $greeting = $userName ? "Hola {$userName}," : "Hola,";

        $message = "🔐 *CÓDIGO DE VERIFICACIÓN - READYMARKET* 🔐\n\n";
        $message .= "{$greeting}\n\n";
        $message .= "Tu código de verificación es:\n\n";
        $message .= "🔢 *{$otpCode}*\n\n";
        $message .= "⏰ Este código expira en 10 minutos.\n\n";
        $message .= "🔒 Por tu seguridad, no compartas este código con nadie.\n\n";
        $message .= "Si no solicitaste esta verificación, puedes ignorar este mensaje.\n\n";
        $message .= "¡Gracias por elegir ReadyMarket! 🛒";

        return $message;
    }

    /**
     * Format phone number for WhatsApp API
     */
    private function formatPhoneForWhatsApp(string $phoneNumber): string
    {
        // Remove any non-digit characters
        $cleanPhone = preg_replace('/\D/', '', $phoneNumber);

        // If phone starts with +52, remove the +
        if (substr($cleanPhone, 0, 3) === '525') {
            // Likely already has 52 prefix
            return $cleanPhone;
        }

        // If phone starts with 52, keep as is
        if (substr($cleanPhone, 0, 2) === '52') {
            return $cleanPhone;
        }

        // If phone is 10 digits (Mexican format without country code), add 52
        if (strlen($cleanPhone) === 10) {
            return '52' . $cleanPhone;
        }

        // Return as is for other formats
        return $cleanPhone;
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

    /**
     * Format Microsoft error message with detailed product information
     */
    private function formatMicrosoftErrorMessageWithProducts($order, string $errorMessage, array $errorDetails, array $productResults): string
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

        $message .= "\n📦 *PRODUCTOS Y ERRORES:*\n";

        if (!empty($productResults)) {
            foreach ($productResults as $index => $result) {
                $status = $result['success'] ? '✅' : '❌';
                $message .= ($index + 1) . ". {$status} {$result['product_title']} (x{$result['quantity']})\n";

                if (!$result['success'] && !empty($result['error_message'])) {
                    $message .= "   💡 Error: {$result['error_message']}\n";
                }

                if (!empty($result['microsoft_details'])) {
                    $details = $result['microsoft_details'];
                    if (isset($details['error_code'])) {
                        $message .= "   📄 Código: {$details['error_code']}\n";
                    }
                    if (isset($details['http_status'])) {
                        $message .= "   🌐 HTTP: {$details['http_status']}\n";
                    }
                }
                $message .= "\n";
            }
        } else {
            // Fallback to cart items if no detailed results
            if ($order->cartItems && $order->cartItems->count() > 0) {
                foreach ($order->cartItems as $index => $item) {
                    $productName = $item->product->ProductTitle ?? $item->product->SkuTitle ?? 'Producto no disponible';
                    $message .= ($index + 1) . ". ❌ {$productName} (x{$item->quantity})\n";
                }
            }
        }

        $message .= "\n📊 *RESUMEN:*\n";
        if (isset($errorDetails['Total Products'])) {
            $message .= "Total: {$errorDetails['Total Products']}\n";
            $message .= "✅ Exitosos: {$errorDetails['Successful Products']}\n";
            $message .= "❌ Fallidos: {$errorDetails['Failed Products']}\n";
        }

        $message .= "\n⏰ *Fecha:* " . now()->format('d/m/Y H:i:s');
        $message .= "\n\n🔧 *Acción requerida:* Revisar productos fallidos en el sistema";

        return $message;
    }

    /**
     * Send WhatsApp message for invoice generation errors
     */
    public function sendInvoiceErrorNotification($order, string $errorMessage, array $errorDetails = [], array $receiverData = []): void
    {
        try {
            $phoneNumbers = env('WHATSAPP_NOTIFICATION_NUMBER');

            if (!$phoneNumbers || !$this->graphToken || !$this->phoneId) {
                Log::warning('WhatsApp configuration incomplete for invoice error');
                return;
            }

            // Convert comma-separated numbers to array
            $phoneList = array_map('trim', explode(',', $phoneNumbers));

            // Format message for WhatsApp
            $message = $this->formatInvoiceErrorMessage($order, $errorMessage, $errorDetails, $receiverData);

            // Send to each phone number
            foreach ($phoneList as $phoneNumber) {
                if (!empty($phoneNumber)) {
                    try {
                        $this->sendMessage($phoneNumber, $message);
                        Log::info("WhatsApp invoice error notification sent to {$phoneNumber} for Order: {$order->order_number}");
                    } catch (Exception $sendException) {
                        Log::error("Failed to send WhatsApp invoice error to {$phoneNumber}: " . $sendException->getMessage());
                        // Continue with next number even if one fails
                    }
                }
            }

        } catch (Exception $e) {
            Log::error('Error sending WhatsApp invoice error notification: ' . $e->getMessage());
        }
    }

    /**
     * Format invoice error message for WhatsApp
     */
    private function formatInvoiceErrorMessage($order, string $errorMessage, array $errorDetails = [], array $receiverData = []): string
    {
        $message = "🧾🚨 *ERROR EN FACTURACIÓN*\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

        $message .= "📋 *Orden:* {$order->order_number}\n";
        $message .= "💰 *Total:* $" . number_format($order->total_amount, 2) . " MXN\n";
        $message .= "💳 *Estado Pago:* {$order->payment_status}\n";

        if ($order->user) {
            $message .= "\n👤 *Cliente:*\n";
            $message .= "• Nombre: {$order->user->name}\n";
            $message .= "• Email: {$order->user->email}\n";
            if ($order->user->phone) {
                $message .= "• Teléfono: {$order->user->phone}\n";
            }
        }

        if (!empty($receiverData)) {
            $message .= "\n🧾 *Datos de Facturación:*\n";
            $message .= "• RFC: " . ($receiverData['rfc'] ?? 'N/A') . "\n";
            $message .= "• Razón Social: " . ($receiverData['name'] ?? 'N/A') . "\n";
            $message .= "• CP: " . ($receiverData['postal_code'] ?? 'N/A') . "\n";
        }

        $message .= "\n🚨 *Error:*\n";
        $message .= $errorMessage . "\n";

        if (!empty($errorDetails)) {
            $message .= "\n🔧 *Detalles Técnicos:*\n";
            $message .= "```" . json_encode($errorDetails, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "```\n";
        }

        $message .= "\n🛒 *Productos:*\n";
        foreach ($order->items as $index => $item) {
            $productName = $item->product_name ?? $item->product->ProductTitle ?? 'Producto sin nombre';
            $message .= "• {$productName} (x{$item->quantity}) - $" . number_format($item->line_total, 2) . "\n";
        }

        $message .= "\n⏰ *Fecha:* " . now()->format('d/m/Y H:i:s');
        $message .= "\n\n🔧 *Acción requerida:* Revisar el sistema de facturación";

        return $message;
    }
}
