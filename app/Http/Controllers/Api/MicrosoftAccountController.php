<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMicrosoftAccountRequest;
use App\Http\Requests\UpdateMicrosoftAccountRequest;
use App\Http\Requests\LinkExistingMicrosoftAccountRequest;
use App\Http\Resources\MicrosoftAccountResource;
use App\Http\Resources\MicrosoftAccountCollection;
use App\Models\MicrosoftAccount;
use App\Services\MicrosoftPartnerCenterService;
use App\Services\MicrosoftAccountEmailService;
use App\Services\MicrosoftErrorNotificationService;
use App\Services\WhatsAppNotificationService;
use App\Services\PurchaseConfirmationEmailService;
use App\Services\MicrosoftPartnerInvitationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 *     name="Microsoft Accounts",
 *     description="API endpoints for Microsoft Account management"
 * )
 */
class MicrosoftAccountController extends Controller
{
    private MicrosoftPartnerCenterService $partnerCenterService;
    private MicrosoftAccountEmailService $emailService;
    private MicrosoftErrorNotificationService $errorNotificationService;
    private WhatsAppNotificationService $whatsAppService;
    private PurchaseConfirmationEmailService $purchaseEmailService;
    private MicrosoftPartnerInvitationService $invitationService;

    public function __construct(
        MicrosoftPartnerCenterService $partnerCenterService,
        MicrosoftAccountEmailService $emailService,
        MicrosoftErrorNotificationService $errorNotificationService,
        WhatsAppNotificationService $whatsAppService,
        PurchaseConfirmationEmailService $purchaseEmailService,
        MicrosoftPartnerInvitationService $invitationService
    ) {
        $this->partnerCenterService = $partnerCenterService;
        $this->emailService = $emailService;
        $this->errorNotificationService = $errorNotificationService;
        $this->whatsAppService = $whatsAppService;
        $this->purchaseEmailService = $purchaseEmailService;
        $this->invitationService = $invitationService;
    }

    /**
     * @OA\Get(
     *     path="/api/v1/microsoft-accounts",
     *     tags={"Microsoft Accounts"},
     *     summary="List Microsoft accounts",
     *     description="Get paginated list of Microsoft accounts for authenticated user",
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15, maximum=100)
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by status",
     *         required=false,
     *         @OA\Schema(type="string", enum={"active", "pending", "inactive"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/MicrosoftAccount")),
     *             @OA\Property(property="meta", type="object"),
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
            'status' => 'string|in:active,pending,inactive',
            'search' => 'string|max:255',
        ]);

        $userId = auth()->id(); // Usuario autenticado actual
        $perPage = $request->get('per_page', 15);

        $query = MicrosoftAccount::forUser($userId)
          ->orderBy('is_default', 'desc')
          ->orderBy('created_at', 'desc');

        // Filtros
        if ($request->filled('status')) {
            switch ($request->status) {
                case 'active':
                    $query->where('is_active', true)->where('is_pending', false);
                    break;
                case 'pending':
                    $query->where('is_pending', true);
                    break;
                case 'inactive':
                    $query->where('is_active', false)->where('is_pending', false);
                    break;
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('organization', 'like', "%{$search}%")
                  ->orWhere('domain', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        $accounts = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => new MicrosoftAccountCollection($accounts),
            'message' => 'Microsoft accounts retrieved successfully'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/microsoft-accounts",
     *     tags={"Microsoft Accounts"},
     *     summary="Create Microsoft account",
     *     description="Create a new Microsoft account with Partner Center integration",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/StoreMicrosoftAccountRequest")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Account created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/MicrosoftAccount"),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function store(StoreMicrosoftAccountRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $userId = auth()->id(); // Usuario autenticado actual
            $validated = $request->validated();
            $validated['user_id'] = $userId;

            // Si se marca como default, quitar default de otras cuentas
            if ($validated['is_default'] ?? false) {
                MicrosoftAccount::forUser($userId)->update(['is_default' => false]);
            }

            // Crear cuenta localmente primero
            $account = MicrosoftAccount::create($validated);

            // Registrar actividad de creación
            $this->logActivity('create', $account, 'Cuenta Microsoft creada');

            // Intentar crear en Partner Center o simular según configuración
            $microsoftIntegrationResult = [
                'success' => false,
                'error' => null,
                'details' => null
            ];

            // Verificar si está en modo fake (para pruebas)
            $fakeMode = env('MICROSOFT_FAKE_MODE', false);

            if ($fakeMode) {
                // Modo fake para pruebas - simular creación exitosa
                $fakeMicrosoftId = 'fake-' . uniqid() . '-' . time();

                $account->update([
                    'microsoft_id' => $fakeMicrosoftId,
                    'is_pending' => false,
                    'is_active' => true,
                ]);

                // Actualizar progreso del usuario
                $this->updateUserProgress($userId);

                $this->logActivity('activate', $account, 'Cuenta Microsoft activada (MODO FAKE)');

                // Simular envío de credenciales en modo fake
                $fakePassword = 'FakePass123!';
                $this->emailService->sendCredentials(
                    $validated,
                    $fakePassword
                );

                // Enviar notificación por WhatsApp en modo fake
                $this->whatsAppService->sendMicrosoftAccountSuccessNotification(
                    $validated,
                    $fakePassword
                );

                $microsoftIntegrationResult = [
                    'success' => true,
                    'error' => null,
                    'details' => 'Cuenta creada exitosamente (MODO FAKE - SIN INTERACCIÓN CON MICROSOFT)'
                ];

            } else {
                // Modo normal - interactuar con Microsoft Partner Center
                try {
                    // Preparar datos para Partner Center con domain_concatenated
                    $customerData = array_merge($validated, [
                        'domain_concatenated' => $account->domain_concatenated,
                        'culture' => $account->culture ?? 'es-MX'
                    ]);

                    $customerResult = $this->partnerCenterService->createCustomer($customerData);

                    $account->update([
                        'microsoft_id' => $customerResult['microsoft_id'],
                        'is_pending' => false,
                        'is_active' => true,
                    ]);

                    // Aceptar acuerdo de Microsoft
                    $this->partnerCenterService->acceptCustomerAgreement(
                        $customerResult['microsoft_id'],
                        $customerData
                    );

                    // Enviar credenciales por email
                    if (!empty($customerResult['password'])) {
                        $this->emailService->sendCredentials(
                            $customerData,
                            $customerResult['password']
                        );

                        // Enviar notificación por WhatsApp
                        $this->whatsAppService->sendMicrosoftAccountSuccessNotification(
                            $customerData,
                            $customerResult['password']
                        );
                    }

                    // Actualizar progreso del usuario
                    $this->updateUserProgress($userId);

                    $this->logActivity('activate', $account, 'Cuenta Microsoft activada en Partner Center');

                    $microsoftIntegrationResult = [
                        'success' => true,
                        'error' => null,
                        'details' => 'Cuenta creada exitosamente en Microsoft Partner Center'
                    ];

                } catch (\Exception $e) {
                    Log::error('Microsoft Account: Partner Center integration failed', [
                        'account_id' => $account->id,
                        'error' => $e->getMessage()
                    ]);

                    // La cuenta se mantiene como pendiente para retry posterior
                    $account->update(['is_pending' => true, 'is_active' => false]);

                    // Determinar el tipo de error para dar un mensaje más claro
                    $errorMessage = $e->getMessage();
                    if (strpos($errorMessage, 'NoActiveResellerProgram') !== false) {
                        $microsoftIntegrationResult = [
                            'success' => false,
                            'error' => 'RESELLER_PROGRAM_INACTIVE',
                            'details' => 'La cuenta de Microsoft Partner Center no tiene un programa de revendedor activo. La cuenta se guardó localmente pero no se pudo crear en Microsoft.'
                        ];
                    } elseif (strpos($errorMessage, 'Domain') !== false) {
                        $microsoftIntegrationResult = [
                            'success' => false,
                            'error' => 'DOMAIN_ERROR',
                            'details' => 'Error relacionado con el dominio. La cuenta se guardó localmente pero no se pudo crear en Microsoft.'
                        ];
                    } else {
                        $microsoftIntegrationResult = [
                            'success' => false,
                            'error' => 'UNKNOWN_ERROR',
                            'details' => 'Error desconocido al crear la cuenta en Microsoft: ' . $errorMessage
                        ];
                    }

                    // Enviar notificaciones de error (email y WhatsApp)
                    try {
                        // Preparar detalles del error para las notificaciones
                        $errorDetails = [
                            'details' => 'Error al crear cuenta en Microsoft Partner Center: ' . $errorMessage,
                            'error_code' => $microsoftIntegrationResult['error'],
                            'account_id' => $account->id,
                            'user_id' => $userId,
                            'timestamp' => now()->format('Y-m-d H:i:s')
                        ];

                        // Extraer detalles específicos de Microsoft si están disponibles
                        $microsoftErrorDetails = [];
                        if (method_exists($e, 'getResponse') && $e->getResponse()) {
                            $response = $e->getResponse();
                            $responseBody = $response->getBody()->getContents();
                            $microsoftErrorDetails = [
                                'http_status' => $response->getStatusCode(),
                                'raw_response' => $responseBody
                            ];

                            // Intentar extraer error_code y description del JSON de respuesta
                            $decodedResponse = json_decode($responseBody, true);
                            if ($decodedResponse && isset($decodedResponse['error'])) {
                                if (isset($decodedResponse['error']['code'])) {
                                    $microsoftErrorDetails['error_code'] = $decodedResponse['error']['code'];
                                }
                                if (isset($decodedResponse['error']['message'])) {
                                    $microsoftErrorDetails['description'] = $decodedResponse['error']['message'];
                                }
                            }
                        }

                        // Usar el método específico para errores de creación de cuenta
                        $this->errorNotificationService->sendMicrosoftAccountCreationErrorNotification(
                            $account,
                            "Error al crear cuenta Microsoft: " . $errorMessage,
                            $errorDetails,
                            $microsoftErrorDetails
                        );

                        Log::info("Error notifications sent for Microsoft account creation failure", [
                            'account_id' => $account->id,
                            'error_type' => $microsoftIntegrationResult['error']
                        ]);

                    } catch (\Exception $notificationException) {
                        Log::error("Failed to send error notifications for Microsoft account creation", [
                            'account_id' => $account->id,
                            'notification_error' => $notificationException->getMessage()
                        ]);
                    }
                }
            }

            DB::commit();

            // Preparar respuesta detallada
            $responseData = [
                'success' => true,
                'data' => new MicrosoftAccountResource($account->fresh()),
                'microsoft_integration' => $microsoftIntegrationResult
            ];

            if ($microsoftIntegrationResult['success']) {
                $responseData['message'] = 'Cuenta Microsoft creada exitosamente en la base de datos y en Microsoft Partner Center';
                $responseData['status'] = 'COMPLETE_SUCCESS';
            } else {
                $responseData['message'] = 'Cuenta Microsoft guardada en la base de datos, pero falló la integración con Microsoft Partner Center';
                $responseData['status'] = 'PARTIAL_SUCCESS';
            }

            return response()->json($responseData, 201);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Microsoft Account: Creation failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'data' => $request->validated()
            ]);

            // Enviar notificaciones de error si existe una cuenta parcialmente creada
            try {
                if (isset($account) && $account) {
                    $errorDetails = [
                        'details' => 'Error durante la creación completa de la cuenta Microsoft: ' . $e->getMessage(),
                        'error_code' => 'ACCOUNT_CREATION_FAILURE',
                        'account_id' => $account->id ?? 'N/A',
                        'user_id' => $userId,
                        'timestamp' => now()->format('Y-m-d H:i:s')
                    ];

                    $microsoftErrorDetails = [
                        'description' => 'Error general durante el proceso de creación de cuenta',
                        'error_code' => 'INTERNAL_ERROR'
                    ];

                    $this->errorNotificationService->sendMicrosoftAccountCreationErrorNotification(
                        $account,
                        "Error crítico en creación de cuenta Microsoft: " . $e->getMessage(),
                        $errorDetails,
                        $microsoftErrorDetails
                    );

                    Log::info("Critical error notifications sent for Microsoft account creation", [
                        'account_id' => $account->id ?? 'N/A',
                        'user_id' => $userId
                    ]);
                }
            } catch (\Exception $notificationException) {
                Log::error("Failed to send critical error notifications", [
                    'original_error' => $e->getMessage(),
                    'notification_error' => $notificationException->getMessage()
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Error al crear la cuenta: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/microsoft-accounts/{id}",
     *     tags={"Microsoft Accounts"},
     *     summary="Get Microsoft account",
     *     description="Get specific Microsoft account by ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/MicrosoftAccount"),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Account not found")
     * )
     */
    public function show($id): JsonResponse
    {
        $userId = auth()->id(); // Usuario autenticado actual

        $account = MicrosoftAccount::forUser($userId)->find((int)$id);

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Microsoft account not found or access denied'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new MicrosoftAccountResource($account),
            'message' => 'Microsoft account retrieved successfully'
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/microsoft-accounts/{id}",
     *     tags={"Microsoft Accounts"},
     *     summary="Update Microsoft account",
     *     description="Update specific Microsoft account",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/UpdateMicrosoftAccountRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Account updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/MicrosoftAccount"),
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
     */
    public function update(UpdateMicrosoftAccountRequest $request, $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $userId = auth()->id(); // Usuario autenticado actual

            $account = MicrosoftAccount::forUser($userId)->find((int)$id);

            if (!$account) {
                return response()->json([
                    'success' => false,
                    'message' => 'Microsoft account not found or access denied'
                ], 404);
            }

            $validated = $request->validated();

            // Si se marca como default, quitar default de otras cuentas
            if (($validated['is_default'] ?? false) && !$account->is_default) {
                $account->markAsDefault();
                unset($validated['is_default']); // Ya se manejó en markAsDefault
            }

            $account->update($validated);

            $this->logActivity('update', $account, 'Cuenta Microsoft actualizada');

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => new MicrosoftAccountResource($account->fresh()),
                'message' => 'Cuenta actualizada correctamente'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Microsoft Account: Update failed', [
                'account_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la cuenta: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/microsoft-accounts/{id}",
     *     tags={"Microsoft Accounts"},
     *     summary="Delete Microsoft account",
     *     description="Soft delete Microsoft account",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Account deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
     */
    public function destroy($id): JsonResponse
    {
        $userId = auth()->id(); // Usuario autenticado actual

        $account = MicrosoftAccount::forUser($userId)->find((int)$id);

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Microsoft account not found or access denied'
            ], 404);
        }

        try {
            $this->logActivity('delete', $account, 'Cuenta Microsoft eliminada');

            $account->delete();

            return response()->json([
                'success' => true,
                'message' => 'Cuenta eliminada correctamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Microsoft Account: Delete failed', [
                'account_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la cuenta: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/microsoft-accounts/check-domain",
     *     tags={"Microsoft Accounts"},
     *     summary="Check domain availability",
     *     description="Check if domain is available globally (not user-specific)",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="domain", type="string", example="mycompany")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Domain check result",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="available", type="boolean"),
     *             @OA\Property(property="domain", type="string"),
     *             @OA\Property(property="domain_concatenated", type="string"),
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
     */
    public function checkDomain(Request $request): JsonResponse
    {
        $request->validate([
            'domain' => 'required|string|max:150'
        ]);

        $account = new MicrosoftAccount();
        $cleanDomain = $account->formatDomain($request->domain);
        $domainConcatenated = $account->generateDomainConcatenated($request->domain);
        $available = $account->isDomainAvailable($cleanDomain);

        return response()->json([
            'success' => true,
            'available' => $available,
            'domain' => $cleanDomain,
            'domain_concatenated' => $domainConcatenated,
            'message' => $available
                ? 'Dominio disponible'
                : 'El dominio ya está registrado'
        ]);
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/microsoft-accounts/{id}/set-default",
     *     tags={"Microsoft Accounts"},
     *     summary="Set account as default",
     *     description="Mark specific account as default for the user",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Account set as default successfully"
     *     )
     * )
     */
    public function setDefault($id): JsonResponse
    {
        $userId = auth()->id(); // Usuario autenticado actual

        $account = MicrosoftAccount::forUser($userId)->find((int)$id);

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Microsoft account not found or access denied'
            ], 404);
        }

        try {
            $account->markAsDefault();

            $this->logActivity('set_default', $account, 'Cuenta marcada como predeterminada');

            return response()->json([
                'success' => true,
                'data' => new MicrosoftAccountResource($account->fresh()),
                'message' => 'Cuenta marcada como predeterminada'
            ]);

        } catch (\Exception $e) {
            Log::error('Microsoft Account: Set default failed', [
                'account_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al marcar como predeterminada: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/microsoft-accounts/{id}/verify",
     *     tags={"Microsoft Accounts"},
     *     summary="Verify account in Microsoft",
     *     description="Verify account status in Microsoft Partner Center",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Verification result"
     *     )
     * )
     */
    public function verify($id): JsonResponse
    {
        $userId = auth()->id(); // Usuario autenticado actual

        $account = MicrosoftAccount::forUser($userId)->find((int)$id);

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Microsoft account not found or access denied'
            ], 404);
        }

        if (!$account->microsoft_id) {
            return response()->json([
                'success' => false,
                'message' => 'Cuenta no tiene Microsoft ID',
                'verified' => false
            ]);
        }

        try {
            $syncResult = $this->partnerCenterService->syncAccount($account->microsoft_id);

            // Actualizar estado si es necesario
            if (!$account->is_active) {
                $account->activate();
                $this->logActivity('verify', $account, 'Cuenta verificada en Microsoft');
            }

            return response()->json([
                'success' => true,
                'verified' => true,
                'data' => new MicrosoftAccountResource($account->fresh()),
                'sync_result' => $syncResult,
                'message' => 'Cuenta verificada correctamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Microsoft Account: Verification failed', [
                'account_id' => $id,
                'microsoft_id' => $account->microsoft_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'verified' => false,
                'message' => 'Error al verificar la cuenta: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/microsoft-accounts/progress",
     *     tags={"Microsoft Accounts"},
     *     summary="Get user progress",
     *     description="Get current user's account setup progress",
     *     @OA\Response(
     *         response=200,
     *         description="Progress information"
     *     )
     * )
     */
    public function progress(): JsonResponse
    {
        $userId = auth()->id(); // Usuario autenticado actual

        $totalAccounts = MicrosoftAccount::forUser($userId)->count();
        $activeAccounts = MicrosoftAccount::forUser($userId)->active()->count();
        $pendingAccounts = MicrosoftAccount::forUser($userId)->pending()->count();
        $hasDefaultAccount = MicrosoftAccount::forUser($userId)->default()->exists();

        // Calcular progreso basado en criterios del sistema viejo
        $progress = 0;
        $criteria = [];

        if ($totalAccounts > 0) {
            $progress += 30;
            $criteria['has_accounts'] = true;
        }

        if ($activeAccounts > 0) {
            $progress += 40;
            $criteria['has_active_accounts'] = true;
        }

        if ($hasDefaultAccount) {
            $progress += 20;
            $criteria['has_default_account'] = true;
        }

        // For now, skip user progress check
        // if ($user && isset($user->user_progress_accountonmicrosoft) && $user->user_progress_accountonmicrosoft) {
        //     $progress += 10;
        //     $criteria['microsoft_progress_marked'] = true;
        // }

        return response()->json([
            'success' => true,
            'data' => [
                'progress_percentage' => min($progress, 100),
                'total_accounts' => $totalAccounts,
                'active_accounts' => $activeAccounts,
                'pending_accounts' => $pendingAccounts,
                'has_default_account' => $hasDefaultAccount,
                'criteria' => $criteria,
                'is_complete' => $progress >= 100
            ],
            'message' => 'Progress retrieved successfully'
        ]);
    }

    /**
     * Registrar actividad en logs
     */
    private function logActivity(string $activity, MicrosoftAccount $account, string $description): void
    {
        try {
            // Mapear actividades a IDs como en el sistema viejo
            $activityIds = [
                'create' => 2,
                'update' => 3,
                'delete' => 4,
                'activate' => 2,
                'verify' => 2,
                'set_default' => 3,
            ];

            DB::table('users_logs')->insert([
                'log_idactivity' => $activityIds[$activity] ?? 2,
                'log_iduser' => auth()->id(),
                'log_idconfig' => $account->configuration_id,
                'log_idstore' => $account->store_id,
                'log_mod' => 'microsoft_accounts',
                'log_title' => $account->organization . ' - ' . $account->domain_concatenated,
                'log_date' => now()->format('Y-m-d'),
                'log_time' => now()->format('H:i:s'),
                'log_id' => (string)$account->id,
                'created_at' => now(),
            ]);

        } catch (\Exception $e) {
            Log::error('Microsoft Account: Failed to log activity', [
                'activity' => $activity,
                'account_id' => $account->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Actualizar progreso del usuario
     */
    private function updateUserProgress(int $userId): void
    {
        try {
            DB::table('users')
              ->where('id', $userId)
              ->update(['user_progress_accountonmicrosoft' => 1]);

        } catch (\Exception $e) {
            Log::error('Microsoft Account: Failed to update user progress', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/microsoft-accounts/{id}/retry",
     *     tags={"Microsoft Accounts"},
     *     summary="Retry Microsoft account creation",
     *     description="Retry creating a Microsoft account in Partner Center for a pending account",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Microsoft account ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Retry operation completed",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", enum={"COMPLETE_SUCCESS", "PARTIAL_SUCCESS", "COMPLETE_FAILURE"}),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", ref="#/components/schemas/MicrosoftAccount"),
     *             @OA\Property(property="microsoft_integration", type="object",
     *                 @OA\Property(property="success", type="boolean"),
     *                 @OA\Property(property="details", type="string"),
     *                 @OA\Property(property="error_code", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Microsoft account not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Account is not in pending status"
     *     )
     * )
     */
    public function retry(int $id): JsonResponse
    {
        $user = auth()->user();

        $microsoftAccount = MicrosoftAccount::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$microsoftAccount) {
            return response()->json([
                'status' => 'COMPLETE_FAILURE',
                'message' => 'Cuenta Microsoft no encontrada'
            ], 404);
        }

        // Verificar que la cuenta esté en estado pendiente
        if (!$microsoftAccount->is_pending) {
            return response()->json([
                'status' => 'COMPLETE_FAILURE',
                'message' => 'Esta cuenta no está en estado pendiente. Solo las cuentas pendientes pueden ser reintentadas.',
                'microsoft_integration' => [
                    'success' => false,
                    'details' => 'La cuenta debe estar en estado pendiente para poder reintentar la creación en Microsoft Partner Center.',
                    'error_code' => 'NOT_PENDING'
                ]
            ], 422);
        }

        Log::info('Microsoft Account: Retrying Partner Center creation', [
            'account_id' => $microsoftAccount->id,
            'user_id' => $user->id,
            'organization' => $microsoftAccount->organization,
            'domain' => $microsoftAccount->domain
        ]);

        // Intentar crear en Microsoft Partner Center
        $microsoftIntegrationResult = [
            'success' => false,
            'details' => '',
            'error_code' => null
        ];

        try {
            // Preparar datos para Partner Center
            $partnerCenterData = [
                'domain_concatenated' => $microsoftAccount->domain_concatenated ?? ($microsoftAccount->domain . '.onmicrosoft.com'),
                'email' => $microsoftAccount->email,
                'organization' => $microsoftAccount->organization,
                'first_name' => $microsoftAccount->first_name,
                'last_name' => $microsoftAccount->last_name,
                'phone' => $microsoftAccount->phone ?: '+52 555 000 0000',
                'address' => $microsoftAccount->address ?: 'Sin dirección especificada',
                'city' => $microsoftAccount->city ?: 'Sin ciudad',
                'state_code' => $microsoftAccount->state_code ?: 'MX',
                'postal_code' => $microsoftAccount->postal_code ?: '00000',
                'country_code' => $microsoftAccount->country_code ?: 'MX',
                'language_code' => $microsoftAccount->language_code ?: 'es',
                'culture' => $microsoftAccount->culture ?: 'es-MX'
            ];

            Log::info('Microsoft Account: Retry - Calling Partner Center service', [
                'account_id' => $microsoftAccount->id,
                'partner_center_data' => $partnerCenterData
            ]);

            $result = $this->partnerCenterService->createCustomer($partnerCenterData);

            if ($result['success']) {
                // Éxito completo - actualizar cuenta
                $microsoftAccount->update([
                    'is_pending' => false,
                    'is_active' => true,
                    'microsoft_customer_id' => $result['data']['id'] ?? null,
                    'tenant_id' => $result['data']['companyProfile']['tenantId'] ?? null,
                    'updated_at' => now()
                ]);

                $microsoftIntegrationResult = [
                    'success' => true,
                    'details' => 'Cuenta Microsoft creada exitosamente en Partner Center.',
                    'error_code' => null
                ];

                Log::info('Microsoft Account: Retry successful', [
                    'account_id' => $microsoftAccount->id,
                    'microsoft_customer_id' => $result['data']['id'] ?? null
                ]);

                return response()->json([
                    'status' => 'COMPLETE_SUCCESS',
                    'message' => 'Cuenta Microsoft creada exitosamente',
                    'data' => new MicrosoftAccountResource($microsoftAccount->fresh()),
                    'microsoft_integration' => $microsoftIntegrationResult
                ]);

            } else {
                // Error en Partner Center - mantener como pendiente pero registrar el error
                $errorCode = 'UNKNOWN_ERROR';
                $errorDetails = $result['error'] ?? 'Error desconocido en Partner Center';
                $originalError = $errorDetails; // Mantener el error original de Microsoft

                if (strpos($errorDetails, 'NoActiveResellerProgram') !== false ||
                    strpos($errorDetails, '600103') !== false) {
                    $errorCode = 'RESELLER_PROGRAM_INACTIVE';
                    $errorDetails = 'El programa de revendedor de Microsoft no está activo. Contacte al administrador.';
                } elseif (strpos($errorDetails, '600092') !== false ||
                          strpos($errorDetails, 'Enter a valid name') !== false ||
                          strpos($errorDetails, 'Test') !== false) {
                    $errorCode = 'INVALID_NAME';
                    $errorDetails = 'Microsoft no permite el uso de ciertos nombres como "Test". Por favor, use un nombre real de empresa.';
                } elseif (strpos(strtolower($errorDetails), 'domain') !== false) {
                    $errorCode = 'DOMAIN_ERROR';
                    $errorDetails = 'Error relacionado con el dominio. Verifique que el dominio no esté en uso.';
                }

                $microsoftIntegrationResult = [
                    'success' => false,
                    'details' => $errorDetails,
                    'error_code' => $errorCode,
                    'microsoft_original_error' => $originalError // Incluir el error original de Microsoft
                ];

                Log::warning('Microsoft Account: Retry failed - Partner Center error', [
                    'account_id' => $microsoftAccount->id,
                    'error' => $result['error'] ?? 'Unknown error',
                    'error_code' => $errorCode
                ]);

                // Enviar notificaciones de error para el retry fallido
                try {
                    $errorDetailsForNotification = [
                        'details' => 'Error en reintento de creación de cuenta Microsoft: ' . $errorDetails,
                        'error_code' => $errorCode,
                        'account_id' => $microsoftAccount->id,
                        'timestamp' => now()->format('Y-m-d H:i:s'),
                        'retry_attempt' => true
                    ];

                    $microsoftErrorDetails = [
                        'error_code' => $errorCode,
                        'description' => $errorDetails,
                        'original_error' => $originalError
                    ];

                    $this->errorNotificationService->sendMicrosoftAccountCreationErrorNotification(
                        $microsoftAccount,
                        "Error en reintento de cuenta Microsoft: " . $errorDetails,
                        $errorDetailsForNotification,
                        $microsoftErrorDetails
                    );

                    Log::info("Retry error notifications sent for Microsoft account", [
                        'account_id' => $microsoftAccount->id,
                        'error_code' => $errorCode
                    ]);

                } catch (\Exception $notificationException) {
                    Log::error("Failed to send retry error notifications", [
                        'account_id' => $microsoftAccount->id,
                        'notification_error' => $notificationException->getMessage()
                    ]);
                }

                return response()->json([
                    'status' => 'PARTIAL_SUCCESS',
                    'message' => 'Cuenta guardada localmente, pero falló la integración con Microsoft',
                    'data' => new MicrosoftAccountResource($microsoftAccount->fresh()),
                    'microsoft_integration' => $microsoftIntegrationResult
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Microsoft Account: Retry exception', [
                'account_id' => $microsoftAccount->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Intentar extraer el mensaje específico de Microsoft si está disponible
            $errorMessage = $e->getMessage();
            $errorCode = 'INTERNAL_ERROR';
            $errorDetails = 'Error interno del servidor durante el reintento. Inténtelo más tarde.';

            if (strpos($errorMessage, 'Partner Center API error') !== false) {
                $errorCode = 'MICROSOFT_API_ERROR';
                $errorDetails = 'Error del API de Microsoft Partner Center. Verifique los datos e inténtelo más tarde.';

                // Si contiene el error específico de Microsoft, extraerlo
                if (strpos($errorMessage, '600092') !== false || strpos($errorMessage, 'Enter a valid name') !== false) {
                    $errorCode = 'INVALID_NAME';
                    $errorDetails = 'Microsoft no permite el uso de ciertos nombres como "Test". Por favor, use un nombre real de empresa.';
                }
            }

            $microsoftIntegrationResult = [
                'success' => false,
                'details' => $errorDetails,
                'error_code' => $errorCode,
                'microsoft_original_error' => $errorMessage
            ];

            // Enviar notificaciones de error para la excepción en retry
            try {
                $errorDetailsForNotification = [
                    'details' => 'Excepción durante reintento de cuenta Microsoft: ' . $errorDetails,
                    'error_code' => $errorCode,
                    'account_id' => $microsoftAccount->id,
                    'timestamp' => now()->format('Y-m-d H:i:s'),
                    'retry_exception' => true
                ];

                $microsoftErrorDetails = [
                    'error_code' => $errorCode,
                    'description' => $errorDetails,
                    'original_error' => $errorMessage
                ];

                $this->errorNotificationService->sendMicrosoftAccountCreationErrorNotification(
                    $microsoftAccount,
                    "Excepción en reintento de cuenta Microsoft: " . $errorMessage,
                    $errorDetailsForNotification,
                    $microsoftErrorDetails
                );

                Log::info("Retry exception notifications sent for Microsoft account", [
                    'account_id' => $microsoftAccount->id,
                    'error_code' => $errorCode
                ]);

            } catch (\Exception $notificationException) {
                Log::error("Failed to send retry exception notifications", [
                    'account_id' => $microsoftAccount->id,
                    'notification_error' => $notificationException->getMessage()
                ]);
            }

            return response()->json([
                'status' => 'PARTIAL_SUCCESS',
                'message' => 'Cuenta existe localmente, pero falló el reintento de integración con Microsoft',
                'data' => new MicrosoftAccountResource($microsoftAccount->fresh()),
                'microsoft_integration' => $microsoftIntegrationResult
            ]);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/microsoft-accounts/link-existing",
     *     tags={"Microsoft Accounts"},
     *     summary="Link existing Microsoft account",
     *     description="Link an existing Microsoft account with Global Admin credentials. This creates a pending account that requires manual verification.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/LinkExistingMicrosoftAccountRequest")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Account linked successfully, pending verification",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/MicrosoftAccount"),
     *             @OA\Property(property="invitation_data", type="object"),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function linkExisting(LinkExistingMicrosoftAccountRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $userId = auth()->id();
            $validated = $request->validated();
            
            // Crear una cuenta con datos mínimos en estado pending
            $account = new MicrosoftAccount();
            $cleanDomain = $account->formatDomain($validated['domain']);
            $domainConcatenated = $account->generateDomainConcatenated($validated['domain']);
            
            // Generar ID temporal para Microsoft
            $temporaryMicrosoftId = 'pending-link-' . uniqid() . '-' . time();
            
            $accountData = [
                'user_id' => $userId,
                'microsoft_id' => $temporaryMicrosoftId,
                'domain' => $cleanDomain,
                'domain_concatenated' => $domainConcatenated,
                'global_admin_email' => $validated['global_admin_email'],
                'email' => $validated['global_admin_email'], // Usar el mismo email temporalmente
                'first_name' => 'Pending', // Temporal
                'last_name' => 'Verification', // Temporal
                'organization' => $cleanDomain, // Usar dominio como organización temporal
                'account_type' => 'linked',
                'is_pending' => true,
                'is_active' => false,
                'is_default' => false,
                'country_code' => 'MX',
                'language_code' => 'es-MX',
                'culture' => 'es-MX',
            ];

            $account = MicrosoftAccount::create($accountData);

            // Generar datos de invitación
            $invitationData = $this->invitationService->generateInvitationData(
                $domainConcatenated,
                $validated['global_admin_email']
            );

            // Registrar actividad
            $this->logActivity('link_existing', $account, 'Cuenta Microsoft existente vinculada (pendiente de verificación)');

            // Log de generación de invitación
            $this->invitationService->logInvitationGenerated(
                $account->id,
                $domainConcatenated,
                $validated['global_admin_email']
            );

            // Enviar email con instrucciones
            try {
                \Mail::send('emails.microsoft-link-existing-instructions', [
                    'domain' => $domainConcatenated,
                    'global_admin_email' => $validated['global_admin_email'],
                    'urls' => $invitationData['urls'],
                    'partner' => $invitationData['partner'],
                ], function ($message) use ($validated, $invitationData) {
                    $message->to($validated['global_admin_email'])
                            ->subject('Instrucciones para vincular tu cuenta Microsoft - ' . $invitationData['partner']['partner_name']);
                });

                Log::info('Link existing account: Instructions email sent', [
                    'account_id' => $account->id,
                    'email' => $validated['global_admin_email']
                ]);

            } catch (\Exception $mailException) {
                Log::error('Link existing account: Failed to send instructions email', [
                    'account_id' => $account->id,
                    'error' => $mailException->getMessage()
                ]);
                // No falla la operación si el email falla
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => new MicrosoftAccountResource($account->fresh()),
                'invitation_data' => $invitationData,
                'message' => 'Cuenta vinculada correctamente. Se han enviado las instrucciones por email.',
                'next_steps' => [
                    'Se ha registrado la cuenta como pendiente',
                    'Revisa tu correo para seguir las instrucciones',
                    'Debes aceptar la invitación desde el portal de Microsoft',
                    'Una vez aceptada, la cuenta se activará automáticamente'
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Microsoft Account: Link existing failed', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'domain' => $validated['domain'] ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al vincular la cuenta: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/microsoft-accounts/{id}/verify-link",
     *     tags={"Microsoft Accounts"},
     *     summary="Verify and activate linked account",
     *     description="Verify that a linked account has accepted the partner invitation and activate it",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Account verified and activated"
     *     ),
     *     @OA\Response(response=404, description="Account not found"),
     *     @OA\Response(response=422, description="Account not ready for verification")
     * )
     */
    public function verifyLink($id): JsonResponse
    {
        try {
            $userId = auth()->id();
            $account = MicrosoftAccount::forUser($userId)->find((int)$id);

            if (!$account) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cuenta no encontrada'
                ], 404);
            }

            if ($account->account_type !== 'linked') {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta cuenta no es una cuenta vinculada'
                ], 422);
            }

            if (!$account->is_pending) {
                return response()->json([
                    'success' => true,
                    'message' => 'La cuenta ya está verificada y activa',
                    'data' => new MicrosoftAccountResource($account)
                ]);
            }

            // TODO: Aquí se podría implementar verificación real con Microsoft Partner Center API
            // para comprobar si se aceptó la invitación
            // Por ahora, lo activamos manualmente cuando el usuario lo solicite

            $account->update([
                'is_pending' => false,
                'is_active' => true,
            ]);

            // Actualizar progreso del usuario
            $this->updateUserProgress($userId);

            $this->logActivity('verify_link', $account, 'Cuenta vinculada verificada y activada');

            return response()->json([
                'success' => true,
                'message' => 'Cuenta verificada y activada correctamente',
                'data' => new MicrosoftAccountResource($account->fresh())
            ]);

        } catch (\Exception $e) {
            Log::error('Microsoft Account: Verify link failed', [
                'account_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al verificar la cuenta: ' . $e->getMessage()
            ], 500);
        }
    }
}
