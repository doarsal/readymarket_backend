<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\CurrencyService;
use Symfony\Component\HttpFoundation\Response;

class CurrencyMiddleware
{
    protected $currencyService;

    public function __construct(CurrencyService $currencyService)
    {
        $this->currencyService = $currencyService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get store ID from environment or request
        $storeId = $this->getStoreId($request);

        // Store in request for later use (no target currency needed for now)
        $request->merge([
            'store_id' => $storeId
        ]);

        // Add to request attributes for easier access
        $request->attributes->set('store_id', $storeId);

        return $next($request);
    }

    /**
     * Get store ID from various sources
     */
    private function getStoreId(Request $request): int
    {
        // Priority order:
        // 1. Request parameter
        // 2. Header
        // 3. Environment variable
        // 4. Default to 1

        if ($request->has('store_id')) {
            return (int) $request->get('store_id');
        }

        if ($request->header('X-Store-ID')) {
            return (int) $request->header('X-Store-ID');
        }

        return (int) config('app.store_id', env('STORE_ID', 1));
    }

    /**
     * Get target currency from various sources
     */
    private function getTargetCurrency(Request $request): ?string
    {
        // For now, always return null to use store default currency
        // This method can be expanded later for multi-currency support
        return null;
    }
}
