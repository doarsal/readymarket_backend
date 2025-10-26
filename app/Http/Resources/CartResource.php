<?php

namespace App\Http\Resources;

use Config;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Cart Resource - transforms cart data for API responses
 */
class CartResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $activeItems = $this->items->where('status', 'active');

        return [
            'id'           => $this->id,
            'user_id'      => $this->user_id,
            'session_id'   => $this->when(!auth()->check(), $this->session_id),
            'cart_token'   => $this->cart_token,
            'store_id'     => $this->store_id,
            'currency_id'  => $this->currency_id,
            'status'       => $this->status,
            'expires_at'   => $this->expires_at?->toISOString(),
            'subtotal'     => number_format($this->subtotal, 2),
            'tax_amount'   => number_format($this->tax_amount, 2),
            'total_amount' => number_format($this->total_amount, 2),
            'metadata'     => $this->metadata,
            'created_at'   => $this->created_at?->toISOString(),
            'updated_at'   => $this->updated_at?->toISOString(),

            // Include items if loaded
            'items'        => $this->whenLoaded('items', function() {
                return CartItemResource::collection($this->items->where('status', 'active'));
            }),

            // Cart statistics
            'stats'        => [
                'items_count'     => $activeItems->sum('quantity'),
                'unique_products' => $activeItems->count(),
                'currency_code'   => Config::get('app.default_currency'),
            ],
        ];
    }
}
