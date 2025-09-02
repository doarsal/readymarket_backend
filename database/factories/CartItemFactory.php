<?php

namespace Database\Factories;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class CartItemFactory extends Factory
{
    protected $model = CartItem::class;

    public function definition(): array
    {
        $quantity = $this->faker->numberBetween(1, 10);
        $unitPrice = $this->faker->randomFloat(2, 10, 500);

        return [
            'cart_id' => Cart::factory(),
            'product_id' => Product::factory(),
            'sku_id' => $this->faker->regexify('[A-Z0-9]{12}:[0-9]{4}'),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $quantity * $unitPrice,
            'metadata' => null,
            'status' => 'active',
        ];
    }

    public function savedForLater(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'saved_for_later',
        ]);
    }

    public function withMetadata(array $metadata): static
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => $metadata,
        ]);
    }
}
