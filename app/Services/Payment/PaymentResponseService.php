<?php

namespace App\Services\Payment;

use App\Models\PaymentResponse;
use App\Models\PaymentSession;
use App\Services\OrderService;
use Illuminate\Support\Facades\Log;

class PaymentResponseService
{
    protected OrderService $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    /**
     * Procesa una respuesta completa de MITEC
     */
    public function processPaymentResponse(
        array $parsedData,
        string $rawXml,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?\App\Models\PaymentSession $paymentSession = null
    ): PaymentResponse {

        Log::info('Procesando respuesta de MITEC', [
            'transaction_reference' => $parsedData['r3ds_reference'] ?? $parsedData['payment_folio'] ?? 'unknown',
            'response' => $parsedData['payment_response'] ?? 'unknown',
            'auth_code' => $parsedData['payment_auth'] ?? null,
            'payment_session_recibida' => $paymentSession ? $paymentSession->id : 'NO_RECIBIDA'
        ]);

        // Si no se pasó PaymentSession, intentar buscarla
        if (!$paymentSession) {
            $transactionReference = $parsedData['r3ds_reference'] ?? $parsedData['payment_folio'] ?? null;

            if ($transactionReference) {
                // Intentar buscar con diferentes formatos
                $paymentSession = PaymentSession::where('transaction_reference', $transactionReference)->first();

                if (!$paymentSession) {
                    // Intentar sin el sufijo si tiene guión bajo
                    $baseName = explode('_', $transactionReference)[0];
                    $paymentSession = PaymentSession::where('transaction_reference', 'LIKE', $baseName . '%')->first();
                }

                if (!$paymentSession) {
                    // Buscar cualquier sesión reciente (último recurso)
                    $paymentSession = PaymentSession::where('created_at', '>=', now()->subHours(2))
                        ->orderBy('created_at', 'desc')
                        ->first();
                }
            }
        }

        Log::info('PaymentSession encontrada', [
            'transaction_reference_buscado' => $parsedData['r3ds_reference'] ?? $parsedData['payment_folio'] ?? null,
            'payment_session_encontrada' => $paymentSession ? $paymentSession->id : 'NO_ENCONTRADA',
            'payment_session_reference' => $paymentSession ? $paymentSession->transaction_reference : 'N/A',
            'cart_id' => $paymentSession ? $paymentSession->cart_id : 'N/A',
            'user_id' => $paymentSession ? $paymentSession->user_id : 'N/A'
        ]);        // 2. Crear el registro de respuesta de pago
        $paymentResponse = PaymentResponse::createFromMitecResponse(
            $parsedData,
            $paymentSession,
            $rawXml,
            $ipAddress,
            $userAgent
        );

        Log::info('PaymentResponse creado', [
            'payment_response_id' => $paymentResponse->id,
            'status' => $paymentResponse->payment_status
        ]);

        // 3. Procesar según el estado del pago
        if ($paymentResponse->isApproved()) {
            $this->processApprovedPayment($paymentResponse, $paymentSession);
        } elseif ($paymentResponse->isError()) {
            $this->processFailedPayment($paymentResponse, $paymentSession);
        }

        return $paymentResponse;
    }

    /**
     * Procesa un pago aprobado
     */
    protected function processApprovedPayment(PaymentResponse $paymentResponse, ?PaymentSession $paymentSession): void
    {
        Log::info('Procesando pago aprobado', [
            'payment_response_id' => $paymentResponse->id,
            'auth_code' => $paymentResponse->auth_code,
            'cart_id_en_response' => $paymentResponse->cart_id,
            'cart_id_en_session' => $paymentSession?->cart_id
        ]);

        // Usar cart_id del PaymentResponse si no hay session
        $cartId = $paymentResponse->cart_id ?? $paymentSession?->cart_id;

        if (!$cartId) {
            Log::error('No se puede crear orden: cart_id faltante', [
                'payment_response_id' => $paymentResponse->id,
                'payment_session_id' => $paymentSession?->id
            ]);
            return;
        }

        // Buscar el carrito
        $cart = \App\Models\Cart::find($cartId);
        if (!$cart) {
            Log::error('Carrito no encontrado', [
                'cart_id' => $cartId
            ]);
            return;
        }

        // Verificar que el carrito tenga items
        if ($cart->items()->count() === 0) {
            Log::error('Carrito vacío, no se puede crear orden', [
                'cart_id' => $cart->id
            ]);
            return;
        }

        try {
            // Crear la orden desde el carrito
            $order = $this->orderService->createOrderFromCart($cart, $paymentResponse, $paymentSession);

            // Actualizar PaymentResponse con order_id
            $paymentResponse->update(['order_id' => $order->id]);

            Log::info('Orden creada exitosamente desde pago aprobado', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'payment_response_id' => $paymentResponse->id,
                'payment_response_updated' => true,
                'billing_information_id' => $order->billing_information_id,
                'microsoft_account_id' => $order->microsoft_account_id
            ]);

        } catch (\Exception $e) {
            Log::error('Error creando orden desde carrito', [
                'cart_id' => $cart->id,
                'payment_response_id' => $paymentResponse->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }    /**
     * Procesa un pago fallido
     */
    protected function processFailedPayment(PaymentResponse $paymentResponse, ?PaymentSession $paymentSession): void
    {
        Log::info('Procesando pago fallido', [
            'payment_response_id' => $paymentResponse->id,
            'error_code' => $paymentResponse->cd_error,
            'error_message' => $paymentResponse->nb_error
        ]);

        // Si existe una orden asociada (aunque no debería en este punto), cancelarla
        if ($paymentResponse->order_id) {
            $order = \App\Models\Order::find($paymentResponse->order_id);
            if ($order) {
                $this->orderService->cancelOrderForFailedPayment(
                    $order,
                    "Payment failed: {$paymentResponse->cd_error} - {$paymentResponse->nb_error}"
                );
            }
        }

        // El carrito se mantiene activo para que el usuario pueda intentar de nuevo
        if ($paymentSession && $paymentSession->cart_id) {
            $cart = \App\Models\Cart::find($paymentSession->cart_id);
            if ($cart && $cart->status === 'completed') {
                // Reactivar el carrito si se había marcado como completado prematuramente
                $cart->update([
                    'status' => 'active',
                    'expires_at' => now()->addDays(7) // Extender expiración
                ]);

                Log::info('Carrito reactivado después de pago fallido', [
                    'cart_id' => $cart->id
                ]);
            }
        }
    }

    /**
     * Parsea la respuesta XML de MITEC usando la función existente de response.php
     */
    public function parseXmlResponse(string $xmlString): array
    {
        // Reusar la función parseXmlResponse del response.php existente
        libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_string($xmlString);

            if (!$xml) {
                $errors = libxml_get_errors();
                $errorMessage = 'XML parsing failed';
                if (!empty($errors)) {
                    $errorMessage .= ': ' . $errors[0]->message;
                }
                throw new \Exception($errorMessage);
            }

            return [
                'r3ds_reference' => (string)($xml->r3ds_reference ?? ''),
                'r3ds_dsTransId' => (string)($xml->r3ds_dsTransId ?? ''),
                'r3ds_eci' => (string)($xml->r3ds_eci ?? ''),
                'r3ds_cavv' => (string)($xml->r3ds_cavv ?? ''),
                'r3ds_transStatus' => (string)($xml->r3ds_transStatus ?? ''),
                'r3ds_responseCode' => (string)($xml->r3ds_responseCode ?? ''),
                'r3ds_responseDescription' => (string)($xml->r3ds_responseDescription ?? ''),
                'payment_folio' => (string)($xml->CENTEROFPAYMENTS->reference ?? ''),
                'payment_response' => (string)($xml->CENTEROFPAYMENTS->response ?? ''),
                'payment_auth' => (string)($xml->CENTEROFPAYMENTS->auth ?? ''),
                'cd_response' => (string)($xml->CENTEROFPAYMENTS->cd_response ?? ''),
                'cd_error' => (string)($xml->CENTEROFPAYMENTS->cd_error ?? ''),
                'nb_error' => (string)($xml->CENTEROFPAYMENTS->nb_error ?? ''),
                'time' => (string)($xml->CENTEROFPAYMENTS->time ?? ''),
                'date' => (string)($xml->CENTEROFPAYMENTS->date ?? ''),
                'voucher' => (string)($xml->CENTEROFPAYMENTS->voucher ?? ''),
                'voucher_comercio' => (string)($xml->CENTEROFPAYMENTS->voucher_comercio ?? ''),
                'voucher_cliente' => (string)($xml->CENTEROFPAYMENTS->voucher_cliente ?? ''),
                'cc_name' => (string)($xml->r3ds_cc_name ?? ''),
                'cc_number' => (string)($xml->r3ds_cc_number ?? ''),
                'cc_type' => (string)($xml->CENTEROFPAYMENTS->cc_type ?? ''),
                'amount' => (string)($xml->CENTEROFPAYMENTS->amount ?? ''),
                'branch' => (string)($xml->r3ds_idBranch ?? ''),
                'auth_bancaria' => (string)($xml->r3ds_autorizacion_bancaria ?? ''),
                'auth_full' => (string)($xml->r3ds_auth_full ?? ''),
                'protocolo' => (string)($xml->r3ds_protocolo ?? ''),
                'version' => (string)($xml->r3ds_version ?? ''),
                'friendly_response' => (string)($xml->CENTEROFPAYMENTS->friendly_response ?? ''),
            ];

        } finally {
            libxml_use_internal_errors(false);
            libxml_clear_errors();
        }
    }

    /**
     * Determina si un pago fue aprobado basándose en los datos parseados
     */
    public function isPaymentApproved(array $parsedData): bool
    {
        $response = strtolower($parsedData['payment_response'] ?? '');
        return $response === 'approved' || $response === 'aprobada';
    }
}
