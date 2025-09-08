<?php

namespace App\Http\Traits;

use App\Services\CurrencyService;
use Illuminate\Http\Request;

trait HasCurrencyConversion
{
    protected $currencyService;

    /**
     * Get currency service instance
     */
    protected function getCurrencyService(): CurrencyService
    {
        if (!$this->currencyService) {
            $this->currencyService = app(CurrencyService::class);
        }
        return $this->currencyService;
    }

    /**
     * Get store ID from request
     */
    protected function getStoreId(Request $request = null): int
    {
        $request = $request ?? request();

        return $request->attributes->get('store_id',
               $request->get('store_id',
               config('app.store_id', env('STORE_ID', 1))));
    }

    /**
     * Get target currency from request (always null - use store default)
     */
    protected function getTargetCurrency(Request $request = null): ?string
    {
        // For now, always use store default currency
        return null;
    }

    /**
     * Convert product price
     */
    protected function convertProductPrice($product, Request $request = null): array
    {
        $storeId = $this->getStoreId($request);
        $targetCurrency = $this->getTargetCurrency($request);

        return $this->getCurrencyService()->getProductPrice($product, $storeId, $targetCurrency);
    }

    /**
     * Add price information to product data
     */
    protected function addPriceInfo(array $productData, $product, Request $request = null): array
    {
        $priceInfo = $this->convertProductPrice($product, $request);

        $productData['price'] = array_merge($productData['price'] ?? [], [
            'amount' => $priceInfo['amount'],
            'formatted' => $priceInfo['formatted'],
            'currency_code' => $priceInfo['currency_code'],
            'currency_symbol' => $priceInfo['currency_symbol'],
            'currency_name' => $priceInfo['currency_name'],
            'original_amount' => $priceInfo['original_amount'],
            'original_currency' => $priceInfo['original_currency'],
            'exchange_rate' => $priceInfo['exchange_rate'],
            'price_source' => $priceInfo['price_source']
        ]);

        return $productData;
    }

    /**
     * Format multiple products with currency conversion
     */
    protected function formatProductsWithCurrency($products, Request $request = null): array
    {
        if ($products->isEmpty()) {
            return [];
        }

        $storeId = $this->getStoreId($request);
        $targetCurrency = $this->getTargetCurrency($request);
        $currencyService = $this->getCurrencyService();

        return $products->map(function ($product) use ($currencyService, $storeId, $targetCurrency) {
            $priceInfo = $currencyService->getProductPrice($product, $storeId, $targetCurrency);

            // Convert to array if it's a model
            $productArray = is_array($product) ? $product : $product->toArray();

            // Add enhanced price information
            $productArray['price'] = [
                'amount' => $priceInfo['amount'],
                'formatted' => $priceInfo['formatted'],
                'currency_code' => $priceInfo['currency_code'],
                'currency_symbol' => $priceInfo['currency_symbol'],
                'currency_name' => $priceInfo['currency_name'],
                'original_amount' => $priceInfo['original_amount'],
                'original_currency' => $priceInfo['original_currency'],
                'unit_price' => $priceInfo['unit_price'],
                'erp_price' => $priceInfo['erp_price'],
                'exchange_rate' => $priceInfo['exchange_rate'],
                'price_source' => $priceInfo['price_source']
            ];

            return $productArray;
        })->toArray();
    }
}
