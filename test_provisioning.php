<?php

/**
 * Script de prueba para aprovisionar un pedido desde el carrito
 *
 * Uso: php test_provisioning.php
 */

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use App\Services\PartnerCenterProvisioningService;
use App\Models\Order;
use App\Models\Cart;
use App\Models\User;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Configuración
$userId = 1;
$cartToken = 'guVSSDoG7Ftt3rkd337GP4Ttg4sdQ22R'; // Cart ID 613

echo "\n=== SCRIPT DE PRUEBA DE APROVISIONAMIENTO ===\n\n";

try {
    // 1. Verificar usuario
    $user = User::find($userId);
    if (!$user) {
        die("Error: Usuario con ID {$userId} no encontrado\n");
    }
    echo "✓ Usuario encontrado: {$user->name} ({$user->email})\n";

    // 2. Buscar carrito
    $cart = Cart::where('cart_token', $cartToken)->first();
    if (!$cart) {
        die("Error: Carrito con token {$cartToken} no encontrado\n");
    }
    echo "✓ Carrito encontrado: ID {$cart->id}\n";

    // 3. Cargar carrito con relaciones necesarias para createFromCart
    $cartWithRelations = Cart::with(['items.product', 'store.currencies'])->find($cart->id);

    // Si el carrito no tiene store_id, asignar la primera tienda disponible
    if (!$cartWithRelations->store_id) {
        $defaultStore = DB::table('stores')->first();
        if ($defaultStore) {
            $cartWithRelations->store_id = $defaultStore->id;
            $cartWithRelations->save();
            echo "✓ Carrito actualizado con store (ID: {$defaultStore->id})\n";
        } else {
            die("Error: No hay stores configuradas en el sistema\n");
        }
        // Recargar con relaciones
        $cartWithRelations = Cart::with(['items.product', 'store.currencies'])->find($cart->id);
    }

    if (!$cartWithRelations || $cartWithRelations->items->isEmpty()) {
        die("Error: El carrito está vacío o no tiene items válidos\n");
    }

    echo "✓ Items en carrito: {$cartWithRelations->items->count()}\n";
    foreach ($cartWithRelations->items as $item) {
        $product = $item->product;
        echo "  - {$product->ProductTitle} (SKU: {$product->SkuId})\n";
        echo "    Cantidad: {$item->quantity} x $" . number_format($item->unit_price, 2) . " = $" . number_format($item->total_price, 2) . "\n";
    }

    // 4. Obtener billing information del usuario
    $billingInfo = DB::table('billing_information')
        ->where('user_id', $userId)
        ->first();

    if (!$billingInfo) {
        echo "⚠ Advertencia: Usuario no tiene información de facturación\n";
    }

    // Obtener cuenta Microsoft con ID 42 (recién creada)
    $microsoftAccount = DB::table('microsoft_accounts')
        ->where('id', 42)
        ->first();

    if (!$microsoftAccount) {
        die("Error: Cuenta Microsoft con ID 42 no encontrada\n");
    }
    echo "✓ Cuenta Microsoft encontrada: {$microsoftAccount->domain} (ID: {$microsoftAccount->id})\n";

    // 5. Crear pedido usando el método estático Order::createFromCart
    echo "\n--- CREANDO PEDIDO ---\n";

    $orderData = [
        'billing_information_id' => $billingInfo ? $billingInfo->id : null,
        'microsoft_account_id' => $microsoftAccount->id,
        'payment_method' => 'credit_card',
        'payment_status' => 'paid',
        'status' => 'processing',
        'notes' => 'Pedido de prueba - Cart Token: ' . $cartToken,
    ];

    // Usar el método estático que maneja precios automáticamente desde Product
    $order = Order::createFromCart($cartWithRelations, $orderData);

    echo "✓ Pedido creado: #{$order->order_number} (ID: {$order->id})\n";
    echo "  Subtotal: $" . number_format($order->subtotal, 2) . "\n";
    echo "  Impuestos: $" . number_format($order->tax_amount, 2) . "\n";
    echo "  Total: $" . number_format($order->total_amount, 2) . " {$order->currency->code}\n";
    echo "  Items: {$order->items->count()}\n";
    echo "  Estado: {$order->status}\n";
    echo "  Cuenta Microsoft ID: {$order->microsoft_account_id}\n";
    echo "  Estado: {$order->status} / {$order->fulfillment_status}\n";

    // 6. Aprovisionar en Microsoft
    echo "\n--- APROVISIONANDO EN MICROSOFT ---\n";

    // Verificar order_items antes de aprovisionar
    $orderItems = DB::table('order_items')->where('order_id', $order->id)->get();
    echo "Order items creados: {$orderItems->count()}\n";
    foreach ($orderItems as $item) {
        echo "  - Product ID: {$item->product_id}, SKU: {$item->sku_id}\n";
        echo "    Fulfillment status: " . ($item->fulfillment_status ?? 'NULL') . "\n";
    }

    // Verificar cart_items
    $cartItems = DB::table('cart_items')->where('cart_id', $cart->id)->get();
    echo "Cart items: {$cartItems->count()}\n";
    foreach ($cartItems as $item) {
        echo "  - Product ID: {$item->product_id}, Status: {$item->status}\n";
    }

    echo "\n";

    $provisioningService = app(PartnerCenterProvisioningService::class);

    try {
        $result = $provisioningService->processOrder($order->id);

        echo "\n✓✓✓ APROVISIONAMIENTO EXITOSO ✓✓✓\n";
        echo "Detalles:\n";

        if (is_array($result)) {
            echo "  - Estado: " . ($result['status'] ?? 'N/A') . "\n";
            echo "  - Mensaje: " . ($result['message'] ?? 'N/A') . "\n";

            if (isset($result['subscriptions'])) {
                echo "  - Suscripciones creadas: " . count($result['subscriptions']) . "\n";
                foreach ($result['subscriptions'] as $sub) {
                    echo "    * Subscription ID: {$sub['subscription_id']}\n";
                    echo "      Producto: {$sub['product_name']}\n";
                    echo "      Cantidad: {$sub['quantity']}\n";
                    if (isset($sub['auto_renew_enabled'])) {
                        echo "      Auto-renovación: " . ($sub['auto_renew_enabled'] ? 'SI' : 'NO') . "\n";
                    }
                }
            }
        } else {
            echo "  - Resultado: " . print_r($result, true) . "\n";
        }

        // 7. Verificar licencias creadas en la base de datos
        echo "\n--- VERIFICANDO LICENCIAS EN BD ---\n";
        $licenses = DB::table('subscriptions')
            ->where('order_id', $order->id)
            ->get();

        echo "✓ Licencias en BD: {$licenses->count()}\n";
        foreach ($licenses as $license) {
            echo "  - ID: {$license->id}\n";
            echo "    Subscription ID: {$license->subscription_id}\n";
            echo "    Producto ID: {$license->product_id}\n";
            echo "    Cantidad: {$license->quantity}\n";
            echo "    Estado: " . ($license->status ? 'Activa' : 'Inactiva') . "\n";
        }

    } catch (\Exception $e) {
        echo "\n✗✗✗ ERROR EN APROVISIONAMIENTO ✗✗✗\n";
        echo "Error: {$e->getMessage()}\n";
        echo "Archivo: {$e->getFile()}:{$e->getLine()}\n";
        echo "\nStack trace:\n{$e->getTraceAsString()}\n";

        // Marcar pedido como fallido
        $order->update([
            'fulfillment_status' => 'failed',
            'notes' => ($order->notes ?? '') . "\nError en aprovisionamiento: " . $e->getMessage()
        ]);
    }

} catch (\Exception $e) {
    echo "\n✗ ERROR GENERAL: {$e->getMessage()}\n";
    echo "Archivo: {$e->getFile()}:{$e->getLine()}\n";
}

echo "\n=== FIN DEL SCRIPT ===\n\n";
