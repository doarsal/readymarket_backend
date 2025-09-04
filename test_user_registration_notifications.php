<?php

// Script de prueba para notificaciones de registro de usuario
// Run this from the backend directory: php test_user_registration_notifications.php

require_once 'vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Contracts\Console\Kernel;

// Create Laravel application instance
$app = require_once 'bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Test the notification services
echo "🧪 Testing User Registration Notifications\n";
echo "================================================================\n\n";

try {
    // Create a fake user for testing
    $testUser = new \App\Models\User([
        'id' => 999999,
        'first_name' => 'Juan Carlos',
        'last_name' => 'Pérez García',
        'name' => 'Juan Carlos Pérez García',
        'email' => 'juan.perez@testcompany.com',
        'phone' => '+52 777 123 4567',
        'company_name' => 'Test Company México',
        'position' => 'Director de TI',
        'city' => 'Cuernavaca',
        'state' => 'Morelos',
        'country' => 'México',
        'email_verified_at' => now(),
        'created_at' => now(),
        'updated_at' => now()
    ]);

    // Test the notification service
    echo "1. Testing UserRegistrationNotificationService...\n";
    $notificationService = app(\App\Services\UserRegistrationNotificationService::class);

    echo "   - Sending test notifications...\n";
    $notificationService->sendNewUserRegistrationNotification($testUser);

    echo "   ✅ Notifications sent successfully!\n\n";

    // Check environment variables
    echo "2. Checking notification configuration...\n";
    $notificationsEnabled = env('SEND_USER_REGISTRATION_NOTIFICATIONS', false);
    $emailRecipients = env('USER_REGISTRATION_NOTIFICATION_EMAIL', 'Not configured');
    $whatsappNumbers = env('WHATSAPP_NOTIFICATION_NUMBER', 'Not configured');

    echo "   - Notifications enabled: " . ($notificationsEnabled ? '✅ YES' : '❌ NO') . "\n";
    echo "   - Email recipients: {$emailRecipients}\n";
    echo "   - WhatsApp numbers: {$whatsappNumbers}\n\n";

    echo "3. Testing WhatsApp service separately...\n";
    $whatsappService = new \App\Services\WhatsAppNotificationService();

    echo "   - Sending WhatsApp test message...\n";
    $whatsappService->sendUserRegistrationNotification($testUser);

    echo "   ✅ WhatsApp notification sent!\n\n";

    echo "🎉 Test completed successfully!\n";
    echo "📧 Check your email for the new user registration notification.\n";
    echo "📱 Check WhatsApp for the notification message.\n";
    echo "📋 Check logs for detailed information.\n\n";

    echo "💡 To enable/disable notifications, set SEND_USER_REGISTRATION_NOTIFICATIONS=true/false in .env\n";

} catch (\Exception $e) {
    echo "❌ Test failed with error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
