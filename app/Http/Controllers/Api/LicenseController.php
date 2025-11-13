<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LicenseResource;
use App\Models\Subscription;
use App\Models\MicrosoftAccount;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Licenses",
 *     description="User licenses management endpoints"
 * )
 */
class LicenseController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/user/licenses",
     *     summary="Get all licenses for authenticated user",
     *     description="Returns paginated list of user's licenses with filtering options",
     *     operationId="getUserLicenses",
     *     tags={"Licenses"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by status: active, inactive, all",
     *         required=false,
     *         @OA\Schema(type="string", enum={"active", "inactive", "all"})
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Filter by type: subscription, perpetual, azure, all",
     *         required=false,
     *         @OA\Schema(type="string", enum={"subscription", "perpetual", "azure", "all"})
     *     ),
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
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/LicenseResource"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Validate request parameters
            $validated = $request->validate([
                'status' => 'nullable|in:active,inactive,all',
                'type' => 'nullable|in:subscription,perpetual,azure,all',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            $perPage = $validated['per_page'] ?? 15;

            // Query builder with eager loading for performance
            $query = Subscription::query()
                ->whereHas('order', function($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->with([
                    'product:idproduct,ProductTitle,SkuTitle,Publisher,BillingPlan,prod_icon,TermDuration',
                    'order:id,order_number,created_at,total_amount',
                    'microsoftAccount:id,domain_concatenated,organization'
                ]);

            // Apply status filter
            if (isset($validated['status']) && $validated['status'] !== 'all') {
                $isActive = $validated['status'] === 'active';
                $query->where('status', $isActive ? 1 : 0);
            }

            // Apply type filter
            if (isset($validated['type']) && $validated['type'] !== 'all') {
                $this->applyTypeFilter($query, $validated['type']);
            }

            // Order by creation date (newest first)
            $query->orderBy('created_at', 'desc');

            // Paginate results
            $licenses = $query->paginate($perPage);

            // Get statistics
            $stats = $this->getLicenseStats($user);

            Log::info('Licenses fetched successfully', [
                'user_id' => $user->id,
                'total' => $licenses->total(),
                'filters' => $validated
            ]);

            return response()->json([
                'success' => true,
                'data' => LicenseResource::collection($licenses),
                'stats' => $stats,
                'meta' => [
                    'current_page' => $licenses->currentPage(),
                    'last_page' => $licenses->lastPage(),
                    'per_page' => $licenses->perPage(),
                    'total' => $licenses->total(),
                    'from' => $licenses->firstItem(),
                    'to' => $licenses->lastItem(),
                ]
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Error fetching licenses', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching licenses'
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/user/licenses/{id}",
     *     summary="Get specific license details",
     *     description="Returns detailed information about a specific license",
     *     operationId="getLicenseById",
     *     tags={"Licenses"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="License ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/LicenseResource")
     *     ),
     *     @OA\Response(response=404, description="License not found"),
     *     @OA\Response(response=403, description="Access denied")
     * )
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $user = $request->user();

            // Find license and verify ownership
            $license = Subscription::with([
                    'product',
                    'order',
                    'microsoftAccount'
                ])
                ->whereHas('order', function($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->findOrFail($id);

            Log::info('License details fetched', [
                'user_id' => $user->id,
                'license_id' => $id
            ]);

            return response()->json([
                'success' => true,
                'data' => new LicenseResource($license)
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'License not found or access denied'
            ], 404);

        } catch (\Exception $e) {
            Log::error('Error fetching license details', [
                'license_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching license details'
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/user/microsoft-accounts/{accountId}/licenses",
     *     summary="Get licenses for specific Microsoft account",
     *     description="Returns all licenses associated with a specific Microsoft account",
     *     operationId="getLicensesByAccount",
     *     tags={"Licenses"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="accountId",
     *         in="path",
     *         description="Microsoft Account ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation"
     *     ),
     *     @OA\Response(response=404, description="Microsoft account not found")
     * )
     */
    public function byAccount(Request $request, int $accountId): JsonResponse
    {
        try {
            $user = $request->user();

            // Verify Microsoft account belongs to user
            $microsoftAccount = MicrosoftAccount::where('id', $accountId)
                ->where('user_id', $user->id)
                ->firstOrFail();

            // Get all active licenses for this account
            $licenses = Subscription::where('microsoft_account_id', $accountId)
                ->with(['product', 'order'])
                ->orderBy('created_at', 'desc')
                ->get();

            // Calculate totals
            $totalLicenses = $licenses->sum('quantity');
            $activeLicenses = $licenses->where('status', 1)->sum('quantity');
            $totalProducts = $licenses->count();

            Log::info('Licenses by account fetched', [
                'user_id' => $user->id,
                'account_id' => $accountId,
                'total_licenses' => $totalLicenses
            ]);

            return response()->json([
                'success' => true,
                'microsoft_account' => [
                    'id' => $microsoftAccount->id,
                    'domain' => $microsoftAccount->domain_concatenated,
                    'business_name' => $microsoftAccount->business_name,
                    'status' => $microsoftAccount->status,
                ],
                'data' => LicenseResource::collection($licenses),
                'summary' => [
                    'total_licenses' => $totalLicenses,
                    'active_licenses' => $activeLicenses,
                    'inactive_licenses' => $totalLicenses - $activeLicenses,
                    'total_products' => $totalProducts,
                ]
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Microsoft account not found or access denied'
            ], 404);

        } catch (\Exception $e) {
            Log::error('Error fetching licenses by account', [
                'account_id' => $accountId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching licenses'
            ], 500);
        }
    }

    /**
     * Apply type filter to query
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $type
     * @return void
     */
    private function applyTypeFilter($query, string $type): void
    {
        switch ($type) {
            case 'subscription':
                $query->whereIn('transaction_type', ['New', 'Renew', 'Upgrade'])
                    ->whereHas('product', function($q) {
                        $q->whereIn('BillingPlan', ['Monthly', 'Annual', 'Triennial']);
                    });
                break;

            case 'perpetual':
                $query->whereHas('product', function($q) {
                    $q->where('BillingPlan', 'OneTime')
                      ->where('ProductTitle', 'like', '%Perpetual%');
                });
                break;

            case 'azure':
                $query->whereHas('product', function($q) {
                    $q->where('ProductTitle', 'like', '%Azure%');
                });
                break;
        }
    }

    /**
     * Get license statistics for user
     *
     * @param \App\Models\User $user
     * @return array
     */
    private function getLicenseStats($user): array
    {
        $allLicenses = Subscription::whereHas('order', function($query) use ($user) {
            $query->where('user_id', $user->id);
        })->get();

        $totalLicenses = $allLicenses->sum('quantity');
        $activeLicenses = $allLicenses->where('status', 1)->sum('quantity');
        $totalProducts = $allLicenses->count();

        // Count Microsoft accounts with licenses
        $microsoftAccountsCount = $allLicenses->pluck('microsoft_account_id')->unique()->count();

        return [
            'total_licenses' => $totalLicenses,
            'active_licenses' => $activeLicenses,
            'inactive_licenses' => $totalLicenses - $activeLicenses,
            'total_products' => $totalProducts,
            'microsoft_accounts_with_licenses' => $microsoftAccountsCount,
        ];
    }

    /**
     * @OA\Post(
     *     path="/api/v1/licenses/{id}/cancel",
     *     summary="Cancel a subscription",
     *     description="Cancel an active subscription in Microsoft Partner Center. Note: New commerce licenses have a limited cancellation window (typically 7 days from purchase).",
     *     operationId="cancelLicense",
     *     tags={"Licenses"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="License (Subscription) ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="reason", type="string", example="Customer requested cancellation", description="Optional cancellation reason")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Subscription cancelled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Subscription cancelled successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Cancellation window expired or not authorized"),
     *     @OA\Response(response=404, description="Subscription not found"),
     *     @OA\Response(response=500, description="Cancellation failed")
     * )
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        try {
            // Get subscription with relationships
            $subscription = Subscription::with(['order.user', 'microsoftAccount'])
                ->where('id', $id)
                ->firstOrFail();

            // Authorization: User must own this subscription
            if ($subscription->order->user_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to cancel this subscription'
                ], 403);
            }

            // Check if already cancelled
            if ($subscription->status != 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'This subscription is already inactive or cancelled'
                ], 400);
            }

            // Use cancellation service
            $cancellationService = app(\App\Services\SubscriptionCancellationService::class);
            $result = $cancellationService->cancelSubscription(
                $id,
                $request->input('reason', 'Customer requested cancellation')
            );

            return response()->json([
                'success' => true,
                'message' => 'Subscription cancelled successfully',
                'data' => $result
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription not found'
            ], 404);

        } catch (\Exception $e) {
            Log::error('Subscription cancellation failed', [
                'subscription_id' => $id,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel subscription: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle auto-renewal for a subscription
     */
    public function toggleAutoRenew(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'auto_renew_enabled' => 'required|boolean'
            ]);

            // Get subscription with relationships
            $subscription = Subscription::with(['order.user', 'microsoftAccount', 'product'])
                ->where('id', $id)
                ->firstOrFail();

            // Authorization: User must own this subscription
            if ($subscription->order->user_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to modify this subscription'
                ], 403);
            }

            // Check if active
            if ($subscription->status != 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only active subscriptions can be modified'
                ], 400);
            }

            // Use auto-renew service
            $autoRenewService = app(\App\Services\SubscriptionAutoRenewService::class);
            $result = $autoRenewService->toggleAutoRenew(
                $id,
                $validated['auto_renew_enabled']
            );

            // Reload subscription with fresh data to get updated renewal_info
            $subscription->refresh();
            $subscription->load([
                'product:idproduct,ProductTitle,SkuTitle,Publisher,BillingPlan,prod_icon,TermDuration',
                'order:id,order_number,created_at,total_amount',
                'microsoftAccount:id,domain_concatenated,organization'
            ]);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => new \App\Http\Resources\LicenseResource($subscription)
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription not found'
            ], 404);

        } catch (\Exception $e) {
            Log::error('Auto-renewal toggle failed', [
                'subscription_id' => $id,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle auto-renewal: ' . $e->getMessage()
            ], 500);
        }
    }
}
