<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Cart;

class CartAuthMiddleware
{
    /**
     * Handle an incoming request.
     * Permite acceso a usuarios autenticados y guests (sin validar token al principio)
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->guard('sanctum')->user();

        if ($user) {
            // Usuario autenticado - permitir acceso completo
            return $next($request);
        }

        // Usuario guest - permitir acceso
        // El CartService se encargará de crear/obtener carrito según sea necesario
        // Para operaciones como "show" o "clear" que no requieren carrito existente

        $method = $request->method();

        // Para operaciones que modifican items específicos, verificar que el carrito exista
        if (in_array($method, ['PUT', 'DELETE']) && $request->route('item')) {
            $cartToken = $request->header('X-Cart-Token');

            if (!$cartToken) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cart token required for this operation'
                ], 401);
            }

            // Verificar que el token corresponda a un carrito válido
            $cart = Cart::where('cart_token', $cartToken)
                       ->where('status', 'active')
                       ->whereNull('user_id')
                       ->first();

            if (!$cart) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid cart token'
                ], 401);
            }
        }

        return $next($request);
    }
}
