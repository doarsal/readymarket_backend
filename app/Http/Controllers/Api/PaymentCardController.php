<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentCardResource;
use App\Models\PaymentCard;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

/**
 * @OA\Tag(
 *     name="Payment Cards",
 *     description="API Endpoints for Payment Cards Management - Secure card storage and management"
 * )
 */
class PaymentCardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('throttle:payment-cards')->only(['store', 'update', 'destroy']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/payment-cards",
     *     tags={"Payment Cards"},
     *     summary="Get user's payment cards",
     *     description="Returns list of user's payment cards (secured)",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="active_only",
     *         in="query",
     *         description="Show only active cards",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payment cards retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="masked_card_number", type="string", example="**** **** **** 1234"),
     *                 @OA\Property(property="brand", type="string", example="VISA"),
     *                 @OA\Property(property="card_type", type="string", example="credit"),
     *                 @OA\Property(property="is_default", type="boolean", example=true),
     *                 @OA\Property(property="is_expired", type="boolean", example=false)
     *             ))
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $userId = Auth::id();
        $cacheKey = "payment_cards_user_{$userId}";

        if ($request->boolean('active_only')) {
            $cacheKey .= '_active';
        }

        $cards = Cache::remember($cacheKey, 1800, function () use ($request, $userId) { // 30 minutos
            $query = PaymentCard::forUser($userId);

            if ($request->boolean('active_only')) {
                $query->active();
            }

            return $query->orderBy('is_default', 'desc')
                         ->orderBy('created_at', 'desc')
                         ->get();
        });

        return response()->json([
            'success' => true,
            'data' => PaymentCardResource::collection($cards)
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/payment-cards",
     *     tags={"Payment Cards"},
     *     summary="Add new payment card",
     *     description="Creates a new payment card (tokenized and encrypted)",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"card_number", "expiry_month", "expiry_year", "cardholder_name", "mitec_merchant_used"},
     *             @OA\Property(property="card_number", type="string", example="4111111111111111"),
     *             @OA\Property(property="expiry_month", type="integer", example=12),
     *             @OA\Property(property="expiry_year", type="integer", example=2025),
     *             @OA\Property(property="cardholder_name", type="string", example="John Doe"),
     *             @OA\Property(property="mitec_merchant_used", type="string", example="merchant_123"),
     *             @OA\Property(property="mitec_card_id", type="string", example="mitec_card_456"),
     *             @OA\Property(property="is_default", type="boolean", example=false)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Payment card added successfully"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'card_number' => 'required|string|regex:/^[0-9]{13,19}$/',
            'expiry_month' => 'required|integer|between:1,12',
            'expiry_year' => 'required|integer|min:' . date('Y'),
            'cardholder_name' => 'required|string|max:100',
            'mitec_merchant_used' => 'required|string|max:50',
            'mitec_card_id' => 'nullable|string|max:100',
            'is_default' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();
        $userId = Auth::id();
        $clientIp = $request->ip();

        // Validar que la tarjeta no esté expirada
        if ($this->isCardExpired($validated['expiry_month'], $validated['expiry_year'])) {
            return response()->json([
                'success' => false,
                'message' => 'Card is expired'
            ], 422);
        }

        // Generar huella digital de la tarjeta
        $fingerprint = PaymentCard::generateFingerprint(
            $validated['card_number'],
            $validated['expiry_month'],
            $validated['expiry_year']
        );

        // Verificar si la tarjeta ya existe
        $existingCard = PaymentCard::where('user_id', $userId)
                                  ->where('card_fingerprint', $fingerprint)
                                  ->first();

        if ($existingCard) {
            return response()->json([
                'success' => false,
                'message' => 'This card is already registered'
            ], 422);
        }

        // Determinar marca de la tarjeta
        $brand = $this->detectCardBrand($validated['card_number']);

        try {
            $card = PaymentCard::create([
                'user_id' => $userId,
                'card_fingerprint' => $fingerprint,
                'last_four_digits' => substr($validated['card_number'], -4),
                'brand' => $brand,
                'card_type' => 'credit', // Puede determinarse con más lógica
                'expiry_month_encrypted' => $validated['expiry_month'],
                'expiry_year_encrypted' => $validated['expiry_year'],
                'cardholder_name_encrypted' => $validated['cardholder_name'],
                'mitec_card_id' => $validated['mitec_card_id'] ?? null,
                'mitec_merchant_used' => $validated['mitec_merchant_used'],
                'is_default' => $validated['is_default'] ?? false,
                'created_ip' => $clientIp,
            ]);

            // Limpiar cache del usuario
            $this->clearUserCardsCache($userId);

            return response()->json([
                'success' => true,
                'message' => 'Payment card added successfully',
                'data' => new PaymentCardResource($card)
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save payment card'
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/payment-cards/{id}",
     *     tags={"Payment Cards"},
     *     summary="Get payment card by ID",
     *     description="Returns a single payment card (user must own the card)",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Payment card ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payment card retrieved successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Payment card not found"
     *     )
     * )
     */
    public function show(string $id): JsonResponse
    {
        $card = PaymentCard::forUser(Auth::id())->active()->find($id);

        if (!$card) {
            return response()->json([
                'success' => false,
                'message' => 'Payment card not found'
            ], 404);
        }

        // Actualizar último uso
        $card->update([
            'last_used_ip' => request()->ip(),
            'last_used_at' => Carbon::now()
        ]);

        return response()->json([
            'success' => true,
            'data' => new PaymentCardResource($card)
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/payment-cards/{id}",
     *     tags={"Payment Cards"},
     *     summary="Update payment card",
     *     description="Updates payment card settings (limited fields for security)",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Payment card ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="cardholder_name", type="string", example="John Doe"),
     *             @OA\Property(property="is_default", type="boolean", example=true),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payment card updated successfully"
     *     )
     * )
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $card = PaymentCard::forUser(Auth::id())->find($id);

        if (!$card) {
            return response()->json([
                'success' => false,
                'message' => 'Payment card not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'cardholder_name' => 'sometimes|string|max:100',
            'is_default' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();
        $updateData = [];

        // Solo actualizar campos permitidos
        if (isset($validated['cardholder_name'])) {
            $updateData['cardholder_name_encrypted'] = $validated['cardholder_name'];
        }

        if (isset($validated['is_default'])) {
            $updateData['is_default'] = $validated['is_default'];
        }

        if (isset($validated['is_active'])) {
            $updateData['is_active'] = $validated['is_active'];
        }

        $card->update($updateData);

        // Limpiar cache del usuario
        $this->clearUserCardsCache(Auth::id());

        return response()->json([
            'success' => true,
            'message' => 'Payment card updated successfully',
            'data' => new PaymentCardResource($card->fresh())
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/payment-cards/{id}",
     *     tags={"Payment Cards"},
     *     summary="Delete payment card",
     *     description="Soft deletes a payment card (marks as inactive)",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Payment card ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payment card deleted successfully"
     *     )
     * )
     */
    public function destroy(string $id): JsonResponse
    {
        $card = PaymentCard::forUser(Auth::id())->find($id);

        if (!$card) {
            return response()->json([
                'success' => false,
                'message' => 'Payment card not found'
            ], 404);
        }

        // Soft delete - marcar como inactiva en lugar de eliminar
        $card->update(['is_active' => false]);

        // Si era la tarjeta por defecto, asignar otra como default
        if ($card->is_default) {
            $newDefault = PaymentCard::forUser(Auth::id())
                                    ->active()
                                    ->where('id', '!=', $card->id)
                                    ->first();

            if ($newDefault) {
                $newDefault->update(['is_default' => true]);
            }
        }

        // Limpiar cache del usuario
        $this->clearUserCardsCache(Auth::id());

        return response()->json([
            'success' => true,
            'message' => 'Payment card deleted successfully'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/payment-cards/{id}/set-default",
     *     tags={"Payment Cards"},
     *     summary="Set card as default",
     *     description="Sets a payment card as the default for the user",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Payment card ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Default card updated successfully"
     *     )
     * )
     */
    public function setDefault(string $id): JsonResponse
    {
        $card = PaymentCard::forUser(Auth::id())->active()->find($id);

        if (!$card) {
            return response()->json([
                'success' => false,
                'message' => 'Payment card not found'
            ], 404);
        }

        $card->update(['is_default' => true]);

        // Limpiar cache del usuario
        $this->clearUserCardsCache(Auth::id());

        return response()->json([
            'success' => true,
            'message' => 'Default payment card updated successfully',
            'data' => new PaymentCardResource($card->fresh())
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/payment-cards/validate-expiration",
     *     tags={"Payment Cards"},
     *     summary="Validate expiration of all user cards",
     *     description="Returns expiration status of all user's payment cards",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Card expiration validation completed",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="expired_cards", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="expiring_soon", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="valid_cards", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function validateExpiration(): JsonResponse
    {
        $userId = Auth::id();
        $cards = PaymentCard::forUser($userId)->active()->get();

        $expiredCards = [];
        $expiringSoon = []; // Próximas a vencer (3 meses)
        $validCards = [];

        foreach ($cards as $card) {
            $cardResource = new PaymentCardResource($card);
            $cardData = $cardResource->toArray(request());

            if ($card->is_expired) {
                $expiredCards[] = $cardData;
            } elseif ($this->isExpiringSoon($card)) {
                $expiringSoon[] = $cardData;
            } else {
                $validCards[] = $cardData;
            }
        }

        // Si hay tarjetas expiradas, marcarlas como inactivas automáticamente
        if (!empty($expiredCards)) {
            PaymentCard::forUser($userId)
                      ->whereIn('id', collect($expiredCards)->pluck('id'))
                      ->update(['is_active' => false]);

            // Limpiar cache
            $this->clearUserCardsCache($userId);
        }

        return response()->json([
            'success' => true,
            'message' => 'Card expiration validation completed',
            'data' => [
                'expired_cards' => $expiredCards,
                'expiring_soon' => $expiringSoon,
                'valid_cards' => $validCards,
                'summary' => [
                    'total_cards' => count($cards),
                    'expired_count' => count($expiredCards),
                    'expiring_soon_count' => count($expiringSoon),
                    'valid_count' => count($validCards)
                ]
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/payment-cards/{id}/validate-expiration",
     *     tags={"Payment Cards"},
     *     summary="Validate expiration of specific card",
     *     description="Returns detailed expiration status of a specific payment card",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Payment card ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Card expiration status retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="is_expired", type="boolean", example=false),
     *             @OA\Property(property="is_expiring_soon", type="boolean", example=true),
     *             @OA\Property(property="days_until_expiry", type="integer", example=45),
     *             @OA\Property(property="expiry_date", type="string", example="2025-12-31")
     *         )
     *     )
     * )
     */
    public function validateCardExpiration(string $id): JsonResponse
    {
        $card = PaymentCard::forUser(Auth::id())->find($id);

        if (!$card) {
            return response()->json([
                'success' => false,
                'message' => 'Payment card not found'
            ], 404);
        }

        $expiryDate = $this->getCardExpiryDate($card);
        $daysUntilExpiry = $this->getDaysUntilExpiry($card);

        $validationData = [
            'card_id' => $card->id,
            'is_expired' => $card->is_expired,
            'is_expiring_soon' => $this->isExpiringSoon($card),
            'days_until_expiry' => $daysUntilExpiry,
            'expiry_date' => $expiryDate->format('Y-m-d'),
            'expiry_month_year' => $expiryDate->format('m/Y'),
            'validation_timestamp' => now()->toISOString()
        ];

        // Si está expirada, marcar como inactiva automáticamente
        if ($card->is_expired && $card->is_active) {
            $card->update(['is_active' => false]);
            $this->clearUserCardsCache(Auth::id());
            $validationData['auto_deactivated'] = true;
        }

        return response()->json([
            'success' => true,
            'message' => 'Card expiration validation completed',
            'data' => $validationData
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/payment-cards/valid-for-payment",
     *     tags={"Payment Cards"},
     *     summary="Get valid cards for payment",
     *     description="Returns only active and non-expired cards that can be used for payments",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Valid payment cards retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="summary", type="object")
     *         )
     *     )
     * )
     */
    public function getValidForPayment(): JsonResponse
    {
        $userId = Auth::id();
        $cacheKey = "payment_cards_user_{$userId}_valid_only";

        $validCards = Cache::remember($cacheKey, 1800, function () use ($userId) {
            return PaymentCard::forUser($userId)
                             ->active()
                             ->get()
                             ->filter(function ($card) {
                                 return !$card->is_expired;
                             })
                             ->sortBy(function ($card) {
                                 return $card->is_default ? 0 : 1; // Default first
                             })
                             ->values();
        });

        $summary = [
            'total_valid_cards' => $validCards->count(),
            'has_default_card' => $validCards->firstWhere('is_default') !== null,
            'expiring_soon_count' => $validCards->filter(function ($card) {
                return $card->is_expiring_soon;
            })->count()
        ];

        return response()->json([
            'success' => true,
            'message' => 'Valid payment cards retrieved successfully',
            'data' => PaymentCardResource::collection($validCards),
            'summary' => $summary
        ]);
    }

    /**
     * Detectar marca de tarjeta basado en el número
     */
    private function detectCardBrand(string $cardNumber): string
    {
        $cardNumber = preg_replace('/\D/', '', $cardNumber);

        // Patrones de marcas de tarjetas
        $patterns = [
            'VISA' => '/^4[0-9]{12}(?:[0-9]{3})?$/',
            'MASTERCARD' => '/^5[1-5][0-9]{14}$|^2(?:2(?:2[1-9]|[3-9][0-9])|[3-6][0-9][0-9]|7(?:[01][0-9]|20))[0-9]{12}$/',
            'AMEX' => '/^3[47][0-9]{13}$/',
            'DISCOVER' => '/^6(?:011|5[0-9]{2})[0-9]{12}$/',
            'JCB' => '/^(?:2131|1800|35\d{3})\d{11}$/',
            'DINERS' => '/^3(?:0[0-5]|[68][0-9])[0-9]{11}$/',
        ];

        foreach ($patterns as $brand => $pattern) {
            if (preg_match($pattern, $cardNumber)) {
                return $brand;
            }
        }

        return 'UNKNOWN';
    }

    /**
     * Verificar si la tarjeta está expirada
     */
    private function isCardExpired(int $month, int $year): bool
    {
        $currentMonth = (int) date('m');
        $currentYear = (int) date('Y');

        return ($year < $currentYear) || ($year == $currentYear && $month < $currentMonth);
    }

    /**
     * Verificar si la tarjeta está próxima a vencer (3 meses)
     */
    private function isExpiringSoon(PaymentCard $card): bool
    {
        $expiryDate = $this->getCardExpiryDate($card);
        $threeMonthsFromNow = now()->addMonths(3);

        return $expiryDate->lte($threeMonthsFromNow) && !$card->is_expired;
    }

    /**
     * Obtener fecha completa de expiración de la tarjeta
     */
    private function getCardExpiryDate(PaymentCard $card): \Carbon\Carbon
    {
        $month = (int) $card->expiry_month;
        $year = (int) $card->expiry_year;

        // Último día del mes de expiración
        return \Carbon\Carbon::createFromDate($year, $month, 1)->endOfMonth();
    }

    /**
     * Obtener días hasta la expiración
     */
    private function getDaysUntilExpiry(PaymentCard $card): int
    {
        $expiryDate = $this->getCardExpiryDate($card);
        $today = now()->startOfDay();

        if ($card->is_expired) {
            return $today->diffInDays($expiryDate, false); // Número negativo para expiradas
        }

        return $today->diffInDays($expiryDate);
    }

    /**
     * Limpiar cache de tarjetas del usuario
     */
    private function clearUserCardsCache(int $userId): void
    {
        $patterns = [
            "payment_cards_user_{$userId}",
            "payment_cards_user_{$userId}_active",
            "payment_cards_user_{$userId}_valid_only"
        ];

        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }
    }
}
