<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Servicio para autenticación con Microsoft Partner Center API
 * Genera tokens OAuth con el audience correcto
 */
class MicrosoftAuthService
{
    private string $tenantId;
    private string $clientId;
    private string $clientSecret;
    private string $scope;
    private int $tokenTimeout;

    public function __construct()
    {
        $this->tenantId = config('services.microsoft.tenant_id', env('MICROSOFT_TENANT_ID'));
        $this->clientId = config('services.microsoft.client_id', env('MICROSOFT_CLIENT_ID'));
        $this->clientSecret = config('services.microsoft.client_secret', env('MICROSOFT_CLIENT_SECRET'));
        $this->scope = config('services.microsoft.api_scope', env('MICROSOFT_API_SCOPE', 'https://api.partnercenter.microsoft.com/.default'));
        $this->tokenTimeout = config('services.microsoft.token_timeout', env('MICROSOFT_API_TOKEN_TIMEOUT', 60));

        if (empty($this->tenantId) || empty($this->clientId) || empty($this->clientSecret)) {
            throw new Exception('Microsoft OAuth credentials not configured. Please set MICROSOFT_TENANT_ID, MICROSOFT_CLIENT_ID, and MICROSOFT_CLIENT_SECRET in .env');
        }
    }

    /**
     * Obtener token de acceso (con caché)
     * Los tokens se cachean por 50 minutos (expiran en 60)
     */
    public function getAccessToken(): string
    {
        $cacheKey = 'microsoft_partner_center_token';

        // Intentar obtener del caché
        $cachedToken = Cache::get($cacheKey);
        if ($cachedToken) {
            Log::debug('Microsoft Auth: Using cached token');
            return $cachedToken;
        }

        // Generar nuevo token
        Log::info('Microsoft Auth: Requesting new access token');
        $token = $this->requestNewToken();

        // Cachear por 50 minutos (los tokens de Microsoft expiran en 60 minutos)
        Cache::put($cacheKey, $token, now()->addMinutes(50));

        return $token;
    }

    /**
     * Solicitar nuevo token a Microsoft
     */
    private function requestNewToken(): string
    {
        try {
            // Partner Center API usa OAuth 2.0 v1.0 endpoint con 'resource' parameter
            $tokenUrl = "https://login.microsoftonline.com/{$this->tenantId}/oauth2/token";

            Log::debug('Microsoft Auth: Requesting token', [
                'url' => $tokenUrl,
                'tenant_id' => $this->tenantId,
                'client_id' => $this->clientId,
                'resource' => 'https://api.partnercenter.microsoft.com'
            ]);

            $response = Http::timeout($this->tokenTimeout)
                ->asForm()
                ->post($tokenUrl, [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'resource' => 'https://api.partnercenter.microsoft.com'  // v1.0 usa 'resource' no 'scope'
                ]);

            if (!$response->successful()) {
                $errorBody = $response->json();
                Log::error('Microsoft Auth: Token request failed', [
                    'status' => $response->status(),
                    'error' => $errorBody['error'] ?? 'Unknown',
                    'error_description' => $errorBody['error_description'] ?? $response->body()
                ]);
                throw new Exception('Failed to obtain access token: ' . ($errorBody['error_description'] ?? $response->body()));
            }

            $data = $response->json();

            if (empty($data['access_token'])) {
                throw new Exception('No access token in response');
            }

            Log::info('Microsoft Auth: Token obtained successfully', [
                'token_type' => $data['token_type'] ?? 'N/A',
                'expires_in' => $data['expires_in'] ?? 'N/A',
                'resource' => $data['resource'] ?? 'N/A'
            ]);

            return $data['access_token'];

        } catch (Exception $e) {
            Log::error('Microsoft Auth: Exception while obtaining token', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new Exception('Error al obtener token de autenticación de Microsoft: ' . $e->getMessage());
        }
    }

    /**
     * Limpiar token del caché (útil para forzar renovación)
     */
    public function clearTokenCache(): void
    {
        Cache::forget('microsoft_partner_center_token');
        Log::info('Microsoft Auth: Token cache cleared');
    }

    /**
     * Verificar si las credenciales están configuradas
     */
    public static function areCredentialsConfigured(): bool
    {
        return !empty(env('MICROSOFT_TENANT_ID'))
            && !empty(env('MICROSOFT_CLIENT_ID'))
            && !empty(env('MICROSOFT_CLIENT_SECRET'));
    }
}
