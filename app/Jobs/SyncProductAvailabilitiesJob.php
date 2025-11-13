<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\MicrosoftAuthService;

class SyncProductAvailabilitiesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 hora
    public $tries = 1;

    protected $market;
    protected $batchSize;
    protected $delay;
    protected $force;

    /**
     * Create a new job instance.
     */
    public function __construct(
        string $market = 'MX',
        int $batchSize = 50,
        int $delay = 5,
        bool $force = false
    ) {
        $this->market = $market;
        $this->batchSize = $batchSize;
        $this->delay = $delay;
        $this->force = $force;
    }

    /**
     * Execute the job.
     */
    public function handle(MicrosoftAuthService $authService)
    {
        Log::info('Starting product availabilities sync job', [
            'market' => $this->market,
            'batch_size' => $this->batchSize,
            'delay' => $this->delay,
            'force' => $this->force
        ]);

        $stats = [
            'total' => 0,
            'updated' => 0,
            'unavailable' => 0,
            'errors' => 0,
            'started_at' => now(),
        ];

        try {
            // Obtener token
            $token = $authService->getAccessToken();

            // Obtener productos únicos
            $query = DB::table('products')
                ->select('ProductId', 'SkuId')
                ->distinct();

            if (!$this->force) {
                $query->where(function($q) {
                    $q->whereNull('availability_checked_at')
                      ->orWhere('availability_error', '!=', '')
                      ->orWhereNotNull('availability_error');
                });
            }

            $uniqueProducts = $query->get();
            $stats['total'] = $uniqueProducts->count();

            $partnerCenterApiUrl = config('services.microsoft.partner_center_base_url');
            $processed = 0;

            foreach ($uniqueProducts as $product) {
                $processed++;

                try {
                    $url = "{$partnerCenterApiUrl}/products/{$product->ProductId}/skus/{$product->SkuId}/availabilities?country={$this->market}";

                    $response = Http::timeout(30)
                        ->withHeaders([
                            'Authorization' => 'Bearer ' . $token,
                            'Accept' => 'application/json'
                        ])
                        ->get($url);

                    if ($response->successful()) {
                        $data = $response->json();
                        $availabilities = $data['items'] ?? [];

                        if (!empty($availabilities)) {
                            // Preferir Commercial segment
                            $commercialAvailability = collect($availabilities)->first(function($item) {
                                return isset($item['segment']) && strtolower($item['segment']) === 'commercial';
                            });

                            $selectedAvailability = $commercialAvailability ?? $availabilities[0];
                            $availabilityId = $selectedAvailability['id'];
                            $segment = ucfirst(strtolower($selectedAvailability['segment'] ?? 'commercial'));

                            DB::table('products')
                                ->where('ProductId', $product->ProductId)
                                ->where('SkuId', $product->SkuId)
                                ->update([
                                    'Id' => $availabilityId,
                                    'Segment' => $segment,
                                    'is_available' => true,
                                    'availability_checked_at' => now(),
                                    'availability_error' => null
                                ]);

                            $stats['updated']++;
                        } else {
                            DB::table('products')
                                ->where('ProductId', $product->ProductId)
                                ->where('SkuId', $product->SkuId)
                                ->update([
                                    'is_available' => false,
                                    'availability_checked_at' => now(),
                                    'availability_error' => 'No availabilities found'
                                ]);

                            $stats['unavailable']++;
                        }
                    } elseif ($response->status() === 404) {
                        DB::table('products')
                            ->where('ProductId', $product->ProductId)
                            ->where('SkuId', $product->SkuId)
                            ->update([
                                'is_available' => false,
                                'availability_checked_at' => now(),
                                'availability_error' => 'Product not found (404)'
                            ]);

                        $stats['errors']++;
                    } else {
                        $stats['errors']++;
                    }

                } catch (\Exception $e) {
                    Log::error("Error syncing availability for {$product->ProductId}:{$product->SkuId}", [
                        'error' => $e->getMessage()
                    ]);
                    $stats['errors']++;
                }

                // Delay entre batches
                if ($processed % $this->batchSize === 0 && $processed < $stats['total']) {
                    sleep($this->delay);
                }
            }

            $stats['completed_at'] = now();
            $stats['duration_seconds'] = now()->diffInSeconds($stats['started_at']);

            Log::info('Product availabilities sync job completed successfully', $stats);

        } catch (\Exception $e) {
            Log::error('Product availabilities sync job failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'stats' => $stats
            ]);

            throw $e;
        }
    }
}
