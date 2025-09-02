<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

/**
 * @OA\Tag(
 *     name="Products",
 *     description="Product management endpoints"
 * )
 */
class ProductController extends ApiController
{
    /**
     * @OA\Get(
     *     path="/api/v1/products",
     *     tags={"Products"},
     *     summary="Get list of products",
     *     description="Returns paginated list of products grouped by SkuId + Id with variants",
     *     @OA\Parameter(
     *         name="category_id",
     *         in="query",
     *         description="Filter by category ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         description="Filter by store ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search in product title and publisher",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of products per page (1-100)",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=16)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, default=1)
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort field",
     *         required=false,
     *         @OA\Schema(type="string", enum={"title", "unit_price", "publisher"}, default="title")
     *     ),
     *     @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         description="Sort order",
     *         required=false,
     *         @OA\Schema(type="string", enum={"asc", "desc"}, default="asc")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Products retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=883),
     *                 @OA\Property(property="title", type="string", example="10-year audit log retention"),
     *                 @OA\Property(property="description", type="string", example="Para ayudar a cumplir..."),
     *                 @OA\Property(property="product_id", type="string", example="CFQ7TTC0HL8Z"),
     *                 @OA\Property(property="sku_id", type="string", example="0001"),
     *                 @OA\Property(property="publisher", type="string", example="Microsoft Corporation"),
     *                 @OA\Property(property="logo", type="string", example="1/products/CFQ7TTC0HL8Z/icon.png"),
     *                 @OA\Property(property="details", type="object",
     *                     @OA\Property(property="market", type="string", example="MX"),
     *                     @OA\Property(property="segment", type="string", example="Commercial")
     *                 ),
     *                 @OA\Property(property="category", type="object",
     *                     @OA\Property(property="id", type="integer", example=1)
     *                 ),
     *                 @OA\Property(property="variants", type="array", @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=883),
     *                     @OA\Property(property="billing_plan", type="string", example="Monthly"),
     *                     @OA\Property(property="unit_price", type="string", example="1.92"),
     *                     @OA\Property(property="term_duration", type="string", example="P1M"),
     *                     @OA\Property(property="sku_id", type="string", example="0001")
     *                 ))
     *             )),
     *             @OA\Property(property="pagination", type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="per_page", type="integer", example=16),
     *                 @OA\Property(property="total", type="integer", example=25),
     *                 @OA\Property(property="total_pages", type="integer", example=2)
     *             ),
     *             @OA\Property(property="message", type="string", example="Products retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error"
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        try {
            $request = request();

            // Validación de parámetros de entrada
            $request->validate([
                'category_id' => 'nullable|integer',
                'store_id' => 'nullable|integer|exists:stores,id',
                'search' => 'nullable|string|max:255',
                'per_page' => 'nullable|integer|min:1|max:100',
                'page' => 'nullable|integer|min:1',
                'sort_by' => 'nullable|string|in:title,unit_price,publisher',
                'sort_order' => 'nullable|string|in:asc,desc'
            ]);

            $perPage = $request->get('per_page', 16);
            $page = $request->get('page', 1);
            $sortBy = $request->get('sort_by', 'title');
            $sortOrder = $request->get('sort_order', 'asc');

            // Crear cache key único basado en los filtros
            $cacheKey = $this->generateProductsCacheKey($request, $perPage, $page, $sortBy, $sortOrder);

            // Cache por 15 minutos para productos con filtros
            // Solo cachear si no hay búsqueda (búsquedas son muy dinámicas)
            $shouldCache = !$request->filled('search');
            $cacheTime = 900; // 15 minutos

            if ($shouldCache) {
                $result = Cache::remember($cacheKey, $cacheTime, function () use ($request, $perPage, $page, $sortBy, $sortOrder) {
                    return $this->getProductsData($request, $perPage, $page, $sortBy, $sortOrder);
                });
            } else {
                // Si hay búsqueda, no usar cache para resultados más frescos
                $result = $this->getProductsData($request, $perPage, $page, $sortBy, $sortOrder);
            }

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * Generar cache key único para productos basado en filtros
     */
    private function generateProductsCacheKey($request, $perPage, $page, $sortBy, $sortOrder): string
    {
        $filters = [
            'category_id' => $request->get('category_id', 'all'),
            'store_id' => $request->get('store_id', 'all'),
            'per_page' => $perPage,
            'page' => $page,
            'sort_by' => $sortBy,
            'sort_order' => $sortOrder
        ];

        $filterString = http_build_query($filters);
        return 'products_list_' . md5($filterString);
    }

    /**
     * Obtener datos de productos con toda la lógica de consulta
     */
    private function getProductsData($request, $perPage, $page, $sortBy, $sortOrder): array
    {
        // Consulta principal con GROUP BY correcto
        $query = Product::select([
            'products.SkuId',
            'products.Id',
            \DB::raw('MIN(products.idproduct) as idproduct'),
            \DB::raw('MIN(products.SkuTitle) as SkuTitle'),
            \DB::raw('MIN(products.SkuDescription) as SkuDescription'),
            \DB::raw('MIN(products.ProductId) as ProductId'),
            \DB::raw('MIN(products.Publisher) as Publisher'),
            \DB::raw('MIN(products.prod_icon) as prod_icon'),
            \DB::raw('MIN(products.Market) as Market'),
            \DB::raw('MIN(products.Segment) as Segment'),
            \DB::raw('MIN(products.category_id) as category_id'),
            \DB::raw('MIN(categories.name) as category_name'),
            \DB::raw('MIN(products.UnitPrice) as UnitPrice')
        ])
        ->leftJoin('categories', 'products.category_id', '=', 'categories.id');

        // Filtros básicos
        $query->where('products.is_active', true)
              ->whereNotNull('products.Id')
              ->where('products.UnitPrice', '>', 0)
              ->whereNull('products.deleted_at');

        // Filter by category
        if ($request->filled('category_id')) {
            $query->where('products.category_id', $request->category_id);
        }

        // Filter by store
        if ($request->filled('store_id')) {
            $query->where('products.store_id', $request->store_id);
        }

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('products.SkuTitle', 'like', "%{$search}%")
                  ->orWhere('products.Publisher', 'like', "%{$search}%");
            });
        }

        // GROUP BY SkuId + Id (como el sistema viejo)
        $query->groupBy('products.SkuId', 'products.Id');

        // Ordenar según parámetros
        switch ($sortBy) {
            case 'unit_price':
                $query->orderBy('UnitPrice', $sortOrder);
                break;
            case 'publisher':
                $query->orderBy('Publisher', $sortOrder);
                break;
            case 'title':
            default:
                $query->orderBy('SkuTitle', $sortOrder);
                break;
        }

        // Ordenamiento secundario para consistencia
        $query->orderBy('products.SkuId', 'asc')
              ->orderBy('products.Id', 'asc');

        // Paginar los productos agrupados
        $paginatedProducts = $query->paginate($perPage, ['*'], 'page', $page);

        // Ahora obtener las variantes para cada producto agrupado
        $productsWithVariants = [];

        foreach ($paginatedProducts->items() as $product) {
            // Obtener todas las variantes para esta combinación SkuId + Id
            $variants = Product::select([
                'idproduct',
                'BillingPlan',
                'UnitPrice',
                'TermDuration',
                'SkuId',
                'ERPPrice'
            ])
            ->where('SkuId', $product->SkuId)
            ->where('Id', $product->Id)
            ->where('is_active', true)
            ->whereNotNull('Id')
            ->where('UnitPrice', '>', 0)
            ->whereNull('deleted_at')
            ->orderBy('BillingPlan')
            ->get();

            $productsWithVariants[] = [
                'id' => $product->idproduct,
                'title' => $product->SkuTitle,
                'description' => $product->SkuDescription,
                'product_id' => $product->ProductId,
                'sku_id' => $product->SkuId,
                'id_field' => $product->Id,
                'publisher' => $product->Publisher,
                'logo' => $product->prod_icon,
                'details' => [
                    'market' => $product->Market,
                    'segment' => $product->Segment,
                ],
                'category' => [
                    'id' => $product->category_id,
                    'name' => $product->category_name,
                ],
                'variants' => $variants->map(function($variant) {
                    return [
                        'id' => $variant->idproduct,
                        'billing_plan' => $variant->BillingPlan,
                        'unit_price' => $variant->UnitPrice,
                        'erp_price' => $variant->ERPPrice,
                        'term_duration' => $variant->TermDuration,
                        'sku_id' => $variant->SkuId,
                    ];
                })->toArray()
            ];
        }

        return [
            'success' => true,
            'data' => $productsWithVariants,
            'pagination' => [
                'current_page' => $paginatedProducts->currentPage(),
                'per_page' => $paginatedProducts->perPage(),
                'total' => $paginatedProducts->total(),
                'total_pages' => $paginatedProducts->lastPage(),
                'from' => $paginatedProducts->firstItem(),
                'to' => $paginatedProducts->lastItem(),
            ],
            'message' => 'Products retrieved successfully'
        ];
    }

    /**
     * @OA\Get(
     *     path="/api/v1/products/{id}",
     *     tags={"Products"},
     *     summary="Get product by ID",
     *     description="Returns a single product by its internal ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Product internal ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=883),
     *                 @OA\Property(property="title", type="string", example="10-year audit log retention"),
     *                 @OA\Property(property="description", type="string", example="Para ayudar a cumplir..."),
     *                 @OA\Property(property="product_id", type="string", example="CFQ7TTC0HL8Z"),
     *                 @OA\Property(property="sku_id", type="string", example="0001"),
     *                 @OA\Property(property="publisher", type="string", example="Microsoft Corporation"),
     *                 @OA\Property(property="logo", type="string", example="1/products/CFQ7TTC0HL8Z/icon.png"),
     *                 @OA\Property(property="pricing", type="object",
     *                     @OA\Property(property="unit_price", type="string", example="1.92"),
     *                     @OA\Property(property="currency", type="string", example="USD")
     *                 ),
     *                 @OA\Property(property="details", type="object",
     *                     @OA\Property(property="term_duration", type="string", example="P1M"),
     *                     @OA\Property(property="billing_plan", type="string", example="Monthly"),
     *                     @OA\Property(property="market", type="string", example="MX"),
     *                     @OA\Property(property="segment", type="string", example="Commercial")
     *                 ),
     *                 @OA\Property(property="category", type="object",
     *                     @OA\Property(property="id", type="integer", example=1)
     *                 )
     *             ),
     *             @OA\Property(property="message", type="string", example="Product retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product not found"
     *     )
     * )
     */
    public function show(string $id): JsonResponse
    {
        try {
            $product = Product::where('idproduct', $id)->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $product->idproduct,
                    'title' => $product->SkuTitle,
                    'description' => $product->SkuDescription,
                    'product_id' => $product->ProductId,
                    'sku_id' => $product->SkuId,
                    'publisher' => $product->Publisher,
                    'logo' => $product->prod_icon,
                    'pricing' => [
                        'unit_price' => $product->UnitPrice,
                        'currency' => $product->Currency,
                    ],
                    'details' => [
                        'term_duration' => $product->TermDuration,
                        'billing_plan' => $product->BillingPlan,
                        'market' => $product->Market,
                        'segment' => $product->Segment,
                    ],
                    'category' => [
                        'id' => $product->category_id,
                    ],
                ],
                'message' => 'Product retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/products/by-product-id/{productId}",
     *     tags={"Products"},
     *     summary="Get product with all variants by ProductId",
     *     description="Returns a product with all its variants grouped by Microsoft ProductId",
     *     @OA\Parameter(
     *         name="productId",
     *         in="path",
     *         description="Microsoft Product ID (e.g., CFQ7TTC0HL8Z)",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product with variants retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=883),
     *                 @OA\Property(property="title", type="string", example="10-year audit log retention"),
     *                 @OA\Property(property="description", type="string", example="Para ayudar a cumplir..."),
     *                 @OA\Property(property="product_id", type="string", example="CFQ7TTC0HL8Z"),
     *                 @OA\Property(property="sku_id", type="string", example="0001"),
     *                 @OA\Property(property="publisher", type="string", example="Microsoft Corporation"),
     *                 @OA\Property(property="logo", type="string", example="1/products/CFQ7TTC0HL8Z/icon.png"),
     *                 @OA\Property(property="details", type="object",
     *                     @OA\Property(property="term_duration", type="string", example="P1M"),
     *                     @OA\Property(property="billing_plan", type="string", example="Monthly"),
     *                     @OA\Property(property="market", type="string", example="MX"),
     *                     @OA\Property(property="segment", type="string", example="Commercial")
     *                 ),
     *                 @OA\Property(property="category", type="object",
     *                     @OA\Property(property="id", type="integer", example=1)
     *                 ),
     *                 @OA\Property(property="variants", type="array", @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=883),
     *                     @OA\Property(property="billing_plan", type="string", example="Monthly"),
     *                     @OA\Property(property="unit_price", type="string", example="1.92"),
     *                     @OA\Property(property="term_duration", type="string", example="P1M"),
     *                     @OA\Property(property="sku_id", type="string", example="0001")
     *                 ))
     *             ),
     *             @OA\Property(property="message", type="string", example="Product with variants retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product not found"
     *     )
     * )
     */
    public function showByProductId(string $productId): JsonResponse
    {
        try {
            // Log para debug
            \Log::info('ProductController::showByProductId called with: ' . $productId);

            // Buscar el producto principal SOLO por el campo Id (datos precisos)
            $mainProduct = Product::where('Id', $productId)
                                ->where('is_active', true)
                                ->whereNotNull('Id')
                                ->where('UnitPrice', '>', 0)
                                ->whereNull('deleted_at')
                                ->first();

            \Log::info('Found product: ' . ($mainProduct ? 'YES' : 'NO'));

            if (!$mainProduct) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found'
                ], 404);
            }

            // Buscar todas las variantes con el mismo SkuId + Id (exactamente igual que en index)
            $products = Product::where('SkuId', $mainProduct->SkuId)
                             ->where('Id', $mainProduct->Id)
                             ->where('is_active', true)
                             ->whereNotNull('Id')
                             ->where('UnitPrice', '>', 0)
                             ->whereNull('deleted_at')
                             ->orderBy('UnitPrice', 'asc')
                             ->get();

            // Crear array de variantes únicas por BillingPlan
            $variants = [];
            $seenBillingPlans = [];

            foreach ($products as $product) {
                $billingPlan = $product->BillingPlan;
                if (!in_array($billingPlan, $seenBillingPlans)) {
                    $variants[] = [
                        'id' => $product->idproduct,
                        'billing_plan' => $product->BillingPlan,
                        'unit_price' => $product->UnitPrice,
                        'term_duration' => $product->TermDuration,
                        'sku_id' => $product->SkuId,
                    ];
                    $seenBillingPlans[] = $billingPlan;
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $mainProduct->idproduct,
                    'title' => $mainProduct->SkuTitle,
                    'description' => $mainProduct->SkuDescription,
                    'product_id' => $mainProduct->ProductId,
                    'sku_id' => $mainProduct->SkuId,
                    'publisher' => $mainProduct->Publisher,
                    'logo' => $mainProduct->prod_icon,
                    'details' => [
                        'term_duration' => $mainProduct->TermDuration,
                        'billing_plan' => $mainProduct->BillingPlan,
                        'market' => $mainProduct->Market,
                        'segment' => $mainProduct->Segment,
                    ],
                    'category' => [
                        'id' => $mainProduct->category_id,
                    ],
                    'variants' => $variants,
                ],
                'message' => 'Product with variants retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving product variants',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/products/by-sku-id/{skuId}",
     *     tags={"Products"},
     *     summary="Get product by SKU ID",
     *     description="Returns a specific product with its billing variants by SKU ID",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="skuId",
     *         in="path",
     *         description="Product SKU ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=883),
     *                 @OA\Property(property="title", type="string", example="AI Builder Capacity Add-on T1"),
     *                 @OA\Property(property="description", type="string", example="Construya el tren y..."),
     *                 @OA\Property(property="product_id", type="string", example="CFQ7TTC0LH0Z"),
     *                 @OA\Property(property="sku_id", type="string", example="0001"),
     *                 @OA\Property(property="publisher", type="string", example="Microsoft Corporation"),
     *                 @OA\Property(property="logo", type="string", example="1/products/CFQ7TTC0LH0Z/icon.png"),
     *                 @OA\Property(property="details", type="object",
     *                     @OA\Property(property="market", type="string", example="MX"),
     *                     @OA\Property(property="segment", type="string", example="Commercial")
     *                 ),
     *                 @OA\Property(property="category", type="object",
     *                     @OA\Property(property="id", type="integer", example=1)
     *                 ),
     *                 @OA\Property(property="variants", type="array", @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=883),
     *                     @OA\Property(property="billing_plan", type="string", example="Monthly"),
     *                     @OA\Property(property="unit_price", type="string", example="1.92"),
     *                     @OA\Property(property="term_duration", type="string", example="P1M"),
     *                     @OA\Property(property="sku_id", type="string", example="0001")
     *                 ))
     *             ),
     *             @OA\Property(property="message", type="string", example="Product retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product not found"
     *     )
     * )
     */
    public function showBySkuId(string $skuId): JsonResponse
    {
        try {
            $products = Product::where('SkuId', $skuId)
                             ->where('is_active', true)
                             ->orderBy('UnitPrice', 'asc')
                             ->get();

            if ($products->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found'
                ], 404);
            }

            // Agrupar por ProductId + SkuId (para este SkuId específico)
            $groupedByProductId = [];

            foreach ($products as $product) {
                $productId = $product->ProductId;

                if (!isset($groupedByProductId[$productId])) {
                    $groupedByProductId[$productId] = [
                        'base_product' => $product,
                        'variants' => [],
                        'seen_billing_plans' => []
                    ];
                }

                // Solo agregar si no hemos visto este BillingPlan para este ProductId+SkuId
                $billingPlan = $product->BillingPlan;
                if (!in_array($billingPlan, $groupedByProductId[$productId]['seen_billing_plans'])) {
                    $groupedByProductId[$productId]['variants'][] = [
                        'id' => $product->idproduct,
                        'billing_plan' => $product->BillingPlan,
                        'unit_price' => $product->UnitPrice,
                        'term_duration' => $product->TermDuration,
                        'sku_id' => $product->SkuId,
                    ];
                    $groupedByProductId[$productId]['seen_billing_plans'][] = $billingPlan;
                }
            }

            // Tomar el primer grupo (debería ser solo uno para un SkuId específico)
            $firstGroup = reset($groupedByProductId);
            $baseProduct = $firstGroup['base_product'];
            $uniqueVariants = $firstGroup['variants'];

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $baseProduct->idproduct,
                    'title' => $baseProduct->SkuTitle,
                    'description' => $baseProduct->SkuDescription,
                    'product_id' => $baseProduct->ProductId,
                    'sku_id' => $baseProduct->SkuId,
                    'publisher' => $baseProduct->Publisher,
                    'logo' => $baseProduct->prod_icon,
                    'details' => [
                        'market' => $baseProduct->Market,
                        'segment' => $baseProduct->Segment,
                    ],
                    'category' => [
                        'id' => $baseProduct->category_id,
                    ],
                    'variants' => $uniqueVariants,
                ],
                'message' => 'Product retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving product',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/products",
     *     tags={"Products"},
     *     summary="Create a new product",
     *     description="Creates a new product with the provided data",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"SkuTitle", "ProductId", "SkuId", "Publisher", "UnitPrice", "BillingPlan", "category_id"},
     *             @OA\Property(property="SkuTitle", type="string", example="Microsoft 365 Business Premium"),
     *             @OA\Property(property="ProductId", type="string", example="CFQ7TTC0LDL0"),
     *             @OA\Property(property="SkuId", type="string", example="0001"),
     *             @OA\Property(property="SkuDescription", type="string", example="Descripción del producto"),
     *             @OA\Property(property="Publisher", type="string", example="Microsoft Corporation"),
     *             @OA\Property(property="UnitPrice", type="string", example="25.50"),
     *             @OA\Property(property="BillingPlan", type="string", example="Monthly"),
     *             @OA\Property(property="TermDuration", type="string", example="P1M"),
     *             @OA\Property(property="Market", type="string", example="MX"),
     *             @OA\Property(property="Segment", type="string", example="Commercial"),
     *             @OA\Property(property="category_id", type="integer", example=1),
     *             @OA\Property(property="prod_icon", type="string", example="path/to/icon.png")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Product created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1001),
     *                 @OA\Property(property="title", type="string", example="Microsoft 365 Business Premium"),
     *                 @OA\Property(property="product_id", type="string", example="CFQ7TTC0LDL0"),
     *                 @OA\Property(property="publisher", type="string", example="Microsoft Corporation")
     *             ),
     *             @OA\Property(property="message", type="string", example="Product created successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error"
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Validación de datos de entrada
            $validatedData = $request->validate([
                'SkuTitle' => 'required|string|max:255',
                'ProductId' => 'required|string|max:50',
                'SkuId' => 'required|string|max:50',
                'SkuDescription' => 'nullable|string',
                'Publisher' => 'required|string|max:255',
                'UnitPrice' => 'required|numeric|min:0',
                'BillingPlan' => 'required|string|max:50',
                'TermDuration' => 'nullable|string|max:20',
                'Market' => 'nullable|string|max:10',
                'Segment' => 'nullable|string|max:50',
                'category_id' => 'required|integer|exists:prod_categories,idcategory',
                'prod_icon' => 'nullable|string|max:500',
            ]);

            // Crear el producto
            $product = Product::create($validatedData);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $product->idproduct,
                    'title' => $product->SkuTitle,
                    'product_id' => $product->ProductId,
                    'sku_id' => $product->SkuId,
                    'publisher' => $product->Publisher,
                    'unit_price' => $product->UnitPrice,
                    'billing_plan' => $product->BillingPlan,
                ],
                'message' => 'Product created successfully'
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
                'message' => 'Error creating product',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/v1/products/{id}",
     *     tags={"Products"},
     *     summary="Update a product",
     *     description="Updates an existing product with the provided data",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Product ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="SkuTitle", type="string", example="Microsoft 365 Business Premium Updated"),
     *             @OA\Property(property="SkuDescription", type="string", example="Descripción actualizada"),
     *             @OA\Property(property="Publisher", type="string", example="Microsoft Corporation"),
     *             @OA\Property(property="UnitPrice", type="string", example="30.00"),
     *             @OA\Property(property="BillingPlan", type="string", example="Annual"),
     *             @OA\Property(property="TermDuration", type="string", example="P1Y"),
     *             @OA\Property(property="Market", type="string", example="MX"),
     *             @OA\Property(property="Segment", type="string", example="Commercial"),
     *             @OA\Property(property="category_id", type="integer", example=1),
     *             @OA\Property(property="prod_icon", type="string", example="path/to/new-icon.png")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=883),
     *                 @OA\Property(property="title", type="string", example="Microsoft 365 Business Premium Updated"),
     *                 @OA\Property(property="product_id", type="string", example="CFQ7TTC0LDL0"),
     *                 @OA\Property(property="publisher", type="string", example="Microsoft Corporation")
     *             ),
     *             @OA\Property(property="message", type="string", example="Product updated successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error"
     *     )
     * )
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            // Buscar el producto
            $product = Product::find($id);

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found'
                ], 404);
            }

            // Validación de datos de entrada
            $validatedData = $request->validate([
                'SkuTitle' => 'sometimes|required|string|max:255',
                'SkuDescription' => 'nullable|string',
                'Publisher' => 'sometimes|required|string|max:255',
                'UnitPrice' => 'sometimes|required|numeric|min:0',
                'BillingPlan' => 'sometimes|required|string|max:50',
                'TermDuration' => 'nullable|string|max:20',
                'Market' => 'nullable|string|max:10',
                'Segment' => 'nullable|string|max:50',
                'category_id' => 'sometimes|required|integer|exists:prod_categories,idcategory',
                'prod_icon' => 'nullable|string|max:500',
            ]);

            // Actualizar el producto
            $product->update($validatedData);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $product->idproduct,
                    'title' => $product->SkuTitle,
                    'product_id' => $product->ProductId,
                    'sku_id' => $product->SkuId,
                    'publisher' => $product->Publisher,
                    'unit_price' => $product->UnitPrice,
                    'billing_plan' => $product->BillingPlan,
                ],
                'message' => 'Product updated successfully'
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating product',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/products/{id}",
     *     tags={"Products"},
     *     summary="Delete a product",
     *     description="Deletes an existing product",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Product ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Product deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product not found"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function destroy($id): JsonResponse
    {
        try {
            // Buscar el producto
            $product = Product::find($id);

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found'
                ], 404);
            }

            // Eliminar el producto
            $product->delete();

            return response()->json([
                'success' => true,
                'message' => 'Product deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting product',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/products/search",
     *     tags={"Products"},
     *     summary="Advanced product search",
     *     description="Advanced search with multiple filters",
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         description="Search query",
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
     *         name="category_id",
     *         in="query",
     *         description="Filter by category ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="min_price",
     *         in="query",
     *         description="Minimum price filter",
     *         required=false,
     *         @OA\Schema(type="number")
     *     ),
     *     @OA\Parameter(
     *         name="max_price",
     *         in="query",
     *         description="Maximum price filter",
     *         required=false,
     *         @OA\Schema(type="number")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Search results retrieved successfully"
     *     )
     * )
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $query = Product::query();

        // Search in SkuTitle and Publisher
        if ($request->filled('q') && strlen($request->q) >= 1) {
            $searchTerm = $request->q;
            $query->where(function($q) use ($searchTerm) {
                $q->where('SkuTitle', 'like', '%' . $searchTerm . '%')
                  ->orWhere('Publisher', 'like', '%' . $searchTerm . '%')
                  ->orWhere('SkuDescription', 'like', '%' . $searchTerm . '%');
            });
        }

        // Filter by store
        if ($request->has('store_id')) {
            $query->where('store_id', $request->store_id);
        }

        // Filter by category
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Price range filters
        if ($request->filled('min_price') && is_numeric($request->min_price)) {
            $query->where('UnitPrice', '>=', $request->min_price);
        }

        if ($request->filled('max_price') && is_numeric($request->max_price)) {
            $query->where('UnitPrice', '<=', $request->max_price);
        }

        // Only active products
        $query->where('is_active', true);

        // Pagination
        $perPage = min($request->get('per_page', 16), 100);
        $products = $query->orderBy('SkuTitle')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $products->items(),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'from' => $products->firstItem(),
                'to' => $products->lastItem()
            ]
        ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Search failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/products/by-store/{storeId}",
     *     tags={"Products"},
     *     summary="Get products by store",
     *     description="Returns all products for a specific store",
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
     *         description="Store products retrieved successfully"
     *     )
     * )
     */
    public function getByStore(int $storeId): JsonResponse
    {
        try {
            $products = Product::where('store_id', $storeId)
                              ->where('is_active', true)
                              ->whereNotNull('SkuTitle')
                              ->orderBy('SkuTitle')
                              ->paginate(16);

            return response()->json([
                'success' => true,
                'data' => $products->items(),
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading products by store: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/products/stats",
     *     tags={"Products"},
     *     summary="Get product statistics",
     *     description="Returns product statistics by store and category",
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
        $stats = [
            'total_products' => Product::count(),
            'by_store' => Product::select('store_id', DB::raw('count(*) as count'))
                                ->groupBy('store_id')
                                ->get(),
            'by_category' => Product::select('category_id', DB::raw('count(*) as count'))
                                   ->groupBy('category_id')
                                   ->get(),
            'price_ranges' => [
                'under_100' => Product::where('UnitPrice', '<', 100)->count(),
                '100_to_500' => Product::whereBetween('UnitPrice', [100, 500])->count(),
                '500_to_1000' => Product::whereBetween('UnitPrice', [500, 1000])->count(),
                'over_1000' => Product::where('UnitPrice', '>', 1000)->count()
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/products/popular",
     *     tags={"Products"},
     *     summary="Get popular products",
     *     description="Returns cached list of popular/top products",
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation"
     *     )
     * )
     */
    public function popular(): JsonResponse
    {
        try {
            // Cache por 30 minutos para productos populares
            $popularProducts = Cache::remember('products_popular', 1800, function () {
                return Product::where('top', 1)
                    ->where('is_active', true)
                    ->whereNotNull('Id')
                    ->where('UnitPrice', '>', 0)
                    ->whereNull('deleted_at')
                    ->orderBy('UnitPrice', 'asc')
                    ->limit(12)
                    ->get();
            });

            return response()->json([
                'success' => true,
                'data' => ProductResource::collection($popularProducts),
                'message' => 'Popular products retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving popular products',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/products/clear-cache",
     *     tags={"Products"},
     *     summary="Clear product caches",
     *     description="Clear all product related caches (admin operation)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Cache cleared successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="All product caches cleared successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error clearing cache"
     *     )
     * )
     *
     * Clear product related caches (useful for admin operations)
     */
    public function clearCache(): JsonResponse
    {
        try {
            // Limpiar cache específico de productos
            Cache::forget('products_popular');

            // Limpiar cache de productos con filtros usando patrón
            $this->clearProductsListCache();

            // Limpiar cache de categorías también - obtener store IDs dinámicamente
            $storeIds = \App\Models\Store::pluck('id')->toArray();
            $storeIds[] = 'all'; // Agregar 'all' para el cache general

            foreach ($storeIds as $storeId) {
                Cache::forget("categories_store_{$storeId}");
            }

            // Limpiar cache de productos destacados
            $this->clearFeaturedProductsCache();

            // Limpiar cache de otros módulos relacionados
            Cache::forget('currencies_active');
            Cache::forget('tax_regimes');
            Cache::forget('roles_with_permissions');
            Cache::forget('permissions_grouped');
            Cache::forget('postal_codes_autocomplete');

            // Limpiar cache de exchange rates (usar flush para patrones complejos)
            Cache::flush();

            return response()->json([
                'success' => true,
                'message' => 'All caches cleared successfully (products, categories, currencies, tax regimes, roles, permissions, postal codes, exchange rates)'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error clearing cache',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Limpiar cache de listas de productos (con filtros)
     */
    private function clearProductsListCache(): void
    {
        // Limpiar cache de productos principales con diferentes combinaciones comunes
        $commonFilters = [
            ['category_id' => 'all', 'store_id' => 'all', 'per_page' => 16, 'page' => 1, 'sort_by' => 'title', 'sort_order' => 'asc'],
            ['category_id' => 'all', 'store_id' => 'all', 'per_page' => 20, 'page' => 1, 'sort_by' => 'title', 'sort_order' => 'asc'],
            ['category_id' => 'all', 'store_id' => 'all', 'per_page' => 16, 'page' => 1, 'sort_by' => 'unit_price', 'sort_order' => 'asc'],
        ];

        foreach ($commonFilters as $filters) {
            $filterString = http_build_query($filters);
            $cacheKey = 'products_list_' . md5($filterString);
            Cache::forget($cacheKey);
        }
    }

    /**
     * Limpiar cache de productos destacados
     */
    private function clearFeaturedProductsCache(): void
    {
        // Limpiar cache de productos destacados con diferentes configuraciones
        $commonConfigs = [
            ['cat' => 5, 'prod' => 6],
            ['cat' => 3, 'prod' => 4],
            ['cat' => 10, 'prod' => 8],
        ];

        foreach ($commonConfigs as $config) {
            $cacheKey = "featured_products_cat{$config['cat']}_prod{$config['prod']}";
            Cache::forget($cacheKey);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/products/featured",
     *     tags={"Products"},
     *     summary="Get featured products by category",
     *     description="Returns featured products grouped by category with cache",
     *     @OA\Parameter(
     *         name="categories_limit",
     *         in="query",
     *         description="Number of categories to return (default: 5)",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=10)
     *     ),
     *     @OA\Parameter(
     *         name="products_per_category",
     *         in="query",
     *         description="Number of products per category (default: 6)",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=20)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Featured products by category retrieved successfully"
     *     )
     * )
     */
    public function featured(Request $request): JsonResponse
    {
        try {
            $categoriesLimit = min((int) $request->get('categories_limit', 5), 10);
            $productsPerCategory = min((int) $request->get('products_per_category', 6), 20);

            $cacheKey = "featured_products_cat{$categoriesLimit}_prod{$productsPerCategory}";
            $cacheTime = 3600; // 1 hora

            $featuredData = Cache::remember($cacheKey, $cacheTime, function () use ($categoriesLimit, $productsPerCategory) {
                $categories = Category::where('is_active', 1)
                    ->where('is_deleted', 0)
                    ->orderBy('sort_order')
                    ->limit($categoriesLimit)
                    ->get();

                $result = [];
                foreach ($categories as $category) {
                    $products = Product::where('is_active', 1)
                        ->where('category_id', $category->id)
                        ->where('top', 1)
                        ->orderBy('visits', 'desc')
                        ->limit($productsPerCategory)
                        ->get();

                    if ($products->isNotEmpty()) {
                        $result[] = [
                            'category' => [
                                'id' => $category->id,
                                'name' => $category->name,
                                'identifier' => $category->identifier
                            ],
                            'products' => ProductResource::collection($products)
                        ];
                    }
                }

                return $result;
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'featured_categories' => $featuredData,
                    'cache_info' => [
                        'cached' => Cache::has($cacheKey),
                        'expires_in' => $cacheTime
                    ]
                ],
                'message' => 'Featured products by category retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving featured products',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
