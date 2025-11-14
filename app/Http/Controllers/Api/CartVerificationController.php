<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Services\MicrosoftAuthService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Cart Verification",
 *     description="Endpoints para verificar y actualizar productos del carrito"
 * )
 */
class CartVerificationController extends Controller
{
    private MicrosoftAuthService $authService;

    public function __construct(MicrosoftAuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * @OA\Post(
     *     path="/api/v1/cart/verify-products",
     *     summary="Verificar y actualizar productos del carrito",
     *     description="Verifica disponibilidad y actualiza precios/nombres de productos en el carrito del usuario",
     *     tags={"Cart Verification"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"cart_id"},
     *             @OA\Property(property="cart_id", type="integer", example=123, description="ID del carrito")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Productos verificados exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="total_products", type="integer", example=3),
     *             @OA\Property(property="verified_count", type="integer", example=3),
     *             @OA\Property(property="available_count", type="integer", example=2),
     *             @OA\Property(property="unavailable_count", type="integer", example=1),
     *             @OA\Property(property="updated_count", type="integer", example=1),
     *             @OA\Property(
     *                 property="products",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="product_id", type="integer", example=168),
     *                     @OA\Property(property="status", type="string", example="available"),
     *                     @OA\Property(property="updated", type="boolean", example=true),
     *                     @OA\Property(
     *                         property="changes",
     *                         type="object",
     *                         @OA\Property(property="price_changed", type="boolean", example=true),
     *                         @OA\Property(property="old_price", type="string", example="10.85"),
     *                         @OA\Property(property="new_price", type="string", example="11.20"),
     *                         @OA\Property(property="name_changed", type="boolean", example=false),
     *                         @OA\Property(property="availability_id_changed", type="boolean", example=true)
     *                     ),
     *                     @OA\Property(
     *                         property="current_data",
     *                         type="object",
     *                         @OA\Property(property="ProductTitle", type="string", example="Office 365 E1"),
     *                         @OA\Property(property="UnitPrice", type="string", example="11.20"),
     *                         @OA\Property(property="Id", type="string", example="CFQ7TTC0WLR4")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Carrito no encontrado"),
     *     @OA\Response(response=422, description="Error de validación")
     * )
     */
    public function verifyCartProducts(Request $request): JsonResponse
    {
        $request->validate([
            'cart_id' => 'required|integer|exists:carts,id'
        ]);

        $cart = Cart::with('items.product')->find($request->cart_id);

        if (!$cart) {
            return response()->json([
                'success' => false,
                'message' => 'Carrito no encontrado'
            ], 404);
        }

        // Verificar que el carrito pertenece al usuario autenticado
        if ($cart->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado'
            ], 403);
        }

        $products = $cart->items->pluck('product')->unique('idproduct');
        $verifiedProducts = [];
        $availableCount = 0;
        $unavailableCount = 0;
        $updatedCount = 0;
        $removedProductIds = [];

        foreach ($products as $product) {
            if (!$product) {
                continue;
            }

            $result = $this->verifyAndUpdateProduct($product);
            $verifiedProducts[] = $result;

            if ($result['status'] === 'available') {
                $availableCount++;
            } else {
                // Producto no disponible - eliminarlo del carrito
                $unavailableCount++;
                $removedProductIds[] = $product->idproduct;

                // Eliminar todos los items del carrito que tengan este producto
                DB::table('cart_items')
                    ->where('cart_id', $cart->id)
                    ->where('product_id', $product->idproduct)
                    ->delete();

                Log::info("Producto no disponible eliminado del carrito", [
                    'cart_id' => $cart->id,
                    'product_id' => $product->idproduct,
                    'product_name' => $product->ProductTitle
                ]);
            }

            if ($result['updated']) {
                $updatedCount++;
            }
        }

        // Recalcular totales del carrito después de eliminar productos
        if (!empty($removedProductIds)) {
            $this->recalculateCartTotals($cart->id);
        }

        return response()->json([
            'success' => true,
            'total_products' => count($verifiedProducts),
            'verified_count' => count($verifiedProducts),
            'available_count' => $availableCount,
            'unavailable_count' => $unavailableCount,
            'updated_count' => $updatedCount,
            'removed_product_ids' => $removedProductIds,
            'products' => $verifiedProducts
        ]);
    }

    /**
     * Recalcular totales del carrito
     */
    private function recalculateCartTotals(int $cartId): void
    {
        $cartItems = DB::table('cart_items')
            ->where('cart_id', $cartId)
            ->get();

        $subtotal = 0;
        foreach ($cartItems as $item) {
            $subtotal += floatval($item->total_price);
        }

        // Calcular impuestos (si aplica)
        $taxRate = 0.16; // 16% IVA - ajustar según tu lógica
        $taxAmount = 0; // Por ahora sin impuestos, ajustar si es necesario
        $totalAmount = $subtotal + $taxAmount;

        DB::table('carts')
            ->where('id', $cartId)
            ->update([
                'subtotal' => number_format($subtotal, 2, '.', ''),
                'tax_amount' => number_format($taxAmount, 2, '.', ''),
                'total_amount' => number_format($totalAmount, 2, '.', ''),
                'updated_at' => now()
            ]);
    }

    /**
     * Verificar y actualizar un producto específico
     */
    private function verifyAndUpdateProduct($product): array
    {
        $productId = $product->idproduct;
        $oldPrice = $product->UnitPrice;
        $oldName = $product->ProductTitle;
        $oldAvailabilityId = $product->Id;

        try {
            // Obtener token de acceso
            $token = $this->authService->getAccessToken();

            // Consultar availabilities desde Microsoft
            $response = Http::withToken($token)
                ->timeout(30)
                ->get("https://api.partnercenter.microsoft.com/v1/products/{$product->ProductId}/skus/{$product->SkuId}/availabilities", [
                    'country' => $product->Market ?? 'MX'
                ]);

            if (!$response->successful()) {
                Log::warning("Failed to fetch availabilities for product {$productId}", [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);

                // Marcar como no disponible
                DB::table('products')
                    ->where('idproduct', $productId)
                    ->update([
                        'is_available' => false,
                        'availability_checked_at' => now(),
                        'availability_error' => 'HTTP ' . $response->status() . ': ' . $response->body()
                    ]);

                return [
                    'product_id' => $productId,
                    'status' => 'unavailable',
                    'updated' => false,
                    'error' => 'No se pudo verificar disponibilidad',
                    'current_data' => [
                        'ProductTitle' => $oldName,
                        'UnitPrice' => $oldPrice,
                        'Id' => $oldAvailabilityId
                    ]
                ];
            }

            $data = $response->json();
            $availabilities = $data['items'] ?? [];

            if (empty($availabilities)) {
                // No hay availabilities - producto no disponible
                DB::table('products')
                    ->where('idproduct', $productId)
                    ->update([
                        'is_available' => false,
                        'availability_checked_at' => now(),
                        'availability_error' => 'No availabilities found'
                    ]);

                return [
                    'product_id' => $productId,
                    'status' => 'unavailable',
                    'updated' => false,
                    'error' => 'Producto no disponible en Microsoft',
                    'current_data' => [
                        'ProductTitle' => $oldName,
                        'UnitPrice' => $oldPrice,
                        'Id' => $oldAvailabilityId
                    ]
                ];
            }

            // Tomar el primer availability disponible
            $firstAvailability = $availabilities[0];
            $newAvailabilityId = $firstAvailability['id'] ?? null;

            if (!$newAvailabilityId) {
                return [
                    'product_id' => $productId,
                    'status' => 'unavailable',
                    'updated' => false,
                    'error' => 'No se encontró AvailabilityId',
                    'current_data' => [
                        'ProductTitle' => $oldName,
                        'UnitPrice' => $oldPrice,
                        'Id' => $oldAvailabilityId
                    ]
                ];
            }

            // Obtener precio actualizado
            $newPrice = null;
            $catalogItemId = $firstAvailability['catalogItemId'] ?? null;

            if ($catalogItemId) {
                $terms = $firstAvailability['terms'] ?? [];
                foreach ($terms as $term) {
                    if (isset($term['billingPlan']) && $term['billingPlan'] === $product->BillingPlan) {
                        $pricing = $term['price'] ?? null;
                        if ($pricing && isset($pricing['listPrice'])) {
                            $newPrice = $pricing['listPrice'];
                            break;
                        }
                    }
                }
            }

            // Si no encontramos precio, usar el anterior
            if ($newPrice === null) {
                $newPrice = $oldPrice;
            }

            // Detectar cambios
            $priceChanged = abs(floatval($newPrice) - floatval($oldPrice)) > 0.01;
            $availabilityIdChanged = $newAvailabilityId !== $oldAvailabilityId;
            $hasChanges = $priceChanged || $availabilityIdChanged;

            // Actualizar producto en BD
            DB::table('products')
                ->where('idproduct', $productId)
                ->update([
                    'Id' => $newAvailabilityId,
                    'UnitPrice' => $newPrice,
                    'is_available' => true,
                    'availability_checked_at' => now(),
                    'availability_error' => null
                ]);

            return [
                'product_id' => $productId,
                'status' => 'available',
                'updated' => $hasChanges,
                'changes' => [
                    'price_changed' => $priceChanged,
                    'old_price' => $oldPrice,
                    'new_price' => $newPrice,
                    'name_changed' => false, // El nombre no cambia en este proceso
                    'availability_id_changed' => $availabilityIdChanged,
                    'old_availability_id' => $oldAvailabilityId,
                    'new_availability_id' => $newAvailabilityId
                ],
                'current_data' => [
                    'ProductTitle' => $product->ProductTitle,
                    'UnitPrice' => $newPrice,
                    'Id' => $newAvailabilityId,
                    'Publisher' => $product->Publisher,
                    'SkuTitle' => $product->SkuTitle
                ]
            ];

        } catch (\Exception $e) {
            Log::error("Error verifying product {$productId}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            DB::table('products')
                ->where('idproduct', $productId)
                ->update([
                    'availability_checked_at' => now(),
                    'availability_error' => $e->getMessage()
                ]);

            return [
                'product_id' => $productId,
                'status' => 'error',
                'updated' => false,
                'error' => 'Error al verificar: ' . $e->getMessage(),
                'current_data' => [
                    'ProductTitle' => $oldName,
                    'UnitPrice' => $oldPrice,
                    'Id' => $oldAvailabilityId
                ]
            ];
        }
    }
}
