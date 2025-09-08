<?php

namespace App\Http\Resources;

use App\Services\CurrencyService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    protected $currencyService;
    protected $storeId;
    protected $targetCurrency;

    /**
     * Create a new resource instance.
     */
    public function __construct($resource, $storeId = null, $targetCurrency = null)
    {
        parent::__construct($resource);
        $this->storeId = $storeId;
        $this->targetCurrency = $targetCurrency;
        $this->currencyService = app(CurrencyService::class);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Get store ID and use store default currency
        $storeId = $this->storeId
                  ?? $request->attributes->get('store_id')
                  ?? $request->get('store_id')
                  ?? config('app.store_id', env('STORE_ID', 1));

        // Always use store default currency (no target currency)
        $targetCurrency = null;

        // Get converted price information
        $priceInfo = $this->currencyService->getProductPrice(
            $this->resource,
            $storeId,
            $targetCurrency
        );

        return [
            'id' => $this->idproduct,
            'product_id' => $this->ProductId,
            'sku_id' => $this->SkuId,
            'title' => $this->ProductTitle,
            'sku_title' => $this->SkuTitle,
            'publisher' => $this->Publisher,
            'description' => $this->SkuDescription,
            'price' => [
                // New enhanced price information
                'amount' => $priceInfo['amount'],
                'formatted' => $priceInfo['formatted'],
                'currency_code' => $priceInfo['currency_code'],
                'currency_symbol' => $priceInfo['currency_symbol'],
                'currency_name' => $priceInfo['currency_name'],

                // Original price information for backwards compatibility
                'unit_price' => $this->UnitPrice,
                'currency' => $this->Currency,
                'erp_price' => $this->ERPPrice,

                // Additional conversion information
                'original_amount' => $priceInfo['original_amount'],
                'original_currency' => $priceInfo['original_currency'],
                'exchange_rate' => $priceInfo['exchange_rate'],
                'price_source' => $priceInfo['price_source'],
            ],
            'media' => [
                'icon' => $this->prod_icon,
                'slide_image' => $this->prod_slideimage,
                'screenshots' => array_filter([
                    $this->prod_screenshot1,
                    $this->prod_screenshot2,
                    $this->prod_screenshot3,
                    $this->prod_screenshot4,
                ]),
            ],
            'market' => $this->Market,
            'segment' => $this->Segment,
            'is_active' => (bool) $this->is_active,
            'is_top' => (bool) $this->is_top,
            'category' => $this->whenLoaded('category'),
            'tags' => $this->Tags ? explode(',', $this->Tags) : [],
            'effective_dates' => [
                'start' => $this->EffectiveStartDate,
                'end' => $this->EffectiveEndDate,
            ],
        ];
    }

    /**
     * Create resource collection with currency context
     */
    public static function collection($resource, $storeId = null, $targetCurrency = null)
    {
        return tap(new ProductResourceCollection($resource, static::class), function ($collection) use ($storeId, $targetCurrency) {
            $collection->storeId = $storeId;
            $collection->targetCurrency = $targetCurrency;
        });
    }
}
