<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class SecurityRateLimiter
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $type = 'general'): Response
    {
        $limits = config('security.rate_limits');

        // Obtener configuración según el tipo
        $limit = $limits[$type] ?? $limits['api_general'];

        // Crear key único por IP y usuario (si está autenticado)
        $key = $this->resolveRequestSignature($request, $type);

        // Aplicar rate limiting
        if (RateLimiter::tooManyAttempts($key, $limit['attempts'])) {
            $seconds = RateLimiter::availableIn($key);

            // Log intento de rate limit exceeded
            \Log::warning('Rate limit exceeded', [
                'type' => $type,
                'key' => $key,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'retry_after' => $seconds
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Demasiadas solicitudes. Intenta nuevamente en ' . $seconds . ' segundos.',
                'retry_after' => $seconds
            ], 429)->header('Retry-After', $seconds);
        }

        // Registrar intento
        RateLimiter::hit($key, $limit['decay_minutes'] * 60);

        $response = $next($request);

        // Agregar headers informativos
        $response->headers->set('X-RateLimit-Limit', $limit['attempts']);
        $response->headers->set('X-RateLimit-Remaining', $limit['attempts'] - RateLimiter::attempts($key));

        return $response;
    }

    /**
     * Resolver signature único para el request
     */
    protected function resolveRequestSignature(Request $request, string $type): string
    {
        $userId = $request->user()?->id ?? 'guest';
        $ip = $request->ip();

        return "rate_limit:{$type}:{$userId}:{$ip}";
    }
}
