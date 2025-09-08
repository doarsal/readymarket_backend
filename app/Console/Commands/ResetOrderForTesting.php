<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use App\Models\OrderItem;

class ResetOrderForTesting extends Command
{
    protected $signature = 'test:reset-order {order_id}';
    protected $description = 'Reset an order for testing purposes';

    public function handle()
    {
        $orderId = $this->argument('order_id');

        try {
            $order = Order::find($orderId);

            if (!$order) {
                $this->error("❌ Order not found: {$orderId}");
                return 1;
            }

            $this->info("🔄 Resetting order {$order->order_number} (ID: {$orderId})");

            // Reset order status
            $order->update([
                'status' => 'processing',
                'fulfillment_status' => null
            ]);

            // Delete existing order items
            $deletedItems = OrderItem::where('order_id', $orderId)->count();
            OrderItem::where('order_id', $orderId)->delete();

            $this->info("✅ Order reset successfully");
            $this->info("   - Status: processing");
            $this->info("   - Fulfillment status: null");
            $this->info("   - Deleted order items: {$deletedItems}");

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error resetting order: " . $e->getMessage());
            return 1;
        }
    }
}
