<?php

namespace Database\Factories;

use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Store>
 */
class StoreFactory extends Factory
{
    protected $model = Store::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'slug' => $this->faker->unique()->slug(),
            'description' => $this->faker->paragraph(),
            'logo' => $this->faker->optional()->imageUrl(200, 200, 'business'),
            'banner' => $this->faker->optional()->imageUrl(800, 300, 'business'),
            'website' => $this->faker->optional()->url(),
            'email' => $this->faker->companyEmail(),
            'phone' => $this->faker->optional()->phoneNumber(),
            'address' => $this->faker->optional()->address(),
            'city' => $this->faker->optional()->city(),
            'state' => $this->faker->optional()->state(),
            'country' => $this->faker->optional()->country(),
            'postal_code' => $this->faker->optional()->postcode(),
            'timezone' => $this->faker->timezone(),
            'status' => $this->faker->randomElement(['active', 'inactive', 'pending']),
            'settings' => [
                'theme' => 'default',
                'currency' => 'USD',
                'language' => 'en'
            ],
        ];
    }

    /**
     * Indicate that the store is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    /**
     * Indicate that the store is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }
}
