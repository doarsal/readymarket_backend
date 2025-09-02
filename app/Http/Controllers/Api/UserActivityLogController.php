<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LogActivityRequest;
use App\Models\UserActivityLog;
use App\Models\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "User Activity Logs", description: "Operations to record and query user activity logs")]
class UserActivityLogController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/v1/user-activities/log",
     *     tags={"User Activity Logs"},
     *     summary="Log user activity",
     *     description="Records a new activity performed by the authenticated user. Super simple endpoint - just pass the activity_id and optional data.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="activity_id", type="integer", description="Activity ID to record", example=1),
     *             @OA\Property(property="module", type="string", maxLength=120, description="Module where executed (optional)", example="billing"),
     *             @OA\Property(property="title", type="string", maxLength=120, description="Descriptive title (optional)", example="Created new billing"),
     *             @OA\Property(property="reference_id", type="string", maxLength=255, description="Object reference ID (optional)", example="bill_123"),
     *             @OA\Property(property="metadata", type="object", description="Additional data (optional)", example={"amount": 100, "currency": "USD"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Activity logged successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Activity recorded successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/UserActivityLog")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function logActivity(LogActivityRequest $request): JsonResponse
    {
        $log = UserActivityLog::logActivity(
            activityId: $request->activity_id,
            userId: auth()->id(),
            module: $request->module,
            title: $request->title,
            referenceId: $request->reference_id,
            metadata: $request->metadata
        );

        return response()->json([
            'success' => true,
            'message' => 'Activity recorded successfully',
            'data' => $log->load(['activity', 'user'])
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/user-activities",
     *     tags={"User Activity Logs"},
     *     summary="Get user activity logs",
     *     description="Gets the activity history of the authenticated user",
     *     security={{"bearerAuth":{}}},
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
     *         description="Records per page",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=15)
     *     ),
     *     @OA\Parameter(
     *         name="activity_id",
     *         in="query",
     *         description="Filter by activity ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="module",
     *         in="query",
     *         description="Filter by module",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Start date (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="End date (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Logs retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Logs retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="per_page", type="integer", example=15),
     *                 @OA\Property(property="total", type="integer", example=50),
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(ref="#/components/schemas/UserActivityLog")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function getUserActivities(Request $request): JsonResponse
    {
        $query = UserActivityLog::forUser(auth()->id());

        // Optional filters
        if ($request->filled('activity_id')) {
            $query->forActivity($request->activity_id);
        }

        if ($request->filled('module')) {
            $query->forModule($request->module);
        }

        if ($request->filled('start_date')) {
            $query->dateRange($request->start_date, $request->end_date);
        }

        // Ordenar por más reciente
        $query->orderBy('created_at', 'desc');

        // Paginar
        $perPage = min($request->get('per_page', 15), 100);
        $logs = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Logs retrieved successfully',
            'data' => $logs
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/user-activities/recent",
     *     tags={"User Activity Logs"},
     *     summary="Get recent user activities",
     *     description="Gets the latest activities from the authenticated user",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Maximum number of records",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=50, default=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Recent activities retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Recent activities retrieved"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/UserActivityLog")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function getRecentActivities(Request $request): JsonResponse
    {
        $limit = min($request->get('limit', 10), 50);
        $activities = UserActivityLog::getRecentForUser(auth()->id(), $limit);

        return response()->json([
            'success' => true,
            'message' => 'Recent activities retrieved',
            'data' => $activities
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/user-activities/stats",
     *     tags={"User Activity Logs"},
     *     summary="Get user activity statistics",
     *     description="Gets summarized statistics of user activities",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="days",
     *         in="query",
     *         description="Number of days backwards for statistics",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=365, default=30)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Statistics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Statistics retrieved"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="total_activities", type="integer", example=156),
     *                 @OA\Property(property="activities_by_type", type="object"),
     *                 @OA\Property(property="activities_by_module", type="object"),
     *                 @OA\Property(property="daily_activity", type="array", @OA\Items(type="object"))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function getActivityStats(Request $request): JsonResponse
    {
        $days = min($request->get('days', 30), 365);
        $startDate = now()->subDays($days)->startOfDay();
        $userId = auth()->id();

        $totalActivities = UserActivityLog::forUser($userId)
            ->where('user_activity_logs.created_at', '>=', $startDate)
            ->count();

        $activitiesByType = UserActivityLog::forUser($userId)
            ->where('user_activity_logs.created_at', '>=', $startDate)
            ->join('activities', 'user_activity_logs.activity_id', '=', 'activities.id')
            ->selectRaw('activities.name, COUNT(*) as count')
            ->groupBy('activities.id', 'activities.name')
            ->orderBy('count', 'desc')
            ->get()
            ->pluck('count', 'name');

        $activitiesByModule = UserActivityLog::forUser($userId)
            ->where('user_activity_logs.created_at', '>=', $startDate)
            ->whereNotNull('module')
            ->selectRaw('module, COUNT(*) as count')
            ->groupBy('module')
            ->orderBy('count', 'desc')
            ->get()
            ->pluck('count', 'module');

        $dailyActivity = UserActivityLog::forUser($userId)
            ->where('user_activity_logs.created_at', '>=', $startDate)
            ->selectRaw('DATE(user_activity_logs.created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Statistics retrieved',
            'data' => [
                'total_activities' => $totalActivities,
                'activities_by_type' => $activitiesByType,
                'activities_by_module' => $activitiesByModule,
                'daily_activity' => $dailyActivity,
                'period_days' => $days
            ]
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/user-activities/quick-log/{activityId}",
     *     tags={"User Activity Logs"},
     *     summary="Quick activity log (super simple)",
     *     description="Super simple endpoint - just pass the activity ID in the URL and you're done. Ideal for quick logs.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="activityId",
     *         in="path",
     *         description="Activity ID to log",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="module", type="string", description="Module (optional)", example="billing"),
     *             @OA\Property(property="title", type="string", description="Title (optional)", example="Action performed"),
     *             @OA\Property(property="reference_id", type="string", description="Reference ID (optional)", example="item_123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Activity logged",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Activity logged")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Activity not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function quickLog(Request $request, int $activityId): JsonResponse
    {
        // Verificar que la actividad existe
        $activity = Activity::findOrFail($activityId);

        $log = UserActivityLog::logActivity(
            activityId: $activityId,
            userId: auth()->id(),
            module: $request->module,
            title: $request->title ?? $activity->name,
            referenceId: $request->reference_id
        );

        return response()->json([
            'success' => true,
            'message' => 'Activity logged',
            'data' => [
                'log_id' => $log->id,
                'activity' => $activity->name,
                'logged_at' => $log->created_at
            ]
        ], 201);
    }
}
