<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\Product;
use App\Models\ExchangeRate;
use Config;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    /**
     * Get current store currency code
     */
    private function getStoreCurrencyCode(): string
    {
        $storeId = config('app.store_id', 1);
        $store = \App\Models\Store::find($storeId);

        if (!$store) {
            return Config::get('app.default_currency'); // Fallback
        }

        $defaultCurrency = $store->getDefaultCurrency();
        return $defaultCurrency ? $defaultCurrency->code : Config::get('app.default_currency');
    }

    /**
     * Convert currency amount from USD to store currency
     */
    private function convertCurrency(float $amount, string $fromCurrency, string $toCurrency): float
    {
        if ($fromCurrency === $toCurrency) {
            return $amount;
        }

        // Obtener IDs de las monedas
        $fromCurrencyModel = \App\Models\Currency::where('code', $fromCurrency)->first();
        $toCurrencyModel = \App\Models\Currency::where('code', $toCurrency)->first();

        if (!$fromCurrencyModel || !$toCurrencyModel) {
            return $amount; // No se puede convertir
        }

        // Obtener tipo de cambio
        $exchangeRate = ExchangeRate::where('from_currency_id', $fromCurrencyModel->id)
            ->where('to_currency_id', $toCurrencyModel->id)
            ->where('is_active', true)
            ->orderBy('date', 'desc')
            ->first();

        if (!$exchangeRate) {
            return $amount; // No hay tipo de cambio disponible
        }

        return $amount * $exchangeRate->rate;
    }
        /**
     * @OA\Get(
     *     path="/api/v1/categories",
     *     operationId="getCategories",
     *     tags={"Categories"},
     *     summary="Get list of categories",
     *     description="Returns list of active categories with products count",
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         description="Filter by store ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Categories retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/Category")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error"
     *     )
     * )
     *
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $request = request();

        // Validación
        $request->validate([
            'store_id' => 'nullable|integer|exists:stores,id'
        ]);

        // Crear cache key único por store
        $storeId = $request->get('store_id', 'all');
        $cacheKey = "categories_store_{$storeId}";

        // Cache por 1 hora (3600 segundos)
        $categories = Cache::remember($cacheKey, 3600, function () use ($request) {
            $query = Category::active()->ordered();

            // Filter by store
            if ($request->filled('store_id')) {
                $query->where('store_id', $request->store_id);
            }

            $categories = $query->get();

            // Calcular el conteo correcto de productos agrupados para cada categoría
            foreach ($categories as $category) {
                // Contar productos únicos por SkuId + Id para esta categoría
                $productQuery = Product::select('SkuId', 'Id')
                    ->where('category_id', $category->id)
                    ->where('is_active', true)
                    ->whereNotNull('Id')
                    ->where('UnitPrice', '>', 0)
                    ->whereNull('deleted_at');

                // Si hay filtro por tienda, aplicar también a los productos
                if ($request->filled('store_id')) {
                    $productQuery->where('store_id', $request->store_id);
                }

                $uniqueProductsCount = $productQuery->groupBy('SkuId', 'Id')
                    ->get()
                    ->count();

                $category->active_products_count = $uniqueProductsCount;
            }

            return $categories;
        });

        return response()->json([
            'success' => true,
            'data' => CategoryResource::collection($categories),
            'message' => 'Categories retrieved successfully'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/categories",
     *     operationId="storeCategory",
     *     tags={"Categories"},
     *     summary="Create a new category",
     *     description="Store a newly created category in storage",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "identifier"},
     *             @OA\Property(property="name", type="string", maxLength=255, example="Microsoft Azure"),
     *             @OA\Property(property="image", type="string", maxLength=255, example="categories/azure.png"),
     *             @OA\Property(property="identifier", type="string", maxLength=255, example="az1B23cD"),
     *             @OA\Property(property="is_active", type="boolean", example=true),
     *             @OA\Property(property="sort_order", type="integer", minimum=0, example=1),
     *             @OA\Property(property="columns", type="integer", minimum=1, maximum=12, example=4),
     *             @OA\Property(property="description", type="string", example="Cloud computing services")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Category created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Category created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Category")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationError")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     *
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'image' => 'nullable|string|max:255',
            'identifier' => 'required|string|max:255|unique:categories',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
            'columns' => 'integer|min:1|max:12',
            'description' => 'nullable|string'
        ]);

        $category = Category::create($validated);

        return response()->json([
            'success' => true,
            'data' => $category,
            'message' => 'Category created successfully'
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/categories/{id}",
     *     operationId="getCategoryById",
     *     tags={"Categories"},
     *     summary="Get category by ID",
     *     description="Returns a single category with its products",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Category ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Category retrieved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Category")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Category not found"
     *     )
     * )
     *
     * Display the specified resource.
     */
    public function show(Category $category): JsonResponse
    {
        try {
            // Load products count
            $category->products_count = $category->products()->count();
            $category->active_products_count = $category->activeProducts()->count();

            // Get all attributes including computed ones
            $categoryData = array_merge($category->getAttributes(), [
                'products_count' => $category->products_count,
                'active_products_count' => $category->active_products_count
            ]);

            // Increment visits after getting the data
            $category->increment('visits');

            return response()->json([
                'success' => true,
                'data' => $categoryData,
                'message' => 'Category retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading category: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/v1/categories/{id}",
     *     operationId="updateCategory",
     *     tags={"Categories"},
     *     summary="Update category",
     *     description="Update the specified category in storage",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Category ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", maxLength=255, example="Microsoft Azure"),
     *             @OA\Property(property="image", type="string", maxLength=255, example="categories/azure.png"),
     *             @OA\Property(property="identifier", type="string", maxLength=255, example="az1B23cD"),
     *             @OA\Property(property="is_active", type="boolean", example=true),
     *             @OA\Property(property="is_deleted", type="boolean", example=false),
     *             @OA\Property(property="sort_order", type="integer", minimum=0, example=1),
     *             @OA\Property(property="columns", type="integer", minimum=1, maximum=12, example=4),
     *             @OA\Property(property="description", type="string", example="Cloud computing services")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Category updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Category updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Category")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Category not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationError")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     *
     * Update the specified resource in storage.
     */
    public function update(Request $request, Category $category): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'string|max:255',
            'image' => 'nullable|string|max:255',
            'identifier' => ['string', 'max:255', Rule::unique('categories')->ignore($category->id)],
            'is_active' => 'boolean',
            'is_deleted' => 'boolean',
            'sort_order' => 'integer|min:0',
            'columns' => 'integer|min:1|max:12',
            'description' => 'nullable|string'
        ]);

        $category->update($validated);

        return response()->json([
            'success' => true,
            'data' => $category,
            'message' => 'Category updated successfully'
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/categories/{id}",
     *     operationId="deleteCategory",
     *     tags={"Categories"},
     *     summary="Delete category",
     *     description="Soft delete the specified category",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Category ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Category deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Category soft deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Category not found"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     *
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category): JsonResponse
    {
        $category->update(['is_deleted' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Category soft deleted successfully'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/categories/{id}/products",
     *     operationId="getCategoryProducts",
     *     tags={"Categories"},
     *     summary="Get products for a category",
     *     description="Returns paginated list of products for a specific category",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Category ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="featured",
     *         in="query",
     *         description="Filter featured products",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="bestsellers",
     *         in="query",
     *         description="Filter bestseller products",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search term for products",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         description="Filter by store ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=16, minimum=1, maximum=100)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Category products retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 allOf={
     *                     @OA\Schema(ref="#/components/schemas/PaginatedResponse"),
     *                     @OA\Schema(
     *                         @OA\Property(
     *                             property="data",
     *                             type="array",
     *                             @OA\Items(ref="#/components/schemas/Product")
     *                         )
     *                     )
     *                 }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Category not found"
     *     )
     * )
     *
     * Get products for a specific category
     */
    public function products(Category $category, Request $request): JsonResponse
    {
        // Validación
        $request->validate([
            'store_id' => 'nullable|integer|exists:stores,id',
            'featured' => 'nullable|boolean',
            'bestsellers' => 'nullable|boolean',
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100'
        ]);

        $query = $category->activeProducts();

        // Filter by store
        if ($request->filled('store_id')) {
            $query->where('store_id', $request->store_id);
        }

        // Apply filters
        if ($request->filled('featured')) {
            $query->where('top', true);
        }

        if ($request->filled('bestsellers')) {
            $query->where('bestseller', true);
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('SkuTitle', 'like', "%{$search}%")
                  ->orWhere('SkuDescription', 'like', "%{$search}%")
                  ->orWhere('Publisher', 'like', "%{$search}%");
            });
        }

        // Pagination
        $perPage = min($request->input('per_page', 16), 100);

        try {
            $products = $query->paginate($perPage);

            // Procesar los productos para SOBREESCRIBIR los precios originales
            $processedProducts = $products->getCollection()->map(function ($product) {
                $convertedPrice = $this->convertCurrency((float)$product->UnitPrice, 'USD', $this->getStoreCurrencyCode());
                $storeCurrencyCode = $this->getStoreCurrencyCode();

                // SOBREESCRIBIR los campos que usa el frontend
                $productArray = $product->toArray();
                $productArray['UnitPrice'] = number_format($convertedPrice, 2); // El precio que usa el frontend
                $productArray['Currency'] = $storeCurrencyCode; // La moneda que usa el frontend

                return $productArray;
            });            // Actualizar la colección de productos procesados
            $products->setCollection($processedProducts);

            return response()->json([
                'success' => true,
                'data' => $products,
                'message' => 'Category products retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading category products: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/categories/by-store/{storeId}",
     *     tags={"Categories"},
     *     summary="Get categories by store",
     *     description="Returns all categories for a specific store",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="storeId",
     *         in="path",
     *         description="Store ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Store categories retrieved successfully"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function getByStore(int $storeId): JsonResponse
    {
        $categories = Category::where('store_id', $storeId)
                            ->where('is_active', true)
                            ->orderBy('sort_order')
                            ->get();

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/categories/stats",
     *     tags={"Categories"},
     *     summary="Get category statistics",
     *     description="Returns category statistics with product counts",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Statistics retrieved successfully"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function getStats(): JsonResponse
    {
        $stats = Category::withCount(['products', 'activeProducts'])
                        ->where('is_active', true)
                        ->orderBy('products_count', 'desc')
                        ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_categories' => Category::where('is_active', true)->count(),
                'categories_with_products' => $stats,
                'most_popular' => $stats->first()
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/categories/menu",
     *     operationId="getCategoriesMenu",
     *     tags={"Categories"},
     *     summary="Get categories with products for header menu",
     *     description="Returns categories with prioritized products for the header navigation menu",
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         description="Filter by store ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Menu categories retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="image", type="string"),
     *                     @OA\Property(property="identifier", type="string"),
     *                     @OA\Property(property="description", type="string"),
     *                     @OA\Property(
     *                         property="products",
     *                         type="array",
     *                         @OA\Items(
     *                             @OA\Property(property="idproduct", type="integer"),
     *                             @OA\Property(property="SkuId", type="string"),
     *                             @OA\Property(property="SkuTitle", type="string"),
     *                             @OA\Property(property="SkuDescription", type="string"),
     *                             @OA\Property(property="UnitPrice", type="string"),
     *                             @OA\Property(property="prod_icon", type="string"),
     *                             @OA\Property(property="is_top", type="boolean"),
     *                             @OA\Property(property="is_bestseller", type="boolean"),
     *                             @OA\Property(property="is_novelty", type="boolean"),
     *                             @OA\Property(property="priority_type", type="string", enum={"top", "bestseller", "novelty", "random"})
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error"
     *     )
     * )
     *
     * Get categories with prioritized products for header menu
     */
    public function menu(): JsonResponse
    {
        $request = request();

        // Validación
        $request->validate([
            'store_id' => 'nullable|integer|exists:stores,id'
        ]);

        // Crear cache key único por store
        $storeId = $request->get('store_id', 'all');
        $cacheKey = "categories_menu_store_{$storeId}";

        // Cache por 30 minutos (1800 segundos)
        $menuData = Cache::remember($cacheKey, 1800, function () use ($request) {
            $query = Category::active()->ordered();

            // Filter by store
            if ($request->filled('store_id')) {
                $query->where('store_id', $request->store_id);
            }

            $categories = $query->get();

            $menuCategories = [];

            foreach ($categories as $category) {
                // Base query para productos de esta categoría
                $baseQuery = Product::select([
                    'idproduct',
                    'SkuId',
                    'Id',
                    'SkuTitle',
                    'SkuDescription',
                    'UnitPrice',
                    'prod_icon',
                    'is_top',
                    'is_bestseller',
                    'is_novelty',
                    'category_id'
                ])
                ->where('category_id', $category->id)
                ->where('is_active', true)
                ->whereNotNull('Id')
                ->where('UnitPrice', '>', 0)
                ->whereNull('deleted_at');

                // Si hay filtro por tienda, aplicar también a los productos
                if ($request->filled('store_id')) {
                    $baseQuery->where('store_id', $request->store_id);
                }

                // Estrategia de priorización:
                // 1. Primero obtener productos TOP (máximo 10)
                $topProducts = (clone $baseQuery)
                    ->where('is_top', true)
                    ->distinct()
                    ->orderBy('SkuTitle')
                    ->limit(10)
                    ->get()
                    ->map(function ($product) {
                        $product->priority_type = 'top';
                        return $product;
                    });

                $products = collect($topProducts);
                $remainingSlots = 10 - $products->count();

                // 2. Si necesitamos más, agregar BESTSELLERS
                if ($remainingSlots > 0) {
                    $bestsellerProducts = (clone $baseQuery)
                        ->where('is_bestseller', true)
                        ->whereNotIn('SkuId', $products->pluck('SkuId'))
                        ->distinct()
                        ->orderBy('SkuTitle')
                        ->limit($remainingSlots)
                        ->get()
                        ->map(function ($product) {
                            $product->priority_type = 'bestseller';
                            return $product;
                        });

                    $products = $products->merge($bestsellerProducts);
                    $remainingSlots = 10 - $products->count();
                }

                // 3. Si necesitamos más, agregar NOVELTIES
                if ($remainingSlots > 0) {
                    $noveltyProducts = (clone $baseQuery)
                        ->where('is_novelty', true)
                        ->whereNotIn('SkuId', $products->pluck('SkuId'))
                        ->distinct()
                        ->orderBy('SkuTitle')
                        ->limit($remainingSlots)
                        ->get()
                        ->map(function ($product) {
                            $product->priority_type = 'novelty';
                            return $product;
                        });

                    $products = $products->merge($noveltyProducts);
                    $remainingSlots = 10 - $products->count();
                }

                // 4. Si aún necesitamos más, completar con productos aleatorios
                if ($remainingSlots > 0) {
                    $randomProducts = (clone $baseQuery)
                        ->whereNotIn('SkuId', $products->pluck('SkuId'))
                        ->distinct()
                        ->inRandomOrder()
                        ->limit($remainingSlots)
                        ->get()
                        ->map(function ($product) {
                            $product->priority_type = 'random';
                            return $product;
                        });

                    $products = $products->merge($randomProducts);
                }

                // Solo incluir categorías que tengan productos
                if ($products->count() > 0) {
                    $menuCategories[] = [
                        'id' => $category->id,
                        'name' => $category->name,
                        'image' => $category->image,
                        'identifier' => $category->identifier,
                        'description' => $category->description,
                        'products_count' => $products->count(),
                        'products' => $products->values()
                    ];
                }
            }

            return $menuCategories;
        });

        return response()->json([
            'success' => true,
            'data' => $menuData,
            'message' => 'Menu categories retrieved successfully'
        ]);
    }
}
