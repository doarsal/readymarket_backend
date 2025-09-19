<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\Cart;
use App\Models\PageView;
use App\Models\Search;
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

    /**
     * Get comprehensive executive dashboard data
     * All the real metrics requested: users, products, sales, visits, etc.
     */
    public function getExecutiveDashboard(Request $request): JsonResponse
    {
        try {
            $dateRange = $this->getDateRange($request);
            $storeId = $request->get('store_id');

            // Base queries with date filtering
            $ordersQuery = DB::table('orders')->whereBetween('orders.created_at', $dateRange);
            $pageViewsQuery = DB::table('page_views')->whereBetween('page_views.created_at', $dateRange);
            $cartsQuery = DB::table('carts')->whereBetween('carts.created_at', $dateRange);

            if ($storeId) {
                $ordersQuery->where('store_id', $storeId);
                $cartsQuery->where('store_id', $storeId);
            }

            // 1. KPIs Principales
            $totalUsers = DB::table('users')->count();
            $totalProductsActive = DB::table('products')->where('is_active', 1)->count();
            $totalSalesCount = $ordersQuery->count();
            $totalSalesAmount = $ordersQuery->where('payment_status', 'paid')->sum('total_amount');
            $totalVisits = $pageViewsQuery->count();
            $totalMicrosoftAccounts = DB::table('microsoft_accounts')->where('is_active', 1)->count();

            // 2. Producto más comprado
            $mostBoughtProduct = DB::table('order_items')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->join('products', 'order_items.product_id', '=', 'products.idproduct')
                ->whereBetween('orders.created_at', $dateRange)
                ->select('products.ProductTitle', DB::raw('COUNT(*) as total_purchases'))
                ->groupBy('order_items.product_id', 'products.ProductTitle')
                ->orderBy('total_purchases', 'desc')
                ->first();

            // 3. Producto más visitado
            $mostVisitedProduct = DB::table('page_views')
                ->whereBetween('page_views.created_at', $dateRange)
                ->where('page_views.page_type', 'product-details')
                ->whereNotNull('page_views.resource_id')
                ->select('page_views.resource_id', DB::raw('COUNT(*) as visits'))
                ->groupBy('page_views.resource_id')
                ->orderByDesc('visits')
                ->first();

            $mostVisitedProductDetails = null;
            if ($mostVisitedProduct) {
                $mostVisitedProductDetails = DB::table('products')
                    ->where('idproduct', $mostVisitedProduct->resource_id)
                    ->select('ProductTitle', 'Publisher')
                    ->first();
            }

            // 4. Categoría más visitada
            $mostVisitedCategory = DB::table('page_views')
                ->whereBetween('page_views.created_at', $dateRange)
                ->where('page_views.page_type', 'products-category')
                ->whereNotNull('page_views.resource_id')
                ->select('page_views.resource_id', DB::raw('COUNT(*) as visits'))
                ->groupBy('page_views.resource_id')
                ->orderByDesc('visits')
                ->first();

            $mostVisitedCategoryDetails = null;
            if ($mostVisitedCategory) {
                $mostVisitedCategoryDetails = DB::table('categories')
                    ->where('id', $mostVisitedCategory->resource_id)
                    ->select('name', 'identifier')
                    ->first();
            }

            // 5. Top 10 productos más visitados
            $topVisitedProducts = DB::table('page_views')
                ->whereBetween('page_views.created_at', $dateRange)
                ->where('page_views.page_type', 'product-details')
                ->whereNotNull('page_views.resource_id')
                ->select('page_views.resource_id', DB::raw('COUNT(*) as visits'))
                ->groupBy('page_views.resource_id')
                ->orderByDesc('visits')
                ->limit(10)
                ->get();

            $topVisitedProductsDetails = [];
            foreach ($topVisitedProducts as $product) {
                $details = DB::table('products')
                    ->where('idproduct', $product->resource_id)
                    ->select('ProductTitle', 'Publisher', 'UnitPrice')
                    ->first();
                if ($details) {
                    $topVisitedProductsDetails[] = [
                        'title' => $details->ProductTitle,
                        'publisher' => $details->Publisher,
                        'price' => $details->UnitPrice,
                        'visits' => $product->visits
                    ];
                }
            }

            // 6. Visitas por geografía
            $visitsByCountry = DB::table('page_views')
                ->whereBetween('page_views.created_at', $dateRange)
                ->whereNotNull('page_views.country')
                ->select('page_views.country', 'page_views.region', DB::raw('COUNT(*) as visits'))
                ->groupBy('page_views.country', 'page_views.region')
                ->orderByDesc('visits')
                ->limit(10)
                ->get();

            // 7. Top 10 mejores clientes (más compras)
            $topCustomers = DB::table('orders')
                ->join('users', 'orders.user_id', '=', 'users.id')
                ->whereBetween('orders.created_at', $dateRange)
                ->where('orders.payment_status', 'paid')
                ->select(
                    'users.id',
                    DB::raw('CONCAT(users.first_name, " ", users.last_name) as name'),
                    'users.email',
                    DB::raw('COUNT(*) as total_orders'),
                    DB::raw('SUM(orders.total_amount) as total_spent')
                )
                ->groupBy('users.id', 'users.first_name', 'users.last_name', 'users.email')
                ->orderBy('total_spent', 'desc')
                ->limit(10)
                ->get();

            // 8. Estadísticas de carritos
            $abandonedCartsQuery = DB::table('carts')->whereBetween('carts.created_at', $dateRange);
            if ($storeId) {
                $abandonedCartsQuery->where('carts.store_id', $storeId);
            }

            $abandonedCarts = $abandonedCartsQuery->where('carts.status', 'abandoned')->count();
            $activeCarts = DB::table('carts')
                ->whereBetween('carts.created_at', $dateRange)
                ->when($storeId, function($query, $storeId) {
                    return $query->where('store_id', $storeId);
                })
                ->where('status', 'active')
                ->count();

            $convertedCarts = DB::table('carts')
                ->whereBetween('carts.created_at', $dateRange)
                ->when($storeId, function($query, $storeId) {
                    return $query->where('store_id', $storeId);
                })
                ->where('status', 'converted')
                ->count();

            $abandonedCartsValue = DB::table('carts')
                ->join('cart_items', 'carts.id', '=', 'cart_items.cart_id')
                ->join('products', 'cart_items.product_id', '=', 'products.idproduct')
                ->where('carts.status', 'abandoned')
                ->whereBetween('carts.created_at', $dateRange)
                ->when($storeId, function($query, $storeId) {
                    return $query->where('carts.store_id', $storeId);
                })
                ->sum(DB::raw('cart_items.quantity * CAST(products.UnitPrice as DECIMAL(10,2))'));

            // Valor total de carritos activos (potencial pérdida si se abandonan)
            $activeCartsValue = DB::table('carts')
                ->join('cart_items', 'carts.id', '=', 'cart_items.cart_id')
                ->join('products', 'cart_items.product_id', '=', 'products.idproduct')
                ->where('carts.status', 'active')
                ->whereBetween('carts.created_at', $dateRange)
                ->when($storeId, function($query, $storeId) {
                    return $query->where('carts.store_id', $storeId);
                })
                ->sum(DB::raw('cart_items.quantity * CAST(products.UnitPrice as DECIMAL(10,2))'));

            // Valor total de carritos convertidos (ventas realizadas)
            $convertedCartsValue = DB::table('carts')
                ->join('cart_items', 'carts.id', '=', 'cart_items.cart_id')
                ->join('products', 'cart_items.product_id', '=', 'products.idproduct')
                ->where('carts.status', 'converted')
                ->whereBetween('carts.created_at', $dateRange)
                ->when($storeId, function($query, $storeId) {
                    return $query->where('carts.store_id', $storeId);
                })
                ->sum(DB::raw('cart_items.quantity * CAST(products.UnitPrice as DECIMAL(10,2))'));

            // 9. Clicks en American Express (modal opens)
            $amexClicks = DB::table('page_views')
                ->whereBetween('page_views.created_at', $dateRange)
                ->whereJsonContains('page_views.additional_data->modal_type', 'american_express')
                ->count();

            // 10. Lista de visitas por página
            $pageVisits = DB::table('page_views')
                ->whereBetween('page_views.created_at', $dateRange)
                ->select('page_views.page_type', 'page_views.page_path', DB::raw('COUNT(*) as visits'))
                ->groupBy('page_views.page_type', 'page_views.page_path')
                ->orderByDesc('visits')
                ->limit(20)
                ->get();

            // 11. Visitas por categoría
            $visitsByCategory = DB::table('page_views')
                ->whereBetween('page_views.created_at', $dateRange)
                ->where('page_views.page_type', 'products-category')
                ->whereNotNull('page_views.resource_id')
                ->join('categories', 'page_views.resource_id', '=', 'categories.id')
                ->select('categories.name', 'categories.identifier', DB::raw('COUNT(*) as visits'))
                ->groupBy('categories.id', 'categories.name', 'categories.identifier')
                ->orderByDesc('visits')
                ->get();

            // 12. Tendencia de ventas (últimos 30 días por día)
            $salesTrend = DB::table('orders')
                ->where('payment_status', 'paid')
                ->whereBetween('created_at', $dateRange)
                ->select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('COUNT(*) as orders'),
                    DB::raw('SUM(total_amount) as revenue')
                )
                ->groupBy(DB::raw('DATE(created_at)'))
                ->orderBy('date', 'asc')
                ->get();

            // 13. Estadísticas de dispositivos
            $deviceStats = DB::table('page_views')
                ->whereBetween('page_views.created_at', $dateRange)
                ->select('page_views.device_type', DB::raw('COUNT(*) as visits'))
                ->groupBy('page_views.device_type')
                ->orderByDesc('visits')
                ->get();

            // 14. Estadísticas de navegadores
            $browserStats = DB::table('page_views')
                ->whereBetween('page_views.created_at', $dateRange)
                ->select('page_views.browser', DB::raw('COUNT(*) as visits'))
                ->whereNotNull('page_views.browser')
                ->groupBy('page_views.browser')
                ->orderByDesc('visits')
                ->limit(10)
                ->get();

            // 15. Estadísticas de sistemas operativos
            $osStats = DB::table('page_views')
                ->whereBetween('page_views.created_at', $dateRange)
                ->select('page_views.os', DB::raw('COUNT(*) as visits'))
                ->whereNotNull('page_views.os')
                ->groupBy('page_views.os')
                ->orderByDesc('visits')
                ->limit(10)
                ->get();

            // 16. Estadísticas de búsquedas
            $totalSearches = DB::table('searches')
                ->whereBetween('searches.created_at', $dateRange)
                ->when($storeId, function($query, $storeId) {
                    return $query->where('store_id', $storeId);
                })
                ->count();

            $averageSearchResults = DB::table('searches')
                ->whereBetween('searches.created_at', $dateRange)
                ->when($storeId, function($query, $storeId) {
                    return $query->where('store_id', $storeId);
                })
                ->avg('total_results');

            $topSearchTerms = DB::table('searches')
                ->whereBetween('searches.created_at', $dateRange)
                ->when($storeId, function($query, $storeId) {
                    return $query->where('store_id', $storeId);
                })
                ->select('search_term', DB::raw('COUNT(*) as search_count'), DB::raw('AVG(total_results) as avg_results'))
                ->groupBy('search_term')
                ->orderByDesc('search_count')
                ->limit(10)
                ->get();

            // Lista detallada de búsquedas para la tabla (con paginación)
            $allSearches = DB::table('searches')
                ->whereBetween('searches.created_at', $dateRange)
                ->when($storeId, function($query, $storeId) {
                    return $query->where('store_id', $storeId);
                })
                ->leftJoin('users', 'searches.user_id', '=', 'users.id')
                ->select(
                    'searches.id',
                    'searches.search_term',
                    'searches.total_results',
                    'searches.created_at',
                    'searches.session_id',
                    DB::raw('CONCAT(users.first_name, " ", users.last_name) as user_name'),
                    'users.email as user_email'
                )
                ->orderByDesc('searches.created_at')
                ->limit(100) // Limitar a 100 registros más recientes
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'kpis' => [
                        'total_users' => $totalUsers,
                        'total_products_active' => $totalProductsActive,
                        'total_sales_count' => $totalSalesCount,
                        'total_sales_amount' => round($totalSalesAmount, 2),
                        'total_visits' => $totalVisits,
                        'total_microsoft_accounts' => $totalMicrosoftAccounts
                    ],
                    'top_metrics' => [
                        'most_bought_product' => $mostBoughtProduct,
                        'most_visited_product' => $mostVisitedProductDetails ? [
                            'title' => $mostVisitedProductDetails->ProductTitle,
                            'publisher' => $mostVisitedProductDetails->Publisher,
                            'visits' => $mostVisitedProduct->visits
                        ] : null,
                        'most_visited_category' => $mostVisitedCategoryDetails ? [
                            'name' => $mostVisitedCategoryDetails->name,
                            'identifier' => $mostVisitedCategoryDetails->identifier,
                            'visits' => $mostVisitedCategory->visits
                        ] : null
                    ],
                    'top_lists' => [
                        'top_visited_products' => $topVisitedProductsDetails,
                        'top_customers' => $topCustomers,
                        'visits_by_geography' => $visitsByCountry,
                        'visits_by_category' => $visitsByCategory
                    ],
                    'carts' => [
                        'abandoned_count' => $abandonedCarts,
                        'abandoned_value' => round($abandonedCartsValue, 2),
                        'active_count' => $activeCarts,
                        'active_value' => round($activeCartsValue, 2),
                        'converted_count' => $convertedCarts,
                        'converted_value' => round($convertedCartsValue, 2),
                        'total_carts' => $abandonedCarts + $activeCarts + $convertedCarts,
                        'abandonment_rate' => ($abandonedCarts + $activeCarts + $convertedCarts) > 0 ?
                            round(($abandonedCarts / ($abandonedCarts + $activeCarts + $convertedCarts)) * 100, 2) : 0,
                        'conversion_rate' => ($abandonedCarts + $activeCarts + $convertedCarts) > 0 ?
                            round(($convertedCarts / ($abandonedCarts + $activeCarts + $convertedCarts)) * 100, 2) : 0
                    ],
                    'interactions' => [
                        'amex_clicks' => $amexClicks,
                        'page_visits' => $pageVisits
                    ],
                    'technology' => [
                        'device_stats' => $deviceStats,
                        'browser_stats' => $browserStats,
                        'os_stats' => $osStats
                    ],
                    'searches' => [
                        'total_searches' => $totalSearches,
                        'average_results' => round($averageSearchResults ?? 0, 2),
                        'top_search_terms' => $topSearchTerms,
                        'all_searches' => $allSearches
                    ],
                    'trends' => [
                        'sales_trend' => $salesTrend
                    ]
                ],
                'period' => $request->get('period', 'month'),
                'date_range' => $dateRange
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving dashboard data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper method to get date range based on period
     */
    private function getDateRange(Request $request): array
    {
        $period = $request->get('period', 'year');
        $now = now();

        switch ($period) {
            case 'today':
                return [$now->copy()->startOfDay(), $now->copy()->endOfDay()];
            case 'week':
                return [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()];
            case 'month':
                return [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()];
            case 'quarter':
                return [$now->copy()->startOfQuarter(), $now->copy()->endOfQuarter()];
            case 'year':
                return [$now->copy()->startOfYear(), $now->copy()->endOfYear()];
            case 'all':
                // Para mostrar todos los datos históricos
                return [\Carbon\Carbon::parse('2020-01-01'), $now->copy()->endOfYear()];
            case 'custom':
                $start = $request->get('start_date', $now->copy()->startOfMonth());
                $end = $request->get('end_date', $now->copy()->endOfMonth());
                return [
                    \Carbon\Carbon::parse($start)->startOfDay(),
                    \Carbon\Carbon::parse($end)->endOfDay()
                ];
            default:
                return [$now->copy()->startOfYear(), $now->copy()->endOfYear()];
        }
    }

    /**
     * Manually mark carts as abandoned for testing purposes
     *
     * @OA\Post(
     *     path="/api/v1/analytics/mark-abandoned-carts",
     *     tags={"Analytics"},
     *     summary="Mark old carts as abandoned",
     *     description="Marks carts as abandoned after specified hours of inactivity",
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="hours", type="integer", description="Hours of inactivity", example=24)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Carts marked as abandoned successfully"
     *     )
     * )
     */
    public function markAbandonedCarts(Request $request): JsonResponse
    {
        $hours = $request->get('hours', 24);
        $cutoffTime = now()->subHours($hours);

        $abandonedCount = DB::table('carts')
            ->where('status', 'active')
            ->where('updated_at', '<', $cutoffTime)
            ->update(['status' => 'abandoned']);

        return response()->json([
            'success' => true,
            'message' => "Se marcaron {$abandonedCount} carritos como abandonados",
            'data' => [
                'marked_count' => $abandonedCount,
                'hours_inactive' => $hours,
                'cutoff_time' => $cutoffTime->format('Y-m-d H:i:s')
            ]
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/analytics/track-search",
     *     tags={"Analytics"},
     *     summary="Track a search query",
     *     description="Records a search query with its results count and metadata",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"search_term", "total_results"},
     *             @OA\Property(property="search_term", type="string", description="The search term used"),
     *             @OA\Property(property="total_results", type="integer", description="Number of results found"),
     *             @OA\Property(property="session_id", type="string", description="User session ID"),
     *             @OA\Property(property="store_id", type="integer", description="Store ID if applicable")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Search tracked successfully"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function trackSearch(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'search_term' => 'required|string|max:255',
                'total_results' => 'required|integer|min:0',
                'session_id' => 'nullable|string|max:255',
                'store_id' => 'nullable|integer',
                'user_id' => 'nullable|integer'
            ]);

            // Determinar el user_id
            // 1. Si se envía user_id explícitamente desde el frontend, usar ese
            // 2. Si no, intentar obtenerlo de la sesión autenticada (si existe)
            $userId = $request->user_id ?? (auth()->check() ? auth()->id() : null);

            // Crear el registro de búsqueda
            $search = Search::create([
                'search_term' => trim($request->search_term),
                'total_results' => $request->total_results,
                'user_id' => $userId,
                'store_id' => $request->store_id,
                'session_id' => $request->session_id ?? session()->getId(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent')
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Search tracked successfully',
                'data' => [
                    'search_id' => $search->id,
                    'search_term' => $search->search_term,
                    'total_results' => $search->total_results,
                    'user_id' => $search->user_id
                ]
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error tracking search: ' . $e->getMessage()
            ], 500);
        }
    }
}
