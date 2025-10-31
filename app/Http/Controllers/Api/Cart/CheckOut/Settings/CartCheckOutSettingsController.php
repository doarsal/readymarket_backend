<?php

namespace App\Http\Controllers\Api\Cart\CheckOut\Settings;

use App;
use App\Actions\ExchangeRate;
use App\Http\Controllers\Controller;
use Config;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartCheckOutSettingsController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/cart/min-cart-amount",
     *     tags={"Cart"},
     *     summary="Obtener el monto mínimo del carrito",
     *     description="Devuelve el monto mínimo configurado para el carrito con el tipo de cambio actual y el monto en USD. Los valores se almacenan en caché y se actualizan diariamente según la configuración.",
     *     @OA\Response(
     *         response=200,
     *         description="Respuesta exitosa",
     *         @OA\JsonContent(
     *             type="object",
     *             required={"success", "data"},
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 required={"exchange", "usd"},
     *                 @OA\Property(
     *                     property="exchange_rate",
     *                     type="string",
     *                     example="19.21",
     *                     description="Precio de cambio"
     *                 ),
     *                 @OA\Property(
     *                     property="min_cart_amount",
     *                     type="string",
     *                     example="50.00",
     *                     description="Monto mínimo para el carrito"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error interno del servidor",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Server Error")
     *         )
     *     ),
     *     security={}
     * )
     * @throws BindingResolutionException
     */
    public function __invoke(Request $request)
    {
        $user                   = Auth::user();
        $firstBuyDiscountActive = Config::get('products.first_buy.active');

        $minCartAmount      = number_format(Config::get('exchange-rate.min_cart_amount'), 2);
        $exchangeRateAction = App::make(ExchangeRate::class);
        $exchangeRate       = $exchangeRateAction->execute();

        return response()->json([
            'success' => true,
            'data'    => [
                'exchange_rate'      => number_format($exchangeRate, 2),
                'min_cart_amount'    => $minCartAmount,
                'first_buy_discount' => [
                    'active'   => $firstBuyDiscountActive && !$user->orders()->paid()->exists(),
                    'discount' => Config::get('products.first_buy.discount'),
                ],
            ],
        ]);
    }
}
