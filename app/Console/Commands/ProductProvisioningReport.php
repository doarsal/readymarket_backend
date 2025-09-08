<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;

class ProductProvisioningReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:report
                            {--days=7 : Number of days to look back}
                            {--format=table : Output format (table, json, csv)}
                            {--order-id= : Specific order ID to analyze}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate provisioning success/failure report';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = $this->option('days');
        $format = $this->option('format');
        $orderId = $this->option('order-id');

        $this->info('📊 Generating Product Provisioning Report...');
        $this->info('═══════════════════════════════════════════');

        if ($orderId) {
            $this->generateOrderReport($orderId, $format);
        } else {
            $this->generateOverallReport($days, $format);
        }
    }

    private function generateOrderReport($orderId, $format)
    {
        $order = Order::with(['orderItems', 'user', 'microsoftAccount'])
                     ->find($orderId);

        if (!$order) {
            $this->error("❌ Order {$orderId} not found");
            return;
        }

        $this->info("📦 Order Details: {$order->order_number}");
        $this->info("👤 Customer: " . ($order->user->name ?? 'N/A') . " (" . ($order->user->email ?? 'N/A') . ")");
        $this->info("🏢 Microsoft Account: " . ($order->microsoftAccount->domain_concatenated ?? 'N/A'));
        $this->info("💰 Total: $" . number_format($order->total_amount, 2));
        $this->info("📅 Created: {$order->created_at->format('Y-m-d H:i:s')}");
        $this->info("📋 Status: {$order->status} / {$order->fulfillment_status}");
        $this->newLine();

        $items = $order->orderItems;

        $summary = [
            'total' => $items->count(),
            'fulfilled' => $items->where('fulfillment_status', 'fulfilled')->count(),
            'failed' => $items->where('fulfillment_status', 'failed')->count(),
            'pending' => $items->where('fulfillment_status', 'pending')->count(),
            'processing' => $items->where('fulfillment_status', 'processing')->count(),
        ];

        $this->info("📊 Summary:");
        $this->info("   Total Products: {$summary['total']}");
        $this->info("   ✅ Fulfilled: {$summary['fulfilled']}");
        $this->info("   ❌ Failed: {$summary['failed']}");
        $this->info("   ⏳ Pending: {$summary['pending']}");
        $this->info("   🔄 Processing: {$summary['processing']}");
        $this->newLine();

        if ($format === 'table') {
            $this->table(
                ['Product', 'SKU', 'Qty', 'Status', 'Error', 'Processed At'],
                $items->map(function ($item) {
                    return [
                        substr($item->product_title, 0, 30),
                        $item->sku_id,
                        $item->quantity,
                        $this->getStatusIcon($item->fulfillment_status),
                        $item->fulfillment_error ? substr($item->fulfillment_error, 0, 40) . '...' : '-',
                        $item->fulfilled_at ? $item->fulfilled_at->format('m-d H:i') :
                            ($item->processing_started_at ? $item->processing_started_at->format('m-d H:i') : '-')
                    ];
                })->toArray()
            );
        }

        // Show detailed errors for failed items
        $failedItems = $items->where('fulfillment_status', 'failed');
        if ($failedItems->count() > 0) {
            $this->warn("❌ Failed Products Details:");
            foreach ($failedItems as $item) {
                $this->warn("   • {$item->product_title} ({$item->sku_id})");
                $this->warn("     Error: {$item->fulfillment_error}");
                $this->warn("     Updated: {$item->updated_at->format('Y-m-d H:i:s')}");
                $this->newLine();
            }
        }
    }

    private function generateOverallReport($days, $format)
    {
        $fromDate = now()->subDays($days);

        // Overall statistics
        $stats = $this->getOverallStats($fromDate);

        $this->info("📅 Period: Last {$days} days (from {$fromDate->format('Y-m-d')})");
        $this->newLine();

        $this->info("📈 Overall Statistics:");
        $this->info("   Orders Processed: {$stats['total_orders']}");
        $this->info("   Products Attempted: {$stats['total_products']}");
        $this->info("   ✅ Success Rate: " . number_format($stats['success_rate'], 1) . "%");
        $this->info("   ❌ Failure Rate: " . number_format($stats['failure_rate'], 1) . "%");
        $this->newLine();

        // Orders breakdown
        $orderStats = $this->getOrderStats($fromDate);
        $this->info("📦 Orders Breakdown:");
        $this->table(
            ['Status', 'Count', 'Percentage'],
            collect($orderStats)->map(function ($stat) {
                return [
                    $this->getStatusIcon($stat->fulfillment_status) . ' ' . ucfirst($stat->fulfillment_status),
                    $stat->count,
                    number_format(($stat->count / $stats['total_orders']) * 100, 1) . '%'
                ];
            })->toArray()
        );

        // Product success rates
        $productStats = $this->getProductStats($fromDate);
        if ($productStats->count() > 0) {
            $this->info("🛍️ Top Products by Attempts (min 3 attempts):");
            $this->table(
                ['Product', 'SKU', 'Total', 'Success', 'Failed', 'Rate'],
                $productStats->take(10)->map(function ($stat) {
                    return [
                        substr($stat->product_title, 0, 25),
                        $stat->sku_id,
                        $stat->total_attempts,
                        $stat->successful,
                        $stat->failed,
                        number_format($stat->success_rate, 1) . '%'
                    ];
                })->toArray()
            );
        }

        // Recent failures
        $recentFailures = $this->getRecentFailures($fromDate);
        if ($recentFailures->count() > 0) {
            $this->warn("🚨 Recent Failures (Last 10):");
            $this->table(
                ['Order', 'Product', 'Error', 'When'],
                $recentFailures->take(10)->map(function ($failure) {
                    return [
                        $failure->order_number,
                        substr($failure->product_title, 0, 20),
                        substr($failure->fulfillment_error, 0, 40) . '...',
                        $failure->updated_at->diffForHumans()
                    ];
                })->toArray()
            );
        }

        // Common errors
        $commonErrors = $this->getCommonErrors($fromDate);
        if ($commonErrors->count() > 0) {
            $this->warn("🔍 Most Common Errors:");
            $this->table(
                ['Error Type', 'Count', 'Example'],
                $commonErrors->take(5)->map(function ($error) {
                    return [
                        $this->categorizeError($error->fulfillment_error),
                        $error->count,
                        substr($error->fulfillment_error, 0, 50) . '...'
                    ];
                })->toArray()
            );
        }
    }

    private function getOverallStats($fromDate)
    {
        $totalOrders = Order::where('created_at', '>=', $fromDate)->count();

        $productStats = OrderItem::where('created_at', '>=', $fromDate)
                                ->selectRaw('
                                    COUNT(*) as total_products,
                                    SUM(CASE WHEN fulfillment_status = "fulfilled" THEN 1 ELSE 0 END) as successful,
                                    SUM(CASE WHEN fulfillment_status = "failed" THEN 1 ELSE 0 END) as failed
                                ')
                                ->first();

        $successRate = $productStats->total_products > 0
            ? ($productStats->successful / $productStats->total_products) * 100
            : 0;

        return [
            'total_orders' => $totalOrders,
            'total_products' => $productStats->total_products,
            'successful_products' => $productStats->successful,
            'failed_products' => $productStats->failed,
            'success_rate' => $successRate,
            'failure_rate' => 100 - $successRate
        ];
    }

    private function getOrderStats($fromDate)
    {
        return Order::where('created_at', '>=', $fromDate)
                   ->groupBy('fulfillment_status')
                   ->selectRaw('fulfillment_status, COUNT(*) as count')
                   ->get();
    }

    private function getProductStats($fromDate)
    {
        return OrderItem::where('created_at', '>=', $fromDate)
                       ->groupBy('sku_id', 'product_title')
                       ->havingRaw('COUNT(*) >= 3')
                       ->selectRaw('
                           sku_id,
                           product_title,
                           COUNT(*) as total_attempts,
                           SUM(CASE WHEN fulfillment_status = "fulfilled" THEN 1 ELSE 0 END) as successful,
                           SUM(CASE WHEN fulfillment_status = "failed" THEN 1 ELSE 0 END) as failed,
                           ROUND((SUM(CASE WHEN fulfillment_status = "fulfilled" THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as success_rate
                       ')
                       ->orderBy('total_attempts', 'desc')
                       ->get();
    }

    private function getRecentFailures($fromDate)
    {
        return OrderItem::join('orders', 'order_items.order_id', '=', 'orders.id')
                       ->where('order_items.fulfillment_status', 'failed')
                       ->where('order_items.updated_at', '>=', $fromDate)
                       ->select('order_items.*', 'orders.order_number')
                       ->orderBy('order_items.updated_at', 'desc')
                       ->get();
    }

    private function getCommonErrors($fromDate)
    {
        return OrderItem::where('fulfillment_status', 'failed')
                       ->where('updated_at', '>=', $fromDate)
                       ->whereNotNull('fulfillment_error')
                       ->groupBy('fulfillment_error')
                       ->selectRaw('fulfillment_error, COUNT(*) as count')
                       ->orderBy('count', 'desc')
                       ->get();
    }

    private function getStatusIcon($status)
    {
        return match($status) {
            'fulfilled' => '✅',
            'failed' => '❌',
            'pending' => '⏳',
            'processing' => '🔄',
            'partially_fulfilled' => '⚠️',
            default => '❓'
        };
    }

    private function categorizeError($error)
    {
        if (str_contains($error, 'Catalogitem Id') && str_contains($error, 'invalid')) {
            return 'Invalid Catalog ID';
        }
        if (str_contains($error, 'TermDuration')) {
            return 'Invalid Term Duration';
        }
        if (str_contains($error, 'token') || str_contains($error, 'authentication')) {
            return 'Authentication Error';
        }
        if (str_contains($error, 'timeout')) {
            return 'Timeout Error';
        }
        if (str_contains($error, 'HTTP')) {
            return 'HTTP Error';
        }
        return 'Other Error';
    }
}
