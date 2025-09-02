<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Validator;

class ValidateSecureHeaders
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Excluir rutas de documentación de validación estricta
        if ($request->is('api/documentation*') ||
            $request->is('docs*') ||
            $request->is('api-docs') ||
            $request->is('docs/api-docs')) {
            return $next($request);
        }        // Validar headers críticos para seguridad
        $validator = Validator::make($request->headers->all(), [
            'x-cart-token' => 'nullable|string|max:255|regex:/^[a-zA-Z0-9_-]+$/',
            'user-agent' => 'required|string|max:500',
            'accept' => 'string|max:200',
            'content-type' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Headers inválidos detectados',
                'errors' => $validator->errors()
            ], 400);
        }

        // Validar que no haya headers sospechosos
        $suspiciousHeaders = ['x-forwarded-for', 'x-real-ip', 'x-originating-ip'];
        foreach ($suspiciousHeaders as $header) {
            if ($request->hasHeader($header)) {
                // Log intento sospechoso pero no bloquear (para compatibilidad con proxies)
                \Log::warning('Header sospechoso detectado', [
                    'header' => $header,
                    'value' => $request->header($header),
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent()
                ]);
            }
        }

        return $next($request);
    }
}
