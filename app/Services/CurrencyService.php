<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\Store;
use App\Models\ExchangeRate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CurrencyService
{
    /**
     * Get default currency for a store
     */
    public function getStoreCurrency(int $storeId): ?Currency
    {
        return Cache::remember("store_{$storeId}_currency", 3600, function () use ($storeId) {
            $store = Store::find($storeId);
            if (!$store) {
                return null;
            }

            // Get store's default currency
            $currency = $store->currencies()
                             ->wherePivot('is_default', true)
                             ->wherePivot('is_active', true)
                             ->first();

            // Fallback to first active currency if no default
            if (!$currency) {
                $currency = $store->currencies()
                                 ->wherePivot('is_active', true)
                                 ->orderBy('store_currencies.sort_order')
                                 ->first();
            }

            return $currency;
        });
    }

    /**
     * Get currency by code
     */
    public function getCurrencyByCode(string $code): ?Currency
    {
        return Cache::remember("currency_code_{$code}", 3600, function () use ($code) {
            return Currency::where('code', $code)
                          ->where('is_active', true)
                          ->first();
        });
    }

    /**
     * Convert amount between currencies
     */
    public function convertAmount(
        float $amount,
        int|string $fromCurrency,
        int|string $toCurrency,
        ?string $date = null
    ): float {
        // Handle currency IDs or codes
        $fromCurrencyId = is_int($fromCurrency) ? $fromCurrency : $this->getCurrencyIdByCode($fromCurrency);
        $toCurrencyId = is_int($toCurrency) ? $toCurrency : $this->getCurrencyIdByCode($toCurrency);

        if (!$fromCurrencyId || !$toCurrencyId || $fromCurrencyId === $toCurrencyId) {
            return $amount;
        }

        $date = $date ?? now()->format('Y-m-d');
        $cacheKey = "exchange_rate_{$fromCurrencyId}_{$toCurrencyId}_{$date}";

        $rate = Cache::remember($cacheKey, 1800, function () use ($fromCurrencyId, $toCurrencyId, $date) {
            return ExchangeRate::getRate($fromCurrencyId, $toCurrencyId, $date);
        });

        return $amount * $rate;
    }

    /**
     * Format price according to currency settings
     */
    public function formatPrice(float $amount, Currency $currency): string
    {
        $formattedAmount = number_format(
            $amount,
            $currency->decimal_places,
            $currency->decimal_separator,
            $currency->thousands_separator
        );

        $baseFormatted = $currency->symbol_position === 'before'
            ? $currency->symbol . $formattedAmount
            : $formattedAmount . $currency->symbol;

        // Agregar el código de moneda al final
        return $baseFormatted . ' ' . $currency->code;
    }

    /**
     * Convert and format price for a specific store
     */
    public function convertAndFormatPrice(
        float $amount,
        int|string $fromCurrency,
        int $storeId,
        ?string $targetCurrencyCode = null
    ): array {
        $storeCurrency = $this->getStoreCurrency($storeId);

        if (!$storeCurrency) {
            Log::warning("No currency found for store {$storeId}");
            return [
                'amount' => $amount,
                'formatted' => number_format($amount, 2),
                'currency_code' => 'USD',
                'currency_symbol' => '$'
            ];
        }

        // If target currency is specified, use it instead of store default
        if ($targetCurrencyCode) {
            $targetCurrency = $this->getCurrencyByCode($targetCurrencyCode);
            if ($targetCurrency) {
                $storeCurrency = $targetCurrency;
            }
        }

        $convertedAmount = $this->convertAmount($amount, $fromCurrency, $storeCurrency->id);
        $formattedPrice = $this->formatPrice($convertedAmount, $storeCurrency);

        return [
            'amount' => round($convertedAmount, $storeCurrency->decimal_places),
            'formatted' => $formattedPrice,
            'currency_code' => $storeCurrency->code,
            'currency_symbol' => $storeCurrency->symbol,
            'currency_name' => $storeCurrency->name,
            'original_amount' => $amount,
            'exchange_rate' => $convertedAmount / $amount
        ];
    }

    /**
     * Get detailed price information for product
     */
    public function getProductPrice($product, int $storeId, ?string $targetCurrencyCode = null): array
    {
        // Clean the price string (remove commas, spaces)
        $unitPrice = (float) str_replace([',', ' '], '', $product->UnitPrice ?? '0');
        $erpPrice = (float) str_replace([',', ' '], '', $product->ERPPrice ?? '0');

        // Use ERP price if available, otherwise unit price
        $basePrice = $erpPrice > 0 ? $erpPrice : $unitPrice;

        // Get original currency from product
        $originalCurrency = $product->Currency ?? 'USD';

        // Convert and format
        $priceInfo = $this->convertAndFormatPrice($basePrice, $originalCurrency, $storeId, $targetCurrencyCode);

        // Add product-specific information
        $priceInfo['original_currency'] = $originalCurrency;
        $priceInfo['unit_price'] = $unitPrice;
        $priceInfo['erp_price'] = $erpPrice;
        $priceInfo['price_source'] = $erpPrice > 0 ? 'erp' : 'unit';

        return $priceInfo;
    }

    /**
     * Get currency ID by code
     */
    private function getCurrencyIdByCode(string $code): ?int
    {
        $currency = $this->getCurrencyByCode($code);
        return $currency ? $currency->id : null;
    }

    /**
     * Get all available currencies for a store
     */
    public function getStoreCurrencies(int $storeId): \Illuminate\Database\Eloquent\Collection
    {
        return Cache::remember("store_{$storeId}_currencies", 3600, function () use ($storeId) {
            $store = Store::find($storeId);
            if (!$store) {
                return collect();
            }

            return $store->currencies()
                        ->wherePivot('is_active', true)
                        ->orderBy('store_currencies.is_default', 'desc')
                        ->orderBy('store_currencies.sort_order')
                        ->get();
        });
    }

    /**
     * Clear cache for store currencies
     */
    public function clearStoreCache(int $storeId): void
    {
        Cache::forget("store_{$storeId}_currency");
        Cache::forget("store_{$storeId}_currencies");
    }

    /**
     * Clear all currency cache
     */
    public function clearCurrencyCache(): void
    {
        // Clear specific patterns would require a more advanced cache implementation
        // For now, just clear the main cache
        Cache::flush();
    }
}
