<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="Users",
 *     description="API Endpoints for User Management"
 * )
 */
class UserController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/users",
     *     tags={"Users"},
     *     summary="Get all users",
     *     description="Returns list of users with their roles",
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search in name or email",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="role",
     *         in="query",
     *         description="Filter by role slug",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="active",
     *         in="query",
     *         description="Filter by active status",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of users per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15, maximum=100)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Users retrieved successfully"
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::with(['roles.permissions']);

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        // Filter by role
        if ($request->filled('role')) {
            $query->whereHas('roles', function($q) use ($request) {
                $q->where('slug', $request->role);
            });
        }

        // Filter by active status
        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        // Pagination
        $perPage = min($request->get('per_page', 15), 100);
        $users = $query->orderBy('name')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $users->items(),
            'pagination' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'from' => $users->firstItem(),
                'to' => $users->lastItem()
            ]
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/users",
     *     tags={"Users"},
     *     summary="Create new user",
     *     description="Creates a new user",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "email", "password"},
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", example="john@example.com"),
     *             @OA\Property(property="password", type="string", example="password123"),
     *             @OA\Property(property="first_name", type="string", example="John"),
     *             @OA\Property(property="last_name", type="string", example="Doe"),
     *             @OA\Property(property="phone", type="string", example="+1234567890"),
     *             @OA\Property(property="role_ids", type="array", @OA\Items(type="integer"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="User created successfully"
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'avatar' => 'nullable|string|max:255',
            'timezone' => 'nullable|string|max:50',
            'language' => 'nullable|string|max:10',
            'is_active' => 'boolean',
            'role_ids' => 'array',
            'role_ids.*' => 'exists:roles,id'
        ]);

        // Hash password
        $validated['password'] = Hash::make($validated['password']);

        // Extract role_ids before creating user
        $roleIds = $validated['role_ids'] ?? [];
        unset($validated['role_ids']);

        $user = User::create($validated);

        // Assign roles if provided
        if (!empty($roleIds)) {
            $user->roles()->attach($roleIds);
        }

        $user->load(['roles.permissions']);

        return response()->json([
            'success' => true,
            'message' => 'User created successfully',
            'data' => $user
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/users/{id}",
     *     tags={"Users"},
     *     summary="Get user by ID",
     *     description="Returns a single user with roles and permissions",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="User ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User retrieved successfully"
     *     )
     * )
     */
    public function show(User $user): JsonResponse
    {
        $user->load(['roles.permissions']);

        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/users/{id}",
     *     tags={"Users"},
     *     summary="Update user",
     *     description="Updates an existing user",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="User ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User updated successfully"
     *     )
     * )
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'string|max:255',
            'email' => ['email', Rule::unique('users')->ignore($user->id)],
            'password' => 'string|min:8',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'avatar' => 'nullable|string|max:255',
            'timezone' => 'nullable|string|max:50',
            'language' => 'nullable|string|max:10',
            'is_active' => 'boolean',
            'role_ids' => 'array',
            'role_ids.*' => 'exists:roles,id'
        ]);

        // Hash password if provided
        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        // Extract role_ids before updating user
        $roleIds = $validated['role_ids'] ?? null;
        unset($validated['role_ids']);

        $user->update($validated);

        // Update roles if provided
        if ($roleIds !== null) {
            $user->roles()->sync($roleIds);
        }

        $user->load(['roles.permissions']);

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'data' => $user
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/users/{id}",
     *     tags={"Users"},
     *     summary="Delete user",
     *     description="Deletes a user",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="User ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User deleted successfully"
     *     )
     * )
     */
    /**
     * @OA\Delete(
     *     path="/api/v1/users/{id}",
     *     tags={"Users"},
     *     summary="Soft delete a user",
     *     description="Soft deletes a user (can be restored later)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="User ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User soft deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="User deleted successfully (can be restored)")
     *         )
     *     ),
     *     @OA\Response(response=404, description="User not found")
     * )
     */
    public function destroy(User $user): JsonResponse
    {
        $user->delete(); // This is now a soft delete

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully (can be restored)'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/users/{id}/permissions",
     *     tags={"Users"},
     *     summary="Get user permissions",
     *     description="Returns all permissions for a user through their roles",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="User ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User permissions retrieved successfully"
     *     )
     * )
     */
    public function permissions(User $user): JsonResponse
    {
        $permissions = $user->permissions();

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user->only(['id', 'name', 'email']),
                'roles' => $user->roles->map(function($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                        'slug' => $role->slug,
                        'permissions' => $role->permissions
                    ];
                }),
                'all_permissions' => $permissions->groupBy('group')
            ]
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/users/{id}/roles",
     *     tags={"Users"},
     *     summary="Assign roles to user",
     *     description="Assigns roles to a user",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="User ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"role_ids"},
     *             @OA\Property(property="role_ids", type="array", @OA\Items(type="integer")),
     *             @OA\Property(property="store_id", type="integer", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Roles assigned successfully"
     *     )
     * )
     */
    public function assignRoles(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'role_ids' => 'required|array',
            'role_ids.*' => 'exists:roles,id',
            'store_id' => 'nullable|exists:stores,id'
        ]);

        $storeId = $validated['store_id'] ?? null;

        foreach ($validated['role_ids'] as $roleId) {
            $user->assignRole($roleId, $storeId);
        }

        $user->load(['roles.permissions']);

        return response()->json([
            'success' => true,
            'message' => 'Roles assigned successfully',
            'data' => $user
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/users/{id}/roles/{roleId}",
     *     tags={"Users"},
     *     summary="Remove role from user",
     *     description="Removes a role from a user",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="User ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="roleId",
     *         in="path",
     *         description="Role ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Role removed successfully"
     *     )
     * )
     */
    public function removeRole(User $user, int $roleId): JsonResponse
    {
        $user->removeRole($roleId);

        return response()->json([
            'success' => true,
            'message' => 'Role removed successfully'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/users/{id}/restore",
     *     tags={"Users"},
     *     summary="Restore a soft deleted user",
     *     description="Restores a user that was soft deleted",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="User ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User restored successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="User restored successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/User")
     *         )
     *     ),
     *     @OA\Response(response=404, description="User not found")
     * )
     */
    public function restore(int $id): JsonResponse
    {
        $user = User::withTrashed()->findOrFail($id);

        if (!$user->trashed()) {
            return response()->json([
                'success' => false,
                'message' => 'User is not deleted'
            ], 400);
        }

        $user->restore();

        return response()->json([
            'success' => true,
            'message' => 'User restored successfully',
            'data' => $user->load('roles')
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/users/{id}/force",
     *     tags={"Users"},
     *     summary="Permanently delete a user",
     *     description="Permanently deletes a user from the database",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="User ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User permanently deleted",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="User permanently deleted")
     *         )
     *     ),
     *     @OA\Response(response=404, description="User not found")
     * )
     */
    public function forceDelete(int $id): JsonResponse
    {
        $user = User::withTrashed()->findOrFail($id);

        // Detach all roles first
        $user->roles()->detach();

        $user->forceDelete();

        return response()->json([
            'success' => true,
            'message' => 'User permanently deleted'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/users/{id}/deactivate",
     *     tags={"Users"},
     *     summary="Deactivate a user",
     *     description="Deactivates a user account",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="User ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User deactivated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="User deactivated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/User")
     *         )
     *     ),
     *     @OA\Response(response=404, description="User not found")
     * )
     */
    public function deactivate(User $user): JsonResponse
    {
        $user->update(['is_active' => false]);
        $user->refresh(); // Refresh model to get updated data

        return response()->json([
            'success' => true,
            'message' => 'User deactivated successfully',
            'data' => $user->load('roles')
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/users/{id}/activate",
     *     tags={"Users"},
     *     summary="Activate a user",
     *     description="Activates a user account",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="User ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User activated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="User activated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/User")
     *         )
     *     ),
     *     @OA\Response(response=404, description="User not found")
     * )
     */
    public function activate(User $user): JsonResponse
    {
        $user->update(['is_active' => true]);
        $user->refresh(); // Refresh model to get updated data

        return response()->json([
            'success' => true,
            'message' => 'User activated successfully',
            'data' => $user->load('roles')
        ]);
    }

    /**
     * Update current user profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        // Debug para verificar qué está recibiendo el servidor
        \Log::info('Updating profile for user ID: ' . $user->id);
        \Log::info('Request data:', $request->except(['avatar'])); // Excluir avatar para evitar logs enormes
        \Log::info('Request Content-Type: ' . $request->header('Content-Type'));
        \Log::info('All Request Headers: ', $request->headers->all());

        // Log específico para el archivo
        if ($request->hasFile('avatar')) {
            $avatar = $request->file('avatar');
            \Log::info('Avatar file info:', [
                'original_name' => $avatar->getClientOriginalName(),
                'mime_type' => $avatar->getMimeType(),
                'extension' => $avatar->getClientOriginalExtension(),
                'size' => $avatar->getSize(),
                'error' => $avatar->getError(),
                'temp_path' => $avatar->getPathname(),
                'is_valid' => $avatar->isValid()
            ]);

            // Verificar si el archivo es realmente una imagen
            try {
                $imageInfo = getimagesize($avatar->getPathname());
                \Log::info('Image validation passed with getimagesize: ', [
                    'width' => $imageInfo[0] ?? 'unknown',
                    'height' => $imageInfo[1] ?? 'unknown',
                    'type' => $imageInfo[2] ?? 'unknown',
                    'mime' => $imageInfo['mime'] ?? 'unknown'
                ]);
            } catch (\Exception $e) {
                \Log::error('Error validating image with getimagesize: ' . $e->getMessage());
            }
        } else {
            \Log::info('No avatar file in request');
            if ($request->has('avatar')) {
                \Log::info('Avatar key exists but is not a file. Value type: ' . gettype($request->input('avatar')));
                \Log::info('Avatar value dump: ' . json_encode($request->input('avatar')));
            }

            // Revisar todos los archivos en la solicitud
            \Log::info('All files in request: ', $request->allFiles());
        }

        try {
            $validated = $request->validate([
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email,' . $user->id,
                'phone' => 'nullable|string|max:20',
                'current_password' => 'nullable|required_with:password|string',
                'password' => 'nullable|string|min:8|confirmed',
                'avatar' => 'nullable|max:5120' // Eliminar validaciones específicas temporalmente
            ]);

            \Log::info('Validation passed successfully');

            // If password is being changed, verify current password
            if (!empty($validated['password'])) {
                if (empty($validated['current_password']) || !Hash::check($validated['current_password'], $user->password)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'La contraseña actual es incorrecta'
                    ], 422);
                }
                $validated['password'] = Hash::make($validated['password']);
            } else {
                unset($validated['password']);
            }

            // Handle avatar upload
            if ($request->hasFile('avatar')) {
                try {
                    $avatar = $request->file('avatar');
                    \Log::info('Processing avatar after validation: ' . $avatar->getClientOriginalName());

                    // Validar manualmente que sea una imagen
                    $extension = strtolower($avatar->getClientOriginalExtension());
                    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

                    if (!in_array($extension, $allowedExtensions)) {
                        \Log::error('Invalid file extension: ' . $extension);
                        return response()->json([
                            'success' => false,
                            'message' => 'El archivo debe ser una imagen (jpg, jpeg, png, gif, webp)'
                        ], 422);
                    }

                    // Generar un nombre de archivo único con timestamp
                    $filename = time() . '_' . Str::random(10) . '.' . $extension;

                    // Almacenar el archivo manualmente
                    $path = 'avatars/' . $filename;
                    Storage::disk('public')->put($path, file_get_contents($avatar->getRealPath()));

                    \Log::info('Avatar stored successfully at: ' . $path);
                    $validated['avatar'] = $path;

                    // Si el usuario ya tenía un avatar, eliminarlo
                    if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                        Storage::disk('public')->delete($user->avatar);
                        \Log::info('Previous avatar deleted: ' . $user->avatar);
                    }
                } catch (\Exception $e) {
                    \Log::error('Error saving avatar: ' . $e->getMessage());
                    throw new \Exception('Error al procesar la imagen: ' . $e->getMessage());
                }
            }

            // Remove current_password from data to update
            unset($validated['current_password']);

            $user->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Perfil actualizado correctamente',
                'data' => $user->fresh()
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Validation error:', $e->errors());
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Error updating profile: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el perfil: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete current user profile (soft delete)
     */
    public function deleteProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        // Soft delete the user
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Cuenta eliminada correctamente'
        ]);
    }

    /**
     * Get user profile data for header component
     */
    public function getProfileData(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Initialize default values
            $microsoftAccounts = collect([]);
            $ordersCount = 0;
            $totalInvestment = 0.00;
            $billingInformationCount = 0;

            // Get Microsoft Accounts count and data (with error handling)
            try {
                $microsoftAccounts = \App\Models\MicrosoftAccount::where('user_id', $user->id)
                    ->where('is_active', true)
                    ->select('id', 'organization', 'domain', 'domain_concatenated', 'email', 'is_default')
                    ->get();
            } catch (\Exception $e) {
                // Log error but continue
                \Log::warning('Error fetching Microsoft accounts: ' . $e->getMessage());
            }

            // Get orders count and total investment for completed orders (with error handling)
            try {
                $ordersData = \App\Models\Order::where('user_id', $user->id)
                    ->whereIn('payment_status', ['paid'])
                    ->selectRaw('COUNT(*) as orders_count, COALESCE(SUM(total_amount), 0) as total_investment')
                    ->first();

                $ordersCount = $ordersData->orders_count ?? 0;
                $totalInvestment = $ordersData->total_investment ?? 0.00;
            } catch (\Exception $e) {
                // Log error but continue
                \Log::warning('Error fetching orders data: ' . $e->getMessage());
            }

            // Get billing information count (with error handling)
            try {
                $billingInformationCount = \App\Models\BillingInformation::where('user_id', $user->id)
                    ->where('active', true)
                    ->count();
            } catch (\Exception $e) {
                // Log error but continue
                \Log::warning('Error fetching billing information: ' . $e->getMessage());
            }

            // Check profile completion
            $isProfileCompleted = !empty($user->first_name) &&
                                 !empty($user->last_name) &&
                                 !empty($user->phone);

            // Check if user has Microsoft accounts
            $hasMicrosoftAccount = $microsoftAccounts->count() > 0;

            // Check if user has billing information
            $hasBillingInformation = $billingInformationCount > 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'microsoft_accounts' => $microsoftAccounts->map(function($account) {
                        return [
                            'id' => $account->id,
                            'organization' => $account->organization ?? '',
                            'domain' => $account->domain ?? '',
                            'domain_concatenated' => $account->domain_concatenated ?? '',
                            'email' => $account->email ?? '',
                            'is_default' => $account->is_default ?? false
                        ];
                    }),
                    'orders_count' => (int) $ordersCount,
                    'total_investment' => (float) $totalInvestment,
                    'billing_information_count' => (int) $billingInformationCount,
                    'is_profile_completed' => $isProfileCompleted,
                    'has_microsoft_account' => $hasMicrosoftAccount,
                    'has_billing_information' => $hasBillingInformation
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in getProfileData: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos del perfil',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500);
        }
    }
}
