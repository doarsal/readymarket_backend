<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreActivityRequest;
use App\Http\Requests\UpdateActivityRequest;
use App\Models\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Activities", description: "Operations related to the activities catalog")]
class ActivityController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/activities",
     *     tags={"Activities"},
     *     summary="List all activities",
     *     description="Gets a paginated list of all activities",
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, default=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=15)
     *     ),
     *     @OA\Parameter(
     *         name="active",
     *         in="query",
     *         description="Filter by active status",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by name or description",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="include_deleted",
     *         in="query",
     *         description="Include deleted activities (soft delete)",
     *         required=false,
     *         @OA\Schema(type="boolean", default=false)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Activities list retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Activities retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="per_page", type="integer", example=15),
     *                 @OA\Property(property="total", type="integer", example=50),
     *                 @OA\Property(property="last_page", type="integer", example=4),
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(ref="#/components/schemas/Activity")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = Activity::query();

        // Filter by active status
        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        }

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Include soft deleted records if requested
        if ($request->boolean('include_deleted')) {
            $query->withTrashed();
        }

        // Order by name
        $query->orderBy('name');

        // Paginate results
        $perPage = min($request->get('per_page', 15), 100);
        $activities = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Activities retrieved successfully',
            'data' => $activities
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/activities",
     *     tags={"Activities"},
     *     summary="Create new activity",
     *     description="Creates a new activity in the catalog",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", maxLength=180, description="Activity name", example="New purchase"),
     *             @OA\Property(property="description", type="string", maxLength=1000, description="Activity description", example="performs a new purchase"),
     *             @OA\Property(property="icon", type="string", maxLength=45, description="FontAwesome icon class", example="fa-shopping-cart"),
     *             @OA\Property(property="active", type="boolean", description="Active status", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Activity created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Activity created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Activity")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function store(StoreActivityRequest $request): JsonResponse
    {
        $activity = Activity::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Activity created successfully',
            'data' => $activity
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/activities/{id}",
     *     tags={"Activities"},
     *     summary="Get specific activity",
     *     description="Gets the details of a specific activity by its ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Activity ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Activity retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Activity retrieved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Activity")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Activity not found")
     * )
     */
    public function show(Activity $activity): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Activity retrieved successfully',
            'data' => $activity
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/activities/{id}",
     *     tags={"Activities"},
     *     summary="Update activity",
     *     description="Updates an existing activity",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Activity ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", maxLength=180, description="Activity name"),
     *             @OA\Property(property="description", type="string", maxLength=1000, description="Activity description"),
     *             @OA\Property(property="icon", type="string", maxLength=45, description="FontAwesome icon class"),
     *             @OA\Property(property="active", type="boolean", description="Active status")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Activity updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Activity updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Activity")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Activity not found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function update(UpdateActivityRequest $request, Activity $activity): JsonResponse
    {
        $activity->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Activity updated successfully',
            'data' => $activity->fresh()
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/activities/{id}",
     *     tags={"Activities"},
     *     summary="Delete activity (soft delete)",
     *     description="Deletes an activity using soft delete",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Activity ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Activity deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Activity deleted successfully")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Activity not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function destroy(Activity $activity): JsonResponse
    {
        $activity->delete();

        return response()->json([
            'success' => true,
            'message' => 'Activity deleted successfully'
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/activities/{id}/force",
     *     tags={"Activities"},
     *     summary="Delete activity permanently",
     *     description="Permanently deletes an activity from the database",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Activity ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Activity permanently deleted",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Activity permanently deleted")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Activity not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function forceDelete(int $id): JsonResponse
    {
        $activity = Activity::withTrashed()->findOrFail($id);
        $activity->forceDelete();

        return response()->json([
            'success' => true,
            'message' => 'Activity permanently deleted'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/activities/{id}/restore",
     *     tags={"Activities"},
     *     summary="Restore deleted activity",
     *     description="Restores an activity that was soft deleted",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Activity ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Activity restored successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Activity restored successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Activity")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Activity not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function restore(int $id): JsonResponse
    {
        $activity = Activity::onlyTrashed()->findOrFail($id);
        $activity->restore();

        return response()->json([
            'success' => true,
            'message' => 'Activity restored successfully',
            'data' => $activity
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/activities/{id}/toggle-status",
     *     tags={"Activities"},
     *     summary="Toggle activity status",
     *     description="Activates or deactivates an activity",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Activity ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Activity status toggled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Activity status toggled successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Activity")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Activity not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function toggleStatus(Activity $activity): JsonResponse
    {
        $activity->update(['active' => !$activity->active]);

        return response()->json([
            'success' => true,
            'message' => 'Activity status toggled successfully',
            'data' => $activity->fresh()
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/activities/active",
     *     tags={"Activities"},
     *     summary="Get only active activities",
     *     description="Gets a list of all active activities",
     *     @OA\Response(
     *         response=200,
     *         description="Active activities retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Active activities retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/Activity")
     *             )
     *         )
     *     )
     * )
     */
    public function getActive(): JsonResponse
    {
        $activities = Activity::active()->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'message' => 'Active activities retrieved successfully',
            'data' => $activities
        ]);
    }
}
