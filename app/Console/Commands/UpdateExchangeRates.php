<?php

namespace App\Console\Commands;

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UpdateExchangeRates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'exchange-rates:update
                            {--store-id=1 : Store ID to update rates for}
                            {--base-currency=USD : Base currency code}
                            {--api-key= : ExchangeRate-API.com API key (optional)}
                            {--force : Force update even if rates exist for today}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update exchange rates from ExchangeRate-API.com for store currencies';

    private $apiKey;
    private $baseUrl = 'https://v6.exchangerate-api.com/v6';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $storeId = $this->option('store-id');
        $baseCurrency = $this->option('base-currency');
        $this->apiKey = $this->option('api-key') ?? config('services.exchangerate_api.key');
        $force = $this->option('force');

        $this->info("🔄 Updating exchange rates for Store ID: {$storeId}");
        $this->info("📍 Base currency: {$baseCurrency}");

        try {
            // Validate store exists
            $store = Store::findOrFail($storeId);
            $this->info("✅ Store found: {$store->name}");

            // Get base currency
            $baseCurrencyModel = Currency::where('code', $baseCurrency)
                                        ->where('is_active', true)
                                        ->first();

            if (!$baseCurrencyModel) {
                $this->error("❌ Base currency '{$baseCurrency}' not found or inactive");
                return 1;
            }

            // Get all active currencies for this store
            $targetCurrencies = $store->currencies()
                                    ->wherePivot('is_active', true)
                                    ->where('currencies.is_active', true)
                                    ->where('currencies.code', '!=', $baseCurrency) // Exclude base currency
                                    ->get();

            if ($targetCurrencies->isEmpty()) {
                $this->warn("⚠️  No target currencies found for store {$storeId}");
                return 0;
            }

            $this->info("🎯 Target currencies: " . $targetCurrencies->pluck('code')->join(', '));

            $today = now()->format('Y-m-d');
            $updatedRates = 0;
            $skippedRates = 0;

            foreach ($targetCurrencies as $targetCurrency) {
                $existingRate = ExchangeRate::where('from_currency_id', $baseCurrencyModel->id)
                                          ->where('to_currency_id', $targetCurrency->id)
                                          ->where('date', $today)
                                          ->first();

                if ($existingRate && !$force) {
                    $this->warn("⏭️  Skipping {$baseCurrency}/{$targetCurrency->code} - rate already exists for today");
                    $skippedRates++;
                    continue;
                }

                $rate = $this->fetchExchangeRate($baseCurrency, $targetCurrency->code);

                if ($rate === null) {
                    $this->error("❌ Failed to fetch rate for {$baseCurrency}/{$targetCurrency->code}");
                    continue;
                }

                // Update or create exchange rate
                ExchangeRate::updateOrCreate(
                    [
                        'from_currency_id' => $baseCurrencyModel->id,
                        'to_currency_id' => $targetCurrency->id,
                        'date' => $today,
                    ],
                    [
                        'rate' => $rate,
                        'source' => 'exchangerate-api.com',
                        'is_active' => true,
                    ]
                );

                $this->info("✅ Updated {$baseCurrency}/{$targetCurrency->code}: {$rate}");
                $updatedRates++;

                // Small delay to be respectful to the API
                usleep(100000); // 0.1 seconds
            }

            $this->newLine();
            $this->info("🎉 Exchange rates update completed!");
            $this->info("📊 Updated: {$updatedRates} rates");
            $this->info("⏭️  Skipped: {$skippedRates} rates");

            // Clear cache to ensure fresh data
            \Illuminate\Support\Facades\Cache::flush();
            $this->info("🗑️  Cache cleared");

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error updating exchange rates: " . $e->getMessage());
            Log::error('Exchange rates update failed', [
                'error' => $e->getMessage(),
                'store_id' => $storeId,
                'base_currency' => $baseCurrency
            ]);
            return 1;
        }
    }

    /**
     * Fetch exchange rate from ExchangeRate-API.com
     */
    private function fetchExchangeRate(string $from, string $to): ?float
    {
        try {
            if ($this->apiKey) {
                // Use paid API with key
                $url = "{$this->baseUrl}/{$this->apiKey}/pair/{$from}/{$to}";
            } else {
                // Use free API without key (limited requests)
                $url = "{$this->baseUrl}/latest/{$from}";
            }

            $this->line("🌐 Fetching: {$from} → {$to}");

            $response = Http::timeout(10)->get($url);

            if (!$response->successful()) {
                $this->error("HTTP Error: " . $response->status());
                return null;
            }

            $data = $response->json();

            if ($data['result'] !== 'success') {
                $this->error("API Error: " . ($data['error-type'] ?? 'Unknown error'));
                return null;
            }

            if ($this->apiKey) {
                // Direct pair response
                return (float) $data['conversion_rate'];
            } else {
                // Extract from conversion_rates array
                if (!isset($data['conversion_rates'][$to])) {
                    $this->error("Currency {$to} not found in response");
                    return null;
                }
                return (float) $data['conversion_rates'][$to];
            }

        } catch (\Exception $e) {
            $this->error("Exception fetching rate {$from}/{$to}: " . $e->getMessage());
            return null;
        }
    }
}
