<?php

namespace App\Imports;

use Config;
use Illuminate\Support\Collection;

trait PriceCalc
{
    use ColumnsAndHeaders;

    private float $priceMultiplier;

    protected const COLUMN_UNIT_PRICE    = 'UnitPrice';

    protected const COLUMN_BILLING_PLAN  = 'BillingPlan';

    protected const COLUMN_TERM_DURATION = 'TermDuration';

    abstract public function __construct();

    protected function initializePriceCalc(): void
    {
        $this->priceMultiplier = floatval(Config::get('products.price_multiplier'));
    }

    protected function calculateUnitPrice(Collection $row): string
    {
        $rowUnitPrice = floatval($this->getColumnValue($row, self::COLUMN_UNIT_PRICE));

        $billingPlan  = $this->getColumnValue($row, self::COLUMN_BILLING_PLAN);
        $termDuration = $this->getColumnValue($row, self::COLUMN_TERM_DURATION);
        $divisor      = $this->getUnitPriceDivisor($termDuration, $billingPlan);

        $unitPrice           = max($rowUnitPrice, 0) / $divisor;
        $priceWithMultiplier = $unitPrice + (($unitPrice * $this->priceMultiplier) / 100);

        return number_format($priceWithMultiplier, 2, '.', '');
    }

    private function getUnitPriceDivisor(?string $termDuration, string $billingPlan): int
    {
        $billingPlan  = strtolower($billingPlan);
        $termDuration = strtolower($termDuration);

        $paysPerYear = match ($billingPlan) {
            'onetime' => 0,
            'annual' => 1,
            'triennial' => 0,
            default => 12,
        };

        if ($termDuration && strlen($termDuration)) {
            $termDurationNumber = intval($termDuration[1]);
            $termDurationUnit   = $termDuration[2];

            $termYears = match ($termDurationUnit) {
                'y' => $termDurationNumber,
                default => 0,
            };
        } else {
            $termYears = 0;
        }

        return max(($termYears * $paysPerYear), 1);
    }
}
