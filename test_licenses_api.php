<?php

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Subscription;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$userId = 1;

echo "\n=== PRUEBA DE API DE LICENCIAS ===\n\n";

try {
    // 1. Obtener usuario
    $user = User::find($userId);
    if (!$user) {
        die("Error: Usuario no encontrado\n");
    }
    echo "✓ Usuario: {$user->name} ({$user->email})\n\n";

    // 2. Obtener suscripciones del usuario a través de orders
    echo "--- CONSULTANDO SUSCRIPCIONES ---\n";

    $subscriptions = Subscription::whereHas('order', function($query) use ($user) {
        $query->where('user_id', $user->id);
    })
    ->with([
        'product:idproduct,ProductTitle,SkuTitle,Publisher,BillingPlan,prod_icon,TermDuration',
        'order:id,order_number,created_at,total_amount',
        'microsoftAccount:id,domain_concatenated,organization'
    ])
    ->get();

    echo "✓ Suscripciones encontradas: {$subscriptions->count()}\n\n";

    foreach ($subscriptions as $sub) {
        echo "==============================================\n";
        echo "ID: {$sub->id}\n";
        echo "Subscription ID: {$sub->subscription_id}\n";
        echo "Producto: " . ($sub->product ? $sub->product->ProductTitle : 'N/A') . "\n";
        echo "SKU: " . ($sub->product ? $sub->product->SkuTitle : 'N/A') . "\n";
        echo "Publisher: " . ($sub->product ? $sub->product->Publisher : 'N/A') . "\n";
        echo "Billing Plan: " . ($sub->product ? $sub->product->BillingPlan : 'N/A') . "\n";
        echo "Cantidad: {$sub->quantity}\n";
        echo "Estado: " . ($sub->status ? 'Activa' : 'Inactiva') . "\n";
        echo "Orden: " . ($sub->order ? $sub->order->order_number : 'N/A') . "\n";
        echo "Cuenta Microsoft: " . ($sub->microsoftAccount ? $sub->microsoftAccount->domain_concatenated : 'N/A') . "\n";
        echo "Organización: " . ($sub->microsoftAccount ? $sub->microsoftAccount->organization : 'N/A') . "\n";
        echo "Creada: {$sub->created_at}\n";
        echo "==============================================\n\n";
    }

    // 3. Probar el recurso LicenseResource
    echo "\n--- PROBANDO LicenseResource ---\n";

    $resource = \App\Http\Resources\LicenseResource::collection($subscriptions);
    $data = $resource->resolve();

    echo "✓ Recursos transformados: " . count($data) . "\n";

    foreach ($data as $license) {
        echo "\nLicencia transformada:\n";
        echo "  - ID: {$license['id']}\n";
        echo "  - Producto: {$license['product_name']}\n";
        echo "  - Tipo: {$license['license_type']}\n";
        echo "  - Estado: {$license['status']}\n";
        echo "  - Renovable: " . ($license['is_renewable'] ? 'Sí' : 'No') . "\n";
        if (isset($license['renewal_info'])) {
            echo "  - Info renovación:\n";
            echo "    * Fecha próxima: {$license['renewal_info']['next_renewal_date']}\n";
            echo "    * Días restantes: {$license['renewal_info']['days_until_renewal']}\n";
        }
    }

    echo "\n✅ PRUEBA EXITOSA\n\n";

} catch (\Exception $e) {
    echo "\n❌ ERROR: {$e->getMessage()}\n";
    echo "Archivo: {$e->getFile()}:{$e->getLine()}\n";
    echo "\nStack trace:\n{$e->getTraceAsString()}\n";
}

echo "\n=== FIN DE LA PRUEBA ===\n\n";
