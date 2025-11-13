<?php

namespace Tests\Unit\Imports;

use App\Imports\SoftwareProductImport;
use Config;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionException;
use Tests\TestCase;

class SoftwareProductImportTest extends TestCase
{
    /**
     * @throws ReflectionException
     */
    #[Test]
    #[DataProvider('divisorProvider')]
    public function it_returns_divisor_correctly(string $termsDuration, string $billingPlan, int $expected): void
    {
        $reflectionClass = new ReflectionClass(SoftwareProductImport::class);
        $divisorMethod   = $reflectionClass->getMethod('getUnitPriceDivisor');

        $this->assertEquals($expected,
            $divisorMethod->invoke(new SoftwareProductImport(), $termsDuration, $billingPlan));
    }

    public static function divisorProvider(): array
    {
        //Terms / Billing plan / expected
        return [
            ['', 'OneTime', 1],
            ['P1M', 'OneTime', 1],
            ['P1Y', 'OneTime', 1],
            ['P3Y', 'OneTime', 1],
            ['P1M', 'Monthly', 1],
            ['P1Y', 'Monthly', 12],
            ['P1Y', 'Annual', 1],
            ['P3Y', 'Monthly', 36],
            ['P3Y', 'Annual', 3],
            ['P3Y', 'Triennial', 1],
        ];
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    #[DataProvider('unitPriceProvider')]
    public function it_calculate_prices_correctly(
        string $termsDuration,
        string $billingPlan,
        string $unitPrice,
        float $expected
    ): void {
        Config::set('products.price_multiplier', 13);

        $row = Collection::make([
            $unitPrice,
            $billingPlan,
            $termsDuration,
        ]);

        $productImport = new SoftwareProductImport();

        $reflectionClass = new ReflectionClass(SoftwareProductImport::class);
        $reflectionClass->getProperty('columnMapping')->setValue($productImport, Collection::make([
            'UnitPrice',
            'BillingPlan',
            'TermDuration',
        ]));
        $unitPriceMethod = $reflectionClass->getMethod('calculateUnitPrice');

        $this->assertEquals($expected, $unitPriceMethod->invoke($productImport, $row));
    }

    public static function unitPriceProvider(): array
    {
        //Terms / Billing plan / UnitPrice / expected
        return [
            ['', 'OneTime', '45.15', 51.02],
            ['P1M', 'OneTime', '45.15', 51.02],
            ['P1Y', 'OneTime', '45.15', 51.02],
            ['P3Y', 'OneTime', '45.15', 51.02],
            ['P1M', 'Monthly', '45.15', 51.02],
            ['P1Y', 'Monthly', '124', 11.68],
            ['P1Y', 'Annual', '500.05', 565.06],
            ['P3Y', 'Monthly', '2503.22', 78.57],
            ['P3Y', 'Annual', '1000', 376.67],
            ['P3Y', 'Triennial', '1245.99', 1407.97],
        ];
    }
}
