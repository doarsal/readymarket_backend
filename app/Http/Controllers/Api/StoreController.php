<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\Language;
use App\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="Stores",
 *     description="API Endpoints for Stores Management"
 * )
 */
class StoreController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/stores",
     *     tags={"Stores"},
     *     summary="Get all stores",
     *     description="Returns paginated list of stores",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Stores retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 15);

        $stores = Store::with(['languages', 'currencies', 'configurations'])
                      ->where('is_active', true)
                      ->orderBy('name')
                      ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $stores
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/stores",
     *     tags={"Stores"},
     *     summary="Create new store",
     *     description="Creates a new store",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "slug"},
     *             @OA\Property(property="name", type="string", example="Tienda México"),
     *             @OA\Property(property="slug", type="string", example="mexico"),
     *             @OA\Property(property="domain", type="string", example="mexico.ejemplo.com"),
     *             @OA\Property(property="default_language", type="string", example="es"),
     *             @OA\Property(property="default_currency", type="string", example="MXN"),
     *             @OA\Property(property="timezone", type="string", example="America/Mexico_City")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Store created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:100|unique:stores,slug',
            'domain' => 'nullable|string|max:255',
            'subdomain' => 'nullable|string|max:100',
            'default_language' => 'nullable|string|max:5',
            'default_currency' => 'nullable|string|max:3',
            'timezone' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'is_maintenance' => 'boolean'
        ]);

        $store = Store::create($validated);

        // Load relationships
        $store->load(['languages', 'currencies', 'configurations']);

        return response()->json([
            'success' => true,
            'message' => 'Store created successfully',
            'data' => $store
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/stores/{id}",
     *     tags={"Stores"},
     *     summary="Get store by ID",
     *     description="Returns a single store",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Store ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Store retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Store not found"
     *     )
     * )
     */
    public function show(Store $store): JsonResponse
    {
        $store->load(['languages', 'currencies', 'configurations']);

        return response()->json([
            'success' => true,
            'data' => $store
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/stores/{id}",
     *     tags={"Stores"},
     *     summary="Update store",
     *     description="Updates an existing store",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Store ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Tienda México"),
     *             @OA\Property(property="slug", type="string", example="mexico"),
     *             @OA\Property(property="domain", type="string", example="mexico.ejemplo.com")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Store updated successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Store not found"
     *     )
     * )
     */
    public function update(Request $request, Store $store): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'string|max:255',
            'slug' => ['string', 'max:100', Rule::unique('stores')->ignore($store->id)],
            'domain' => 'nullable|string|max:255',
            'subdomain' => 'nullable|string|max:100',
            'default_language' => 'nullable|string|max:5',
            'default_currency' => 'nullable|string|max:3',
            'timezone' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'is_maintenance' => 'boolean'
        ]);

        $store->update($validated);
        $store->load(['languages', 'currencies', 'configurations']);

        return response()->json([
            'success' => true,
            'message' => 'Store updated successfully',
            'data' => $store
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/stores/{id}",
     *     tags={"Stores"},
     *     summary="Delete store",
     *     description="Soft deletes a store",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Store ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Store deleted successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Store not found"
     *     )
     * )
     */
    public function destroy(Store $store): JsonResponse
    {
        $store->delete();

        return response()->json([
            'success' => true,
            'message' => 'Store deleted successfully'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/stores/{id}/configurations",
     *     tags={"Stores"},
     *     summary="Set store configuration",
     *     description="Sets configuration values for a store",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Store ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="category", type="string", example="branding"),
     *             @OA\Property(property="key_name", type="string", example="logo_color"),
     *             @OA\Property(property="value", type="string", example="https://example.com/logo.png"),
     *             @OA\Property(property="type", type="string", example="url"),
     *             @OA\Property(property="is_public", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Configuration set successfully"
     *     )
     * )
     */
    public function setConfiguration(Request $request, Store $store): JsonResponse
    {
        $validated = $request->validate([
            'category' => 'required|string|max:50',
            'key_name' => 'required|string|max:100',
            'value' => 'nullable|string',
            'type' => 'in:string,text,json,boolean,integer,file,url',
            'is_public' => 'boolean'
        ]);

        $configuration = $store->setConfiguration(
            $validated['category'],
            $validated['key_name'],
            $validated['value'],
            $validated['type'] ?? 'string',
            $validated['is_public'] ?? false
        );

        return response()->json([
            'success' => true,
            'message' => 'Configuration set successfully',
            'data' => $configuration
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/stores/{id}/configurations",
     *     tags={"Stores"},
     *     summary="Get store configurations",
     *     description="Gets all configurations for a store",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Store ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="category",
     *         in="query",
     *         description="Filter by category",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Configurations retrieved successfully"
     *     )
     * )
     */
    public function getConfigurations(Request $request, Store $store): JsonResponse
    {
        $query = $store->configurations();

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        $configurations = $query->get()->groupBy('category');

        return response()->json([
            'success' => true,
            'data' => $configurations
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/stores/{id}/languages",
     *     tags={"Stores"},
     *     summary="Get store languages",
     *     description="Gets all languages for a store",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Store ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Store languages retrieved successfully"
     *     )
     * )
     */
    public function getLanguages(Store $store): JsonResponse
    {
        $languages = $store->languages()->get();

        return response()->json([
            'success' => true,
            'data' => $languages
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/stores/{id}/languages",
     *     tags={"Stores"},
     *     summary="Add language to store",
     *     description="Adds a language to a store",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Store ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"language_ids"},
     *             @OA\Property(property="language_ids", type="array", @OA\Items(type="integer"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Languages added to store successfully"
     *     )
     * )
     */
    public function addLanguages(Request $request, Store $store): JsonResponse
    {
        $validated = $request->validate([
            'language_ids' => 'required|array',
            'language_ids.*' => 'exists:languages,id'
        ]);

        $store->languages()->syncWithoutDetaching($validated['language_ids']);

        return response()->json([
            'success' => true,
            'message' => 'Languages added to store successfully',
            'data' => $store->languages
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/stores/{id}/languages/{languageId}",
     *     tags={"Stores"},
     *     summary="Remove language from store",
     *     description="Removes a language from a store",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Store ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="languageId",
     *         in="path",
     *         description="Language ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Language removed from store successfully"
     *     )
     * )
     */
    public function removeLanguage(Store $store, int $languageId): JsonResponse
    {
        $store->languages()->detach($languageId);

        return response()->json([
            'success' => true,
            'message' => 'Language removed from store successfully'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/stores/{id}/currencies",
     *     tags={"Stores"},
     *     summary="Get store currencies",
     *     description="Gets all currencies for a store",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Store ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Store currencies retrieved successfully"
     *     )
     * )
     */
    public function getCurrencies(Store $store): JsonResponse
    {
        $currencies = $store->currencies()->get();

        return response()->json([
            'success' => true,
            'data' => $currencies
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/stores/{id}/currencies",
     *     tags={"Stores"},
     *     summary="Add currency to store",
     *     description="Adds a currency to a store",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Store ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"currency_ids"},
     *             @OA\Property(property="currency_ids", type="array", @OA\Items(type="integer"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Currencies added to store successfully"
     *     )
     * )
     */
    public function addCurrencies(Request $request, Store $store): JsonResponse
    {
        $validated = $request->validate([
            'currency_ids' => 'required|array',
            'currency_ids.*' => 'exists:currencies,id'
        ]);

        $store->currencies()->syncWithoutDetaching($validated['currency_ids']);

        return response()->json([
            'success' => true,
            'message' => 'Currencies added to store successfully',
            'data' => $store->currencies
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/stores/{id}/currencies/{currencyId}",
     *     tags={"Stores"},
     *     summary="Remove currency from store",
     *     description="Removes a currency from a store",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Store ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="currencyId",
     *         in="path",
     *         description="Currency ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Currency removed from store successfully"
     *     )
     * )
     */
    public function removeCurrency(Store $store, int $currencyId): JsonResponse
    {
        $store->currencies()->detach($currencyId);

        return response()->json([
            'success' => true,
            'message' => 'Currency removed from store successfully'
        ]);
    }
}
