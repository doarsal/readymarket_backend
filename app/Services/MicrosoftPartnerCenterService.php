<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MicrosoftPartnerCenterService
{
    private ?string $token = null;

    /**
     * Obtener URL de credenciales desde configuración
     */
    private function getCredentialsUrl(): string
    {
        return config('services.microsoft.credentials_url', env('MICROSOFT_CREDENTIALS_URL'));
    }

    /**
     * Obtener URL base de Partner Center desde configuración
     */
    private function getPartnerCenterBaseUrl(): string
    {
        return config('services.microsoft.partner_center_base_url', env('MICROSOFT_PARTNER_CENTER_BASE_URL'));
    }

    /**
     * Obtener ID de plantilla de acuerdo desde configuración
     */
    private function getAgreementTemplateId(): string
    {
        return config('services.microsoft.agreement_template_id', env('MICROSOFT_AGREEMENT_TEMPLATE_ID'));
    }

    /**
     * Obtener token de autenticación
     */
    public function getAuthToken(): string
    {
        if ($this->token) {
            return $this->token;
        }

        try {
            $response = Http::timeout(10)
                           ->get($this->getCredentialsUrl());

            if (!$response->successful()) {
                throw new Exception('Failed to get credentials: ' . $response->body());
            }

            $data = $response->json();

            if (empty($data['item']['token'])) {
                throw new Exception('Invalid token in credentials response');
            }

            $this->token = $data['item']['token'];
            return $this->token;

        } catch (Exception $e) {
            Log::error('Microsoft Partner Center: Failed to get auth token', [
                'error' => $e->getMessage()
            ]);
            throw new Exception('Error al conectar con servicio de credenciales: ' . $e->getMessage());
        }
    }

    /**
     * Crear cliente en Microsoft Partner Center
     */
    public function createCustomer(array $customerData): array
    {
        Log::info('Microsoft Partner Center: Starting customer creation', [
            'domain' => $customerData['domain_concatenated'] ?? 'N/A',
            'email' => $customerData['email'] ?? 'N/A'
        ]);

        $token = $this->getAuthToken();
        Log::info('Microsoft Partner Center: Token obtained successfully');

        $payload = [
            'CompanyProfile' => [
                'Domain' => $customerData['domain_concatenated']
            ],
            'BillingProfile' => [
                'Culture' => $customerData['culture'],
                'Email' => $customerData['email'],
                'Language' => $customerData['language_code'],
                'CompanyName' => $customerData['organization'],
                'DefaultAddress' => [
                    'FirstName' => $customerData['first_name'],
                    'LastName' => $customerData['last_name'],
                    'AddressLine1' => $customerData['address'],
                    'City' => $customerData['city'],
                    'State' => $customerData['state_code'],
                    'PostalCode' => $customerData['postal_code'],
                    'Country' => $customerData['country_code'],
                    'PhoneNumber' => $customerData['phone']
                ]
            ]
        ];

        Log::info('Microsoft Partner Center: Payload prepared', ['payload' => $payload]);

        try {
            $url = $this->getPartnerCenterBaseUrl() . '/customers';
            Log::info('Microsoft Partner Center: Making API request', [
                'url' => $url,
                'has_token' => !empty($token),
                'token_prefix' => substr($token, 0, 20) . '...'
            ]);

            $response = Http::timeout(20)
                           ->withHeaders([
                               'Content-Type' => 'application/json',
                               'Accept' => 'application/json',
                               'Authorization' => 'Bearer ' . $token
                           ])
                           ->post($url, $payload);

            Log::info('Microsoft Partner Center: API response received', [
                'status' => $response->status(),
                'successful' => $response->successful(),
                'response_size' => strlen($response->body())
            ]);

            if (!$response->successful()) {
                Log::error('Microsoft Partner Center: Failed to create customer', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'payload' => $payload
                ]);
                throw new Exception('Partner Center API error: ' . $response->body());
            }

            $responseData = $response->json();

            // Extraer Microsoft ID
            $microsoftId = $responseData['id'] ??
                          $responseData['userCredentials']['userName'] ??
                          null;

            if (!$microsoftId) {
                throw new Exception('Microsoft ID not returned in response');
            }

            return [
                'microsoft_id' => $microsoftId,
                'password' => $responseData['userCredentials']['password'] ?? '',
                'response' => $responseData
            ];

        } catch (Exception $e) {
            Log::error('Microsoft Partner Center: Create customer failed', [
                'error' => $e->getMessage(),
                'customer_data' => $customerData
            ]);
            throw new Exception('Error al crear cliente en Partner Center: ' . $e->getMessage());
        }
    }

    /**
     * Aceptar acuerdo de Microsoft Customer Agreement
     */
    public function acceptCustomerAgreement(string $microsoftId, array $contactData): bool
    {
        $token = $this->getAuthToken();

        $payload = [
            'primaryContact' => [
                'firstName' => $contactData['first_name'],
                'lastName' => $contactData['last_name'],
                'email' => 'admin@' . $contactData['domain_concatenated'],
                'phoneNumber' => $contactData['phone']
            ],
            'templateId' => $this->getAgreementTemplateId(),
            'dateAgreed' => now()->format('Y-m-d'),
            'type' => 'MicrosoftCustomerAgreement'
        ];

        try {
            $response = Http::timeout(15)
                           ->withHeaders([
                               'Authorization' => 'Bearer ' . $token,
                               'Content-Type' => 'application/json'
                           ])
                           ->post($this->getPartnerCenterBaseUrl() . "/customers/{$microsoftId}/agreements", $payload);

            if (!$response->successful()) {
                Log::warning('Microsoft Partner Center: Failed to accept agreement', [
                    'microsoft_id' => $microsoftId,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                // No lanzar excepción aquí, ya que el acuerdo podría fallar pero la cuenta estar creada
                return false;
            }

            return true;

        } catch (Exception $e) {
            Log::error('Microsoft Partner Center: Accept agreement failed', [
                'microsoft_id' => $microsoftId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Obtener información de cliente
     */
    public function getCustomer(string $microsoftId): ?array
    {
        $token = $this->getAuthToken();

        try {
            $response = Http::timeout(10)
                           ->withHeaders([
                               'Authorization' => 'Bearer ' . $token,
                               'Accept' => 'application/json'
                           ])
                           ->get($this->getPartnerCenterBaseUrl() . "/customers/{$microsoftId}");

            if (!$response->successful()) {
                return null;
            }

            return $response->json();

        } catch (Exception $e) {
            Log::error('Microsoft Partner Center: Get customer failed', [
                'microsoft_id' => $microsoftId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Verificar si un cliente existe en Partner Center
     */
    public function verifyCustomer(string $microsoftId): bool
    {
        return $this->getCustomer($microsoftId) !== null;
    }

    /**
     * Sincronizar cuenta con Partner Center
     */
    public function syncAccount(string $microsoftId): array
    {
        $customerData = $this->getCustomer($microsoftId);

        if (!$customerData) {
            throw new Exception('Customer not found in Partner Center');
        }

        return [
            'verified' => true,
            'customer_data' => $customerData,
            'last_sync' => now()->toISOString()
        ];
    }
}
