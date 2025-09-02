<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="Permissions",
 *     description="API Endpoints for Permission Management"
 * )
 */
class PermissionController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/permissions",
     *     tags={"Permissions"},
     *     summary="Get all permissions",
     *     description="Returns list of permissions grouped by module",
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search in name or description",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="group",
     *         in="query",
     *         description="Filter by permission group",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="grouped",
     *         in="query",
     *         description="Return permissions grouped by module",
     *         required=false,
     *         @OA\Schema(type="boolean", default=true)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Permissions retrieved successfully"
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        // Solo cachear si no hay búsqueda y no hay filtro de grupo específico
        $shouldCache = !$request->filled('search') && !$request->filled('group');
        $grouped = $request->get('grouped', true);

        if ($shouldCache) {
            $cacheKey = "permissions_list_grouped_{$grouped}";
            $cacheTime = 1800; // 30 minutos - los permisos cambian poco

            $result = Cache::remember($cacheKey, $cacheTime, function () use ($grouped) {
                return $this->getPermissionsData(null, null, $grouped);
            });
        } else {
            $result = $this->getPermissionsData($request->get('search'), $request->get('group'), $grouped);
        }

        return response()->json($result);
    }

    /**
     * Obtener datos de permisos
     */
    private function getPermissionsData($search = null, $groupFilter = null, $grouped = true): array
    {
        $query = Permission::query();

        // Search functionality
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%")
                  ->orWhere('group', 'like', "%{$search}%");
            });
        }

        // Filter by group
        if ($groupFilter) {
            $query->where('group', $groupFilter);
        }

        $permissions = $query->orderBy('group')->orderBy('name')->get();

        // Group permissions by module if requested
        if ($grouped) {
            $groupedPermissions = $permissions->groupBy('group');

            return [
                'success' => true,
                'data' => $groupedPermissions,
                'groups' => $groupedPermissions->keys()
            ];
        }

        return [
            'success' => true,
            'data' => $permissions
        ];
    }

    /**
     * @OA\Post(
     *     path="/api/v1/permissions",
     *     tags={"Permissions"},
     *     summary="Create new permission",
     *     description="Creates a new permission",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "slug", "group"},
     *             @OA\Property(property="name", type="string", example="Create Products"),
     *             @OA\Property(property="slug", type="string", example="products.create"),
     *             @OA\Property(property="group", type="string", example="products"),
     *             @OA\Property(property="description", type="string", example="Allows creating new products")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Permission created successfully"
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:permissions,slug',
            'group' => 'required|string|max:100',
            'description' => 'nullable|string'
        ]);

        $permission = Permission::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Permission created successfully',
            'data' => $permission
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/permissions/{id}",
     *     tags={"Permissions"},
     *     summary="Get permission by ID",
     *     description="Returns a single permission with roles",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Permission ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Permission retrieved successfully"
     *     )
     * )
     */
    public function show(Permission $permission): JsonResponse
    {
        try {
            $permission->load('roles');

            return response()->json([
                'success' => true,
                'data' => $permission
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading permission: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/v1/permissions/{id}",
     *     tags={"Permissions"},
     *     summary="Update permission",
     *     description="Updates an existing permission",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Permission ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Permission updated successfully"
     *     )
     * )
     */
    public function update(Request $request, Permission $permission): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'string|max:255',
            'slug' => ['string', 'max:255', Rule::unique('permissions')->ignore($permission->id)],
            'group' => 'string|max:100',
            'description' => 'nullable|string'
        ]);

        $permission->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Permission updated successfully',
            'data' => $permission
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/permissions/{id}",
     *     tags={"Permissions"},
     *     summary="Delete permission",
     *     description="Deletes a permission",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Permission ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Permission deleted successfully"
     *     )
     * )
     */
    public function destroy(Permission $permission): JsonResponse
    {
        // Detach from all roles first
        $permission->roles()->detach();

        $permission->delete();

        return response()->json([
            'success' => true,
            'message' => 'Permission deleted successfully'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/permissions/groups",
     *     tags={"Permissions"},
     *     summary="Get permission groups",
     *     description="Returns all permission groups",
     *     @OA\Response(
     *         response=200,
     *         description="Permission groups retrieved successfully"
     *     )
     * )
     */
    public function groups(): JsonResponse
    {
        $groups = Permission::select('group')
            ->distinct()
            ->orderBy('group')
            ->pluck('group');

        return response()->json([
            'success' => true,
            'data' => $groups
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/permissions/{id}/roles",
     *     tags={"Permissions"},
     *     summary="Get permission roles",
     *     description="Returns all roles that have this permission",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Permission ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Permission roles retrieved successfully"
     *     )
     * )
     */
    public function roles(Permission $permission): JsonResponse
    {
        try {
            $roles = $permission->roles()->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'permission' => $permission->only(['id', 'name', 'slug', 'group', 'description']),
                    'roles' => $roles
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading permission roles: ' . $e->getMessage()
            ], 500);
        }
    }
}
