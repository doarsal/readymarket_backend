<?php

namespace App\Http\Controllers\Api\Cart;

use App;
use App\Actions\MinCartExchangeRate;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\Container\BindingResolutionException;

class MinCartAmountController extends Controller
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
     *                     property="exchange",
     *                     type="string",
     *                     example="1500.00",
     *                     description="Monto mínimo del carrito en moneda local (formato string con 2 decimales)"
     *                 ),
     *                 @OA\Property(
     *                     property="usd",
     *                     type="string",
     *                     example="50.00",
     *                     description="Monto equivalente en USD (formato string con 2 decimales)"
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
    public function __invoke()
    {
        $minCartExchangeRateAction = App::make(MinCartExchangeRate::class);
        $exchangeData              = $minCartExchangeRateAction->execute();

        return response()->json([
            'success' => true,
            'data'    => $exchangeData,
        ]);
    }
}
