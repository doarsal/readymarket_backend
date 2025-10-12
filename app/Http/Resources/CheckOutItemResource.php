<?php

namespace App\Http\Resources;

use App\Models\CheckOutItem;
use App\Services\CurrencyService;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\App;

/** @var CheckOutItem $resource */
class CheckOutItemResource extends JsonResource
{
    protected CurrencyService $currencyService;
    protected mixed           $storeId;
    protected ?string         $currencyCode;

    /**
     * Create a new resource instance.
     * @throws BindingResolutionException
     */
    public function __construct(CheckOutItem $resource, $storeId = null)
    {
        parent::__construct($resource);
        $this->storeId         = $storeId;
        $this->currencyCode    = $resource->currency?->code ?? 'USD';
        $this->currencyService = App::make(CurrencyService::class);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $price         = $this->resource->price;
        $minCartAmount = $this->resource->min_cart_amount;
        $maxCartAmount = $this->resource->max_cart_amount;

        return [
            'id'                   => $this->resource->id,
            'item'                 => $this->resource->item,
            'description'          => $this->resource->description,
            'price'                => $price ? $this->getPriceInfo($price) : null,
            'default'              => $this->resource->default,
            'min_cart_amount'      => $minCartAmount ? $this->getPriceInfo($minCartAmount) : null,
            'max_cart_amount'      => $maxCartAmount ? $this->getPriceInfo($maxCartAmount) : null,
            'percentage_of_amount' => $this->resource->percentage_of_amount,
            'help_cta'             => $this->resource->help_cta,
            'help_text'            => $this->resource->help_text,
            'is_active'            => $this->resource->is_active,
        ];
    }

    private function getPriceInfo(string $originalPrice): array
    {
        $price     = (float) str_replace([',', ' '], '', $originalPrice);
        $priceInfo = $this->currencyService->convertAndFormatPrice($price, $this->currencyCode, $this->storeId);

        return array_merge($priceInfo, [
            'original_price'    => $price,
            'original_currency' => $this->currencyCode,
        ]);
    }
}
