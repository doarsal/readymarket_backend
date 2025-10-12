<?php

namespace Database\Factories;

use App\Models\Cart;
use App\Models\CartCheckOutItem;
use App\Models\CheckOutItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class CartCheckOutItemFactory extends Factory
{
    protected $model = CartCheckOutItem::class;

    public function definition(): array
    {
        return [
            'quantity'   => $this->faker->randomNumber(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),

            'cart_id'           => Cart::factory(),
            'check_out_item_id' => CheckOutItem::factory(),
        ];
    }
}
