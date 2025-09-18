<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\Cart;
use App\Models\PageView;
use App\Services\GeoLocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Jenssegers\Agent\Agent;

/**
 * @OA\Tag(
 *     name="Analytics",
 *     description="Analytics and reporting endpoints"
 * )
 */
class AnalyticsController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/analytics/dashboard",
     *     tags={"Analytics"},
     *     summary="Get dashboard analytics",
     *     description="Returns analytics data for the admin dashboard",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         description="Filter by store ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Dashboard analytics retrieved successfully"
     *     )
     * )
     */
    public function dashboard(Request $request): JsonResponse
    {
        $storeId = $request->get('store_id');

        $cacheKey = "dashboard_analytics_" . ($storeId ?? 'all');

        $analytics = Cache::remember($cacheKey, 300, function () use ($storeId) {
            $query = Product::query();
            $categoryQuery = Category::query();
            $storeQuery = Store::query();

            if ($storeId) {
                $query->where('store_id', $storeId);
                $categoryQuery->where('store_id', $storeId);
                $storeQuery->where('id', $storeId);
            }

            return [
                'overview' => [
                    'total_products' => $query->count(),
                    'active_products' => $query->where('is_active', true)->count(),
                    'total_categories' => $categoryQuery->count(),
                    'active_categories' => $categoryQuery->where('is_active', true)->count(),
                    'total_stores' => $storeQuery->count(),
                    'active_stores' => $storeQuery->where('is_active', true)->count(),
                ],
                'products' => [
                    'by_segment' => $query->selectRaw('Segment, COUNT(*) as count')
                                         ->groupBy('Segment')
                                         ->get(),
                    'by_market' => $query->selectRaw('Market, COUNT(*) as count')
                                        ->groupBy('Market')
                                        ->get(),
                    'top_publishers' => $query->selectRaw('Publisher, COUNT(*) as count')
                                             ->groupBy('Publisher')
                                             ->orderByDesc('count')
                                             ->limit(10)
                                             ->get(),
                ],
                'categories' => [
                    'most_visited' => $categoryQuery->orderByDesc('visits')
                                                   ->limit(10)
                                                   ->get(['name', 'visits', 'identifier']),
                ]
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $analytics
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/analytics/stores",
     *     tags={"Analytics"},
     *     summary="Get stores analytics",
     *     description="Returns analytics data for stores",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Stores analytics retrieved successfully"
     *     )
     * )
     */
    public function stores(): JsonResponse
    {
        $analytics = Cache::remember('stores_analytics', 300, function () {
            return [
                'overview' => [
                    'total_stores' => Store::count(),
                    'active_stores' => Store::where('is_active', true)->count(),
                    'maintenance_stores' => Store::where('is_maintenance', true)->count(),
                ],
                'stores' => Store::withCount(['products', 'categories'])
                                ->orderByDesc('products_count')
                                ->get()
                                ->map(function ($store) {
                                    return [
                                        'id' => $store->id,
                                        'name' => $store->name,
                                        'slug' => $store->slug,
                                        'is_active' => $store->is_active,
                                        'is_maintenance' => $store->is_maintenance,
                                        'products_count' => $store->products_count,
                                        'categories_count' => $store->categories_count,
                                        'default_language' => $store->default_language,
                                        'default_currency' => $store->default_currency,
                                    ];
                                })
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $analytics
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/analytics/products/top",
     *     tags={"Analytics"},
     *     summary="Get top products analytics",
     *     description="Returns top products by various metrics",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         description="Filter by store ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Number of products to return",
     *         required=false,
     *         @OA\Schema(type="integer", default=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Top products retrieved successfully"
     *     )
     * )
     */
    public function topProducts(Request $request): JsonResponse
    {
        $storeId = $request->get('store_id');
        $limit = $request->get('limit', 10);

        $query = Product::query();

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        $topProducts = [
            'top_products' => $query->where('top', 1)
                                   ->limit($limit)
                                   ->get(['ProductTitle', 'Publisher', 'UnitPrice', 'Market']),
            'bestsellers' => $query->where('bestseller', 1)
                                  ->limit($limit)
                                  ->get(['ProductTitle', 'Publisher', 'UnitPrice', 'Market']),
            'novelties' => $query->where('novelty', 1)
                                ->limit($limit)
                                ->get(['ProductTitle', 'Publisher', 'UnitPrice', 'Market']),
        ];

        return response()->json([
            'success' => true,
            'data' => $topProducts
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/analytics/categories/performance",
     *     tags={"Analytics"},
     *     summary="Get categories performance",
     *     description="Returns performance metrics for categories",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         description="Filter by store ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Categories performance retrieved successfully"
     *     )
     * )
     */
    public function categoriesPerformance(Request $request): JsonResponse
    {
        $storeId = $request->get('store_id');

        $query = Category::select([
            'categories.*',
            DB::raw('COUNT(DISTINCT CONCAT(products.SkuId, "-", products.Id)) as unique_products'),
            DB::raw('COUNT(products.idproduct) as total_variants'),
            DB::raw('AVG(products.UnitPrice) as avg_price'),
            DB::raw('SUM(categories.visits) as total_visits')
        ])
        ->leftJoin('products', 'categories.id', '=', 'products.category_id')
        ->where('categories.is_active', true)
        ->where(function($q) {
            $q->where('products.is_active', true)
              ->orWhereNull('products.idproduct');
        });

        if ($storeId) {
            $query->where('categories.store_id', $storeId);
        }

        $performance = $query->groupBy('categories.id', 'categories.name', 'categories.identifier', 'categories.store_id')
                            ->orderBy('unique_products', 'desc')
                            ->get();

        return response()->json([
            'success' => true,
            'data' => $performance
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/analytics/pricing",
     *     tags={"Analytics"},
     *     summary="Get pricing analytics",
     *     description="Returns pricing analytics and distribution",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         description="Filter by store ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Pricing analytics retrieved successfully"
     *     )
     * )
     */
    public function pricing(Request $request): JsonResponse
    {
        $storeId = $request->get('store_id');

        $query = Product::query();

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        $pricing = [
            'overview' => [
                'total_products' => $query->count(),
                'min_price' => $query->min(DB::raw('CAST(UnitPrice AS DECIMAL(10,2))')),
                'max_price' => $query->max(DB::raw('CAST(UnitPrice AS DECIMAL(10,2))')),
                'avg_price' => $query->avg(DB::raw('CAST(UnitPrice AS DECIMAL(10,2))')),
            ],
            'by_currency' => $query->selectRaw('Currency, COUNT(*) as count, AVG(CAST(UnitPrice AS DECIMAL(10,2))) as avg_price')
                                  ->groupBy('Currency')
                                  ->get(),
            'by_segment' => $query->selectRaw('Segment, COUNT(*) as count, AVG(CAST(UnitPrice AS DECIMAL(10,2))) as avg_price')
                                 ->groupBy('Segment')
                                 ->get(),
        ];

        return response()->json([
            'success' => true,
            'data' => $pricing
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/analytics/system/health",
     *     tags={"Analytics"},
     *     summary="Get system health",
     *     description="Returns system health and integrity checks",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="System health retrieved successfully"
     *     )
     * )
     */
    public function systemHealth(): JsonResponse
    {
        $health = [
            'database' => [
                'status' => 'healthy',
                'tables' => [
                    'products' => Product::count(),
                    'categories' => Category::count(),
                    'stores' => Store::count(),
                    'carts' => Cart::count(),
                    'cart_items' => DB::table('cart_items')->count(),
                    'users' => DB::table('users')->count(),
                    'orders' => DB::table('orders')->count(),
                    'order_items' => DB::table('order_items')->count(),
                ]
            ],
            'integrity' => [
                'products_without_categories' => Product::whereNull('category_id')->count(),
                'products_without_store' => Product::whereNull('store_id')->count(),
                'categories_without_store' => Category::whereNull('store_id')->count(),
                'inactive_stores' => Store::where('is_active', false)->count(),
                'stores_in_maintenance' => Store::where('is_maintenance', true)->count()
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $health
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/analytics/abandoned-carts-simple",
     *     tags={"Analytics"},
     *     summary="Get simple abandoned carts list",
     *     description="Returns simple list of non-converted carts",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         description="Filter by store ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="hours",
     *         in="query",
     *         description="Hours of inactivity to consider abandoned",
     *         @OA\Schema(type="integer", default=24)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Abandoned carts retrieved successfully"
     *     )
     * )
     */
    public function getAbandonedCartsSimple(Request $request): JsonResponse
    {
        $storeId = $request->get('store_id');
        $hours = $request->get('hours', 24);

        $query = Cart::with(['user:id,name,email', 'store:id,name', 'items.product'])
                    ->where('status', '!=', 'converted')
                    ->where('updated_at', '<', now()->subHours($hours))
                    ->whereHas('items'); // Solo carritos con productos

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        $abandonedCarts = $query->orderBy('updated_at', 'desc')->get();

        $stats = [
            'total_abandoned_carts' => $abandonedCarts->count(),
            'total_abandoned_value' => $abandonedCarts->sum('total_amount'),
            'average_cart_value' => $abandonedCarts->avg('total_amount'),
            'with_user_info' => $abandonedCarts->whereNotNull('user_id')->count(),
            'guest_carts' => $abandonedCarts->whereNull('user_id')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'carts' => $abandonedCarts->map(function ($cart) {
                    return [
                        'id' => $cart->id,
                        'cart_token' => $cart->cart_token,
                        'user' => $cart->user ? $cart->user->only(['name', 'email']) : null,
                        'store' => $cart->store->only(['name']),
                        'status' => $cart->status,
                        'total_amount' => $cart->total_amount,
                        'currency' => $cart->currency_id ?? 'USD',
                        'items_count' => $cart->items->count(),
                        'last_activity' => $cart->updated_at->format('Y-m-d H:i:s'),
                        'abandoned_for' => $cart->updated_at->diffForHumans(),
                        'items' => $cart->items->map(function ($item) {
                            return [
                                'product_name' => $item->product->ProductTitle ?? 'Producto',
                                'quantity' => $item->quantity,
                                'unit_price' => $item->unit_price,
                                'total_price' => $item->total_price
                            ];
                        })
                    ];
                }),
                'statistics' => $stats
            ]
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/analytics/track-page-view",
     *     tags={"Analytics"},
     *     summary="Track page view",
     *     description="Records a detailed page view for analytics",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="page_type", type="string", description="Type of page", example="product"),
     *             @OA\Property(property="page_url", type="string", description="Full URL", example="/product/123"),
     *             @OA\Property(property="page_path", type="string", description="Path without params", example="/product"),
     *             @OA\Property(property="page_name", type="string", description="Vue route name", example="product-details"),
     *             @OA\Property(property="resource_id", type="integer", description="Universal resource ID (product, category, etc.)", example=123),
     *             @OA\Property(property="referrer_url", type="string", description="Referrer URL", example="https://google.com"),
     *             @OA\Property(property="utm_source", type="string", description="UTM source", example="google"),
     *             @OA\Property(property="additional_data", type="object", description="Additional custom data")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Page view tracked successfully"
     *     )
     * )
     */
    public function trackPageView(Request $request): JsonResponse
    {
        $request->validate([
            'page_type' => 'required|string|max:100',
            'page_url' => 'required|string|max:500',
            'page_path' => 'required|string|max:300',
            'page_name' => 'nullable|string|max:100',
            'resource_id' => 'nullable|integer', // ID universal del recurso
            'referrer_url' => 'nullable|string|max:500',
            'utm_source' => 'nullable|string|max:100',
            'utm_medium' => 'nullable|string|max:100',
            'utm_campaign' => 'nullable|string|max:100',
            'utm_term' => 'nullable|string|max:100',
            'utm_content' => 'nullable|string|max:100',
            'screen_resolution' => 'nullable|string|max:20',
            'timezone' => 'nullable|string|max:50',
            'additional_data' => 'nullable|array',
        ]);

        // Servicios
        $agent = new Agent();
        $geoService = new GeoLocationService();

        // Obtener usuario de manera segura
        try {
            $user = auth()->guard('sanctum')->user();
        } catch (\Exception $e) {
            $user = null;
        }

        // Obtener IP real del cliente
        $visitorIP = GeoLocationService::getRealIP();

        // Obtener información de geolocalización
        $geoData = $geoService->getLocationByIP($visitorIP);

        // Obtener información del user agent
        $userAgentData = $geoService->parseUserAgent($request->userAgent());

        // Generar session ID único si no se proporciona
        $sessionId = $request->input('session_id') ?: 'api_session_' . uniqid();

        // Preparar datos para el tracking
        $trackingData = [
            'user_id' => $user ? $user->id : null,
            'session_id' => $sessionId,
            'visitor_ip' => $visitorIP,
            'user_agent' => $request->userAgent(),

            // Información de la página
            'page_type' => $request->page_type,
            'page_url' => $request->page_url,
            'page_path' => $request->page_path,
            'page_name' => $request->page_name,
            'page_params' => $request->input('page_params', []),
            'query_params' => $request->input('query_params', []),

            // ID universal del recurso
            'resource_id' => $request->resource_id,

            // Información de referencia
            'referrer_url' => $request->referrer_url,
            'referrer_domain' => $request->referrer_url ? parse_url($request->referrer_url, PHP_URL_HOST) : null,

            // UTM Parameters
            'utm_source' => $request->utm_source,
            'utm_medium' => $request->utm_medium,
            'utm_campaign' => $request->utm_campaign,
            'utm_term' => $request->utm_term,
            'utm_content' => $request->utm_content,

            // Información del dispositivo (usando nuestro servicio mejorado)
            'device_type' => $userAgentData['device_type'],
            'browser' => $userAgentData['browser'],
            'browser_version' => $userAgentData['browser_version'],
            'os' => $userAgentData['os'],
            'os_version' => $userAgentData['os_version'],
            'screen_resolution' => $request->screen_resolution,
            'is_mobile' => $userAgentData['is_mobile'],
            'is_bot' => $userAgentData['is_bot'],
            'language' => $request->header('Accept-Language') ? substr($request->header('Accept-Language'), 0, 2) : null,

            // Información geográfica
            'country' => $geoData['country'],
            'region' => $geoData['region'],
            'city' => $geoData['city'],
            'timezone' => $request->timezone ?: $geoData['timezone'],

            // Datos adicionales (incluyendo coordenadas si están disponibles)
            'additional_data' => array_merge($request->additional_data ?? [], [
                'country_name' => $geoData['country_name'] ?? null,
                'region_code' => $geoData['region_code'] ?? null,
                'postal_code' => $geoData['postal_code'] ?? null,
                'latitude' => $geoData['latitude'] ?? null,
                'longitude' => $geoData['longitude'] ?? null,
            ])
        ];

        // Crear el registro de vista
        $pageView = PageView::trackView($trackingData);

        return response()->json([
            'success' => true,
            'message' => 'Page view tracked successfully',
            'data' => [
                'id' => $pageView->id,
                'page_type' => $pageView->page_type,
                'page_url' => $pageView->page_url,
                'session_id' => $pageView->session_id,
                'location' => [
                    'country' => $geoData['country'],
                    'region' => $geoData['region'],
                    'city' => $geoData['city'],
                    'timezone' => $trackingData['timezone']
                ],
                'tracked_at' => $pageView->created_at->format('Y-m-d H:i:s')
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/analytics/page-views",
     *     tags={"Analytics"},
     *     summary="Get page views analytics",
     *     description="Returns detailed page views analytics data",
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         description="Filter by store ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="page_type",
     *         in="query",
     *         description="Filter by page type",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="days",
     *         in="query",
     *         description="Number of days to analyze (default: 7)",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Page views retrieved successfully"
     *     )
     * )
     */
    public function getPageViews(Request $request): JsonResponse
    {
        $storeId = $request->input('store_id');
        $pageType = $request->input('page_type');
        $days = $request->input('days', 7);

        $query = PageView::query()
            ->where('created_at', '>=', now()->subDays($days))
            ->where('is_bot', false); // Excluir bots

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        if ($pageType) {
            $query->where('page_type', $pageType);
        }

        // Estadísticas generales
        $totalViews = $query->count();
        $uniqueVisitors = $query->distinct('session_id')->count('session_id');

        // Vistas por tipo de página
        $viewsByType = PageView::selectRaw('page_type, COUNT(*) as total')
            ->where('created_at', '>=', now()->subDays($days))
            ->where('is_bot', false)
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->groupBy('page_type')
            ->pluck('total', 'page_type');

        // Páginas más populares
        $popularPages = PageView::selectRaw('page_url, page_type, COUNT(*) as views')
            ->where('created_at', '>=', now()->subDays($days))
            ->where('is_bot', false)
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->when($pageType, fn($q) => $q->where('page_type', $pageType))
            ->groupBy(['page_url', 'page_type'])
            ->orderByDesc('views')
            ->limit(10)
            ->get();

        // Vistas por día
        $viewsByDay = PageView::selectRaw('DATE(created_at) as date, COUNT(*) as views')
            ->where('created_at', '>=', now()->subDays($days))
            ->where('is_bot', false)
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->when($pageType, fn($q) => $q->where('page_type', $pageType))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Información de dispositivos
        $deviceStats = PageView::selectRaw('device_type, COUNT(*) as total')
            ->where('created_at', '>=', now()->subDays($days))
            ->where('is_bot', false)
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->groupBy('device_type')
            ->pluck('total', 'device_type');

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'total_views' => $totalViews,
                    'unique_visitors' => $uniqueVisitors,
                    'days_analyzed' => $days,
                ],
                'views_by_type' => $viewsByType,
                'popular_pages' => $popularPages,
                'views_by_day' => $viewsByDay,
                'device_stats' => $deviceStats,
            ]
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/analytics/track-cart-abandonment",
     *     tags={"Analytics"},
     *     summary="Track cart abandonment",
     *     description="Records cart abandonment for analytics",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="cart_token", type="string", description="Cart token", example="abc123"),
     *             @OA\Property(property="store_id", type="integer", description="Store ID", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cart abandonment tracked successfully"
     *     )
     * )
     */
    public function trackCartAbandonment(Request $request): JsonResponse
    {
        $request->validate([
            'cart_token' => 'required|string',
            'store_id' => 'required|integer|exists:stores,id'
        ]);

        // Update cart status to abandoned
        $cart = Cart::where('cart_token', $request->cart_token)
                   ->where('store_id', $request->store_id)
                   ->first();

        if ($cart) {
            $cart->update(['status' => 'abandoned']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Cart abandonment tracked successfully',
            'data' => [
                'cart_token' => $request->cart_token,
                'tracked_at' => now()->format('Y-m-d H:i:s')
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/analytics/abandoned-carts",
     *     tags={"Analytics"},
     *     summary="Get abandoned carts",
     *     description="Returns detailed abandoned carts data",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         description="Filter by store ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Abandoned carts retrieved successfully"
     *     )
     * )
     */
    public function getAbandonedCarts(Request $request): JsonResponse
    {
        // Redirect to simple abandoned carts for now
        return $this->getAbandonedCartsSimple($request);
    }
}
