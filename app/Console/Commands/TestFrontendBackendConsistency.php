<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestFrontendBackendConsistency extends Command
{
    protected $signature = 'test:frontend-backend-consistency {--base-url=http://localhost:5173}';
    protected $description = 'Test price consistency between frontend and backend for cart and products';

    public function handle(): int
    {
        $baseUrl = $this->option('base-url');
        $backendUrl = config('app.url');

        $this->info("🔍 Probando consistencia Frontend-Backend");
        $this->info("Frontend: {$baseUrl}");
        $this->info("Backend: {$backendUrl}");

        // Test 1: Obtener productos desde la API
        $this->info("\n📦 Test 1: Verificando precios de productos desde la API");
        $this->testProductPrices();

        // Test 2: Verificar carrito
        $this->info("\n🛒 Test 2: Verificando precios del carrito");
        $this->testCartPrices();

        $this->info("\n✅ Tests completados");
        return 0;
    }

    private function testProductPrices(): void
    {
        try {
            $response = Http::get(config('app.url') . '/api/v1/products?per_page=3');

            if (!$response->successful()) {
                $this->error("❌ Error al obtener productos: " . $response->status());
                return;
            }

            $data = $response->json();

            if (!$data['success']) {
                $this->error("❌ API retornó error: " . ($data['message'] ?? 'Unknown error'));
                return;
            }

            $this->info("📊 Analizando " . count($data['data']) . " productos...");

            foreach ($data['data'] as $product) {
                $this->line("\n📦 Producto: " . $product['title']);

                // Precio del producto principal
                if (isset($product['price'])) {
                    $this->line("   💰 Precio principal:");
                    $this->line("      - Convertido: " . $product['price']['formatted']);
                    $this->line("      - Original: " . $product['price']['original_amount'] . " " . $product['price']['original_currency']);
                    $this->line("      - Tasa: " . number_format($product['price']['exchange_rate'], 4));
                }

                // Precios de variantes
                if (isset($product['variants']) && count($product['variants']) > 0) {
                    $this->line("   🔄 Variantes (" . count($product['variants']) . "):");

                    foreach (array_slice($product['variants'], 0, 2) as $variant) {
                        $this->line("      - " . ($variant['billing_plan'] ?? 'N/A') . ": " .
                                  ($variant['price']['formatted'] ?? $variant['formatted_price'] ?? 'N/A'));

                        // Verificar consistencia
                        $backendPrice = $variant['price']['amount'] ?? $variant['unit_price'] ?? 0;
                        $legacyPrice = $variant['unit_price'] ?? 0;

                        if (abs($backendPrice - $legacyPrice) > 0.01) {
                            $this->warn("        ⚠️ Inconsistencia detectada: Backend={$backendPrice}, Legacy={$legacyPrice}");
                        } else {
                            $this->info("        ✅ Precios consistentes");
                        }
                    }
                }
            }

            // Verificar información de moneda
            if (isset($data['currency_info'])) {
                $this->line("\n💱 Información de moneda:");
                $this->line("   - Store ID: " . $data['currency_info']['store_id']);
                $this->line("   - Moneda por defecto: " . $data['currency_info']['default_currency']);
            }

        } catch (\Exception $e) {
            $this->error("❌ Error en test de productos: " . $e->getMessage());
        }
    }

    private function testCartPrices(): void
    {
        try {
            // Primero intentar obtener un carrito existente
            $response = Http::get(config('app.url') . '/api/v1/cart');

            if (!$response->successful()) {
                $this->warn("⚠️ No se pudo obtener carrito: " . $response->status());
                return;
            }

            $data = $response->json();

            if (!$data['success']) {
                $this->warn("⚠️ Error al obtener carrito: " . ($data['message'] ?? 'Unknown error'));
                return;
            }

            $cart = $data['data'];

            if (!isset($cart['items']) || count($cart['items']) === 0) {
                $this->warn("⚠️ Carrito vacío, no se puede verificar consistencia");
                return;
            }

            $this->line("🛒 Carrito ID: " . ($cart['id'] ?? 'N/A'));
            $this->line("💱 Moneda: " . ($cart['currency_code'] ?? 'N/A'));

            foreach ($cart['items'] as $item) {
                if (!isset($item['product'])) continue;

                $this->line("\n   📦 " . $item['product']['title']);
                $this->line("      - Cantidad: " . $item['quantity']);
                $this->line("      - Precio unitario: " . $item['unit_price'] . " " . ($cart['currency_code'] ?? ''));
                $this->line("      - Precio total: " . $item['total_price'] . " " . ($cart['currency_code'] ?? ''));

                // Verificar cálculo
                $unitPrice = (float) str_replace(',', '', $item['unit_price']);
                $calculatedTotal = $unitPrice * $item['quantity'];
                $actualTotal = (float) str_replace(',', '', $item['total_price']);

                if (abs($calculatedTotal - $actualTotal) > 0.01) {
                    $this->error("      ❌ Error en cálculo: {$calculatedTotal} vs {$actualTotal}");
                } else {
                    $this->info("      ✅ Cálculo correcto");
                }
            }

            // Verificar totales del carrito
            $this->line("\n📊 Totales del carrito:");
            $this->line("   - Subtotal: " . ($cart['subtotal'] ?? 'N/A'));
            $this->line("   - Impuestos: " . ($cart['tax_amount'] ?? 'N/A'));
            $this->line("   - Total: " . ($cart['total_amount'] ?? 'N/A'));

        } catch (\Exception $e) {
            $this->error("❌ Error en test de carrito: " . $e->getMessage());
        }
    }
}
