<?php

namespace App\Imports;

use App\Models\Currency;
use App\Models\Product;
use Config;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\RemembersChunkOffset;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;

class ProductsImport implements ToCollection, WithChunkReading, WithCustomCsvSettings
{
    use RemembersChunkOffset;

    private const COLUMN_CHANGE_INDICATOR       = 'ChangeIndicator';

    private const COLUMN_PRODUCT_TITLE          = 'ProductTitle';

    private const COLUMN_PRODUCT_ID             = 'ProductId';

    private const COLUMN_SKU_ID                 = 'SkuId';

    private const COLUMN_SKU_TITLE              = 'SkuTitle';

    private const COLUMN_PUBLISHER              = 'Publisher';

    private const COLUMN_SKU_DESCRIPTION        = 'SkuDescription';

    private const COLUMN_UNIT_OF_MEASURE        = 'UnitOfMeasure';

    private const COLUMN_TERM_DURATION          = 'TermDuration';

    private const COLUMN_BILLING_PLAN           = 'BillingPlan';

    private const COLUMN_MARKET                 = 'Market';

    private const COLUMN_CURRENCY               = 'Currency';

    private const COLUMN_UNIT_PRICE             = 'UnitPrice';

    private const COLUMN_PRICING_TIER_RANGE_MIN = 'PricingTierRangeMin';

    private const COLUMN_PRICING_TIER_RANGE_MAX = 'PricingTierRangeMax';

    private const COLUMN_EFFECTIVE_START_DATE   = 'EffectiveStartDate';

    private const COLUMN_EFFECTIVE_END_DATE     = 'EffectiveEndDate';

    private const COLUMN_TAGS                   = 'Tags';

    private const COLUMN_ERP_PRICE              = 'ERP Price';

    private const COLUMN_SEGMENT                = 'Segment';

    private const COLUMN_PREVIOUS_VALUES        = 'PreviousValues';

    private const COLUMN_UNDEFINED              = 'Undefined';

    private const REQUIRED_COLUMNS              = [
        self::COLUMN_CHANGE_INDICATOR,
        self::COLUMN_PRODUCT_TITLE,
        self::COLUMN_PRODUCT_ID,
        self::COLUMN_SKU_ID,
        self::COLUMN_SKU_TITLE,
        self::COLUMN_PUBLISHER,
        self::COLUMN_SKU_DESCRIPTION,
        self::COLUMN_UNIT_OF_MEASURE,
        self::COLUMN_TERM_DURATION,
        self::COLUMN_BILLING_PLAN,
        self::COLUMN_MARKET,
        self::COLUMN_CURRENCY,
        self::COLUMN_UNIT_PRICE,
        self::COLUMN_PRICING_TIER_RANGE_MIN,
        self::COLUMN_PRICING_TIER_RANGE_MAX,
        self::COLUMN_EFFECTIVE_START_DATE,
        self::COLUMN_EFFECTIVE_END_DATE,
        self::COLUMN_TAGS,
        self::COLUMN_ERP_PRICE,
        self::COLUMN_SEGMENT,
        self::COLUMN_PREVIOUS_VALUES,
    ];

    private const ALL_COLUMNS                   = [
        self::COLUMN_CHANGE_INDICATOR,
        self::COLUMN_PRODUCT_TITLE,
        self::COLUMN_PRODUCT_ID,
        self::COLUMN_SKU_ID,
        self::COLUMN_SKU_TITLE,
        self::COLUMN_PUBLISHER,
        self::COLUMN_SKU_DESCRIPTION,
        self::COLUMN_UNIT_OF_MEASURE,
        self::COLUMN_TERM_DURATION,
        self::COLUMN_BILLING_PLAN,
        self::COLUMN_MARKET,
        self::COLUMN_CURRENCY,
        self::COLUMN_UNIT_PRICE,
        self::COLUMN_PRICING_TIER_RANGE_MIN,
        self::COLUMN_PRICING_TIER_RANGE_MAX,
        self::COLUMN_EFFECTIVE_START_DATE,
        self::COLUMN_EFFECTIVE_END_DATE,
        self::COLUMN_TAGS,
        self::COLUMN_ERP_PRICE,
        self::COLUMN_SEGMENT,
        self::COLUMN_PREVIOUS_VALUES,
    ];

    private Collection $columnMapping;
    public Collection  $productsWithoutCategory;
    public Collection  $correctProducts;
    public Collection  $allProducts;
    private float      $priceMultiplier;
    private ?Currency  $currency;

    public function __construct()
    {
        $this->columnMapping           = Collection::make();
        $this->productsWithoutCategory = Collection::make();
        $this->correctProducts         = Collection::make();
        $this->allProducts             = Collection::make();
        $this->currency                = null;
        $this->priceMultiplier         = floatval(Config::get('products.price_multiplier'));
    }

    public function collection(Collection $collection): void
    {
        $storeId     = Config::get('app.store_id');
        $chunkOffset = $this->getChunkOffset();

        /**
         * @var Collection $row
         */
        foreach ($collection as $index => $row) {
            if ($chunkOffset == 1 && $index == 0) {
                $this->prepareHeaders($row);
                $this->validateHeaders();

                continue;
            }

            if ($this->getColumnValue($row, self::COLUMN_SEGMENT) !== 'Commercial') {
                continue;
            }

            if (!$this->currency) {
                $currencyColumnValue = $this->getColumnValue($row, self::COLUMN_CURRENCY) ?? 'USD';
                $this->currency      = Currency::where('code', $currencyColumnValue)->first();
            }

            $product = Product::updateOrCreate([
                'ProductId'    => $this->getColumnValue($row, self::COLUMN_PRODUCT_ID),
                'SkuId'        => $this->getColumnValue($row, self::COLUMN_SKU_ID),
                'TermDuration' => $this->getColumnValue($row, self::COLUMN_TERM_DURATION),
                'BillingPlan'  => $this->getColumnValue($row, self::COLUMN_BILLING_PLAN),
            ], [
                'ProductTitle'        => $this->getColumnValue($row, self::COLUMN_PRODUCT_TITLE),
                'Id'                  => $this->getColumnValue($row, self::COLUMN_PRODUCT_ID),
                'SkuTitle'            => $this->getColumnValue($row, self::COLUMN_SKU_TITLE),
                'Publisher'           => $this->getColumnValue($row, self::COLUMN_PUBLISHER),
                'SkuDescription'      => $this->getColumnValue($row, self::COLUMN_SKU_DESCRIPTION),
                'UnitOfMeasure'       => $this->getColumnValue($row, self::COLUMN_UNIT_OF_MEASURE),
                'Market'              => $this->getColumnValue($row, self::COLUMN_MARKET),
                'Currency'            => $this->getColumnValue($row, self::COLUMN_CURRENCY),
                'UnitPrice'           => $this->calculateUnitPrice($row),
                'PricingTierRangeMin' => $this->getColumnValue($row, self::COLUMN_PRICING_TIER_RANGE_MIN),
                'PricingTierRangeMax' => $this->getColumnValue($row, self::COLUMN_PRICING_TIER_RANGE_MAX),
                'EffectiveStartDate'  => $this->getColumnValue($row, self::COLUMN_EFFECTIVE_START_DATE),
                'EffectiveEndDate'    => $this->getColumnValue($row, self::COLUMN_EFFECTIVE_END_DATE),
                'Tags'                => $this->getColumnValue($row, self::COLUMN_TAGS),
                'ERPPrice'            => $this->getColumnValue($row, self::COLUMN_ERP_PRICE),
                'Segment'             => $this->getColumnValue($row, self::COLUMN_SEGMENT),
                'store_id'            => $storeId,
                'currency_id'         => $this->currency?->id,
            ]);

            $this->allProducts->push($product->idproduct);

            if ($product->wasRecentlyCreated) {
                $this->productsWithoutCategory->push($product);
                continue;
            }

            if ($product->wasChanged()) {
                $this->correctProducts->push([
                    'product' => [
                        'id'           => $product->getKey(),
                        'ProductId'    => $product->ProductId,
                        'SkuTitle'     => $product->SkuTitle,
                        'SkuId'        => $product->SkuId,
                        'TermDuration' => $product->TermDuration,
                        'BillingPlan'  => $product->BillingPlan,
                    ],
                    'changes' => $product->getChanges(),
                ]);
            }
        }
    }

    public function chunkSize(): int
    {
        return 100;
    }

    private function prepareHeaders(Collection $headers): void
    {
        $headers->each(function(string $headerName) {
            if (in_array($headerName, self::ALL_COLUMNS)) {
                $this->columnMapping->push($headerName);
            } else {
                $this->columnMapping->push(self::COLUMN_UNDEFINED);
            }
        });
    }

    private function validateHeaders(): void
    {
        $errors = Collection::make();

        foreach (self::REQUIRED_COLUMNS as $requiredColumn) {
            if ($this->columnMapping->search($requiredColumn) === false) {
                $errors->push("Falta la columna requerida: {$requiredColumn}");
            }
        }

        if ($errors->isNotEmpty()) {
            throw ValidationException::withMessages([
                'file' => $errors,
            ]);
        }
    }

    private function getColumnValue(Collection $row, string $columnName): mixed
    {
        $columnIndex = $this->columnMapping->search($columnName);

        if ($columnIndex === false) {
            return null;
        }

        return $row->get($columnIndex);
    }

    private function calculateUnitPrice(Collection $row): string
    {
        $unitPrice           = floatval($this->getColumnValue($row, self::COLUMN_UNIT_PRICE));
        $priceWithMultiplier = $unitPrice + (($unitPrice * $this->priceMultiplier) / 100);

        return number_format($priceWithMultiplier, 2, '.', '');
    }

    public function getCsvSettings(): array
    {
        return [
            'input_encoding' => 'UTF-8',
            'delimiter' => ',',
            'enclosure' => '"',
            'escape_character' => '\\',
        ];
    }
}
