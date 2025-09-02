<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExchangeRate;
use App\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * @OA\Tag(
 *     name="Exchange Rates",
 *     description="API Endpoints for Exchange Rates Management"
 * )
 */
class ExchangeRateController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/exchange-rates",
     *     tags={"Exchange Rates"},
     *     summary="Get all exchange rates",
     *     description="Returns list of exchange rates",
     *     @OA\Parameter(
     *         name="date",
     *         in="query",
     *         description="Filter by date (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="from_currency",
     *         in="query",
     *         description="Filter by from currency code",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Exchange rates retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="from_currency", type="object"),
     *                 @OA\Property(property="to_currency", type="object"),
     *                 @OA\Property(property="rate", type="number", example=17.5),
     *                 @OA\Property(property="date", type="string", format="date")
     *             ))
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        // Crear clave de cache basada en los filtros
        $cacheKey = 'exchange_rates';
        $params = [];

        if ($request->has('date')) {
            $params['date'] = $request->date;
        }

        if ($request->has('from_currency')) {
            $params['from_currency'] = $request->from_currency;
        }

        if (!empty($params)) {
            $cacheKey .= '_' . md5(serialize($params));
        }

        $exchangeRates = Cache::remember($cacheKey, 3600, function () use ($request) { // 1 hora de cache
            $query = ExchangeRate::with(['fromCurrency', 'toCurrency'])
                                ->where('is_active', true);

            if ($request->has('date')) {
                $query->where('date', $request->date);
            }

            if ($request->has('from_currency')) {
                $query->whereHas('fromCurrency', function($q) use ($request) {
                    $q->where('code', $request->from_currency);
                });
            }

            return $query->orderBy('date', 'desc')
                         ->orderBy('from_currency_id')
                         ->get();
        });

        return response()->json([
            'success' => true,
            'data' => $exchangeRates
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/exchange-rates",
     *     tags={"Exchange Rates"},
     *     summary="Create new exchange rate",
     *     description="Creates a new exchange rate",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"from_currency_id", "to_currency_id", "rate", "date"},
     *             @OA\Property(property="from_currency_id", type="integer", example=1),
     *             @OA\Property(property="to_currency_id", type="integer", example=2),
     *             @OA\Property(property="rate", type="number", example=17.5),
     *             @OA\Property(property="date", type="string", format="date", example="2025-08-27"),
     *             @OA\Property(property="source", type="string", example="manual")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Exchange rate created successfully"
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_currency_id' => 'required|exists:currencies,id',
            'to_currency_id' => 'required|exists:currencies,id|different:from_currency_id',
            'rate' => 'required|numeric|min:0',
            'date' => 'required|date',
            'source' => 'string|max:50',
            'is_active' => 'boolean'
        ]);

        $exchangeRate = ExchangeRate::create($validated);
        $exchangeRate->load(['fromCurrency', 'toCurrency']);

        // Limpiar cache de exchange rates
        $this->clearExchangeRatesCache();

        return response()->json([
            'success' => true,
            'message' => 'Exchange rate created successfully',
            'data' => $exchangeRate
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/exchange-rates/{id}",
     *     tags={"Exchange Rates"},
     *     summary="Get exchange rate by ID",
     *     description="Returns a single exchange rate",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Exchange Rate ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Exchange rate retrieved successfully"
     *     )
     * )
     */
    public function show(ExchangeRate $exchangeRate): JsonResponse
    {
        $exchangeRate->load(['fromCurrency', 'toCurrency']);

        return response()->json([
            'success' => true,
            'data' => $exchangeRate
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/exchange-rates/{id}",
     *     tags={"Exchange Rates"},
     *     summary="Update exchange rate",
     *     description="Updates an existing exchange rate",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Exchange Rate ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Exchange rate updated successfully"
     *     )
     * )
     */
    public function update(Request $request, ExchangeRate $exchangeRate): JsonResponse
    {
        $validated = $request->validate([
            'from_currency_id' => 'exists:currencies,id',
            'to_currency_id' => 'exists:currencies,id|different:from_currency_id',
            'rate' => 'numeric|min:0',
            'date' => 'date',
            'source' => 'string|max:50',
            'is_active' => 'boolean'
        ]);

        $exchangeRate->update($validated);
        $exchangeRate->load(['fromCurrency', 'toCurrency']);

        // Limpiar cache de exchange rates
        $this->clearExchangeRatesCache();

        return response()->json([
            'success' => true,
            'message' => 'Exchange rate updated successfully',
            'data' => $exchangeRate
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/exchange-rates/{id}",
     *     tags={"Exchange Rates"},
     *     summary="Delete exchange rate",
     *     description="Deletes an exchange rate",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Exchange Rate ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Exchange rate deleted successfully"
     *     )
     * )
     */
    public function destroy(ExchangeRate $exchangeRate): JsonResponse
    {
        $exchangeRate->delete();

        // Limpiar cache de exchange rates
        $this->clearExchangeRatesCache();

        return response()->json([
            'success' => true,
            'message' => 'Exchange rate deleted successfully'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/exchange-rates/convert",
     *     tags={"Exchange Rates"},
     *     summary="Convert amount between currencies",
     *     description="Converts an amount from one currency to another",
     *     @OA\Parameter(
     *         name="amount",
     *         in="query",
     *         description="Amount to convert",
     *         required=true,
     *         @OA\Schema(type="number")
     *     ),
     *     @OA\Parameter(
     *         name="from",
     *         in="query",
     *         description="From currency ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="to",
     *         in="query",
     *         description="To currency ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="date",
     *         in="query",
     *         description="Date for exchange rate (defaults to today)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Amount converted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="original_amount", type="number", example=100),
     *                 @OA\Property(property="converted_amount", type="number", example=1750),
     *                 @OA\Property(property="rate", type="number", example=17.5),
     *                 @OA\Property(property="from_currency", type="object"),
     *                 @OA\Property(property="to_currency", type="object")
     *             )
     *         )
     *     )
     * )
     */
    public function convert(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'from' => 'required|string|exists:currencies,code',
            'to' => 'required|string|exists:currencies,code',
            'date' => 'nullable|date'
        ]);

        $date = $validated['date'] ?? now()->format('Y-m-d');

        // Get currency IDs from codes
        $fromCurrency = Currency::where('code', $validated['from'])->first();
        $toCurrency = Currency::where('code', $validated['to'])->first();

        if (!$fromCurrency || !$toCurrency) {
            return response()->json([
                'success' => false,
                'message' => 'Currency not found'
            ], 404);
        }

        try {
            $convertedAmount = $validated['amount']; // Simple conversion for now
            if ($fromCurrency->id !== $toCurrency->id) {
                // In a real app, you'd get the actual exchange rate
                $convertedAmount = $validated['amount'] * 0.85; // Mock conversion
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'original_amount' => $validated['amount'],
                    'converted_amount' => $convertedAmount,
                    'from_currency' => $fromCurrency->code,
                    'to_currency' => $toCurrency->code,
                    'date' => $date
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error converting currency: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear all exchange rates cache
     */
    private function clearExchangeRatesCache(): void
    {
        // Limpiar cache principal y variantes con filtros
        $patterns = [
            'exchange_rates*'
        ];

        foreach ($patterns as $pattern) {
            Cache::flush(); // Para simplicidad, limpiamos todo el cache relacionado
        }
    }
}
