<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use App\Models\OrderItem;

class ShowOrderDetails extends Command
{
    protected $signature = 'order:details {order_id}';
    protected $description = 'Show detailed order information including product statuses and errors';

    public function handle()
    {
        $orderId = $this->argument('order_id');

        $this->info("📋 DETALLES DE ORDEN: {$orderId}");
        $this->info("===============================");

        try {
            $order = Order::with([
                'cart.items.product',
                'microsoftAccount'
            ])->find($orderId);

            if (!$order) {
                $this->error("❌ Orden no encontrada: {$orderId}");
                return 1;
            }

            // Order basic info
            $this->newLine();
            $this->info("📊 INFORMACIÓN GENERAL:");
            $this->info("Order Number: " . $order->order_number);
            $this->info("Status: " . $order->status);
            $this->info("Fulfillment Status: " . ($order->fulfillment_status ?? 'N/A'));
            $this->info("Created: " . $order->created_at);
            $this->info("Updated: " . $order->updated_at);

            if ($order->microsoftAccount) {
                $this->info("Microsoft Customer ID: " . $order->microsoftAccount->microsoft_customer_id);
            }

            // Get order items with current status
            $orderItems = OrderItem::where('order_id', $orderId)->get();

            if ($orderItems->isEmpty()) {
                $this->warn("⚠️ No se encontraron order_items para esta orden");

                // Show cart items instead
                if ($order->cart && $order->cart->items) {
                    $this->newLine();
                    $this->info("📦 PRODUCTOS EN CART (Sin tracking de fulfillment):");
                    $this->info("================================================");

                    foreach ($order->cart->items as $index => $cartItem) {
                        $this->newLine();
                        $this->info("Producto " . ($index + 1) . ":");
                        $this->info("  - Cart Item ID: " . $cartItem->id);
                        $this->info("  - Product ID: " . $cartItem->product_id);
                        $this->info("  - Título: " . ($cartItem->product->ProductTitle ?? 'N/A'));
                        $this->info("  - SKU: " . ($cartItem->product->SkuId ?? 'N/A'));
                        $this->info("  - Cantidad: " . $cartItem->quantity);
                        $this->info("  - Precio: $" . $cartItem->price);
                        $this->warn("  - Estado: Sin tracking (order_item no creado)");
                    }
                }
            } else {
                $this->newLine();
                $this->info("📦 PRODUCTOS Y ESTADOS DE FULFILLMENT:");
                $this->info("=====================================");

                foreach ($orderItems as $index => $orderItem) {
                    $cartItem = $order->cart->items->where('id', $orderItem->cart_item_id)->first();
                    $product = $cartItem ? $cartItem->product : null;

                    $this->newLine();
                    $this->info("Producto " . ($index + 1) . ":");
                    $this->info("  - Order Item ID: " . $orderItem->id);
                    $this->info("  - Cart Item ID: " . $orderItem->cart_item_id);
                    $this->info("  - Product ID: " . $orderItem->product_id);
                    $this->info("  - Título: " . ($product->ProductTitle ?? 'N/A'));
                    $this->info("  - SKU: " . ($product->SkuId ?? 'N/A'));
                    $this->info("  - Cantidad: " . $orderItem->quantity);
                    $this->info("  - Precio: $" . $orderItem->price);

                    // Status with colors
                    $status = $orderItem->fulfillment_status ?? 'pending';
                    $statusDisplay = match($status) {
                        'fulfilled' => '✅ CUMPLIDO',
                        'processing' => '🔄 PROCESANDO',
                        'failed' => '❌ FALLIDO',
                        'pending' => '⏳ PENDIENTE',
                        default => "🔍 {$status}"
                    };
                    $this->info("  - Estado: " . $statusDisplay);

                    if ($orderItem->processing_started_at) {
                        $this->info("  - Procesamiento iniciado: " . $orderItem->processing_started_at);
                    }

                    if ($orderItem->fulfillment_error) {
                        $this->error("  - Error: " . $orderItem->fulfillment_error);
                    }

                    if ($orderItem->microsoft_subscription_id) {
                        $this->info("  - Microsoft Subscription ID: " . $orderItem->microsoft_subscription_id);
                    }
                }
            }

            // Summary
            $this->newLine();
            $this->info("📈 RESUMEN:");
            $this->info("===========");

            if (!$orderItems->isEmpty()) {
                $totalItems = $orderItems->count();
                $fulfilledItems = $orderItems->where('fulfillment_status', 'fulfilled')->count();
                $failedItems = $orderItems->where('fulfillment_status', 'failed')->count();
                $processingItems = $orderItems->where('fulfillment_status', 'processing')->count();
                $pendingItems = $orderItems->where('fulfillment_status', 'pending')->count();

                $this->info("Total productos: {$totalItems}");
                $this->info("✅ Cumplidos: {$fulfilledItems}");
                $this->info("❌ Fallidos: {$failedItems}");
                $this->info("🔄 Procesando: {$processingItems}");
                $this->info("⏳ Pendientes: {$pendingItems}");

                if ($failedItems > 0) {
                    $this->newLine();
                    $this->error("💡 TIP: Usa 'php artisan retry:failed-products {$orderId}' para reintentar productos fallidos");
                }
            } else {
                $cartItemsCount = $order->cart ? $order->cart->items->count() : 0;
                $this->info("Productos en cart: {$cartItemsCount}");
                $this->warn("Sin tracking de fulfillment individual");
            }

            $this->newLine();
            $this->info("💡 Comandos útiles:");
            $this->info("- Ver reporte de productos: php artisan products:report");
            $this->info("- Reintentar fallidos: php artisan retry:failed-products {$orderId}");
            $this->info("- Probar escenario: php artisan test:provisioning-scenario {$orderId}");

        } catch (\Exception $e) {
            $this->error("❌ Error al obtener detalles de la orden:");
            $this->error($e->getMessage());
            return 1;
        }

        return 0;
    }
}
