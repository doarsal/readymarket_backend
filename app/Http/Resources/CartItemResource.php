<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Cart Item Resource - transforms cart item data for API responses
 */
class CartItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cart_id' => $this->cart_id,
            'product_id' => $this->product_id,
            'sku_id' => $this->sku_id,
            'quantity' => $this->quantity,
            'unit_price' => number_format($this->unit_price, 2),
            'total_price' => number_format($this->total_price, 2),
            'status' => $this->status,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Include product details if loaded
            'product' => $this->whenLoaded('product', function() {
                return [
                    'id' => $this->product->idproduct,
                    'title' => $this->product->ProductTitle,
                    'sku_title' => $this->product->SkuTitle,
                    'description' => $this->product->SkuDescription,
                    'publisher' => $this->product->Publisher,
                    'unit_price' => number_format($this->product->UnitPrice, 2),
                    'currency' => $this->product->Currency,
                    'icon' => $this->product->prod_icon,
                    'category_id' => $this->product->category_id,
                    'billing_plan' => $this->product->BillingPlan,
                    'term_duration' => $this->product->TermDuration,
                ];
            }),
        ];
    }
}
