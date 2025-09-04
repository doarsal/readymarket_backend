<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use App\Services\MicrosoftErrorNotificationService;

class TestMultipleNotifications extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'test:multiple-notifications {order_id?}';

    /**
     * The console command description.
     */
    protected $description = 'Test sending notifications to multiple emails and WhatsApp numbers';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $orderId = $this->argument('order_id') ?? 20;

        $this->info("Testing multiple notifications for order ID: {$orderId}");

        // Get the order
        $order = Order::find($orderId);

        if (!$order) {
            $this->error("Order {$orderId} not found!");
            return;
        }

        // Test notification service
        $notificationService = new MicrosoftErrorNotificationService();

        $this->info("Sending notifications...");

        $notificationService->sendMicrosoftErrorNotification(
            $order,
            'Test error message for multiple notifications',
            [
                'step' => 'testing',
                'timestamp' => now()->toISOString()
            ],
            [
                'error_code' => 'TEST001',
                'description' => 'This is a test notification to multiple recipients',
                'http_status' => 200
            ]
        );

        $this->info("✅ Notifications sent successfully!");
        $this->info("Check your email(s) and WhatsApp(s) for the test messages.");

        // Show configured recipients
        $emails = env('MICROSOFT_ERROR_NOTIFICATION_EMAIL', 'No emails configured');
        $phones = env('WHATSAPP_NOTIFICATION_NUMBER', 'No phones configured');

        $this->info("\n📧 Email recipients: {$emails}");
        $this->info("📱 WhatsApp recipients: {$phones}");
    }
}
