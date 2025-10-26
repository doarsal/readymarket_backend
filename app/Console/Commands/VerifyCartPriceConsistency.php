<?php

namespace App\Console\Commands;

use App\Models\Cart;
use App\Models\Product;
use App\Services\CurrencyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class VerifyCartPriceConsistency extends Command
{
    protected $signature = 'cart:verify-prices {--store-id=1} {--debug}';
    protected $description = 'Verify price consistency between products and cart items';

    protected CurrencyService $currencyService;

    public function __construct(CurrencyService $currencyService)
    {
        parent::__construct();
        $this->currencyService = $currencyService;
    }

    public function handle(): int
    {
        $storeId = (int) $this->option('store-id');
        $debug = $this->option('debug');

        $this->info("🔍 Verificando consistencia de precios para Store ID: {$storeId}");

        // Obtener algunos productos de muestra
        $products = Product::where('is_active', 1)
                          ->whereNotNull('UnitPrice')
                          ->where('UnitPrice', '>', '0')
                          ->take(5)
                          ->get();

        if ($products->isEmpty()) {
            $this->warn("⚠️ No se encontraron productos activos para verificar");
            return 0;
        }

        $this->info("📦 Verificando " . $products->count() . " productos...\n");

        foreach ($products as $product) {
            $this->verifyProductPricing($product, $storeId, $debug);
        }

        // Verificar carritos activos
        $this->info("\n🛒 Verificando carritos activos...");
        $activeCarts = Cart::where('status', 'active')
                          ->with(['items.product'])
                          ->take(3)
                          ->get();

        foreach ($activeCarts as $cart) {
            $this->verifyCartPricing($cart, $storeId, $debug);
        }

        $this->info("\n✅ Verificación completada");
        return 0;
    }

    private function verifyProductPricing(Product $product, int $storeId, bool $debug): void
    {
        try {
            // Método 1: Usar CurrencyService directamente
            $priceInfo1 = $this->currencyService->getProductPrice($product, $storeId);

            // Método 2: Simular CartItem accessor
            $unitPriceUSD = (float) str_replace(',', '', $product->UnitPrice);
            $erpPriceUSD = (float) str_replace(',', '', $product->ERPPrice ?? '0');
            $basePrice = $unitPriceUSD > 0 ? $unitPriceUSD : $erpPriceUSD;

            $storeCurrency = $this->currencyService->getStoreCurrency($storeId);
            $convertedPrice = $this->currencyService->convertAmount(
                $basePrice,
                'USD',
                $storeCurrency->id
            );

            $this->line("📦 Producto: {$product->ProductTitle}");
            $this->line("   💰 Precio USD: {$basePrice}");
            $this->line("   🔄 CurrencyService: {$priceInfo1['formatted']}");
            $this->line("   🔄 Conversión directa: " . number_format($convertedPrice, 2) . " {$storeCurrency->code}");

            $difference = abs($priceInfo1['amount'] - $convertedPrice);
            if ($difference > 0.01) {
                $this->error("   ❌ INCONSISTENCIA DETECTADA: Diferencia de {$difference}");
            } else {
                $this->info("   ✅ Precios consistentes");
            }

            if ($debug) {
                $this->line("   📊 Debug:");
                $this->line("      - ERP Price: {$erpPriceUSD}");
                $this->line("      - Unit Price: {$unitPriceUSD}");
                $this->line("      - Base Price: {$basePrice}");
                $this->line("      - Exchange Rate: " . ($convertedPrice / $basePrice));
            }

        } catch (\Exception $e) {
            $this->error("❌ Error verificando producto {$product->idproduct}: " . $e->getMessage());
        }

        $this->line("");
    }

    private function verifyCartPricing(Cart $cart, int $storeId, bool $debug): void
    {
        $this->line("🛒 Carrito ID: {$cart->id}");

        foreach ($cart->items as $item) {
            if (!$item->product) continue;

            try {
                $cartItemPrice = $item->unit_price; // Usa el accessor
                $directPrice = $this->currencyService->getProductPrice($item->product, $storeId);

                $this->line("   📦 {$item->product->ProductTitle}");
                $this->line("      🛒 Precio en carrito: " . number_format($cartItemPrice, 2));
                $this->line("      📋 Precio directo: {$directPrice['formatted']}");

                $difference = abs($cartItemPrice - $directPrice['amount']);
                if ($difference > 0.01) {
                    $this->error("      ❌ INCONSISTENCIA: Diferencia de {$difference}");
                } else {
                    $this->info("      ✅ Consistente");
                }

            } catch (\Exception $e) {
                $this->error("      ❌ Error: " . $e->getMessage());
            }
        }

        $this->line("");
    }
}
