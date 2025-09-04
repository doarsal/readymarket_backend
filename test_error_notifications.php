<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Services\PartnerCenterProvisioningService;

// Cargar las variables de entorno
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🧪 PRUEBA NOTIFICACIONES DE ERROR DE MICROSOFT\n";
echo "=============================================\n\n";

echo "📧 Email configurado: " . env('MICROSOFT_ERROR_NOTIFICATION_EMAIL') . "\n";
echo "📱 WhatsApp configurado: " . env('WHATSAPP_NOTIFICATION_NUMBER') . "\n";
echo "🔗 Endpoint Microsoft (modo error): " . env('MICROSOFT_TOKEN_ENDPOINT', 'N/A') . "\n\n";

$service = new PartnerCenterProvisioningService();

try {
    echo "🚀 Procesando orden que debería fallar...\n";
    echo "🎯 Esto debería enviar notificaciones de error\n\n";

    $result = $service->processOrder(20);

    if (!$result['success']) {
        echo "❌ ERROR CAPTURADO (esperado):\n";
        echo "Error: " . substr($result['message'], 0, 100) . "...\n\n";

        echo "📋 Verificar:\n";
        echo "✉️  Email enviado a: " . env('MICROSOFT_ERROR_NOTIFICATION_EMAIL') . "\n";
        echo "📱 WhatsApp enviado a: " . env('WHATSAPP_NOTIFICATION_NUMBER') . "\n\n";

        echo "📄 Revisa los logs:\n";
        echo "- storage/logs/laravel.log (para errores generales)\n";
        echo "- storage/logs/partner_center_" . date('Y-m-d') . ".log (para detalles específicos)\n";

    } else {
        echo "⚠️  Inesperado: El procesamiento fue exitoso\n";
    }

} catch (Exception $e) {
    echo "💥 EXCEPCIÓN: " . $e->getMessage() . "\n";
}

echo "\n🏁 Prueba completada.\n";
echo "👀 Verifica tu email y WhatsApp para confirmar que llegaron las notificaciones.\n";
