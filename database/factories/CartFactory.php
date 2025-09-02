<?php

namespace Database\Factories;

use App\Models\Cart;
use App\Models\User;
use App\Models\Store;
use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

class CartFactory extends Factory
{
    protected $model = Cart::class;

    public function definition(): array
    {
        return [
            'user_id' => null, // Se asignará según el caso
            'session_id' => $this->faker->uuid(),
            'store_id' => Store::factory(),
            'currency_id' => Currency::factory(),
            'status' => 'active',
            'expires_at' => $this->faker->dateTimeBetween('now', '+30 days'),
            'subtotal' => $this->faker->randomFloat(2, 0, 1000),
            'tax_amount' => $this->faker->randomFloat(2, 0, 160),
            'total_amount' => $this->faker->randomFloat(2, 0, 1160),
            'metadata' => null,
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
            'session_id' => null,
            'expires_at' => null,
        ]);
    }

    public function forSession(string $sessionId): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
            'session_id' => $sessionId,
            'expires_at' => now()->addDays(30),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => $this->faker->dateTimeBetween('-30 days', '-1 day'),
        ]);
    }

    public function abandoned(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'abandoned',
        ]);
    }
}
