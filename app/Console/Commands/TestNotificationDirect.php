<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MicrosoftErrorNotificationService;
use App\Models\Order;

class TestNotificationDirect extends Command
{
    protected $signature = 'test:notification-direct {order_id}';
    protected $description = 'Test notification system directly';

    public function handle()
    {
        $orderId = $this->argument('order_id');

        try {
            $order = Order::with(['user', 'microsoftAccount'])->find($orderId);

            if (!$order) {
                $this->error("❌ Order not found: {$orderId}");
                return 1;
            }

            $this->info("🧪 Testing direct notification for Order: {$order->order_number}");

            // Simulate the exact data that should be sent
            $productResults = [
                [
                    'cart_item_id' => 1,
                    'product_id' => 887,
                    'sku_id' => 'CFQ7TTC0LHWM',
                    'product_title' => 'Advanced eDiscovery Storage',
                    'quantity' => 1,
                    'success' => false,
                    'error_message' => 'Failed to checkout cart in Microsoft Partner Center: HTTP 400',
                    'microsoft_details' => [
                        'http_status' => 400,
                        'error_code' => 800002,
                        'description' => 'This cart has line items with errors. Please update the cart to fix the errors.',
                        'correlation_id' => '2ab9a730-f82d-4408-870e-671640705c80'
                    ],
                    'subscription_id' => null,
                    'microsoft_cart_id' => null,
                    'processed_at' => now()
                ],
                [
                    'cart_item_id' => 2,
                    'product_id' => 889,
                    'sku_id' => 'CFQ7TTC0HD7Q',
                    'product_title' => 'AI Builder Capacity Add-on T1',
                    'quantity' => 1,
                    'success' => false,
                    'error_message' => 'Failed to checkout cart in Microsoft Partner Center: HTTP 400',
                    'microsoft_details' => [
                        'http_status' => 400,
                        'error_code' => 800002,
                        'description' => 'This cart has line items with errors. Please update the cart to fix the errors.',
                        'correlation_id' => '1ae38956-e3df-4fbc-88b8-82ad2d5517e8'
                    ],
                    'subscription_id' => null,
                    'microsoft_cart_id' => null,
                    'processed_at' => now()
                ]
            ];

            $errorMessage = "Todos los productos fallaron durante el aprovisionamiento";
            $errorDetails = [
                'Order ID' => $order->id,
                'Order Number' => $order->order_number,
                'Order Status' => $order->status,
                'Total Products' => 2,
                'Successful Products' => 0,
                'Failed Products' => 2,
                'Error Time' => now()->format('Y-m-d H:i:s')
            ];

            $this->info("📧 Testing notification service...");
            $this->info("Order User: " . ($order->user ? $order->user->name : 'No user'));
            $this->info("Microsoft Account: " . ($order->microsoftAccount ? 'Present' : 'Missing'));

            $notificationService = new MicrosoftErrorNotificationService();

            $this->info("🚀 Calling sendMicrosoftErrorNotificationWithProducts...");

            $notificationService->sendMicrosoftErrorNotificationWithProducts(
                $order,
                $errorMessage,
                $errorDetails,
                $productResults
            );

            $this->info("✅ Notification call completed!");
            $this->info("💡 Check WhatsApp and email to see if notification arrived");

            // Also test the WhatsApp service directly
            $this->newLine();
            $this->info("📱 Testing WhatsApp service directly...");

            $whatsappService = new \App\Services\WhatsAppNotificationService();
            $whatsappService->sendMicrosoftErrorNotificationWithProducts(
                $order,
                $errorMessage,
                $errorDetails,
                $productResults
            );

            $this->info("✅ WhatsApp service call completed!");

        } catch (\Exception $e) {
            $this->error("❌ Test failed: " . $e->getMessage());
            $this->error("Stack trace:");
            $this->error($e->getTraceAsString());
            return 1;
        }

        return 0;
    }
}
