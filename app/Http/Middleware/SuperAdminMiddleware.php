<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SuperAdminMiddleware
{
    /**
     * Handle an incoming request.
     * Solo permite acceso a Super Administrators
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Verificar que el usuario esté autenticado
        if (!Auth::check()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autenticado. Se requiere Super Administrator.'
                ], 401);
            }

            return redirect()->route('login')->with('error', 'Acceso restringido a Super Administrators');
        }

        $user = Auth::user();

        // Verificar que el usuario sea Super Administrator
        if (!$this->isSuperAdmin($user)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Acceso denegado. Se requiere rol de Super Administrator.'
                ], 403);
            }

            return redirect()->route('login')->with('error', 'No tienes permisos de Super Administrator');
        }

        return $next($request);
    }

    /**
     * Verificar si el usuario es Super Administrator
     */
    private function isSuperAdmin($user): bool
    {
        // Verificar por rol directo
        if ($user->role === 'admin') {
            return true;
        }

        // Verificar por roles de la tabla pivot (si usas sistema de roles)
        if ($user->hasRole('super-admin') || $user->hasRole('admin')) {
            return true;
        }

        // Verificar por permisos específicos
        if ($user->hasPermission('access-admin-panel')) {
            return true;
        }

        return false;
    }
}
