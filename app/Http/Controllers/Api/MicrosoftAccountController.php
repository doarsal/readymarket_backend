<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMicrosoftAccountRequest;
use App\Http\Requests\UpdateMicrosoftAccountRequest;
use App\Http\Resources\MicrosoftAccountResource;
use App\Http\Resources\MicrosoftAccountCollection;
use App\Models\MicrosoftAccount;
use App\Services\MicrosoftPartnerCenterService;
use App\Services\MicrosoftAccountEmailService;
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

    public function __construct(
        MicrosoftPartnerCenterService $partnerCenterService,
        MicrosoftAccountEmailService $emailService
    ) {
        $this->partnerCenterService = $partnerCenterService;
        $this->emailService = $emailService;
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

            // Intentar crear en Partner Center
            $microsoftIntegrationResult = [
                'success' => false,
                'error' => null,
                'details' => null
            ];

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
     *     description="Check if domain is available for current user",
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

        $userId = auth()->id();
        $account = new MicrosoftAccount();
        $cleanDomain = $account->formatDomain($request->domain);
        $domainConcatenated = $account->generateDomainConcatenated($request->domain);
        $available = $account->isDomainAvailable($cleanDomain, $userId);

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

            return response()->json([
                'status' => 'PARTIAL_SUCCESS',
                'message' => 'Cuenta existe localmente, pero falló el reintento de integración con Microsoft',
                'data' => new MicrosoftAccountResource($microsoftAccount->fresh()),
                'microsoft_integration' => $microsoftIntegrationResult
            ]);
        }
    }
}
