<?php

namespace App\Models;

use App\Services\CurrencyService;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\App;

class CheckOutItem extends Model
{
    protected $fillable = [
        'item',
        'description',
        'min_cart_amount',
        'max_cart_amount',
        'percentage_of_amount',
        'help_cta',
        'help_text',
        'price',
        'currency_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    // Function

    public function getPriceWithCart(Cart $cart): float
    {
        $subTotalItemsTotalPrice = $cart->subtotal_items;

        if ($this->percentage_of_amount) {
            $price = $this->percentage_of_amount * $subTotalItemsTotalPrice / 100;
        } else {
            $price = (float) str_replace([',', ' '], '', $this->price ?? '0');
        }

        return round($price, 2);
    }

    /**
     * @throws BindingResolutionException
     */
    private function getPriceInfo(string $originalPrice, mixed $storeId = null): array
    {
        $currencyCode    = $this->currency?->code ?? 'USD';
        $currencyService = App::make(CurrencyService::class);

        $price     = (float) str_replace([',', ' '], '', $originalPrice);
        $priceInfo = $currencyService->convertAndFormatPrice($price, $currencyCode, $storeId);

        return array_merge($priceInfo, [
            'original_price'    => $price,
            'original_currency' => $currencyCode,
        ]);
    }

    //Accessors

    //Relationships
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    //Scopes
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    #[Scope]
    protected function notActive(Builder $query): void
    {
        $query->where('is_active', false);
    }

    #[Scope]
    protected function defaultTrue(Builder $query): void
    {
        $query->where('default', true);
    }
}
