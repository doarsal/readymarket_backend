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
            $response = Http::timeout(config('services.microsoft.token_timeout', env('MICROSOFT_API_TOKEN_TIMEOUT', 60)))
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

            $response = Http::timeout(config('services.microsoft.create_customer_timeout', env('MICROSOFT_API_CREATE_CUSTOMER_TIMEOUT', 120)))
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
            $response = Http::timeout(config('services.microsoft.agreement_timeout', env('MICROSOFT_API_AGREEMENT_TIMEOUT', 60)))
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
            $response = Http::timeout(config('services.microsoft.get_customer_timeout', env('MICROSOFT_API_GET_CUSTOMER_TIMEOUT', 60)))
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

    /**
     * Buscar cliente en Partner Center por dominio y obtener sus datos completos
     *
     * @param string $domain Dominio concatenado (ej: empresa.onmicrosoft.com)
     * @return array|null ['customer_id' => string, 'customer_data' => array] o null si no existe
     */
    public function findCustomerByDomain(string $domain): ?array
    {
        try {
            $token = $this->getAuthToken();

            Log::info('Buscando cliente por dominio en Partner Center', [
                'domain' => $domain
            ]);

            // Buscar el cliente por dominio
            $searchUrl = $this->getPartnerCenterBaseUrl() . '/customers';

            $response = Http::timeout(config('services.microsoft.get_customer_timeout', 60))
                           ->withHeaders([
                               'Authorization' => 'Bearer ' . $token,
                               'Accept' => 'application/json'
                           ])
                           ->get($searchUrl);

            if (!$response->successful()) {
                Log::error('Error al buscar cliente en Partner Center', [
                    'domain' => $domain,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return null;
            }

            $customers = $response->json();

            // Buscar cliente por dominio
            $customer = null;
            if (isset($customers['items'])) {
                foreach ($customers['items'] as $item) {
                    if (isset($item['companyProfile']['domain']) &&
                        strtolower($item['companyProfile']['domain']) === strtolower($domain)) {
                        $customer = $item;
                        break;
                    }
                }
            }

            if (!$customer) {
                Log::warning('Cliente no encontrado en Partner Center', [
                    'domain' => $domain,
                    'total_customers_checked' => isset($customers['items']) ? count($customers['items']) : 0
                ]);
                return null;
            }

            $customerId = $customer['id'];

            Log::info('Cliente encontrado en Partner Center', [
                'domain' => $domain,
                'customer_id' => $customerId,
                'company_name' => $customer['companyProfile']['companyName'] ?? 'N/A'
            ]);

            // Obtener detalles completos del cliente incluyendo billingProfile
            $customerDetails = $this->getCustomer($customerId);

            if ($customerDetails) {
                Log::info('Detalles del cliente obtenidos', [
                    'customer_id' => $customerId,
                    'has_billing_profile' => isset($customerDetails['billingProfile'])
                ]);

                return [
                    'customer_id' => $customerId,
                    'customer_data' => $customerDetails
                ];
            }

            // Si falla getCustomer, devolver datos básicos
            return [
                'customer_id' => $customerId,
                'customer_data' => $customer
            ];

        } catch (Exception $e) {
            Log::error('Error al buscar cliente por dominio', [
                'domain' => $domain,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Verificar si la relación CSP Partner fue aceptada por el cliente
     *
     * Para cuentas vinculadas existentes, verifica si el cliente ya aceptó
     * la invitación de partner en su portal de Microsoft Admin Center
     *
     * @param string $domain Dominio concatenado de Microsoft (ej: empresa.onmicrosoft.com)
     * @return array ['accepted' => bool, 'message' => string, 'details' => array]
     */
    public function checkPartnerRelationshipAccepted(string $domain): array
    {
        $logChannel = Log::build([
            'driver' => 'single',
            'path' => storage_path('logs/partner_center_' . date('Y-m-d') . '.log'),
        ]);

        try {
            $token = $this->getAuthToken();

            $logChannel->info('=== VERIFICANDO RELACIÓN DE PARTNER ===', [
                'domain' => $domain,
                'timestamp' => now()->toDateTimeString()
            ]);

            // Buscar el cliente por dominio primero
            $searchUrl = $this->getPartnerCenterBaseUrl() . '/customers';

            $logChannel->info('Consultando lista de clientes', [
                'url' => $searchUrl
            ]);

            $response = Http::timeout(config('services.microsoft.get_customer_timeout', 60))
                           ->withHeaders([
                               'Authorization' => 'Bearer ' . $token,
                               'Accept' => 'application/json'
                           ])
                           ->get($searchUrl);

            if (!$response->successful()) {
                $logChannel->error('Error al listar clientes', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return [
                    'accepted' => false,
                    'message' => 'No se pudo verificar el estado de la invitación.',
                    'can_retry' => true,
                    'details' => ['error' => 'API_ERROR']
                ];
            }

            $customers = $response->json();

            $logChannel->info('Respuesta de API recibida', [
                'total_items' => isset($customers['items']) ? count($customers['items']) : 0,
                'has_items' => isset($customers['items'])
            ]);

            // Log todos los dominios encontrados
            if (isset($customers['items'])) {
                $domains = array_map(function($item) {
                    return $item['companyProfile']['domain'] ?? 'N/A';
                }, $customers['items']);

                $logChannel->info('Dominios encontrados en Partner Center', [
                    'dominios' => $domains,
                    'buscando' => $domain
                ]);
            }

            // Buscar cliente por dominio
            $customer = null;
            if (isset($customers['items'])) {
                foreach ($customers['items'] as $item) {
                    if (isset($item['companyProfile']['domain']) &&
                        strtolower($item['companyProfile']['domain']) === strtolower($domain)) {
                        $customer = $item;
                        break;
                    }
                }
            }

            if (!$customer) {
                $logChannel->warning('Cliente NO encontrado', [
                    'domain_buscado' => $domain,
                    'total_clientes_revisados' => isset($customers['items']) ? count($customers['items']) : 0
                ]);
                return [
                    'accepted' => false,
                    'message' => 'La invitación aún no ha sido aceptada. Por favor, inicia sesión en tu cuenta de Microsoft y acepta la invitación del partner.',
                    'can_retry' => true,
                    'details' => ['reason' => 'NOT_FOUND']
                ];
            }

            $logChannel->info('Cliente ENCONTRADO', [
                'customer_id' => $customer['id'] ?? 'N/A',
                'company_name' => $customer['companyProfile']['companyName'] ?? 'N/A',
                'domain' => $customer['companyProfile']['domain'] ?? 'N/A'
            ]);

            $logChannel->info('Cliente ENCONTRADO', [
                'customer_id' => $customer['id'] ?? 'N/A',
                'company_name' => $customer['companyProfile']['companyName'] ?? 'N/A',
                'domain' => $customer['companyProfile']['domain'] ?? 'N/A'
            ]);

            // Cliente encontrado - verificar estado de relación
            $microsoftId = $customer['id'];

            // Verificar acuerdos del cliente
            $agreementsUrl = $this->getPartnerCenterBaseUrl() . "/customers/{$microsoftId}/agreements";

            $logChannel->info('Verificando acuerdos del cliente', [
                'url' => $agreementsUrl
            ]);

            $agreementResponse = Http::timeout(60)
                                    ->withHeaders([
                                        'Authorization' => 'Bearer ' . $token,
                                        'Accept' => 'application/json'
                                    ])
                                    ->get($agreementsUrl);

            $hasAgreement = false;
            if ($agreementResponse->successful()) {
                $agreements = $agreementResponse->json();
                if (isset($agreements['items']) && !empty($agreements['items'])) {
                    $hasAgreement = true;
                }

                $logChannel->info('Respuesta de acuerdos', [
                    'successful' => true,
                    'has_items' => isset($agreements['items']),
                    'items_count' => isset($agreements['items']) ? count($agreements['items']) : 0,
                    'has_agreement' => $hasAgreement
                ]);
            } else {
                $logChannel->warning('Error al obtener acuerdos', [
                    'status' => $agreementResponse->status(),
                    'response' => $agreementResponse->body()
                ]);
            }

            $logChannel->info('=== RELACIÓN VERIFICADA EXITOSAMENTE ===', [
                'domain' => $domain,
                'microsoft_id' => $microsoftId,
                'has_agreement' => $hasAgreement
            ]);

            return [
                'accepted' => true,
                'message' => 'La invitación fue aceptada exitosamente.',
                'microsoft_id' => $microsoftId,
                'has_agreement' => $hasAgreement,
                'details' => [
                    'customer_id' => $customer['id'] ?? null,
                    'company_name' => $customer['companyProfile']['companyName'] ?? null,
                    'domain' => $customer['companyProfile']['domain'] ?? null,
                ]
            ];

        } catch (Exception $e) {
            $logChannel->error('=== ERROR EN VERIFICACIÓN ===', [
                'domain' => $domain,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'accepted' => false,
                'message' => 'Error al verificar el estado de la invitación. Inténtalo nuevamente en unos minutos.',
                'can_retry' => true,
                'details' => ['error' => $e->getMessage()]
            ];
        }
    }
}
