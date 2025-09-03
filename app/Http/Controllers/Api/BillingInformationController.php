<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BillingInformation;
use App\Http\Requests\StoreBillingInformationRequest;
use App\Http\Requests\UpdateBillingInformationRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

/**
 * @OA\Tag(
 *     name="Billing Information",
 *     description="API Endpoints for Billing Information Management"
 * )
 */
class BillingInformationController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/billing-information",
     *     tags={"Billing Information"},
     *     summary="Get user's billing information",
     *     description="Returns list of billing information for the authenticated user",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="include_inactive",
     *         in="query",
     *         description="Include inactive billing information",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="with_trashed",
     *         in="query",
     *         description="Include soft deleted billing information",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Billing information retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Billing information retrieved successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/BillingInformation"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = BillingInformation::byUser($user->id)->with(['taxRegime']);

        if (!$request->boolean('include_inactive')) {
            $query->active();
        }

        if ($request->boolean('with_trashed')) {
            $query->withTrashed();
        }

        $billingInfo = $query->orderBy('is_default', 'desc')
                           ->orderBy('created_at', 'desc')
                           ->get();

        return response()->json([
            'success' => true,
            'data' => $billingInfo,
            'message' => 'Billing information retrieved successfully'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/billing-information",
     *     tags={"Billing Information"},
     *     summary="Create new billing information",
     *     description="Creates a new billing information record for the authenticated user",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/StoreBillingInformationRequest")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Billing information created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Billing information created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/BillingInformation")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation errors"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function store(StoreBillingInformationRequest $request): JsonResponse
    {
        $user = Auth::user();

        DB::beginTransaction();
        try {
            $data = $request->validated();
            $data['user_id'] = $user->id;
            $data['active'] = $data['active'] ?? true;
            $data['is_default'] = $data['is_default'] ?? false;

            // Si se marca como default, quitar el default de otros registros
            if ($data['is_default']) {
                BillingInformation::byUser($user->id)->update(['is_default' => false]);
            }

            $billingInfo = BillingInformation::create($data);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $billingInfo,
                'message' => 'Billing information created successfully'
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Error creating billing information: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified billing information.
    /**
     * @OA\Get(
     *     path="/api/v1/billing-information/{id}",
     *     tags={"Billing Information"},
     *     summary="Get specific billing information",
     *     description="Returns a specific billing information record for the authenticated user",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Billing Information ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Billing information retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Billing information retrieved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/BillingInformation")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Billing information not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $user = Auth::user();
        $billingInfo = BillingInformation::byUser($user->id)->with(['taxRegime'])->find($id);

        if (!$billingInfo) {
            return response()->json([
                'success' => false,
                'message' => 'Billing information not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $billingInfo,
            'message' => 'Billing information retrieved successfully'
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/billing-information/{id}",
     *     tags={"Billing Information"},
     *     summary="Update billing information",
     *     description="Updates an existing billing information record for the authenticated user",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Billing Information ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/UpdateBillingInformationRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Billing information updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Billing information updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/BillingInformation")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Billing information not found"),
     *     @OA\Response(response=422, description="Validation errors"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function update(UpdateBillingInformationRequest $request, int $id): JsonResponse
    {
        $user = Auth::user();
        $billingInfo = BillingInformation::byUser($user->id)->find($id);

        if (!$billingInfo) {
            return response()->json([
                'success' => false,
                'message' => 'Billing information not found'
            ], 404);
        }

        DB::beginTransaction();
        try {
            $data = $request->validated();

            // Si se marca como default, quitar el default de otros registros
            if (isset($data['is_default']) && $data['is_default']) {
                BillingInformation::byUser($user->id)
                    ->where('id', '!=', $id)
                    ->update(['is_default' => false]);
            }

            $billingInfo->update($data);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $billingInfo->fresh(),
                'message' => 'Billing information updated successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Error updating billing information: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/billing-information/{id}",
     *     tags={"Billing Information"},
     *     summary="Soft delete billing information",
     *     description="Soft deletes a billing information record (can be restored later)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Billing Information ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Billing information soft deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Billing information deleted successfully")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Billing information not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $user = Auth::user();
        $billingInfo = BillingInformation::byUser($user->id)->find($id);

        if (!$billingInfo) {
            return response()->json([
                'success' => false,
                'message' => 'Billing information not found'
            ], 404);
        }

        $billingInfo->delete();

        return response()->json([
            'success' => true,
            'message' => 'Billing information deleted successfully'
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/billing-information/{id}/force",
     *     tags={"Billing Information"},
     *     summary="Permanently delete billing information",
     *     description="Permanently deletes a billing information record from the database",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Billing Information ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Billing information permanently deleted",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Billing information permanently deleted")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Billing information not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function forceDelete(int $id): JsonResponse
    {
        $user = Auth::user();
        $billingInfo = BillingInformation::withTrashed()->byUser($user->id)->find($id);

        if (!$billingInfo) {
            return response()->json([
                'success' => false,
                'message' => 'Billing information not found'
            ], 404);
        }

        $billingInfo->forceDelete();

        return response()->json([
            'success' => true,
            'message' => 'Billing information permanently deleted'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/billing-information/{id}/restore",
     *     tags={"Billing Information"},
     *     summary="Restore soft deleted billing information",
     *     description="Restores a billing information record that was soft deleted",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Billing Information ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Billing information restored successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Billing information restored successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/BillingInformation")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Billing information not found"),
     *     @OA\Response(response=400, description="Billing information is not deleted"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function restore(int $id): JsonResponse
    {
        $user = Auth::user();
        $billingInfo = BillingInformation::withTrashed()->byUser($user->id)->find($id);

        if (!$billingInfo) {
            return response()->json([
                'success' => false,
                'message' => 'Billing information not found'
            ], 404);
        }

        if (!$billingInfo->trashed()) {
            return response()->json([
                'success' => false,
                'message' => 'Billing information is not deleted'
            ], 400);
        }

        $billingInfo->restore();

        return response()->json([
            'success' => true,
            'data' => $billingInfo->fresh(),
            'message' => 'Billing information restored successfully'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/billing-information/{id}/set-default",
     *     tags={"Billing Information"},
     *     summary="Set billing information as default",
     *     description="Sets a billing information record as the default for the authenticated user",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Billing Information ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Billing information set as default successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Billing information set as default successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/BillingInformation")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Billing information not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function setDefault(int $id): JsonResponse
    {
        $user = Auth::user();
        $billingInfo = BillingInformation::byUser($user->id)->find($id);

        if (!$billingInfo) {
            return response()->json([
                'success' => false,
                'message' => 'Billing information not found'
            ], 404);
        }

        DB::beginTransaction();
        try {
            // Remover default de todos los otros registros
            BillingInformation::byUser($user->id)
                ->where('id', '!=', $id)
                ->update(['is_default' => false]);

            // Establecer este como default
            $billingInfo->update(['is_default' => true]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $billingInfo->fresh(),
                'message' => 'Default billing information set successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Error setting default billing information: ' . $e->getMessage()
            ], 500);
        }
    }
}
