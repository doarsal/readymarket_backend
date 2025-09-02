<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

/**
 * @OA\Tag(
 *     name="Settings",
 *     description="API Endpoints for Global System Settings"
 * )
 */
class SettingsController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/settings",
     *     tags={"Settings"},
     *     summary="Get all system settings",
     *     description="Returns all global system settings",
     *     @OA\Parameter(
     *         name="category",
     *         in="query",
     *         description="Filter by settings category",
     *         required=false,
     *         @OA\Schema(type="string", enum={"app", "mail", "cache", "database", "marketplace"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Settings retrieved successfully"
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $category = $request->get('category');

        $settings = $this->getSystemSettings($category);

        return response()->json([
            'success' => true,
            'data' => $settings
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/settings/app-info",
     *     tags={"Settings"},
     *     summary="Get application information",
     *     description="Returns basic application information and status",
     *     @OA\Response(
     *         response=200,
     *         description="App info retrieved successfully"
     *     )
     * )
     */
    public function appInfo(): JsonResponse
    {
        $info = [
            'app' => [
                'name' => config('app.name', 'Microsoft Marketplace'),
                'version' => '1.0.0',
                'environment' => config('app.env'),
                'debug' => config('app.debug'),
                'timezone' => config('app.timezone'),
                'locale' => config('app.locale'),
                'url' => config('app.url')
            ],
            'laravel' => [
                'version' => app()->version(),
                'php_version' => PHP_VERSION
            ],
            'database' => [
                'connection' => config('database.default'),
                'driver' => config('database.connections.'.config('database.default').'.driver')
            ],
            'cache' => [
                'default' => config('cache.default'),
                'stores' => array_keys(config('cache.stores'))
            ],
            'features' => [
                'multistore' => true,
                'multilanguage' => true,
                'multicurrency' => true,
                'exchange_rates' => true,
                'translations' => true,
                'swagger_docs' => true
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $info
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/settings/marketplace",
     *     tags={"Settings"},
     *     summary="Get marketplace specific settings",
     *     description="Returns marketplace configuration and limits",
     *     @OA\Response(
     *         response=200,
     *         description="Marketplace settings retrieved successfully"
     *     )
     * )
     */
    public function marketplaceSettings(): JsonResponse
    {
        $settings = [
            'pagination' => [
                'default_per_page' => 16,
                'max_per_page' => 100,
                'products_per_page_options' => [8, 16, 32, 48, 64]
            ],
            'search' => [
                'min_query_length' => 2,
                'max_query_length' => 255,
                'search_fields' => ['title', 'publisher', 'description']
            ],
            'products' => [
                'max_title_length' => 255,
                'max_description_length' => 2000,
                'required_fields' => ['title', 'publisher', 'product_id', 'sku_id'],
                'price_precision' => 2
            ],
            'stores' => [
                'max_stores' => 50,
                'max_languages_per_store' => 10,
                'max_currencies_per_store' => 5,
                'slug_validation' => '^[a-z0-9-]+$'
            ],
            'files' => [
                'max_upload_size' => '10MB',
                'allowed_image_types' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
                'image_max_dimensions' => '2048x2048'
            ],
            'api' => [
                'version' => 'v1',
                'rate_limit' => 1000,
                'throttle_attempts' => 60,
                'throttle_decay_minutes' => 1
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $settings
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/settings/marketplace",
     *     tags={"Settings"},
     *     summary="Update marketplace settings",
     *     description="Updates marketplace configuration",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="pagination", type="object",
     *                 @OA\Property(property="default_per_page", type="integer", example=16),
     *                 @OA\Property(property="max_per_page", type="integer", example=100)
     *             ),
     *             @OA\Property(property="search", type="object",
     *                 @OA\Property(property="min_query_length", type="integer", example=2)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Settings updated successfully"
     *     )
     * )
     */
    public function updateMarketplaceSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pagination.default_per_page' => 'nullable|integer|min:1|max:100',
            'pagination.max_per_page' => 'nullable|integer|min:1|max:500',
            'search.min_query_length' => 'nullable|integer|min:1|max:10',
            'search.max_query_length' => 'nullable|integer|min:10|max:1000',
            'stores.max_stores' => 'nullable|integer|min:1|max:1000',
            'api.rate_limit' => 'nullable|integer|min:100|max:10000'
        ]);

        // In a real implementation, you would store these in a settings table
        // For now, we'll cache them
        $cacheKey = 'marketplace_settings';
        $currentSettings = Cache::get($cacheKey, []);

        $updatedSettings = array_merge_recursive($currentSettings, $validated);
        Cache::put($cacheKey, $updatedSettings, now()->addDays(30));

        return response()->json([
            'success' => true,
            'message' => 'Marketplace settings updated successfully',
            'data' => $updatedSettings
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/settings/system-status",
     *     tags={"Settings"},
     *     summary="Get system status",
     *     description="Returns system status and health checks",
     *     @OA\Response(
     *         response=200,
     *         description="System status retrieved successfully"
     *     )
     * )
     */
    public function systemStatus(): JsonResponse
    {
        $status = [
            'overall' => 'healthy',
            'checks' => [
                'database' => $this->checkDatabase(),
                'cache' => $this->checkCache(),
                'storage' => $this->checkStorage(),
                'memory' => $this->checkMemory()
            ],
            'uptime' => $this->getUptime(),
            'last_check' => now()->toISOString()
        ];

        // Determine overall status
        $failedChecks = collect($status['checks'])->filter(fn($check) => $check['status'] !== 'ok');
        if ($failedChecks->count() > 0) {
            $status['overall'] = $failedChecks->count() > 2 ? 'critical' : 'warning';
        }

        return response()->json([
            'success' => true,
            'data' => $status
        ]);
    }

    /**
     * Get system settings based on category
     */
    private function getSystemSettings($category = null): array
    {
        $allSettings = [
            'app' => [
                'name' => config('app.name'),
                'debug' => config('app.debug'),
                'timezone' => config('app.timezone'),
                'locale' => config('app.locale')
            ],
            'mail' => [
                'driver' => config('mail.default'),
                'from_address' => config('mail.from.address'),
                'from_name' => config('mail.from.name')
            ],
            'cache' => [
                'default' => config('cache.default'),
                'prefix' => config('cache.prefix')
            ],
            'database' => [
                'default' => config('database.default'),
                'connections' => array_keys(config('database.connections'))
            ]
        ];

        return $category ? ($allSettings[$category] ?? []) : $allSettings;
    }

    private function checkDatabase(): array
    {
        try {
            \DB::connection()->getPdo();
            return ['status' => 'ok', 'message' => 'Database connection successful'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()];
        }
    }

    private function checkCache(): array
    {
        try {
            Cache::put('health_check', 'ok', 60);
            $value = Cache::get('health_check');
            return ['status' => $value === 'ok' ? 'ok' : 'error', 'message' => 'Cache is working'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Cache error: ' . $e->getMessage()];
        }
    }

    private function checkStorage(): array
    {
        try {
            $path = storage_path('app');
            $freeSpace = disk_free_space($path);
            $totalSpace = disk_total_space($path);
            $usedPercentage = (($totalSpace - $freeSpace) / $totalSpace) * 100;

            $status = $usedPercentage > 90 ? 'warning' : 'ok';
            return [
                'status' => $status,
                'message' => 'Storage usage: ' . round($usedPercentage, 2) . '%',
                'free_space' => $this->formatBytes($freeSpace),
                'total_space' => $this->formatBytes($totalSpace)
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Storage check failed: ' . $e->getMessage()];
        }
    }

    private function checkMemory(): array
    {
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->parseSize(ini_get('memory_limit'));
        $usagePercentage = ($memoryUsage / $memoryLimit) * 100;

        $status = $usagePercentage > 80 ? 'warning' : 'ok';
        return [
            'status' => $status,
            'message' => 'Memory usage: ' . round($usagePercentage, 2) . '%',
            'current' => $this->formatBytes($memoryUsage),
            'limit' => $this->formatBytes($memoryLimit)
        ];
    }

    private function getUptime(): string
    {
        // Simple uptime calculation based on cache
        $startTime = Cache::get('app_start_time', now());
        return $startTime->diffForHumans();
    }

    private function formatBytes($bytes, $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        return round($bytes, $precision) . ' ' . $units[$i];
    }

    private function parseSize($size): int
    {
        $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
        $size = preg_replace('/[^0-9\.]/', '', $size);
        if ($unit) {
            return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
        }
        return round($size);
    }
}
