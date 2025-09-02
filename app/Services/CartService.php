<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class CartService
{
    /**
     * Get or create cart for current user/session - VERSIÓN ORIGINAL QUE FUNCIONABA
     */
    public function getOrCreateCart(): Cart
    {
        $user = auth()->guard('sanctum')->user();

        if ($user) {
            // Usuario autenticado: buscar por user_id
            $cart = Cart::where('user_id', $user->id)
                       ->where('status', 'active')
                       ->first();

            if (!$cart) {
                // Crear carrito para usuario autenticado
                $cart = Cart::create([
                    'user_id' => $user->id,
                    'cart_token' => Str::random(32),
                    'status' => 'active',
                    'expires_at' => now()->addDays(30),
                    'subtotal' => 0.00,
                    'tax_amount' => 0.00,
                    'total_amount' => 0.00,
                ]);
            }
        } else {
            // Usuario invitado: usar cart_token
            $cartToken = request()->header('X-Cart-Token');

            if ($cartToken) {
                $cart = Cart::where('cart_token', $cartToken)
                           ->where('status', 'active')
                           ->whereNull('user_id')
                           ->first();
            }

            if (!isset($cart) || !$cart) {
                // Crear carrito para invitado
                $cart = Cart::create([
                    'user_id' => null,
                    'cart_token' => Str::random(32),
                    'status' => 'active',
                    'expires_at' => now()->addDays(7),
                    'subtotal' => 0.00,
                    'tax_amount' => 0.00,
                    'total_amount' => 0.00,
                ]);
            }
        }

        return $cart;
    }

    /**
     * Get existing cart WITHOUT creating a new one
     */
    private function getExistingCart(): ?Cart
    {
        $user = auth()->guard('sanctum')->user();

        if ($user) {
            // Usuario autenticado: buscar por user_id (después del login/merge ya debe existir)
            return Cart::where('user_id', $user->id)
                      ->where('status', 'active')
                      ->first();
        } else {
            // Usuario invitado: usar cart_token si existe
            $cartToken = request()->header('X-Cart-Token');

            if ($cartToken) {
                return Cart::where('cart_token', $cartToken)
                          ->where('status', 'active')
                          ->whereNull('user_id')
                          ->first();
            }
        }

        return null;
    }

    /**
     * Add item to cart
     */
    public function addItem(int $productId, int $quantity = 1): CartItem
    {
        $cart = $this->getOrCreateCart();
        $product = Product::findOrFail($productId);

        // Obtener el precio correcto del producto
        $unitPrice = $product->UnitPrice ? (float) str_replace(',', '', $product->UnitPrice) : 0;

        if ($unitPrice <= 0) {
            throw new \Exception('El producto no tiene un precio válido');
        }

        // Verificar si el item ya existe en el carrito
        $existingItem = CartItem::where('cart_id', $cart->id)
                               ->where('product_id', $productId)
                               ->where('status', 'active')
                               ->first();

        if ($existingItem) {
            // Actualizar cantidad
            $existingItem->quantity += $quantity;
            $existingItem->total_price = $existingItem->quantity * $existingItem->unit_price;
            $existingItem->save();
            $cartItem = $existingItem;
        } else {
            // Crear nuevo item
            $cartItem = CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $productId,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_price' => $quantity * $unitPrice,
                'status' => 'active',
            ]);
        }

        $this->updateCartTotals($cart);
        return $cartItem;
    }

    /**
     * Remove item from cart
     */
    public function removeItem(int $cartItemId): bool
    {
        $cart = $this->getOrCreateCart();

        $cartItem = CartItem::where('id', $cartItemId)
                           ->where('cart_id', $cart->id)
                           ->first();

        if ($cartItem) {
            $cartItem->delete();
            $this->updateCartTotals($cart);
            return true;
        }

        return false;
    }

    /**
     * Update item quantity - Optimized version
     */
    public function updateItemQuantity(int $cartItemId, int $quantity): bool
    {
        // Optimización: buscar directamente el item sin getOrCreateCart()
        $cartItem = CartItem::with('cart')->find($cartItemId);

        if ($cartItem) {
            if ($quantity <= 0) {
                $cartItem->delete();
            } else {
                $cartItem->quantity = $quantity;
                $cartItem->total_price = $quantity * $cartItem->unit_price;
                $cartItem->save();
            }

            $this->updateCartTotals($cartItem->cart);
            return true;
        }

        return false;
    }

    /**
     * Clear all items from cart
     */
    public function clearCart(): bool
    {
        $cart = $this->getOrCreateCart();

        CartItem::where('cart_id', $cart->id)->delete();
        $this->updateCartTotals($cart);

        return true;
    }

    /**
     * Get cart items count WITHOUT creating cart
     */
    public function getItemsCount(): int
    {
        $cart = $this->getExistingCart();

        if (!$cart) {
            return 0; // No hay carrito = 0 items
        }

        return CartItem::where('cart_id', $cart->id)->sum('quantity');
    }

    /**
     * Get cart summary WITHOUT creating cart (for display purposes)
     */
    public function getCartSummary(): array
    {
        $cart = $this->getExistingCart();

        if (!$cart) {
            return [
                'exists' => false,
                'items_count' => 0,
                'subtotal' => 0.00,
                'total_amount' => 0.00,
                'items' => []
            ];
        }

        $cart->load(['items.product']);

        return [
            'exists' => true,
            'cart_id' => $cart->id,
            'user_id' => $cart->user_id,
            'cart_token' => $cart->cart_token,
            'items_count' => $cart->items->where('status', 'active')->sum('quantity'),
            'subtotal' => $cart->subtotal,
            'total_amount' => $cart->total_amount,
            'items' => $cart->items->where('status', 'active')->map(function($item) {
                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'unit_price' => number_format($item->unit_price, 2),
                    'total_price' => number_format($item->total_price, 2),
                    'product' => $item->product ? [
                        'id' => $item->product->idproduct,
                        'title' => $item->product->ProductTitle,
                        'sku_title' => $item->product->SkuTitle,
                        'publisher' => $item->product->Publisher,
                        'icon' => $item->product->prod_icon,
                    ] : null
                ];
            })->values()
        ];
    }

    /**
     * Update cart totals
     */
    protected function updateCartTotals(Cart $cart): void
    {
        $subtotal = CartItem::where('cart_id', $cart->id)->sum('total_price');
        $taxAmount = $subtotal * 0.1; // 10% tax
        $total = $subtotal + $taxAmount;

        $cart->update([
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $total,
        ]);
    }

    /**
     * Convert guest cart to user cart on login
     */
    public function convertGuestCartToUser(string $guestCartToken, int $userId): Cart
    {
        $guestCart = Cart::where('cart_token', $guestCartToken)
                        ->where('status', 'active')
                        ->whereNull('user_id')
                        ->first();

        if ($guestCart) {
            // Verificar si el usuario ya tiene un carrito
            $userCart = Cart::where('user_id', $userId)
                           ->where('status', 'active')
                           ->first();

            if ($userCart) {
                // Mover items del carrito invitado al carrito del usuario
                CartItem::where('cart_id', $guestCart->id)
                       ->update(['cart_id' => $userCart->id]);

                // Eliminar carrito invitado
                $guestCart->delete();

                $this->updateCartTotals($userCart);
                return $userCart;
            } else {
                // Convertir carrito invitado a carrito de usuario
                $guestCart->update([
                    'user_id' => $userId,
                    'expires_at' => now()->addDays(30)
                ]);

                return $guestCart;
            }
        }

        // Si no hay carrito invitado, crear uno nuevo para el usuario
        return Cart::create([
            'user_id' => $userId,
            'cart_token' => Str::random(32),
            'status' => 'active',
            'expires_at' => now()->addDays(30),
            'subtotal' => 0.00,
            'tax_amount' => 0.00,
            'total_amount' => 0.00,
        ]);
    }

    /**
     * Merge guest cart with user cart on login
     * Esta es la función principal que maneja TODOS los casos posibles
     */
    public function mergeCartOnLogin(int $userId, string $guestCartToken = null): Cart
    {
        Log::info('Starting cart merge on login', [
            'user_id' => $userId,
            'guest_cart_token' => $guestCartToken
        ]);

        // 1. Buscar carrito existente del usuario autenticado
        $userCart = Cart::where('user_id', $userId)
                       ->where('status', 'active')
                       ->first();

        // 2. Buscar carrito de invitado si se proporciona token
        $guestCart = null;
        if ($guestCartToken) {
            $guestCart = Cart::where('cart_token', $guestCartToken)
                            ->where('status', 'active')
                            ->whereNull('user_id')
                            ->first();
        }

        // CASO 1: Usuario tiene carrito Y hay carrito de invitado con items
        if ($userCart && $guestCart && $guestCart->items()->count() > 0) {
            Log::info('Case 1: Merging guest cart items into existing user cart');

            // Transferir items del carrito invitado al carrito del usuario
            foreach ($guestCart->items as $guestItem) {
                // Verificar si el producto ya existe en el carrito del usuario
                $existingUserItem = CartItem::where('cart_id', $userCart->id)
                                          ->where('product_id', $guestItem->product_id)
                                          ->where('status', 'active')
                                          ->first();

                if ($existingUserItem) {
                    // Sumar cantidades si el producto ya existe
                    $existingUserItem->quantity += $guestItem->quantity;
                    $existingUserItem->total_price = $existingUserItem->quantity * $existingUserItem->unit_price;
                    $existingUserItem->save();

                    // Eliminar item del carrito invitado
                    $guestItem->delete();
                } else {
                    // Mover item al carrito del usuario
                    $guestItem->cart_id = $userCart->id;
                    $guestItem->save();
                }
            }

            // Eliminar carrito invitado vacío
            $guestCart->delete();
            $this->updateCartTotals($userCart);

            return $userCart;
        }

        // CASO 2: Usuario NO tiene carrito pero hay carrito de invitado
        if (!$userCart && $guestCart) {
            Log::info('Case 2: Converting guest cart to user cart');

            // Convertir carrito de invitado a carrito de usuario
            $guestCart->update([
                'user_id' => $userId,
                'expires_at' => now()->addDays(30)
            ]);

            return $guestCart;
        }

        // CASO 3: Usuario tiene carrito pero NO hay carrito de invitado (o está vacío)
        if ($userCart) {
            Log::info('Case 3: User has existing cart, no guest cart to merge');

            // Si hay carrito invitado vacío, eliminarlo
            if ($guestCart && $guestCart->items()->count() === 0) {
                $guestCart->delete();
            }

            return $userCart;
        }

        // CASO 4: Usuario NO tiene carrito y NO hay carrito de invitado
        Log::info('Case 4: Creating new cart for user');

        return Cart::create([
            'user_id' => $userId,
            'cart_token' => Str::random(32),
            'status' => 'active',
            'expires_at' => now()->addDays(30),
            'subtotal' => 0.00,
            'tax_amount' => 0.00,
            'total_amount' => 0.00,
        ]);
    }

    /**
     * Clean up old carts for a user (mantener solo el carrito activo más reciente)
     */
    public function cleanupUserCarts(int $userId): void
    {
        $activeCarts = Cart::where('user_id', $userId)
                          ->where('status', 'active')
                          ->orderBy('updated_at', 'desc')
                          ->get();

        if ($activeCarts->count() > 1) {
            // Mantener solo el carrito más reciente
            $keepCart = $activeCarts->first();

            // Mover todos los items de otros carritos al carrito principal
            $otherCarts = $activeCarts->skip(1);

            foreach ($otherCarts as $oldCart) {
                foreach ($oldCart->items as $item) {
                    // Verificar si el producto ya existe en el carrito principal
                    $existingItem = CartItem::where('cart_id', $keepCart->id)
                                          ->where('product_id', $item->product_id)
                                          ->where('status', 'active')
                                          ->first();

                    if ($existingItem) {
                        // Sumar cantidades
                        $existingItem->quantity += $item->quantity;
                        $existingItem->total_price = $existingItem->quantity * $existingItem->unit_price;
                        $existingItem->save();

                        $item->delete();
                    } else {
                        // Mover item al carrito principal
                        $item->cart_id = $keepCart->id;
                        $item->save();
                    }
                }

                // Eliminar carrito vacío
                $oldCart->delete();
            }

            $this->updateCartTotals($keepCart);
        }
    }
}
