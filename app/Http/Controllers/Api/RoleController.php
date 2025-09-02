<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="Roles",
 *     description="API Endpoints for Role Management"
 * )
 */
class RoleController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/roles",
     *     tags={"Roles"},
     *     summary="Get all roles",
     *     description="Returns list of roles with their permissions",
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search in name or description",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="with_permissions",
     *         in="query",
     *         description="Include permissions in response",
     *         required=false,
     *         @OA\Schema(type="boolean", default=true)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of roles per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15, maximum=100)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Roles retrieved successfully"
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        // Solo cachear si no hay búsqueda
        $shouldCache = !$request->filled('search');
        $withPermissions = $request->get('with_permissions', true);
        $perPage = min($request->get('per_page', 15), 100);

        if ($shouldCache) {
            $cacheKey = "roles_list_perm{$withPermissions}_pp{$perPage}";
            $cacheTime = 1800; // 30 minutos

            $result = Cache::remember($cacheKey, $cacheTime, function () use ($withPermissions, $perPage) {
                return $this->getRolesData(null, $withPermissions, $perPage);
            });
        } else {
            $result = $this->getRolesData($request->search, $withPermissions, $perPage);
        }

        return response()->json($result);
    }

    /**
     * Obtener datos de roles
     */
    private function getRolesData($search = null, $withPermissions = true, $perPage = 15): array
    {
        $query = Role::query();

        // Include permissions by default
        if ($withPermissions) {
            $query->with('permissions');
        }

        // Search functionality
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        // Pagination
        $roles = $query->orderBy('name')->paginate($perPage);

        return [
            'success' => true,
            'data' => $roles->items(),
            'pagination' => [
                'current_page' => $roles->currentPage(),
                'last_page' => $roles->lastPage(),
                'per_page' => $roles->perPage(),
                'total' => $roles->total(),
                'from' => $roles->firstItem(),
                'to' => $roles->lastItem()
            ]
        ];
    }

    /**
     * @OA\Post(
     *     path="/api/v1/roles",
     *     tags={"Roles"},
     *     summary="Create new role",
     *     description="Creates a new role",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "slug"},
     *             @OA\Property(property="name", type="string", example="Administrator"),
     *             @OA\Property(property="slug", type="string", example="admin"),
     *             @OA\Property(property="description", type="string", example="Full system access"),
     *             @OA\Property(property="level", type="integer", example=1),
     *             @OA\Property(property="is_active", type="boolean", example=true),
     *             @OA\Property(property="permission_ids", type="array", @OA\Items(type="integer"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Role created successfully"
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:roles,slug',
            'description' => 'nullable|string',
            'level' => 'nullable|integer|min:0|max:100',
            'color' => 'nullable|string|max:7',
            'is_active' => 'boolean',
            'permission_ids' => 'array',
            'permission_ids.*' => 'exists:permissions,id'
        ]);

        // Extract permission_ids before creating role
        $permissionIds = $validated['permission_ids'] ?? [];
        unset($validated['permission_ids']);

        $role = Role::create($validated);

        // Assign permissions if provided
        if (!empty($permissionIds)) {
            $role->permissions()->attach($permissionIds);
        }

        $role->load('permissions');

        return response()->json([
            'success' => true,
            'message' => 'Role created successfully',
            'data' => $role
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/roles/{id}",
     *     tags={"Roles"},
     *     summary="Get role by ID",
     *     description="Returns a single role with permissions",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Role ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Role retrieved successfully"
     *     )
     * )
     */
    public function show(Role $role): JsonResponse
    {
        $role->load(['permissions', 'users']);

        return response()->json([
            'success' => true,
            'data' => $role
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/roles/{id}",
     *     tags={"Roles"},
     *     summary="Update role",
     *     description="Updates an existing role",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Role ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Role updated successfully"
     *     )
     * )
     */
    public function update(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'string|max:255',
            'slug' => ['string', 'max:255', Rule::unique('roles')->ignore($role->id)],
            'description' => 'nullable|string',
            'level' => 'nullable|integer|min:0|max:100',
            'color' => 'nullable|string|max:7',
            'is_active' => 'boolean',
            'permission_ids' => 'array',
            'permission_ids.*' => 'exists:permissions,id'
        ]);

        // Extract permission_ids before updating role
        $permissionIds = $validated['permission_ids'] ?? null;
        unset($validated['permission_ids']);

        $role->update($validated);

        // Update permissions if provided
        if ($permissionIds !== null) {
            $role->permissions()->sync($permissionIds);
        }

        $role->load('permissions');

        return response()->json([
            'success' => true,
            'message' => 'Role updated successfully',
            'data' => $role
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/roles/{id}",
     *     tags={"Roles"},
     *     summary="Delete role",
     *     description="Deletes a role",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Role ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Role deleted successfully"
     *     )
     * )
     */
    public function destroy(Role $role): JsonResponse
    {
        // Detach all permissions and users first
        $role->permissions()->detach();
        $role->users()->detach();

        $role->delete();

        return response()->json([
            'success' => true,
            'message' => 'Role deleted successfully'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/roles/{id}/permissions",
     *     tags={"Roles"},
     *     summary="Get role permissions",
     *     description="Returns all permissions for a role",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Role ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Role permissions retrieved successfully"
     *     )
     * )
     */
    public function permissions(Role $role): JsonResponse
    {
        try {
            $permissions = $role->permissions()->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'role' => $role->only(['id', 'name', 'slug', 'description']),
                    'permissions' => $permissions->groupBy('group')
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading role permissions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/roles/{id}/permissions",
     *     tags={"Roles"},
     *     summary="Assign permissions to role",
     *     description="Assigns permissions to a role",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Role ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"permission_ids"},
     *             @OA\Property(property="permission_ids", type="array", @OA\Items(type="integer"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Permissions assigned successfully"
     *     )
     * )
     */
    public function assignPermissions(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'permission_ids' => 'required|array',
            'permission_ids.*' => 'exists:permissions,id'
        ]);

        $role->permissions()->sync($validated['permission_ids']);
        $role->load('permissions');

        return response()->json([
            'success' => true,
            'message' => 'Permissions assigned successfully',
            'data' => $role
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/roles/{id}/permissions/{permissionId}",
     *     tags={"Roles"},
     *     summary="Remove permission from role",
     *     description="Removes a permission from a role",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Role ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="permissionId",
     *         in="path",
     *         description="Permission ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Permission removed successfully"
     *     )
     * )
     */
    public function removePermission(Role $role, int $permissionId): JsonResponse
    {
        $role->permissions()->detach($permissionId);

        return response()->json([
            'success' => true,
            'message' => 'Permission removed successfully'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/roles/{id}/users",
     *     tags={"Roles"},
     *     summary="Get role users",
     *     description="Returns all users assigned to a role",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Role ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Role users retrieved successfully"
     *     )
     * )
     */
    public function users(Role $role): JsonResponse
    {
        $role->load('users');

        return response()->json([
            'success' => true,
            'data' => [
                'role' => $role->only(['id', 'name', 'slug', 'description']),
                'users' => $role->users
            ]
        ]);
    }
}
