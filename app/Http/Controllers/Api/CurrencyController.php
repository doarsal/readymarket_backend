<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * @OA\Tag(
 *     name="Currencies",
 *     description="API Endpoints for Currencies Management"
 * )
 */
class CurrencyController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/currencies",
     *     tags={"Currencies"},
     *     summary="Get all currencies",
     *     description="Returns list of all currencies",
     *     @OA\Response(
     *         response=200,
     *         description="Currencies retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Dólar Americano"),
     *                 @OA\Property(property="code", type="string", example="USD"),
     *                 @OA\Property(property="symbol", type="string", example="$")
     *             ))
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        // Cache por 6 horas - las monedas raramente cambian
        $currencies = Cache::remember('currencies_active', 21600, function () {
            return Currency::where('is_active', true)
                          ->orderBy('sort_order')
                          ->orderBy('name')
                          ->get();
        });

        return response()->json([
            'success' => true,
            'data' => $currencies
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/currencies",
     *     tags={"Currencies"},
     *     summary="Create new currency",
     *     description="Creates a new currency",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "code", "symbol"},
     *             @OA\Property(property="name", type="string", example="Peso Mexicano"),
     *             @OA\Property(property="code", type="string", example="MXN"),
     *             @OA\Property(property="symbol", type="string", example="$"),
     *             @OA\Property(property="symbol_position", type="string", example="before"),
     *             @OA\Property(property="decimal_places", type="integer", example=2)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Currency created successfully"
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:3|unique:currencies,code',
            'symbol' => 'required|string|max:10',
            'symbol_position' => 'in:before,after',
            'decimal_places' => 'integer|min:0|max:8',
            'thousands_separator' => 'string|max:1',
            'decimal_separator' => 'string|max:1',
            'is_active' => 'boolean',
            'sort_order' => 'integer'
        ]);

        $currency = Currency::create($validated);

        // Limpiar cache cuando se crea nueva moneda
        Cache::forget('currencies_active');

        return response()->json([
            'success' => true,
            'message' => 'Currency created successfully',
            'data' => $currency
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/currencies/{id}",
     *     tags={"Currencies"},
     *     summary="Get currency by ID",
     *     description="Returns a single currency",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Currency ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Currency retrieved successfully"
     *     )
     * )
     */
    public function show(Currency $currency): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $currency
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/currencies/{id}",
     *     tags={"Currencies"},
     *     summary="Update currency",
     *     description="Updates an existing currency",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Currency ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Currency updated successfully"
     *     )
     * )
     */
    public function update(Request $request, Currency $currency): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'string|max:100',
            'code' => 'string|max:3|unique:currencies,code,' . $currency->id,
            'symbol' => 'string|max:10',
            'symbol_position' => 'in:before,after',
            'decimal_places' => 'integer|min:0|max:8',
            'thousands_separator' => 'string|max:1',
            'decimal_separator' => 'string|max:1',
            'is_active' => 'boolean',
            'sort_order' => 'integer'
        ]);

        $currency->update($validated);

        // Limpiar cache cuando se actualiza moneda
        Cache::forget('currencies_active');

        return response()->json([
            'success' => true,
            'message' => 'Currency updated successfully',
            'data' => $currency
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/currencies/{id}",
     *     tags={"Currencies"},
     *     summary="Delete currency",
     *     description="Deletes a currency",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Currency ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Currency deleted successfully"
     *     )
     * )
     */
    public function destroy(Currency $currency): JsonResponse
    {
        $currency->delete();

        // Limpiar cache cuando se elimina moneda
        Cache::forget('currencies_active');

        return response()->json([
            'success' => true,
            'message' => 'Currency deleted successfully'
        ]);
    }
}
