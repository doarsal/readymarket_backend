<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ProductTitle' => $this->faker->words(3, true),
            'ProductId' => $this->faker->unique()->bothify('DG7GMGF0D7FV'),
            'SkuId' => $this->faker->unique()->bothify('CFQ7TTC0LH18:####'),
            'Id' => $this->faker->unique()->uuid(),
            'SkuTitle' => $this->faker->words(2, true),
            'Publisher' => $this->faker->company(),
            'SkuDescription' => $this->faker->paragraph(),
            'UnitOfMeasure' => $this->faker->randomElement(['1 License', '1 Month', '1 Year']),
            'TermDuration' => $this->faker->randomElement(['P1M', 'P1Y', 'P3Y']),
            'BillingPlan' => $this->faker->randomElement(['Monthly', 'Annual']),
            'Market' => 'US',
            'Currency' => 'USD',
            'UnitPrice' => $this->faker->randomFloat(2, 5, 999),
            'PricingTierRangeMin' => 1,
            'PricingTierRangeMax' => 999,
            'EffectiveStartDate' => $this->faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d H:i:s'),
            'EffectiveEndDate' => $this->faker->dateTimeBetween('now', '+1 year')->format('Y-m-d H:i:s'),
            'Tags' => $this->faker->words(3, true),
            'ERPPrice' => $this->faker->randomFloat(2, 5, 999),
            'Segment' => $this->faker->randomElement(['Commercial', 'Education', 'Non-Profit']),
            'prod_idsperiod' => $this->faker->numberBetween(1, 12),
            'prod_idcategory' => $this->faker->numberBetween(1, 10),
            'prod_idsubcategory' => $this->faker->numberBetween(1, 50),
            'prod_idconfig' => $this->faker->numberBetween(1, 5),
            'prod_idcurrency' => $this->faker->numberBetween(1, 3),
            'prod_slide' => $this->faker->boolean(30),
            'prod_active' => 1,
            'prod_icon' => $this->faker->imageUrl(64, 64, 'business'),
            'prod_slideimage' => $this->faker->imageUrl(800, 400, 'business'),
            'prod_screenshot1' => $this->faker->imageUrl(600, 400, 'business'),
            'prod_screenshot2' => $this->faker->imageUrl(600, 400, 'business'),
            'prod_screenshot3' => $this->faker->imageUrl(600, 400, 'business'),
            'prod_screenshot4' => $this->faker->imageUrl(600, 400, 'business'),
            'top' => $this->faker->boolean(20) ? 1 : 0,
            'bestseller' => $this->faker->boolean(15) ? 1 : 0,
            'slide' => $this->faker->boolean(25) ? 1 : 0,
            'novelty' => $this->faker->boolean(30) ? 1 : 0,
            'store_id' => null,
        ];
    }

    /**
     * Indicate that the product is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'prod_active' => 1,
        ]);
    }

    /**
     * Indicate that the product is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'prod_active' => 0,
        ]);
    }

    /**
     * Indicate that the product is a top product.
     */
    public function top(): static
    {
        return $this->state(fn (array $attributes) => [
            'top' => 1,
        ]);
    }

    /**
     * Indicate that the product is a bestseller.
     */
    public function bestseller(): static
    {
        return $this->state(fn (array $attributes) => [
            'bestseller' => 1,
        ]);
    }

    /**
     * Product with specific price.
     */
    public function withPrice(float $price): static
    {
        return $this->state(fn (array $attributes) => [
            'UnitPrice' => $price,
            'ERPPrice' => $price,
        ]);
    }

    /**
     * Product from specific publisher.
     */
    public function fromPublisher(string $publisher): static
    {
        return $this->state(fn (array $attributes) => [
            'Publisher' => $publisher,
        ]);
    }

    /**
     * Product in specific category.
     */
    public function inCategory(int $categoryId): static
    {
        return $this->state(fn (array $attributes) => [
            'prod_idcategory' => $categoryId,
        ]);
    }
}
