<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddToCartRequest;
use App\Http\Requests\UpdateCartItemRequest;
use App\Models\Cart;
use App\Models\CartItem;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Cart",
 *     description="Shopping cart management endpoints"
 * )
 */
class CartController extends Controller
{
    protected CartService $cartService;

    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
    }

    /**
     * @OA\Get(
     *     path="/api/v1/cart",
     *     tags={"Cart"},
     *     summary="Get current cart",
     *     description="Get the current user's cart or session cart if not authenticated",
     *     @OA\Parameter(
     *         name="X-Cart-Token",
     *         in="header",
     *         description="Cart token for guest users",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cart retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/Cart"),
     *             @OA\Property(property="stats", type="object",
     *                 @OA\Property(property="subtotal", type="string", example="99.99"),
     *                 @OA\Property(property="tax_amount", type="string", example="15.99"),
     *                 @OA\Property(property="total_amount", type="string", example="115.98"),
     *                 @OA\Property(property="items_count", type="integer", example=3),
     *                 @OA\Property(property="unique_products", type="integer", example=2),
     *                 @OA\Property(property="cart_token", type="string", example="abc123def456")
     *             )
     *         )
     *     )
     * )
     */
    public function show(Request $request): JsonResponse
    {
        try {
            // Usar getCartSummary para no crear carritos innecesarios
            $cartSummary = $this->cartService->getCartSummary();
            $taxRate = config('facturalo.taxes.iva.rate');

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $cartSummary['exists'] ? $cartSummary['cart_id'] : null,
                    'items' => $cartSummary['items'],
                    'subtotal' => number_format($cartSummary['subtotal'], 2),
                    'tax_amount' => number_format($cartSummary['subtotal'] * $taxRate, 2),
                    'total_amount' => number_format($cartSummary['total_amount'], 2),
                    'currency_code' => 'MXN',
                    'status' => $cartSummary['exists'] ? 'active' : 'empty',
                    'cart_token' => $cartSummary['exists'] ? $cartSummary['cart_token'] : null,
                ],
            ], 200, [
                'X-Cart-Token' => $cartSummary['exists'] ? $cartSummary['cart_token'] : ''
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener carrito', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'cart_token' => request()->header('X-Cart-Token')
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor. Por favor intenta de nuevo.'
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/cart/items",
     *     tags={"Cart"},
     *     summary="Add item to cart",
     *     description="Add a product to the shopping cart",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/AddToCartRequest")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Item added to cart successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Producto agregado al carrito"),
     *             @OA\Property(property="data", ref="#/components/schemas/CartItem"),
     *             @OA\Property(property="cart_stats", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Los datos proporcionados no son válidos"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function addItem(AddToCartRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            $cartItem = $this->cartService->addItem(
                $validated['product_id'],
                $validated['quantity'] ?? 1
            );

            $cartItem->load('product');

            // Usar el carrito que ya existe en el item, NO crear otro
            $cart = $cartItem->cart;
            $cart->load(['items.product']);

            return response()->json([
                'success' => true,
                'message' => 'Producto agregado al carrito.',
                'data' => [
                    'item' => [
                        'id' => $cartItem->id,
                        'product_id' => $cartItem->product_id,
                        'quantity' => $cartItem->quantity,
                        'unit_price' => number_format($cartItem->unit_price, 2),
                        'total_price' => number_format($cartItem->total_price, 2),
                    ],
                    'cart' => [
                        'id' => $cart->id,
                        'items' => $cart->items->where('status', 'active')->map(function($item) {
                            return [
                                'id' => $item->id,
                                'product_id' => $item->product_id,
                                'quantity' => $item->quantity,
                                'unit_price' => number_format($item->unit_price, 2),
                                'total_price' => number_format($item->total_price, 2),
                                'status' => $item->status,
                                'product' => $item->product ? [
                                    'id' => $item->product->idproduct,
                                    'title' => $item->product->ProductTitle,
                                    'sku_title' => $item->product->SkuTitle,
                                    'description' => $item->product->SkuDescription,
                                    'publisher' => $item->product->Publisher,
                                    'icon' => $item->product->prod_icon,
                                    'currency' => $item->product->Currency,
                                ] : null
                            ];
                        })->values(),
                        'subtotal' => number_format($cart->subtotal ?? 0, 2),
                        'tax_amount' => number_format($cart->tax_amount ?? 0, 2),
                        'total_amount' => number_format($cart->total_amount ?? 0, 2),
                        'currency_code' => 'MXN',
                        'status' => $cart->status,
                        'cart_token' => $cart->cart_token,
                    ]
                ]
            ], 201, [
                'X-Cart-Token' => $cart->cart_token
            ]);

        } catch (\Exception $e) {
            Log::error('Cart: Error adding item', [
                'error' => $e->getMessage(),
                'data' => $request->validated(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al agregar el producto al carrito.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/v1/cart/items/{item}",
     *     tags={"Cart"},
     *     summary="Update cart item",
     *     description="Update quantity or metadata of a cart item",
     *     @OA\Parameter(
     *         name="item",
     *         in="path",
     *         description="Cart item ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/UpdateCartItemRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Item updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Item actualizado"),
     *             @OA\Property(property="data", ref="#/components/schemas/CartItem")
     *         )
     *     )
     * )
     */
    public function updateItem(UpdateCartItemRequest $request, CartItem $item): JsonResponse
    {
        try {
            $startTime = microtime(true);

            $validated = $request->validated();

            $success = $this->cartService->updateItemQuantity($item->id, $validated['quantity']);

            if ($success) {
                $queryTime = microtime(true);

                // Optimización: Una sola query en lugar de múltiples loads
                $cart = Cart::with(['items.product'])
                    ->where('id', $item->cart_id)
                    ->first();

                $loadTime = microtime(true);

                Log::info('Cart update performance', [
                    'total_time' => ($loadTime - $startTime) * 1000 . 'ms',
                    'service_time' => ($queryTime - $startTime) * 1000 . 'ms',
                    'load_time' => ($loadTime - $queryTime) * 1000 . 'ms'
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Item actualizado correctamente.',
                    'data' => [
                        'item' => [
                            'id' => $item->id,
                            'product_id' => $item->product_id,
                            'quantity' => $validated['quantity'], // Usar valor actualizado
                            'unit_price' => number_format($item->unit_price, 2),
                            'total_price' => number_format($validated['quantity'] * $item->unit_price, 2),
                        ],
                        'cart' => [
                            'id' => $cart->id,
                            'items' => $cart->items->where('status', 'active')->map(function($cartItem) {
                                return [
                                    'id' => $cartItem->id,
                                    'product_id' => $cartItem->product_id,
                                    'quantity' => $cartItem->quantity,
                                    'unit_price' => number_format($cartItem->unit_price, 2),
                                    'total_price' => number_format($cartItem->total_price, 2),
                                    'status' => $cartItem->status,
                                    'product' => $cartItem->product ? [
                                        'id' => $cartItem->product->idproduct,
                                        'title' => $cartItem->product->ProductTitle,
                                        'sku_title' => $cartItem->product->SkuTitle,
                                        'description' => $cartItem->product->SkuDescription,
                                        'publisher' => $cartItem->product->Publisher,
                                        'icon' => $cartItem->product->prod_icon,
                                        'currency' => $cartItem->product->Currency,
                                    ] : null
                                ];
                            })->values(),
                            'subtotal' => number_format($cart->subtotal ?? 0, 2),
                            'tax_amount' => number_format($cart->tax_amount ?? 0, 2),
                            'total_amount' => number_format($cart->total_amount ?? 0, 2),
                            'currency_code' => 'MXN',
                            'status' => $cart->status,
                            'cart_token' => $cart->cart_token,
                        ]
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo actualizar el item.',
                ], 404);
            }

        } catch (\Exception $e) {
            Log::error('Cart: Error updating item', [
                'error' => $e->getMessage(),
                'item_id' => $item->id,
                'data' => $request->validated(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el item.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/cart/items/{item}",
     *     tags={"Cart"},
     *     summary="Remove item from cart",
     *     description="Remove a specific item from the cart",
     *     @OA\Parameter(
     *         name="item",
     *         in="path",
     *         description="Cart item ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Item removed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Item eliminado del carrito")
     *         )
     *     )
     * )
     */
    public function removeItem(CartItem $item): JsonResponse
    {
        try {
            // Obtener el carrito antes de eliminar el item
            $cart = $item->cart;

            $success = $this->cartService->removeItem($item->id);

            if ($success) {
                // Recargar el carrito actualizado
                $cart->refresh();
                $cart->load(['items.product']);

                return response()->json([
                    'success' => true,
                    'message' => 'Item eliminado del carrito.',
                    'data' => [
                        'cart' => [
                            'id' => $cart->id,
                            'items' => $cart->items->where('status', 'active')->map(function($cartItem) {
                                return [
                                    'id' => $cartItem->id,
                                    'product_id' => $cartItem->product_id,
                                    'quantity' => $cartItem->quantity,
                                    'unit_price' => number_format($cartItem->unit_price, 2),
                                    'total_price' => number_format($cartItem->total_price, 2),
                                    'status' => $cartItem->status,
                                    'product' => $cartItem->product ? [
                                        'id' => $cartItem->product->idproduct,
                                        'title' => $cartItem->product->ProductTitle,
                                        'sku_title' => $cartItem->product->SkuTitle,
                                        'description' => $cartItem->product->SkuDescription,
                                        'publisher' => $cartItem->product->Publisher,
                                        'icon' => $cartItem->product->prod_icon,
                                        'currency' => $cartItem->product->Currency,
                                    ] : null
                                ];
                            })->values(),
                            'subtotal' => number_format($cart->subtotal ?? 0, 2),
                            'tax_amount' => number_format($cart->tax_amount ?? 0, 2),
                            'total_amount' => number_format($cart->total_amount ?? 0, 2),
                            'currency_code' => 'MXN',
                            'status' => $cart->status,
                            'cart_token' => $cart->cart_token,
                        ]
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo eliminar el item.',
                ], 404);
            }

        } catch (\Exception $e) {
            Log::error('Cart: Error removing item', [
                'error' => $e->getMessage(),
                'item_id' => $item->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el item.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/cart/clear",
     *     tags={"Cart"},
     *     summary="Clear cart",
     *     description="Remove all items from the cart",
     *     @OA\Response(
     *         response=200,
     *         description="Cart cleared successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Carrito vaciado")
     *         )
     *     )
     * )
     */
    public function clear(): JsonResponse
    {
        try {
            $this->cartService->clearCart();

            return response()->json([
                'success' => true,
                'message' => 'Carrito vaciado correctamente.',
                'data' => [
                    'items_count' => 0,
                    'subtotal' => '0.00',
                    'total_amount' => '0.00'
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Cart: Error clearing cart', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al vaciar el carrito.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Transferir carrito a abandoned_carts cuando se detecta abandono
     */
    public function markAsAbandoned(Request $request): JsonResponse
    {
        try {
            $cart = $this->cartService->getCart($request);

            if (!$cart || $cart->items->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay carrito para marcar como abandonado'
                ], 404);
            }

            // Preparar datos para abandoned_carts
            $cartData = [
                'user_id' => auth()->id(),
                'session_id' => session()->getId() ?: 'guest_' . uniqid(),
                'store_id' => $cart->store_id,
                'visitor_ip' => $request->ip(),
                'cart_items' => $cart->items->map(function ($item) {
                    return [
                        'product_id' => $item->product_id,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'total_price' => $item->total_price,
                        'product_name' => $item->product->name ?? 'Producto',
                        'product_sku' => $item->product->sku ?? null,
                    ];
                })->toArray(),
                'total_amount' => $cart->total_amount,
                'tax_amount' => $cart->tax_amount,
                'shipping_amount' => $cart->shipping_amount,
                'total_items' => $cart->items->sum('quantity'),
                'currency' => $cart->currency ?? 'USD',
                'user_email' => auth()->user()?->email ?? $request->input('user_email'),
                'checkout_step' => $request->input('checkout_step', 'cart'),
                'last_activity' => now(),
                'user_agent_data' => [
                    'user_agent' => $request->userAgent(),
                    'device_type' => $this->detectDeviceType($request->userAgent())
                ],
                'referrer_url' => $request->header('referer'),
                'abandon_reason' => $request->input('abandon_reason', [])
            ];

            // Crear registro en abandoned_carts
            $abandonedCart = \App\Models\AbandonedCart::create($cartData);

            // Opcional: Marcar el carrito original como abandonado
            $cart->update([
                'status' => 'abandoned',
                'abandoned_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Carrito marcado como abandonado',
                'data' => [
                    'abandoned_cart_id' => $abandonedCart->id,
                    'recovery_token' => $abandonedCart->recovery_token
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error al marcar carrito como abandonado', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al marcar carrito como abandonado',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Marcar carrito abandonado como recuperado cuando se completa la compra
     */
    public static function markAbandonedAsRecovered($cartToken = null, $orderId = null): void
    {
        try {
            $query = \App\Models\AbandonedCart::where('status', 'abandoned');

            if ($cartToken) {
                $query->where('recovery_token', $cartToken);
            } else {
                // Buscar por usuario y session reciente
                $query->where(function($q) {
                    $q->where('user_id', auth()->id())
                      ->orWhere('session_id', session()->getId());
                })
                ->where('created_at', '>=', now()->subHours(24)); // Solo últimas 24 horas
            }

            $abandonedCarts = $query->get();

            foreach ($abandonedCarts as $cart) {
                $cart->update([
                    'status' => 'recovered',
                    'recovered_at' => now(),
                    'recovered_order_id' => $orderId
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error al marcar carritos como recuperados', [
                'error' => $e->getMessage(),
                'cart_token' => $cartToken,
                'order_id' => $orderId
            ]);
        }
    }

    /**
     * Detectar tipo de dispositivo
     */
    private function detectDeviceType(string $userAgent): string
    {
        if (preg_match('/mobile|android|iphone|ipod|blackberry|opera mini|iemobile/i', $userAgent)) {
            return 'mobile';
        } elseif (preg_match('/tablet|ipad|android(?!.*mobile)/i', $userAgent)) {
            return 'tablet';
        } else {
            return 'desktop';
        }
    }
}
