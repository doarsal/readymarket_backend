<?php

namespace Database\Factories;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Currency>
 */
class CurrencyFactory extends Factory
{
    protected $model = Currency::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $currencies = [
            ['name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$'],
            ['name' => 'Euro', 'code' => 'EUR', 'symbol' => '€'],
            ['name' => 'Mexican Peso', 'code' => 'MXN', 'symbol' => '$'],
            ['name' => 'British Pound', 'code' => 'GBP', 'symbol' => '£'],
            ['name' => 'Canadian Dollar', 'code' => 'CAD', 'symbol' => 'C$'],
        ];

        $currency = $this->faker->randomElement($currencies);

        return [
            'name' => $currency['name'],
            'code' => $currency['code'],
            'symbol' => $currency['symbol'],
            'exchange_rate' => $this->faker->randomFloat(6, 0.5, 25.0),
            'is_default' => false,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the currency is the default.
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
            'exchange_rate' => 1.0,
        ]);
    }

    /**
     * Create USD currency
     */
    public function usd(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'US Dollar',
            'code' => 'USD',
            'symbol' => '$',
            'exchange_rate' => 1.0,
        ]);
    }

    /**
     * Create MXN currency
     */
    public function mxn(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Mexican Peso',
            'code' => 'MXN',
            'symbol' => '$',
            'exchange_rate' => 17.5,
        ]);
    }
}
