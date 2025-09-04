<?php

// Simple test script to verify Microsoft account creation error notifications
// Run this from the backend directory: php test_account_notifications.php

require_once 'vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Contracts\Console\Kernel;

// Create Laravel application instance
$app = require_once 'bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Test the notification services
echo "🧪 Testing Microsoft Account Creation Error Notifications\n";
echo "================================================================\n\n";

try {
    // Create a fake Microsoft account for testing
    $testAccount = new \App\Models\MicrosoftAccount([
        'id' => 999999,
        'company_name' => 'Test Company Notifications',
        'domain_concatenated' => 'testnotifications123.onmicrosoft.com',
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => 'test@testnotifications123.com',
        'phone' => '+1234567890',
        'address_line_1' => '123 Test Street',
        'city' => 'Test City',
        'state' => 'Test State',
        'postal_code' => '12345',
        'country' => 'US',
        'status' => 'pending',
        'created_at' => now()
    ]);

    // Test error details
    $errorMessage = "Test error for notification system verification";
    $errorDetails = [
        'details' => 'This is a test error to verify the notification system works properly',
        'error_code' => 'TEST_ERROR',
        'account_id' => $testAccount->id,
        'timestamp' => now()->format('Y-m-d H:i:s'),
        'test_mode' => true
    ];

    $microsoftErrorDetails = [
        'error_code' => '600092',
        'description' => 'Enter a valid name for the company. Test is not allowed.',
        'http_status' => '400',
        'correlation_id' => 'test-correlation-' . uniqid(),
        'request_id' => 'test-request-' . uniqid()
    ];

    // Test the notification service
    echo "1. Testing MicrosoftErrorNotificationService...\n";
    $notificationService = app(\App\Services\MicrosoftErrorNotificationService::class);

    echo "   - Sending test notifications...\n";
    $notificationService->sendMicrosoftAccountCreationErrorNotification(
        $testAccount,
        $errorMessage,
        $errorDetails,
        $microsoftErrorDetails
    );

    echo "   ✅ Notifications sent successfully!\n\n";

    // Check environment variables
    echo "2. Checking notification configuration...\n";
    $emailRecipients = env('MICROSOFT_ERROR_NOTIFICATION_EMAIL', 'Not configured');
    $whatsappNumbers = env('WHATSAPP_NOTIFICATION_NUMBER', 'Not configured');
    $whatsappToken = env('WHATSAPP_GRAPH_TOKEN', 'Not configured');
    $whatsappPhoneId = env('WHATSAPP_PHONE_ID', 'Not configured');

    echo "   - Email recipients: {$emailRecipients}\n";
    echo "   - WhatsApp numbers: {$whatsappNumbers}\n";
    echo "   - WhatsApp token: " . (strlen($whatsappToken) > 10 ? substr($whatsappToken, 0, 10) . '...' : $whatsappToken) . "\n";
    echo "   - WhatsApp phone ID: {$whatsappPhoneId}\n\n";

    echo "3. Testing WhatsApp service separately...\n";
    $whatsappService = new \App\Services\WhatsAppNotificationService();

    echo "   - Sending WhatsApp test message...\n";
    $whatsappService->sendMicrosoftAccountCreationErrorNotification(
        $testAccount,
        $errorMessage,
        $errorDetails,
        $microsoftErrorDetails
    );

    echo "   ✅ WhatsApp notification sent!\n\n";

    echo "🎉 Test completed successfully!\n";
    echo "📧 Check your email for the notification.\n";
    echo "📱 Check WhatsApp for the notification message.\n";
    echo "📋 Check logs for detailed information.\n\n";

} catch (\Exception $e) {
    echo "❌ Test failed with error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
