<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\WhatsAppNotificationService;
use App\Services\MicrosoftErrorNotificationService;
use App\Models\Order;
use App\Models\User;
use App\Models\MicrosoftAccount;

class TestDetailedNotification extends Command
{
    protected $signature = 'test:detailed-notification {order_id?}';
    protected $description = 'Test the new detailed notification system with product-specific errors';

    public function handle()
    {
        $orderId = $this->argument('order_id');

        $this->info("🧪 Testing detailed notification system");
        $this->info("=====================================");

        try {
            // Get or create test order
            $order = $orderId ? Order::find($orderId) : $this->createTestOrder();

            if (!$order) {
                $this->error("❌ Order not found: {$orderId}");
                return 1;
            }

            $this->info("📋 Using Order: {$order->order_number} (ID: {$order->id})");

            // Simulate the scenario you experienced
            $productResults = [
                [
                    'cart_item_id' => 1,
                    'product_id' => 101,
                    'sku_id' => 'CFQ7TTC0LHWM',
                    'product_title' => 'Advanced eDiscovery Storage',
                    'quantity' => 1,
                    'success' => false,
                    'error_message' => "Invalid TermDuration 'P1M' for product: Advanced eDiscovery Storage",
                    'microsoft_details' => [
                        'http_status' => 400,
                        'error_code' => 'InvalidRequest',
                        'description' => 'The specified term duration P1M is not valid for this product type',
                        'correlation_id' => 'f47ac10b-58cc-4372-a567-0e02b2c3d479'
                    ],
                    'subscription_id' => null,
                    'microsoft_cart_id' => null,
                    'processed_at' => now()
                ],
                [
                    'cart_item_id' => 2,
                    'product_id' => 102,
                    'sku_id' => 'CFQ7TTC0HD7Q',
                    'product_title' => 'AI Builder Capacity Add-on T1',
                    'quantity' => 1,
                    'success' => false,
                    'error_message' => "Invalid TermDuration 'P1M' for product: AI Builder Capacity Add-on T1",
                    'microsoft_details' => [
                        'http_status' => 400,
                        'error_code' => 'InvalidRequest',
                        'description' => 'The specified term duration P1M is not valid for this product type',
                        'correlation_id' => 'a1b2c3d4-e5f6-7890-abcd-ef1234567890'
                    ],
                    'subscription_id' => null,
                    'microsoft_cart_id' => null,
                    'processed_at' => now()
                ]
            ];

            $errorMessage = "Aprovisionamiento fallido: 0/2 productos exitosos";
            $errorDetails = [
                'Order ID' => $order->id,
                'Order Number' => $order->order_number,
                'Order Status' => 'processing',
                'Total Products' => 2,
                'Successful Products' => 0,
                'Failed Products' => 2,
                'Error Time' => now()->format('Y-m-d H:i:s')
            ];

            $this->newLine();
            $this->info("📤 Sending detailed notification...");

            // Test WhatsApp service directly
            $whatsappService = new WhatsAppNotificationService();

            $this->info("🔍 Preview of WhatsApp message:");
            $this->info("================================");

            // Get the message format (we'll call the private method via reflection for testing)
            $reflection = new \ReflectionClass($whatsappService);
            $method = $reflection->getMethod('formatMicrosoftErrorMessageWithProducts');
            $method->setAccessible(true);

            $message = $method->invoke($whatsappService, $order, $errorMessage, $errorDetails, $productResults);

            $this->line($message);

            $this->newLine();
            $this->info("📧 Testing notification service...");

            // Test the notification service
            $notificationService = new MicrosoftErrorNotificationService();

            if ($this->confirm('¿Enviar notificación real de prueba?', false)) {
                $notificationService->sendMicrosoftErrorNotificationWithProducts(
                    $order,
                    $errorMessage,
                    $errorDetails,
                    $productResults
                );
                $this->info("✅ Notification sent!");
            } else {
                $this->info("⏭️ Skipped actual sending");
            }

            $this->newLine();
            $this->info("✅ Test completed successfully!");
            $this->info("📝 Key improvements:");
            $this->info("  - Shows specific error for each product");
            $this->info("  - Includes Microsoft error codes and HTTP status");
            $this->info("  - Displays success/failure count");
            $this->info("  - Shows correlation IDs for troubleshooting");

        } catch (\Exception $e) {
            $this->error("❌ Test failed: " . $e->getMessage());
            $this->error("Stack trace:");
            $this->error($e->getTraceAsString());
            return 1;
        }

        return 0;
    }

    private function createTestOrder()
    {
        $this->info("📝 Creating test order...");

        // Create or get test user
        $user = User::firstOrCreate(
            ['email' => 'salvador.rodriguez@readymind.ms'],
            [
                'name' => 'Salvador Rodriguez',
                'phone' => '7771922846',
                'password' => bcrypt('test123')
            ]
        );

        // Create or get test Microsoft account
        $microsoftAccount = MicrosoftAccount::firstOrCreate(
            ['microsoft_id' => 'fc9a0f4e-db7e-4cd8-8b7b-721722b12122'],
            [
                'user_id' => $user->id,
                'company_name' => 'Simple Systems',
                'domain_concatenated' => 'simplesystems112.onmicrosoft.com',
                'microsoft_customer_id' => 'fc9a0f4e-db7e-4cd8-8b7b-721722b12122'
            ]
        );

        // Create test order
        $order = Order::create([
            'user_id' => $user->id,
            'microsoft_account_id' => $microsoftAccount->id,
            'order_number' => 'ORD-' . date('Ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT),
            'status' => 'processing',
            'total_amount' => 600.60,
            'currency' => 'USD'
        ]);

        $this->info("✅ Test order created: {$order->order_number}");

        return $order;
    }
}
