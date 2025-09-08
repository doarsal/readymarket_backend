<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\PartnerCenterProvisioningService;
use App\Models\Order;

class TestProvisioningScenario extends Command
{
    protected $signature = 'test:provisioning-scenario {order_id}';
    protected $description = 'Test provisioning scenario with detailed output';

    public function handle()
    {
        $orderId = $this->argument('order_id');

        $this->info("🧪 Testing provisioning scenario for Order ID: {$orderId}");
        $this->info("===============================================");

        try {
            $service = new PartnerCenterProvisioningService();
            $result = $service->processOrder($orderId);

            // Display overall result
            $this->newLine();
            $this->info("📊 RESULTADO GENERAL:");
            $this->info("Success: " . ($result['success'] ? '✅ SI' : '❌ NO'));
            $this->info("Message: " . $result['message']);

            if (isset($result['order_status'])) {
                $this->info("Order Status: " . $result['order_status']);
                $this->info("Fulfillment Status: " . $result['fulfillment_status']);
            }

            // Display product details
            $this->newLine();
            $this->info("📦 DETALLES POR PRODUCTO:");
            $this->info("==========================");

            if (isset($result['product_details']) && is_array($result['product_details'])) {
                foreach ($result['product_details'] as $index => $product) {
                    $this->newLine();
                    $this->info("Producto " . ($index + 1) . ":");
                    $this->info("  - ID: " . $product['product_id']);
                    $this->info("  - Título: " . $product['product_title']);
                    $this->info("  - Cantidad: " . $product['quantity']);
                    $this->info("  - Estado: " . ($product['status'] === 'success' ? '✅ EXITOSO' : '❌ FALLIDO'));
                    $this->info("  - Procesado: " . $product['processed_at']);

                    if ($product['status'] === 'success') {
                        if (isset($product['subscription_id'])) {
                            $this->info("  - Subscription ID: " . $product['subscription_id']);
                        }
                        if (isset($product['microsoft_cart_id'])) {
                            $this->info("  - Microsoft Cart ID: " . $product['microsoft_cart_id']);
                        }
                    } else {
                        $this->error("  - Error: " . $product['error_message']);
                        if (isset($product['microsoft_error_details']) && !empty($product['microsoft_error_details'])) {
                            $this->error("  - Detalles Microsoft: " . json_encode($product['microsoft_error_details'], JSON_PRETTY_PRINT));
                        }
                    }
                }
            }

            // Display summary
            $this->newLine();
            $this->info("📈 RESUMEN:");
            $this->info("===========");
            $this->info("Total productos: " . ($result['total_products'] ?? 0));
            $this->info("Productos exitosos: " . ($result['successful_products'] ?? 0));
            $this->info("Productos fallidos: " . ($result['failed_products'] ?? 0));

            // Show raw provisioning results if available
            if (isset($result['provisioning_results']) && $this->option('verbose')) {
                $this->newLine();
                $this->info("🔍 RESULTADOS TÉCNICOS (VERBOSE):");
                $this->info("===================================");
                $this->line(json_encode($result['provisioning_results'], JSON_PRETTY_PRINT));
            }

        } catch (\Exception $e) {
            $this->error("❌ Error al procesar la orden:");
            $this->error($e->getMessage());
            $this->error("Stack trace:");
            $this->error($e->getTraceAsString());
        }
    }
}
