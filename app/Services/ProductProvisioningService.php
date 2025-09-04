<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class ProductProvisioningService
{
    private $microsoftService;
    private $partnerCenterApiUrl;

    public function __construct(MicrosoftPartnerCenterService $microsoftService)
    {
        $this->microsoftService = $microsoftService;
        $this->partnerCenterApiUrl = config('services.microsoft.partner_center_base_url', env('MICROSOFT_PARTNER_CENTER_BASE_URL'));
    }

    /**
     * Provision products to Microsoft Partner Center after successful payment
     */
    public function provisionOrderProducts(Order $order): bool
    {
        Log::info("Starting product provisioning for order", ['order_id' => $order->id]);

        try {
            // Get customer Microsoft account
            $microsoftAccount = $order->microsoftAccount;
            if (!$microsoftAccount || !$microsoftAccount->microsoft_customer_id) {
                throw new Exception('Microsoft account not found for order');
            }

            // Get access token
            $token = $this->microsoftService->getAuthToken();
            if (!$token) {
                throw new Exception('Unable to get Microsoft Partner Center access token');
            }

            // Prepare cart items from order
            $cartItems = $this->prepareCartItems($order);
            if (empty($cartItems)) {
                throw new Exception('No valid cart items found in order');
            }

            // Create cart in Partner Center
            $cartId = $this->createCart($microsoftAccount->microsoft_customer_id, $cartItems, $token);
            if (!$cartId) {
                throw new Exception('Failed to create cart in Partner Center');
            }

            // Checkout cart
            $checkoutResponse = $this->checkoutCart($microsoftAccount->microsoft_customer_id, $cartId, $token);
            if (!$checkoutResponse) {
                throw new Exception('Failed to checkout cart in Partner Center');
            }

            // Process Azure usage budget if needed
            $this->handleAzureUsageBudget($order, $microsoftAccount->microsoft_customer_id, $token);

            // Save subscription details
            $this->saveSubscriptionDetails($order, $checkoutResponse);

            // Update order fulfillment status
            $order->update(['fulfillment_status' => 'fulfilled']);

            Log::info("Product provisioning completed successfully", ['order_id' => $order->id]);

            return true;

        } catch (Exception $e) {
            Log::error("Product provisioning failed", [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);

            // Update order with error status
            $order->update([
                'fulfillment_status' => 'failed',
                'fulfillment_error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Prepare cart items from order items
     */
    private function prepareCartItems(Order $order): array
    {
        $cartItems = [];
        $itemId = 0;

        foreach ($order->orderItems as $orderItem) {
            $product = $orderItem->product;

            if (!$product || !$product->microsoft_catalog_item_id) {
                Log::warning("Product missing catalog item ID", ['product_id' => $orderItem->product_id]);
                continue;
            }

            $item = [
                "id" => $itemId,
                "catalogItemId" => $product->microsoft_catalog_item_id,
                "quantity" => $orderItem->quantity,
                "billingCycle" => $product->billing_cycle ?? 'monthly'
            ];

            // Add term duration for annual or custom terms
            if ($product->term_duration) {
                $item["termDuration"] = $product->term_duration;
            }

            $cartItems[] = $item;
            $itemId++;
        }

        return $cartItems;
    }

    /**
     * Create cart in Microsoft Partner Center
     */
    private function createCart(string $customerId, array $cartItems, string $token): ?string
    {
        $cartData = ["lineItems" => $cartItems];

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $token
        ])->timeout(config('services.microsoft.create_cart_timeout', env('MICROSOFT_API_CREATE_CART_TIMEOUT', 120)))
          ->post("{$this->partnerCenterApiUrl}/customers/{$customerId}/carts", $cartData);

        if (!$response->successful()) {
            Log::error("Failed to create cart", [
                'status' => $response->status(),
                'response' => $response->body()
            ]);
            return null;
        }

        $data = $response->json();

        if (!isset($data['id'])) {
            Log::error("Invalid cart response", ['response' => $data]);
            return null;
        }

        Log::info("Cart created successfully", ['cart_id' => $data['id']]);

        return $data['id'];
    }

    /**
     * Checkout cart in Microsoft Partner Center
     */
    private function checkoutCart(string $customerId, string $cartId, string $token): ?array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->timeout(config('services.microsoft.checkout_timeout', env('MICROSOFT_API_CHECKOUT_TIMEOUT', 180)))
          ->post("{$this->partnerCenterApiUrl}/customers/{$customerId}/carts/{$cartId}/checkout");

        if (!$response->successful()) {
            Log::error("Failed to checkout cart", [
                'status' => $response->status(),
                'response' => $response->body()
            ]);
            return null;
        }

        $data = $response->json();

        Log::info("Cart checkout successful", ['response' => $data]);

        return $data;
    }

    /**
     * Handle Azure usage budget setup
     */
    private function handleAzureUsageBudget(Order $order, string $customerId, string $token): void
    {
        // Check if order contains Azure prepaid credits
        $azureCredits = 0;

        foreach ($order->orderItems as $orderItem) {
            $product = $orderItem->product;

            // Check if this is an Azure credit product (you may need to adjust this logic)
            if ($product && str_contains(strtolower($product->name), 'azure') &&
                str_contains(strtolower($product->name), 'prepago')) {
                $azureCredits += $orderItem->quantity * $orderItem->unit_price;
            }
        }

        if ($azureCredits > 0) {
            // Apply 0.86 conversion rate as in original system
            $budgetAmount = round($azureCredits * 0.86, 2);

            $budgetData = [
                "Amount" => $budgetAmount,
                "Attributes" => [
                    "ObjectType" => "SpendingBudget"
                ]
            ];

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $token
            ])->timeout(config('services.microsoft.budget_timeout', env('MICROSOFT_API_BUDGET_TIMEOUT', 90)))
              ->patch("{$this->partnerCenterApiUrl}/customers/{$customerId}/usagebudget", $budgetData);

            if ($response->successful()) {
                Log::info("Azure usage budget set", [
                    'customer_id' => $customerId,
                    'budget_amount' => $budgetAmount
                ]);
            } else {
                Log::warning("Failed to set Azure usage budget", [
                    'customer_id' => $customerId,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
            }
        }
    }

    /**
     * Save subscription details from checkout response
     */
    private function saveSubscriptionDetails(Order $order, array $checkoutResponse): void
    {
        if (!isset($checkoutResponse['orders'])) {
            Log::warning("No orders found in checkout response");
            return;
        }

        foreach ($checkoutResponse['orders'] as $orderData) {
            if (!isset($orderData['lineItems'])) {
                continue;
            }

            foreach ($orderData['lineItems'] as $lineItem) {
                try {
                    // Create subscription record (you may need to create this model)
                    $subscriptionData = [
                        'order_id' => $order->id,
                        'offer_id' => $lineItem['offerId'] ?? null,
                        'subscription_id' => $lineItem['subscriptionId'] ?? null,
                        'term_duration' => $lineItem['termDuration'] ?? null,
                        'transaction_type' => $lineItem['transactionType'] ?? null,
                        'friendly_name' => $lineItem['friendlyName'] ?? null,
                        'quantity' => $lineItem['quantity'] ?? 1,
                        'list_price' => $lineItem['pricing']['listPrice'] ?? 0,
                        'status' => 'active',
                        'created_by' => 'Marketplace'
                    ];

                    // You may want to create a Subscription model for this
                    Log::info("Subscription data to save", $subscriptionData);

                } catch (Exception $e) {
                    Log::error("Failed to save subscription details", [
                        'line_item' => $lineItem,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
    }
}
