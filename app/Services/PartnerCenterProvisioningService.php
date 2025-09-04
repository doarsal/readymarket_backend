<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\MicrosoftAccount;
use App\Models\Subscription;
use App\Services\MicrosoftErrorNotificationService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class PartnerCenterProvisioningService
{
    private $tokenEndpoint;
    private $partnerCenterApiUrl;
    private $microsoftErrorDetails = [];

    public function __construct()
    {
        $this->tokenEndpoint = config('services.microsoft.credentials_url', env('MICROSOFT_CREDENTIALS_URL'));
        $this->partnerCenterApiUrl = config('services.microsoft.partner_center_base_url', env('MICROSOFT_PARTNER_CENTER_BASE_URL'));
    }

    /**
     * Process order and create products in Partner Center
     */
    public function processOrder(int $orderId): array
    {
        try {
            // Create custom log file
            $logFile = storage_path('logs/partner_center_' . date('Y-m-d') . '.log');
            file_put_contents($logFile, "\n" . str_repeat("=", 80) . "\n", FILE_APPEND);
            file_put_contents($logFile, "[" . now() . "] Starting Partner Center provisioning for order ID: {$orderId}\n", FILE_APPEND);

            // Get order with relationships
            $order = Order::with([
                'cart.items.product',
                'microsoftAccount'
            ])->where('id', $orderId)
              ->where('status', 'processing')
              ->first();

            if (!$order) {
                $error = "Order not found or not in processing status: {$orderId}";
                file_put_contents($logFile, "[ERROR] {$error}\n", FILE_APPEND);
                throw new Exception($error);
            }

            file_put_contents($logFile, "[INFO] Order found: {$order->order_number}\n", FILE_APPEND);

            // Check if we're in fake mode
            if (env('MICROSOFT_FAKE_MODE', false)) {
                file_put_contents($logFile, "[INFO] FAKE MODE ENABLED - Simulating successful processing\n", FILE_APPEND);
                return $this->processFakeOrder($order, $logFile);
            }

            // Real processing continues here...

            file_put_contents($logFile, "[INFO] Order found: {$order->order_number}\n", FILE_APPEND);

            if (!$order->cart) {
                $error = "Cart not found for order: {$orderId}";
                file_put_contents($logFile, "[ERROR] {$error}\n", FILE_APPEND);
                throw new Exception($error);
            }

            if (!$order->microsoftAccount) {
                $error = "Microsoft account not found for order: {$orderId}";
                file_put_contents($logFile, "[ERROR] {$error}\n", FILE_APPEND);
                throw new Exception($error);
            }

            $microsoftCustomerId = $order->microsoftAccount->microsoft_id;
            if (!$microsoftCustomerId) {
                $error = "Microsoft customer ID not found for order: {$orderId}";
                file_put_contents($logFile, "[ERROR] {$error}\n", FILE_APPEND);
                throw new Exception($error);
            }

            file_put_contents($logFile, "[INFO] Microsoft customer ID: {$microsoftCustomerId}\n", FILE_APPEND);

            // Get cart items
            $cartItems = $order->cart->items()->with('product')->where('status', 'active')->get();
            if ($cartItems->isEmpty()) {
                $error = "No active cart items found for order: {$orderId}";
                file_put_contents($logFile, "[ERROR] {$error}\n", FILE_APPEND);
                throw new Exception($error);
            }

            file_put_contents($logFile, "[INFO] Processing {$cartItems->count()} cart items\n", FILE_APPEND);

            // Get Microsoft access token
            $token = $this->getMicrosoftToken($logFile);

            // Prepare cart items for Microsoft Partner Center
            $lineItems = $this->prepareLineItems($cartItems, $logFile);

            // Create cart in Microsoft Partner Center
            $cartResponse = $this->createMicrosoftCart($microsoftCustomerId, $lineItems, $token, $logFile);

            // Process checkout
            $checkoutResponse = $this->checkoutMicrosoftCart($microsoftCustomerId, $cartResponse['id'], $token, $logFile);

            // Handle Azure spending budget if needed
            $azureSpendingAmount = $this->calculateAzureSpending($cartItems);
            if ($azureSpendingAmount > 0) {
                $this->setAzureSpendingBudget($microsoftCustomerId, $azureSpendingAmount, $token, $logFile);
            }

            // Save subscription data
            $this->saveSubscriptions($order, $checkoutResponse, $cartItems, $logFile);

            // Update order status to completed
            $order->update(['status' => 'completed']);

            file_put_contents($logFile, "[SUCCESS] Partner Center provisioning completed successfully for order {$orderId}\n", FILE_APPEND);

            return [
                'success' => true,
                'message' => 'Products provisioned successfully in Partner Center',
                'order_id' => $orderId,
                'cart_id' => $cartResponse['id'],
                'subscriptions_count' => count($checkoutResponse['orders'][0]['lineItems'] ?? [])
            ];

        } catch (Exception $e) {
            if (isset($logFile)) {
                file_put_contents($logFile, "[ERROR] Partner Center provisioning failed for order {$orderId}: " . $e->getMessage() . "\n", FILE_APPEND);
                file_put_contents($logFile, "[ERROR] Stack trace: " . $e->getTraceAsString() . "\n", FILE_APPEND);
            }

            Log::error("Partner Center provisioning failed for order {$orderId}: " . $e->getMessage());

            // Send error notification email if not in fake mode
            if (!env('MICROSOFT_FAKE_MODE', false)) {
                $this->sendErrorNotification($order ?? null, $orderId, $e->getMessage(), $logFile ?? null);
            }

            // Don't update order status - keep as 'processing' so it can be retried
            if (isset($order) && $logFile) {
                file_put_contents($logFile, "[INFO] Order status kept as 'processing' for potential retry\n", FILE_APPEND);
            }

            // Construir mensaje de error detallado
            $errorMessage = $e->getMessage();
            $errorDetails = [];

            // Si tenemos detalles del error de Microsoft, incluirlos
            if (!empty($this->microsoftErrorDetails)) {
                $details = $this->microsoftErrorDetails;
                $errorMessage .= "\n\n=== DETALLES DEL ERROR DE MICROSOFT ===";

                if (!empty($details['raw_response'])) {
                    $errorMessage .= "\n📄 RESPUESTA COMPLETA: " . $details['raw_response'];
                }

                if (!empty($details['http_status'])) {
                    $errorMessage .= "\n🔢 CÓDIGO HTTP: " . $details['http_status'];
                }

                if (!empty($details['error_code'])) {
                    $errorMessage .= "\n❌ CÓDIGO ERROR: " . $details['error_code'];
                }

                if (!empty($details['description'])) {
                    $errorMessage .= "\n📝 DESCRIPCIÓN: " . $details['description'];
                }

                if (!empty($details['correlation_id'])) {
                    $errorMessage .= "\n🔗 CORRELATION ID: " . $details['correlation_id'];
                }

                if (!empty($details['request_id'])) {
                    $errorMessage .= "\n📨 REQUEST ID: " . $details['request_id'];
                }

                $errorDetails = $details;
            }

            return [
                'success' => false,
                'message' => $errorMessage,
                'error_type' => 'microsoft_partner_center',
                'microsoft_details' => $errorDetails,
                'order_id' => $orderId
            ];
        }
    }

    /**
     * Get Microsoft access token
     */
    private function getMicrosoftToken($logFile = null): string
    {
        try {
            if ($logFile) file_put_contents($logFile, "[INFO] Requesting Microsoft token from: " . $this->tokenEndpoint . "\n", FILE_APPEND);

            $response = Http::timeout(config('services.microsoft.token_timeout', env('MICROSOFT_API_TOKEN_TIMEOUT', 60)))->get($this->tokenEndpoint);

            if ($logFile) {
                file_put_contents($logFile, "[INFO] Microsoft token response status: " . $response->status() . "\n", FILE_APPEND);
                file_put_contents($logFile, "[INFO] Microsoft token response body: " . $response->body() . "\n", FILE_APPEND);
            }

            if (!$response->successful()) {
                // Capturar detalles del error de token
                $this->microsoftErrorDetails = [
                    'http_status' => $response->status(),
                    'raw_response' => $response->body(),
                    'endpoint' => $this->tokenEndpoint,
                    'error_type' => 'token_authentication'
                ];
                throw new Exception('Failed to get Microsoft token: HTTP ' . $response->status() . ' - Response: ' . $response->body());
            }

            $data = $response->json();

            if (!isset($data['item']['token'])) {
                if ($logFile) file_put_contents($logFile, "[ERROR] Invalid token response structure: " . json_encode($data) . "\n", FILE_APPEND);
                throw new Exception('Invalid token response from Microsoft');
            }

            if ($logFile) file_put_contents($logFile, "[SUCCESS] Microsoft token obtained successfully\n", FILE_APPEND);
            return $data['item']['token'];

        } catch (Exception $e) {
            if ($logFile) file_put_contents($logFile, "[ERROR] Failed to get Microsoft token: " . $e->getMessage() . "\n", FILE_APPEND);
            throw new Exception('Failed to authenticate with Microsoft Partner Center: ' . $e->getMessage());
        }
    }

    /**
     * Prepare cart items for Microsoft Partner Center format
     */
    private function prepareLineItems($cartItems, $logFile = null): array
    {
        $lineItems = [];
        $i = 0;

        foreach ($cartItems as $item) {
            $product = $item->product;

            if (!$product) {
                if ($logFile) file_put_contents($logFile, "[WARNING] Product not found for cart item {$item->id}\n", FILE_APPEND);
                continue;
            }

            // Map product fields to Microsoft format
            $catalogItemId = $product->SkuId; // Using SkuId as catalogItemId
            $quantity = $item->quantity;
            $billingCycle = $product->BillingPlan;
            $termDuration = $product->TermDuration;

            if (!$catalogItemId) {
                if ($logFile) file_put_contents($logFile, "[WARNING] No SkuId found for product {$product->idproduct}\n", FILE_APPEND);
                continue;
            }

            // Build line item based on product type
            $lineItem = [
                "id" => $i,
                "catalogItemId" => $catalogItemId,
                "quantity" => $quantity,
                "billingCycle" => $billingCycle
            ];

            // Add term duration if it exists and is not a prepaid Azure credit
            if (!empty($termDuration) && !($termDuration === "P1M" && strpos($product->ProductTitle, 'Prepago') !== false)) {
                $lineItem["termDuration"] = $termDuration;
            }

            $lineItems[] = $lineItem;

            if ($logFile) {
                file_put_contents($logFile, "[INFO] Prepared line item {$i}: " . json_encode($lineItem) . "\n", FILE_APPEND);
            }

            $i++;
        }

        if ($logFile) file_put_contents($logFile, "[INFO] Prepared " . count($lineItems) . " line items for Microsoft Partner Center\n", FILE_APPEND);

        return $lineItems;
    }

    /**
     * Create cart in Microsoft Partner Center
     */
    private function createMicrosoftCart(string $customerId, array $lineItems, string $token, $logFile = null): array
    {
        try {
            $payload = ["lineItems" => $lineItems];
            $url = "{$this->partnerCenterApiUrl}/customers/{$customerId}/carts";

            if ($logFile) {
                file_put_contents($logFile, "[INFO] Creating Microsoft cart\n", FILE_APPEND);
                file_put_contents($logFile, "[INFO] Customer ID: {$customerId}\n", FILE_APPEND);
                file_put_contents($logFile, "[INFO] URL: {$url}\n", FILE_APPEND);
                file_put_contents($logFile, "[INFO] Payload: " . json_encode($payload, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
            }

            $response = Http::timeout(config('services.microsoft.create_cart_timeout', env('MICROSOFT_API_CREATE_CART_TIMEOUT', 120)))
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $token
                ])
                ->post($url, $payload);

            if ($logFile) {
                file_put_contents($logFile, "[INFO] Microsoft cart creation response status: " . $response->status() . "\n", FILE_APPEND);
                file_put_contents($logFile, "[INFO] Microsoft cart creation response headers: " . json_encode($response->headers()) . "\n", FILE_APPEND);
                file_put_contents($logFile, "[INFO] Microsoft cart creation RAW BODY: " . $response->body() . "\n", FILE_APPEND);
            }

            if (!$response->successful()) {
                if ($logFile) file_put_contents($logFile, "[ERROR] Failed to create Microsoft cart - HTTP {$response->status()}\n", FILE_APPEND);

                // Capture Microsoft error details
                $responseBody = $response->json();
                $this->microsoftErrorDetails = [
                    'http_status' => $response->status(),
                    'error_code' => $responseBody['code'] ?? null,
                    'description' => $responseBody['description'] ?? null,
                    'raw_response' => $response->body(),
                    'correlation_id' => $response->header('MS-CorrelationId'),
                    'request_id' => $response->header('MS-RequestId'),
                ];

                throw new Exception('Failed to create cart in Microsoft Partner Center: HTTP ' . $response->status());
            }

            $data = $response->json();

            if (!isset($data['id'])) {
                if ($logFile) file_put_contents($logFile, "[ERROR] Invalid cart response structure: " . json_encode($data) . "\n", FILE_APPEND);
                throw new Exception('Invalid cart response from Microsoft Partner Center');
            }

            if ($logFile) file_put_contents($logFile, "[SUCCESS] Microsoft cart created successfully with ID: {$data['id']}\n", FILE_APPEND);

            return $data;

        } catch (Exception $e) {
            if ($logFile) file_put_contents($logFile, "[ERROR] Error creating Microsoft cart: " . $e->getMessage() . "\n", FILE_APPEND);
            throw $e;
        }
    }

    /**
     * Checkout cart in Microsoft Partner Center
     */
    private function checkoutMicrosoftCart(string $customerId, string $cartId, string $token, $logFile = null): array
    {
        try {
            $url = "{$this->partnerCenterApiUrl}/customers/{$customerId}/carts/{$cartId}/checkout";

            if ($logFile) {
                file_put_contents($logFile, "[INFO] Checking out Microsoft cart\n", FILE_APPEND);
                file_put_contents($logFile, "[INFO] Customer ID: {$customerId}\n", FILE_APPEND);
                file_put_contents($logFile, "[INFO] Cart ID: {$cartId}\n", FILE_APPEND);
                file_put_contents($logFile, "[INFO] Checkout URL: {$url}\n", FILE_APPEND);
            }

            $response = Http::timeout(config('services.microsoft.checkout_timeout', env('MICROSOFT_API_CHECKOUT_TIMEOUT', 180)))
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $token
                ])
                ->post($url);

            if ($logFile) {
                file_put_contents($logFile, "[INFO] Microsoft cart checkout response status: " . $response->status() . "\n", FILE_APPEND);
                file_put_contents($logFile, "[INFO] Microsoft cart checkout response headers: " . json_encode($response->headers()) . "\n", FILE_APPEND);
                file_put_contents($logFile, "[INFO] Microsoft cart checkout RAW BODY: " . $response->body() . "\n", FILE_APPEND);
            }

            if (!$response->successful()) {
                if ($logFile) file_put_contents($logFile, "[ERROR] Failed to checkout Microsoft cart - HTTP {$response->status()}\n", FILE_APPEND);

                // Capture Microsoft error details for checkout failures
                $responseBody = $response->json();
                $this->microsoftErrorDetails = [
                    'http_status' => $response->status(),
                    'error_code' => $responseBody['code'] ?? null,
                    'description' => $responseBody['description'] ?? null,
                    'raw_response' => $response->body(),
                    'correlation_id' => $response->header('MS-CorrelationId'),
                    'request_id' => $response->header('MS-RequestId'),
                ];

                throw new Exception('Failed to checkout cart in Microsoft Partner Center: HTTP ' . $response->status());
            }

            $data = $response->json();

            if (isset($data['code'])) {
                if ($logFile) file_put_contents($logFile, "[ERROR] Microsoft Partner Center returned error code: " . json_encode($data) . "\n", FILE_APPEND);

                // Capture Microsoft error details for error responses
                $this->microsoftErrorDetails = [
                    'http_status' => $response->status(),
                    'error_code' => $data['code'] ?? null,
                    'description' => $data['description'] ?? null,
                    'raw_response' => $response->body(),
                    'correlation_id' => $response->header('MS-CorrelationId'),
                    'request_id' => $response->header('MS-RequestId'),
                ];

                throw new Exception('Microsoft Partner Center returned error: ' . ($data['description'] ?? 'Unknown error'));
            }

            if ($logFile) file_put_contents($logFile, "[SUCCESS] Microsoft cart checkout completed successfully\n", FILE_APPEND);

            return $data;

        } catch (Exception $e) {
            if ($logFile) file_put_contents($logFile, "[ERROR] Error checking out Microsoft cart: " . $e->getMessage() . "\n", FILE_APPEND);
            throw $e;
        }
    }

    /**
     * Calculate Azure spending amount for prepaid credits
     */
    private function calculateAzureSpending($cartItems): float
    {
        $azureSpending = 0;

        foreach ($cartItems as $item) {
            $product = $item->product;

            if ($product &&
                $product->TermDuration === "P1M" &&
                strpos($product->ProductTitle, 'Prepago') !== false) {

                // For Azure prepaid credits, use quantity as spending amount
                $azureSpending += $item->quantity;
            }
        }

        return $azureSpending;
    }

    /**
     * Set Azure spending budget
     */
    private function setAzureSpendingBudget(string $customerId, float $amount, string $token, $logFile = null): void
    {
        try {
            // Apply discount factor (86% as seen in old system)
            $budgetAmount = round($amount * 0.86, 2);

            $payload = [
                "Amount" => $budgetAmount,
                "Attributes" => [
                    "ObjectType" => "SpendingBudget"
                ]
            ];

            if ($logFile) file_put_contents($logFile, "[INFO] Setting Azure spending budget: {$budgetAmount}\n", FILE_APPEND);

            $response = Http::timeout(config('services.microsoft.budget_timeout', env('MICROSOFT_API_BUDGET_TIMEOUT', 90)))
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $token
                ])
                ->patch("{$this->partnerCenterApiUrl}/customers/{$customerId}/usagebudget", $payload);

            if ($response->successful()) {
                if ($logFile) file_put_contents($logFile, "[SUCCESS] Azure spending budget set successfully\n", FILE_APPEND);
            } else {
                if ($logFile) file_put_contents($logFile, "[WARNING] Failed to set Azure spending budget - HTTP {$response->status()}\n", FILE_APPEND);
            }

        } catch (Exception $e) {
            if ($logFile) file_put_contents($logFile, "[WARNING] Error setting Azure spending budget: " . $e->getMessage() . "\n", FILE_APPEND);
            // Don't throw - this is not critical for order completion
        }
    }

    /**
     * Save subscription data from checkout response
     */
    private function saveSubscriptions(Order $order, array $checkoutResponse, $cartItems, $logFile = null): void
    {
        try {
            if (!isset($checkoutResponse['orders'][0]['lineItems'])) {
                if ($logFile) file_put_contents($logFile, "[WARNING] No line items found in checkout response\n", FILE_APPEND);
                return;
            }

            $lineItems = $checkoutResponse['orders'][0]['lineItems'];

            if ($logFile) file_put_contents($logFile, "[INFO] Saving " . count($lineItems) . " subscriptions\n", FILE_APPEND);

            foreach ($lineItems as $item) {
                // Find corresponding cart item to get product info
                $cartItem = $cartItems->firstWhere(function($cartItem) use ($item) {
                    return $cartItem->product && $cartItem->product->SkuId === $item['offerId'];
                });

                if (!$cartItem) {
                    if ($logFile) file_put_contents($logFile, "[WARNING] Could not find cart item for offer ID: " . $item['offerId'] . "\n", FILE_APPEND);
                    continue;
                }

                // Get sku_id from the product, not from cart_item
                $skuId = $cartItem->product ? $cartItem->product->SkuId : $item['offerId'];

                Subscription::create([
                    'order_id' => $order->id,
                    'subscription_identifier' => $order->order_number,
                    'offer_id' => $item['offerId'] ?? null,
                    'subscription_id' => $item['subscriptionId'] ?? null,
                    'term_duration' => $item['termDuration'] ?? null,
                    'transaction_type' => $item['transactionType'] ?? null,
                    'friendly_name' => $item['friendlyName'] ?? null,
                    'quantity' => $item['quantity'] ?? 1,
                    'pricing' => $item['pricing']['listPrice'] ?? 0,
                    'status' => 1,
                    'microsoft_account_id' => $order->microsoft_account_id,
                    'product_id' => $cartItem->product_id,
                    'sku_id' => $skuId,
                    'created_by' => 'Marketplace'
                ]);
            }

            if ($logFile) file_put_contents($logFile, "[SUCCESS] Saved " . count($lineItems) . " subscriptions for order {$order->id}\n", FILE_APPEND);

        } catch (Exception $e) {
            if ($logFile) file_put_contents($logFile, "[ERROR] Error saving subscriptions: " . $e->getMessage() . "\n", FILE_APPEND);
            // Don't throw - order was processed successfully
        }
    }

    /**
     * Process order in fake mode (for testing/development)
     */
    private function processFakeOrder(Order $order, string $logFile): array
    {
        try {
            file_put_contents($logFile, "[INFO] Processing order in FAKE MODE\n", FILE_APPEND);

            // Get cart items for logging
            $cartItems = $order->cart->items()->with('product')->where('status', 'active')->get();
            file_put_contents($logFile, "[INFO] Processing {$cartItems->count()} cart items in FAKE MODE\n", FILE_APPEND);

            // Simulate line items preparation
            foreach ($cartItems as $index => $item) {
                $fakeLineItem = [
                    'id' => $index,
                    'catalogItemId' => $item->sku_id ?? '0001',
                    'quantity' => $item->quantity,
                    'billingCycle' => 'Monthly',
                    'termDuration' => 'P1M'
                ];
                file_put_contents($logFile, "[INFO] FAKE line item {$index}: " . json_encode($fakeLineItem) . "\n", FILE_APPEND);
            }

            // Simulate Microsoft cart creation success
            file_put_contents($logFile, "[INFO] FAKE - Simulating successful Microsoft cart creation\n", FILE_APPEND);
            $fakeCartId = 'fake-cart-' . uniqid();
            file_put_contents($logFile, "[SUCCESS] FAKE cart created with ID: {$fakeCartId}\n", FILE_APPEND);

            // Simulate checkout success
            file_put_contents($logFile, "[INFO] FAKE - Simulating successful cart checkout\n", FILE_APPEND);
            $fakeCheckoutResponse = [
                'orders' => [
                    [
                        'lineItems' => $cartItems->map(function($item, $index) {
                            return [
                                'offerId' => $item->sku_id ?? '0001',
                                'subscriptionId' => 'fake-sub-' . uniqid(),
                                'termDuration' => 'P1M',
                                'transactionType' => 'New',
                                'friendlyName' => $item->product->product_name ?? 'Fake Product',
                                'quantity' => $item->quantity,
                                'pricing' => [
                                    'listPrice' => $item->unit_price ?? 100
                                ]
                            ];
                        })->toArray()
                    ]
                ]
            ];

            // Save fake subscriptions
            file_put_contents($logFile, "[INFO] FAKE - Saving subscriptions\n", FILE_APPEND);
            $this->saveSubscriptions($order, $fakeCheckoutResponse, $cartItems, $logFile);

            // Update order status to completed
            $order->update(['status' => 'completed']);
            file_put_contents($logFile, "[SUCCESS] FAKE - Order status updated to 'completed'\n", FILE_APPEND);

            file_put_contents($logFile, "[SUCCESS] FAKE MODE - Order processed successfully\n", FILE_APPEND);

            return [
                'success' => true,
                'message' => 'Order processed successfully in FAKE MODE',
                'data' => [
                    'order_id' => $order->id,
                    'cart_id' => $fakeCartId,
                    'subscriptions_count' => $cartItems->count(),
                    'mode' => 'FAKE'
                ]
            ];

        } catch (Exception $e) {
            file_put_contents($logFile, "[ERROR] FAKE MODE processing failed: " . $e->getMessage() . "\n", FILE_APPEND);
            throw new Exception("FAKE MODE processing failed: " . $e->getMessage());
        }
    }

    /**
     * Send error notification email when Microsoft Partner Center fails
     */
    private function sendErrorNotification($order, int $orderId, string $errorMessage, ?string $logFile): void
    {
        try {
            // If order is not loaded, try to load it
            if (!$order) {
                $order = Order::find($orderId);
            }

            if (!$order) {
                if ($logFile) file_put_contents($logFile, "[WARNING] Could not load order for error notification\n", FILE_APPEND);
                return;
            }

            $notificationService = app(MicrosoftErrorNotificationService::class);

            $errorDetails = [
                'Order ID' => $orderId,
                'Order Number' => $order->order_number,
                'Order Status' => $order->status,
                'Error Time' => now()->format('Y-m-d H:i:s'),
                'Log File' => $logFile ? basename($logFile) : 'N/A'
            ];

            $notificationService->sendMicrosoftErrorNotification($order, $errorMessage, $errorDetails, $this->microsoftErrorDetails);

            if ($logFile) {
                file_put_contents($logFile, "[INFO] Error notification email sent to " . env('MICROSOFT_ERROR_NOTIFICATION_EMAIL') . "\n", FILE_APPEND);
            }

        } catch (Exception $e) {
            if ($logFile) {
                file_put_contents($logFile, "[ERROR] Failed to send error notification: " . $e->getMessage() . "\n", FILE_APPEND);
            }
            Log::error("Failed to send Microsoft error notification: " . $e->getMessage());
        }
    }
}
