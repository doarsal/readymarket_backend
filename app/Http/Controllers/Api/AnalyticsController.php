<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\Cart;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

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
     *     path="/api/v1/analytics/track-view",
     *     tags={"Analytics"},
     *     summary="Track page view",
     *     description="Records a page view for analytics",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="page_type", type="string", description="Type of page (product, category, index)", example="product"),
     *             @OA\Property(property="page_id", type="integer", description="ID of the page", example=1),
     *             @OA\Property(property="store_id", type="integer", description="Store ID", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Page view tracked successfully"
     *     )
     * )
     */
    public function trackView(Request $request): JsonResponse
    {
        $request->validate([
            'page_type' => 'required|string|in:product,category,index,store',
            'page_id' => 'nullable|integer',
            'store_id' => 'required|integer|exists:stores,id'
        ]);

        // Simple tracking (you can expand this later)
        return response()->json([
            'success' => true,
            'message' => 'Page view tracked successfully',
            'data' => [
                'page_type' => $request->page_type,
                'page_id' => $request->page_id,
                'store_id' => $request->store_id,
                'tracked_at' => now()->format('Y-m-d H:i:s')
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/analytics/page-views",
     *     tags={"Analytics"},
     *     summary="Get page views analytics",
     *     description="Returns page views analytics data",
     *     security={{"bearerAuth":{}}},
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
     *     @OA\Response(
     *         response=200,
     *         description="Page views retrieved successfully"
     *     )
     * )
     */
    public function getPageViews(Request $request): JsonResponse
    {
        // Simple implementation - you can expand this later
        return response()->json([
            'success' => true,
            'data' => [
                'total_views' => 0,
                'views_by_type' => [
                    'product' => 0,
                    'category' => 0,
                    'index' => 0
                ],
                'message' => 'Page views tracking system ready for implementation'
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
