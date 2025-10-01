<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Excluir rutas de documentación API de políticas estrictas
        $isDocsRoute = $request->is('api/documentation*') ||
            $request->is('docs*') ||
            $request->is('api-docs') ||
            $request->is('docs/api-docs');

        // Headers de seguridad globales
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // NO aplicar X-Frame-Options ni CSP en rutas API para evitar conflictos con CORS
        if ($isDocsRoute) {
            // CSP más permisivo para Swagger UI
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
            $response->headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' cdn.jsdelivr.net unpkg.com; style-src 'self' 'unsafe-inline' cdn.jsdelivr.net fonts.googleapis.com; font-src 'self' fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self';");
        } elseif (!$request->is('api/*')) {
            // CSP estricto solo para rutas web (no API)
            $response->headers->set('X-Frame-Options', 'DENY');
            $response->headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:;");
        }

        // Prevenir MIME type sniffing
        $response->headers->set('X-Download-Options', 'noopen');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        // Solo HTTPS en producción
        if (app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        return $response;
    }
}
