<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\PartnerCenterProvisioningService;

class RetryFailedProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:retry-failed
                            {--order-id= : Specific order ID to retry}
                            {--hours=24 : Hours to look back for failed products}
                            {--dry-run : Show what would be retried without actually retrying}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Retry provisioning of failed products';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $orderId = $this->option('order-id');
        $hours = $this->option('hours');
        $dryRun = $this->option('dry-run');

        $this->info('🔄 Starting failed products retry process...');

        if ($orderId) {
            $this->retrySpecificOrder($orderId, $dryRun);
        } else {
            $this->retryRecentFailedProducts($hours, $dryRun);
        }
    }

    private function retrySpecificOrder($orderId, $dryRun)
    {
        $order = Order::with(['orderItems', 'cart.items.product', 'microsoftAccount'])
                     ->find($orderId);

        if (!$order) {
            $this->error("❌ Order {$orderId} not found");
            return;
        }

        $failedItems = $order->orderItems()
                            ->where('fulfillment_status', 'failed')
                            ->get();

        if ($failedItems->isEmpty()) {
            $this->info("✅ No failed products found in order {$orderId}");
            return;
        }

        $this->info("📦 Order {$order->order_number}: Found {$failedItems->count()} failed products");

        if ($dryRun) {
            $this->table(
                ['Product Title', 'SKU ID', 'Error', 'Failed At'],
                $failedItems->map(function ($item) {
                    return [
                        $item->product_title,
                        $item->sku_id,
                        substr($item->fulfillment_error ?? 'No error message', 0, 50) . '...',
                        $item->updated_at->format('Y-m-d H:i:s')
                    ];
                })->toArray()
            );
            return;
        }

        // Retry provisioning for this order
        $this->retryOrderProvisioning($order);
    }

    private function retryRecentFailedProducts($hours, $dryRun)
    {
        $failedItems = OrderItem::with(['order.cart.items.product', 'order.microsoftAccount'])
                               ->where('fulfillment_status', 'failed')
                               ->where('updated_at', '>=', now()->subHours($hours))
                               ->get()
                               ->groupBy('order_id');

        if ($failedItems->isEmpty()) {
            $this->info("✅ No failed products found in the last {$hours} hours");
            return;
        }

        $this->info("📦 Found failed products in {$failedItems->count()} orders from the last {$hours} hours");

        if ($dryRun) {
            foreach ($failedItems as $orderId => $items) {
                $order = $items->first()->order;
                $this->info("Order {$order->order_number}: {$items->count()} failed products");

                $this->table(
                    ['Product Title', 'SKU ID', 'Error'],
                    $items->map(function ($item) {
                        return [
                            $item->product_title,
                            $item->sku_id,
                            substr($item->fulfillment_error ?? 'No error message', 0, 50) . '...'
                        ];
                    })->toArray()
                );
            }
            return;
        }

        // Retry each order
        foreach ($failedItems as $orderId => $items) {
            $order = $items->first()->order;
            $this->info("🔄 Retrying order {$order->order_number}...");
            $this->retryOrderProvisioning($order);
        }
    }

    private function retryOrderProvisioning($order)
    {
        try {
            // Reset failed items to pending
            $failedItems = $order->orderItems()
                                ->where('fulfillment_status', 'failed')
                                ->get();

            foreach ($failedItems as $item) {
                $item->update([
                    'fulfillment_status' => 'pending',
                    'fulfillment_error' => null,
                    'processing_started_at' => null,
                    'fulfilled_at' => null
                ]);
            }

            // Change order status to processing
            $order->update(['status' => 'processing']);

            // Retry provisioning
            $provisioningService = new PartnerCenterProvisioningService();
            $result = $provisioningService->processOrder($order->id);

            if ($result['success']) {
                $this->info("✅ Order {$order->order_number}: {$result['message']}");

                if (isset($result['provisioning_results'])) {
                    $successful = collect($result['provisioning_results'])->where('success', true)->count();
                    $failed = collect($result['provisioning_results'])->where('success', false)->count();

                    $this->info("   📊 Results: {$successful} successful, {$failed} failed");

                    // Show failed products details
                    $stillFailed = collect($result['provisioning_results'])->where('success', false);
                    if ($stillFailed->count() > 0) {
                        $this->warn("   ⚠️  Still failed products:");
                        foreach ($stillFailed as $failed) {
                            $this->warn("      - {$failed['product_title']}: {$failed['error_message']}");
                        }
                    }
                }
            } else {
                $this->error("❌ Order {$order->order_number}: {$result['message']}");
            }

        } catch (\Exception $e) {
            $this->error("❌ Error retrying order {$order->order_number}: " . $e->getMessage());
        }
    }
}
