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

        // Get products that need retry (failed or processing)
        $retryNeededItems = $order->orderItems()
                                 ->whereIn('fulfillment_status', ['failed', 'processing'])
                                 ->get();

        if ($retryNeededItems->isEmpty()) {
            $this->info("✅ No products need retry in order {$orderId} - all are fulfilled or pending");
            $this->showOrderSummary($order);
            return;
        }

        $this->info("📦 Order {$order->order_number}: Found {$retryNeededItems->count()} products that need retry");

        if ($dryRun) {
            $this->info("🔍 DRY RUN - Would retry these products:");
            $this->table(
                ['Product Title', 'SKU ID', 'Status', 'Error', 'Last Updated'],
                $retryNeededItems->map(function ($item) {
                    return [
                        $item->product_title ?? 'N/A',
                        $item->sku_id ?? 'N/A',
                        $item->fulfillment_status,
                        substr($item->fulfillment_error ?? 'No error message', 0, 50) . '...',
                        $item->updated_at->format('Y-m-d H:i:s')
                    ];
                })->toArray()
            );
            return;
        }

        // Confirm before retrying
        if (!$this->confirm("🔄 Do you want to retry {$retryNeededItems->count()} products for order {$order->order_number}?")) {
            $this->info("⏭️ Retry cancelled");
            return;
        }

        $this->info("🚀 Starting retry process...");

        // Use the intelligent processOrder method that only processes failed/pending products
        $provisioningService = new PartnerCenterProvisioningService();
        $result = $provisioningService->processOrder($orderId);

        // Display results
        $this->newLine();
        if ($result['success']) {
            $this->info("✅ " . $result['message']);
        } else {
            $this->warn("⚠️ " . $result['message']);
        }

        $this->newLine();
        $this->info("📊 PROCESSING SUMMARY:");
        $this->info("====================");
        $this->info("Total products in order: " . $result['total_products']);
        $this->info("Overall successful: " . $result['successful_products']);
        $this->info("Overall failed: " . $result['failed_products']);
        $this->info("Processed this run: " . ($result['products_processed_this_run'] ?? 0));
        $this->info("Successful this run: " . ($result['products_successful_this_run'] ?? 0));
        $this->info("Order status: " . $result['order_status']);
        $this->info("Fulfillment status: " . $result['fulfillment_status']);

        // Show detailed results if there were any products processed
        if (isset($result['product_details']) && !empty($result['product_details'])) {
            $this->newLine();
            $this->info("📋 PRODUCT DETAILS:");
            $this->info("===================");

            foreach ($result['product_details'] as $index => $product) {
                $status = $product['status'] === 'success' ? '✅ SUCCESS' : '❌ FAILED';
                $this->info(($index + 1) . ". {$product['product_title']} - {$status}");

                if ($product['status'] !== 'success' && !empty($product['error_message'])) {
                    $this->warn("   Error: " . $product['error_message']);
                }
            }
        }

        // Suggest next actions
        if ($result['successful_products'] < $result['total_products']) {
            $this->newLine();
            $this->warn("⚠️ Some products still need attention. You can run this command again to retry.");
            $this->info("💡 Commands:");
            $this->info("   - Retry again: php artisan products:retry-failed --order-id={$orderId}");
            $this->info("   - Check details: php artisan order:details {$orderId}");
        } else {
            $this->newLine();
            $this->info("🎉 All products are now successfully fulfilled!");
        }
    }

    /**
     * Show order summary
     */
    private function showOrderSummary(Order $order)
    {
        $totalItems = $order->orderItems->count();
        $fulfilledItems = $order->orderItems->where('fulfillment_status', 'fulfilled')->count();
        $failedItems = $order->orderItems->where('fulfillment_status', 'failed')->count();
        $processingItems = $order->orderItems->where('fulfillment_status', 'processing')->count();
        $pendingItems = $order->orderItems->where('fulfillment_status', 'pending')->count();

        $this->newLine();
        $this->info("📊 Current Order Status:");
        $this->info("Total products: {$totalItems}");
        $this->info("✅ Fulfilled: {$fulfilledItems}");
        $this->info("❌ Failed: {$failedItems}");
        $this->info("🔄 Processing: {$processingItems}");
        $this->info("⏳ Pending: {$pendingItems}");
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
