<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="Health",
 *     description="API health check endpoints"
 * )
 */
class HealthController extends ApiController
{
    /**
     * @OA\Get(
     *     path="/api/v1/health",
     *     tags={"Health"},
     *     summary="API health check",
     *     description="Returns the current status of the API",
     *     @OA\Response(
     *         response=200,
     *         description="API is running",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="API is running"),
     *             @OA\Property(property="timestamp", type="string", example="2025-08-26T22:33:06.767Z"),
     *             @OA\Property(property="version", type="string", example="1.0.0")
     *         )
     *     )
     * )
     */
    public function check(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'API is running',
            'timestamp' => now()->toISOString(),
            'version' => '1.0.0'
        ]);
    }
}
