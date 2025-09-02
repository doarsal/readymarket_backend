<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StoreConfiguration;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="Store Configurations",
 *     description="API Endpoints for Store Configurations Management"
 * )
 */
class StoreConfigurationController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/store-configurations",
     *     tags={"Store Configurations"},
     *     summary="Get all store configurations",
     *     description="Returns list of store configurations with filters",
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         description="Filter by store ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="key",
     *         in="query",
     *         description="Filter by configuration key",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Store configurations retrieved successfully"
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = StoreConfiguration::query();

            if ($request->has('store_id')) {
                $query->where('store_id', $request->store_id);
            }

            if ($request->has('key')) {
                $query->where('key_name', 'like', '%' . $request->key . '%');
            }

            $configurations = $query->paginate(50);

            return response()->json([
                'success' => true,
                'data' => $configurations->items(),
                'pagination' => [
                    'current_page' => $configurations->currentPage(),
                    'last_page' => $configurations->lastPage(),
                    'per_page' => $configurations->perPage(),
                    'total' => $configurations->total()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading store configurations: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/store-configurations",
     *     tags={"Store Configurations"},
     *     summary="Create new store configuration",
     *     description="Creates a new store configuration",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"store_id", "key", "value"},
     *             @OA\Property(property="store_id", type="integer", example=1),
     *             @OA\Property(property="key", type="string", example="tax_rate"),
     *             @OA\Property(property="value", type="string", example="16.0"),
     *             @OA\Property(property="type", type="string", example="decimal")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Store configuration created successfully"
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store_id' => 'required|exists:stores,id',
            'key' => 'required|string|max:255',
            'value' => 'required|string',
            'type' => 'string|in:string,integer,decimal,boolean,json',
            'description' => 'string'
        ]);

        // Check if configuration already exists
        $existing = StoreConfiguration::where('store_id', $validated['store_id'])
                                     ->where('key', $validated['key'])
                                     ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Configuration already exists for this store and key'
            ], 409);
        }

        $configuration = StoreConfiguration::create($validated);
        $configuration->load('store');

        return response()->json([
            'success' => true,
            'message' => 'Store configuration created successfully',
            'data' => $configuration
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/store-configurations/{id}",
     *     tags={"Store Configurations"},
     *     summary="Get store configuration by ID",
     *     description="Returns a single store configuration",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Store Configuration ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Store configuration retrieved successfully"
     *     )
     * )
     */
    public function show(StoreConfiguration $storeConfiguration): JsonResponse
    {
        $storeConfiguration->load('store');

        return response()->json([
            'success' => true,
            'data' => $storeConfiguration
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/store-configurations/{id}",
     *     tags={"Store Configurations"},
     *     summary="Update store configuration",
     *     description="Updates an existing store configuration",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Store Configuration ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Store configuration updated successfully"
     *     )
     * )
     */
    public function update(Request $request, StoreConfiguration $storeConfiguration): JsonResponse
    {
        $validated = $request->validate([
            'store_id' => 'exists:stores,id',
            'key' => 'string|max:255',
            'value' => 'string',
            'type' => 'string|in:string,integer,decimal,boolean,json',
            'description' => 'string'
        ]);

        // Check for duplicate if store or key changes
        if (isset($validated['store_id']) || isset($validated['key'])) {
            $storeId = $validated['store_id'] ?? $storeConfiguration->store_id;
            $key = $validated['key'] ?? $storeConfiguration->key;

            $existing = StoreConfiguration::where('store_id', $storeId)
                                         ->where('key', $key)
                                         ->where('id', '!=', $storeConfiguration->id)
                                         ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Configuration already exists for this store and key'
                ], 409);
            }
        }

        $storeConfiguration->update($validated);
        $storeConfiguration->load('store');

        return response()->json([
            'success' => true,
            'message' => 'Store configuration updated successfully',
            'data' => $storeConfiguration
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/store-configurations/{id}",
     *     tags={"Store Configurations"},
     *     summary="Delete store configuration",
     *     description="Deletes a store configuration",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Store Configuration ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Store configuration deleted successfully"
     *     )
     * )
     */
    public function destroy(StoreConfiguration $storeConfiguration): JsonResponse
    {
        $storeConfiguration->delete();

        return response()->json([
            'success' => true,
            'message' => 'Store configuration deleted successfully'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/store-configurations/by-store/{storeId}",
     *     tags={"Store Configurations"},
     *     summary="Get configurations by store ID",
     *     description="Returns all configurations for a specific store",
     *     @OA\Parameter(
     *         name="storeId",
     *         in="path",
     *         description="Store ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Store configurations retrieved successfully"
     *     )
     * )
     */
    public function getByStore(int $storeId): JsonResponse
    {
        $store = Store::find($storeId);

        if (!$store) {
            return response()->json([
                'success' => false,
                'message' => 'Store not found'
            ], 404);
        }

        $configurations = StoreConfiguration::where('store_id', $storeId)
                                           ->orderBy('key_name')
                                           ->get()
                                           ->keyBy('key_name');

        return response()->json([
            'success' => true,
            'data' => [
                'store' => $store,
                'configurations' => $configurations
            ]
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/store-configurations/bulk",
     *     tags={"Store Configurations"},
     *     summary="Bulk create or update store configurations",
     *     description="Creates or updates multiple store configurations at once",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"store_id", "configurations"},
     *             @OA\Property(property="store_id", type="integer", example=1),
     *             @OA\Property(property="configurations", type="object", example={"tax_rate": "16.0", "currency_symbol": "$"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Store configurations processed successfully"
     *     )
     * )
     */
    public function bulkStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store_id' => 'required|exists:stores,id',
            'configurations' => 'required|array',
            'configurations.*' => 'required|string'
        ]);

        $created = 0;
        $updated = 0;
        $errors = [];

        foreach ($validated['configurations'] as $key => $value) {
            try {
                $configuration = StoreConfiguration::updateOrCreate(
                    [
                        'store_id' => $validated['store_id'],
                        'key' => $key
                    ],
                    ['value' => $value]
                );

                if ($configuration->wasRecentlyCreated) {
                    $created++;
                } else {
                    $updated++;
                }
            } catch (\Exception $e) {
                $errors[] = "Failed to process key '{$key}': " . $e->getMessage();
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Bulk operation completed',
            'data' => [
                'created' => $created,
                'updated' => $updated,
                'errors' => $errors
            ]
        ]);
    }
}
