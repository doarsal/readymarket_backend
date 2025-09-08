<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\OrderItem;
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
        $this->tokenEndpoint = config('services.microsoft.credentials_url');
        $this->partnerCenterApiUrl = config('services.microsoft.partner_center_base_url');
    }

    /**
     * Process order and create products in Partner Center
     */
    public function processOrder(int $orderId): array
    {
        try {
            // Get order with relationships
            $order = Order::with([
                'cart.items.product',
                'microsoftAccount'
            ])->where('id', $orderId)
              ->where('status', 'processing')
              ->first();

            if (!$order) {
                throw new Exception("Order not found or not in processing status: {$orderId}");
            }

            // Check if we're in fake mode
            if (config('services.microsoft.fake_mode', false)) {
                return $this->processFakeOrder($order);
            }

            if (!$order->cart) {
                throw new Exception("Cart not found for order: {$orderId}");
            }

            if (!$order->microsoftAccount) {
                throw new Exception("Microsoft account not found for order: {$orderId}");
            }

            $microsoftCustomerId = $order->microsoftAccount->microsoft_id;
            if (!$microsoftCustomerId) {
                throw new Exception("Microsoft customer ID not found for order: {$orderId}");
            }

            // Get cart items
            $cartItems = $order->cart->items()->with('product')->where('status', 'active')->get();
            if ($cartItems->isEmpty()) {
                throw new Exception("No active cart items found for order: {$orderId}");
            }

            // Get Microsoft access token
            $token = $this->getMicrosoftToken();

            // Process each product individually for resilient provisioning
            $provisioningResults = $this->processProductsIndividually($order, $cartItems, $microsoftCustomerId, $token);

            // Calculate overall success rate
            $totalProducts = $cartItems->count();
            $successfulProducts = collect($provisioningResults)->where('success', true)->count();

            // Set Azure spending budget if applicable (only for successful products)
            $successfulCartItems = $cartItems->whereIn('id',
                collect($provisioningResults)->where('success', true)->pluck('cart_item_id')
            );
            $azureSpendingAmount = $this->calculateAzureSpending($successfulCartItems);
            if ($azureSpendingAmount > 0) {
                $this->setAzureSpendingBudget($microsoftCustomerId, $azureSpendingAmount, $token);
            }

            // Send notification if there are failed products
            if ($successfulProducts < $totalProducts) {
                $this->sendProductFailureNotification($order, $provisioningResults);
            }

            // Determine final order status based on results
            if ($successfulProducts === $totalProducts) {
                // All products successful - mark as completed
                $order->update([
                    'status' => 'completed',
                    'fulfillment_status' => 'fulfilled'
                ]);
            } elseif ($successfulProducts > 0) {
                // Some products failed - keep as processing for manual review
                $order->update([
                    'status' => 'processing',
                    'fulfillment_status' => 'partially_fulfilled'
                ]);
            } else {
                // All products failed - mark as processing with failed fulfillment
                $order->update([
                    'status' => 'processing',
                    'fulfillment_status' => 'failed'
                ]);
            }

            return [
                'success' => $successfulProducts === $totalProducts,
                'message' => $this->generateDetailedMessage($successfulProducts, $totalProducts, $provisioningResults),
                'order_id' => $orderId,
                'order_status' => $order->fresh()->status,
                'fulfillment_status' => $order->fresh()->fulfillment_status,
                'total_products' => $totalProducts,
                'successful_products' => $successfulProducts,
                'failed_products' => $totalProducts - $successfulProducts,
                'product_details' => $this->formatProductDetails($provisioningResults),
                'provisioning_results' => $provisioningResults
            ];

        } catch (Exception $e) {
            // Only send error notification if we haven't processed products individually
            // (if processing failed before individual product processing started)
            if (!config('services.microsoft.fake_mode', false) && !isset($provisioningResults)) {
                $this->sendGeneralErrorNotification($order ?? null, $orderId, $e->getMessage());
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
    private function getMicrosoftToken(): string
    {
        try {
            $response = Http::timeout(config('services.microsoft.token_timeout', 60))->get($this->tokenEndpoint);

            if (!$response->successful()) {
                // Capturar detalles del error de token
                $this->microsoftErrorDetails = [
                    'http_status' => $response->status(),
                    'raw_response' => $response->body(),
                    'endpoint' => $this->tokenEndpoint,
                    'error_type' => 'token_authentication'
                ];
                throw new Exception('Failed to get Microsoft token: HTTP ' . $response->status());
            }

            $data = $response->json();

            if (!isset($data['item']['token'])) {
                throw new Exception('Invalid token response from Microsoft');
            }

            return $data['item']['token'];

        } catch (Exception $e) {
            throw new Exception('Failed to authenticate with Microsoft Partner Center: ' . $e->getMessage());
        }
    }

    /**
     * Process products individually for resilient provisioning
     */
    private function processProductsIndividually(Order $order, $cartItems, string $microsoftCustomerId, string $token): array
    {
        $results = [];

        foreach ($cartItems as $cartItem) {
            $result = [
                'cart_item_id' => $cartItem->id,
                'product_id' => $cartItem->product_id,
                'sku_id' => $cartItem->product->SkuId ?? null,
                'product_title' => $cartItem->product->ProductTitle ?? 'Unknown Product',
                'quantity' => $cartItem->quantity,
                'success' => false,
                'error_message' => null,
                'microsoft_details' => [],
                'subscription_id' => null,
                'microsoft_cart_id' => null,
                'processed_at' => now()
            ];

            try {
                // Mark item as processing
                $this->updateOrderItemStatus($order, $cartItem, 'processing');

                // Prepare single line item
                $lineItem = $this->prepareSingleLineItem($cartItem);

                // Create cart with single item
                $cartResponse = $this->createMicrosoftCart($microsoftCustomerId, [$lineItem], $token);

                // Checkout single item cart
                $checkoutResponse = $this->checkoutMicrosoftCart($microsoftCustomerId, $cartResponse['id'], $token);

                // Save subscription for this specific item
                $this->saveSingleSubscription($order, $checkoutResponse, $cartItem);

                // Mark as successful
                $result['success'] = true;
                $result['subscription_id'] = $checkoutResponse['orders'][0]['lineItems'][0]['subscriptionId'] ?? null;
                $result['microsoft_cart_id'] = $cartResponse['id'];

                // Mark item as fulfilled
                $this->updateOrderItemStatus($order, $cartItem, 'fulfilled');

            } catch (Exception $e) {
                // Capture error details
                $result['error_message'] = $e->getMessage();
                $result['microsoft_details'] = $this->microsoftErrorDetails ?? [];

                // Mark item as failed
                $this->updateOrderItemStatus($order, $cartItem, 'failed', $e->getMessage());

                // Reset error details for next iteration
                $this->microsoftErrorDetails = [];
            }

            $results[] = $result;
        }

        return $results;
    }

    /**
     * Prepare single line item for Microsoft Partner Center
     */
    private function prepareSingleLineItem($cartItem): array
    {
        $product = $cartItem->product;

        if (!$product || !$product->hasValidCatalogItemId()) {
            throw new Exception("Invalid product data for cart item {$cartItem->id}");
        }

        $catalogItemId = $product->catalog_item_id;
        $quantity = $cartItem->quantity;
        $billingCycle = $product->BillingPlan ?? 'Monthly';
        $termDuration = $product->TermDuration;

        // Build line item
        $lineItem = [
            'id' => 0,
            'catalogItemId' => $catalogItemId,
            'quantity' => $quantity,
            'billingCycle' => $billingCycle
        ];

        // Add term duration if it exists and is not a prepaid Azure credit
        if (!empty($termDuration) && !($termDuration === "P1M" && strpos($product->ProductTitle, 'Prepago') !== false)) {
            $lineItem['termDuration'] = $termDuration;
        }

        return $lineItem;
    }

    /**
     * Update order item status in database
     */
    private function updateOrderItemStatus(Order $order, $cartItem, string $status, string $errorMessage = null): void
    {
        // Find the corresponding order item
        $orderItem = $order->orderItems()->where('product_id', $cartItem->product_id)
                                       ->where('sku_id', $cartItem->product->SkuId ?? $cartItem->sku_id)
                                       ->first();

        if ($orderItem) {
            $updateData = [
                'fulfillment_status' => $status,
                'updated_at' => now()
            ];

            if ($status === 'processing') {
                $updateData['processing_started_at'] = now();
            } elseif ($status === 'fulfilled') {
                $updateData['fulfilled_at'] = now();
            } elseif ($status === 'failed') {
                $updateData['fulfillment_error'] = $errorMessage;
            }

            $orderItem->update($updateData);
        }
    }

    /**
     * Save subscription for a single item
     */
    private function saveSingleSubscription(Order $order, array $checkoutResponse, $cartItem): void
    {
        if (!isset($checkoutResponse['orders'][0]['lineItems'][0])) {
            throw new Exception('No line items found in checkout response');
        }

        $item = $checkoutResponse['orders'][0]['lineItems'][0];
        $product = $cartItem->product;

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
            'sku_id' => $product ? $product->SkuId : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Prepare cart items for Microsoft Partner Center format
     */
    private function prepareLineItems($cartItems): array
    {
        $lineItems = [];
        $i = 0;

        foreach ($cartItems as $item) {
            $product = $item->product;

            if (!$product) {
                continue;
            }

            // Map product fields to Microsoft format - Using model method for catalogItemId
            if (!$product->hasValidCatalogItemId()) {
                continue;
            }

            $catalogItemId = $product->catalog_item_id; // Using model accessor
            $quantity = $item->quantity;
            $billingCycle = $product->BillingPlan;
            $termDuration = $product->TermDuration;

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
            $i++;
        }

        return $lineItems;
    }

    /**
     * Create cart in Microsoft Partner Center
     */
    private function createMicrosoftCart(string $customerId, array $lineItems, string $token): array
    {
        try {
            $payload = ["lineItems" => $lineItems];
            $url = "{$this->partnerCenterApiUrl}/customers/{$customerId}/carts";

            $response = Http::timeout(config('services.microsoft.create_cart_timeout', env('MICROSOFT_API_CREATE_CART_TIMEOUT', 120)))
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $token
                ])
                ->post($url, $payload);

            if (!$response->successful()) {
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
                throw new Exception('Invalid cart response from Microsoft Partner Center');
            }

            return $data;

        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Checkout cart in Microsoft Partner Center
     */
    private function checkoutMicrosoftCart(string $customerId, string $cartId, string $token): array
    {
        try {
            $url = "{$this->partnerCenterApiUrl}/customers/{$customerId}/carts/{$cartId}/checkout";

            $response = Http::timeout(config('services.microsoft.checkout_timeout', env('MICROSOFT_API_CHECKOUT_TIMEOUT', 180)))
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $token
                ])
                ->post($url);

            if (!$response->successful()) {
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
                // Capture Microsoft error details for error responses
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

            return $data;

        } catch (Exception $e) {
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
    private function setAzureSpendingBudget(string $customerId, float $amount, string $token): void
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

            $response = Http::timeout(config('services.microsoft.budget_timeout', env('MICROSOFT_API_BUDGET_TIMEOUT', 90)))
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $token
                ])
                ->patch("{$this->partnerCenterApiUrl}/customers/{$customerId}/usagebudget", $payload);

        } catch (Exception $e) {
            // Don't throw - this is not critical for order completion
        }
    }

    /**
     * Save subscription data from checkout response
     */
    private function saveSubscriptions(Order $order, array $checkoutResponse, $cartItems): void
    {
        try {
            if (!isset($checkoutResponse['orders'][0]['lineItems'])) {
                return;
            }

            $lineItems = $checkoutResponse['orders'][0]['lineItems'];

            foreach ($lineItems as $item) {
                // Find corresponding cart item to get product info
                $cartItem = $cartItems->firstWhere(function($cartItem) use ($item) {
                    return $cartItem->product && $cartItem->product->SkuId === $item['offerId'];
                });

                if (!$cartItem) {
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

        } catch (Exception $e) {
            // Don't throw - order was processed successfully
        }
    }

    /**
     * Process order in fake mode (for testing/development)
     */
    private function processFakeOrder(Order $order): array
    {
        try {
            // Get cart items
            $cartItems = $order->cart->items()->with('product')->where('status', 'active')->get();

            // Simulate line items preparation
            foreach ($cartItems as $index => $item) {
                $fakeLineItem = [
                    'id' => $index,
                    'catalogItemId' => $item->sku_id ?? '0001',
                    'quantity' => $item->quantity,
                    'billingCycle' => 'Monthly',
                    'termDuration' => 'P1M'
                ];
            }

            // Simulate Microsoft cart creation success
            $fakeCartId = 'fake-cart-' . uniqid();

            // Simulate checkout success
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
            $this->saveSubscriptions($order, $fakeCheckoutResponse, $cartItems);

            // Update order status to completed
            $order->update(['status' => 'completed']);

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
            throw new Exception("FAKE MODE processing failed: " . $e->getMessage());
        }
    }

    /**
     * Send error notification email when Microsoft Partner Center fails (general errors)
     */
    private function sendGeneralErrorNotification($order, int $orderId, string $errorMessage): void
    {
        try {
            // If order is not loaded, try to load it
            if (!$order) {
                $order = Order::find($orderId);
            }

            if (!$order) {
                return;
            }

            $notificationService = app(MicrosoftErrorNotificationService::class);

            $errorDetails = [
                'Order ID' => $orderId,
                'Order Number' => $order->order_number,
                'Order Status' => $order->status,
                'Error Time' => now()->format('Y-m-d H:i:s'),
                'Error Type' => 'General Processing Error'
            ];

            // Use the old notification method for general errors (before product processing)
            $notificationService->sendMicrosoftErrorNotification($order, $errorMessage, $errorDetails, $this->microsoftErrorDetails);

        } catch (Exception $e) {
            // Silent fail for notification errors
        }
    }

    /**
     * Generate detailed message based on provisioning results
     */
    private function generateDetailedMessage(int $successful, int $total, array $results): string
    {
        if ($successful === $total) {
            return "✅ Todos los productos ({$total}) se aprovisionaron correctamente";
        } elseif ($successful === 0) {
            return "❌ Ningún producto se pudo aprovisionar ({$total} productos fallaron)";
        } else {
            return "⚠️ Aprovisionamiento parcial: {$successful}/{$total} productos exitosos, " . ($total - $successful) . " fallaron";
        }
    }

    /**
     * Format product details for response
     */
    private function formatProductDetails(array $results): array
    {
        $formatted = [];

        foreach ($results as $result) {
            $detail = [
                'product_id' => $result['product_id'],
                'product_title' => $result['product_title'],
                'quantity' => $result['quantity'],
                'status' => $result['success'] ? 'success' : 'failed',
                'processed_at' => $result['processed_at']
            ];

            if ($result['success']) {
                $detail['subscription_id'] = $result['subscription_id'];
                $detail['microsoft_cart_id'] = $result['microsoft_cart_id'];
            } else {
                $detail['error_message'] = $result['error_message'];
                if (!empty($result['microsoft_details'])) {
                    $detail['microsoft_error_details'] = $result['microsoft_details'];
                }
            }

            $formatted[] = $detail;
        }

        return $formatted;
    }

    /**
     * Send notification when products fail during individual processing
     */
    private function sendProductFailureNotification(Order $order, array $provisioningResults): void
    {
        try {
            $notificationService = app(MicrosoftErrorNotificationService::class);

            // Get failed products details
            $failedProducts = collect($provisioningResults)->where('success', false);
            $successfulProducts = collect($provisioningResults)->where('success', true);

            $errorMessage = $failedProducts->count() === count($provisioningResults)
                ? "Todos los productos fallaron durante el aprovisionamiento"
                : "Aprovisionamiento parcial: {$successfulProducts->count()}/{$this->countTotal($provisioningResults)} productos exitosos";

            $errorDetails = [
                'Order ID' => $order->id,
                'Order Number' => $order->order_number,
                'Order Status' => $order->status,
                'Total Products' => count($provisioningResults),
                'Successful Products' => $successfulProducts->count(),
                'Failed Products' => $failedProducts->count(),
                'Error Time' => now()->format('Y-m-d H:i:s')
            ];

            // Pass detailed product results for notification formatting
            $notificationService->sendMicrosoftErrorNotificationWithProducts(
                $order,
                $errorMessage,
                $errorDetails,
                $provisioningResults
            );

        } catch (Exception $e) {
            // Silent fail for notification errors
            Log::error("Failed to send product failure notification", [
                'order_id' => $order->id ?? 'unknown',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Helper to count total provisioning results
     */
    private function countTotal(array $results): int
    {
        return count($results);
    }
}
