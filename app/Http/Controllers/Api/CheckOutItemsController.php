<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CheckOutItemResource;
use App\Models\CheckOutItem;
use Config;
use Illuminate\Http\Request;

class CheckOutItemsController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/check-out-items",
     *     tags={"CheckOut Items"},
     *     summary="List active checkout items",
     *     description="Returns the list of active CheckOut Items. Currency values are formatted based on the internal store context. Prices and minimum/maximum amounts can be null or an object containing price information.",
     *     @OA\Response(
     *         response=200,
     *         description="List retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/CheckOutItem")
     *             )
     *         )
     *     )
     * )
     *
     * @OA\Schema(
     *     schema="Money",
     *     type="object",
     *     nullable=true,
     *     description="Monetary representation. May be null.",
     *     @OA\Property(property="amount", type="number", format="float", example=10),
     *     @OA\Property(property="formatted", type="string", example="$10.00 MXN"),
     *     @OA\Property(property="currency_code", type="string", example="MXN"),
     *     @OA\Property(property="currency_symbol", type="string", example="$"),
     *     @OA\Property(property="currency_name", type="string", example="Mexican Peso"),
     *     @OA\Property(property="original_amount", type="number", format="float", example=10),
     *     @OA\Property(property="exchange_rate", type="number", format="float", example=1),
     *     @OA\Property(property="original_price", type="number", format="float", example=10),
     *     @OA\Property(property="original_currency", type="string", example="USD")
     * )
     *
     * @OA\Schema(
     *     schema="CheckOutItem",
     *     type="object",
     *     required={"id","item","is_active"},
     *     description="Checkout item resource.",
     *     @OA\Property(property="id", type="integer", example=1),
     *     @OA\Property(property="item", type="string", example="Test Item"),
     *     @OA\Property(property="description", type="string", nullable=true, example="Description"),
     *     @OA\Property(property="default", type="boolean", nullable=false, example=true),
     *     @OA\Property(
     *         property="price",
     *         description="Price in the store currency. May be null or an object with details.",
     *         oneOf={
     *             @OA\Schema(ref="#/components/schemas/Money"),
     *             @OA\Schema(type="null")
     *         }
     *     ),
     *     @OA\Property(
     *         property="min_cart_amount",
     *         description="Minimum cart amount required to apply. May be null or a Money object.",
     *         oneOf={
     *             @OA\Schema(ref="#/components/schemas/Money"),
     *             @OA\Schema(type="null")
     *         }
     *     ),
     *     @OA\Property(
     *         property="max_cart_amount",
     *         description="Maximum cart amount for applicability. May be null or a Money object.",
     *         oneOf={
     *             @OA\Schema(ref="#/components/schemas/Money"),
     *             @OA\Schema(type="null")
     *         }
     *     ),
     *     @OA\Property(property="percentage_of_amount", type="integer", nullable=true, example=15, description="Percentage to apply over the amount, if applicable."),
     *     @OA\Property(property="help_cta", type="string", nullable=true, example="Learn more"),
     *     @OA\Property(property="help_text", type="string", nullable=true, example="This charge applies when..."),
     *     @OA\Property(property="is_active", type="boolean", example=true)
     * )
     */
    public function index(Request $request)
    {
        $checkOutItems = CheckOutItem::active()->with('currency')->get();
        $storeId       = $request->attributes->get('store_id') ?? $request->get('store_id') ?? Config::get('app.store_id');
        $items         = $checkOutItems->map(fn($item) => new CheckOutItemResource($item, $storeId));

        return response()->json([
            'success' => true,
            'data'    => $items,
        ]);
    }
}
