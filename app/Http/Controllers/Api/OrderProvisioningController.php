<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PartnerCenterProvisioningService;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class OrderProvisioningController extends Controller
{
    private $provisioningService;

    public function __construct(PartnerCenterProvisioningService $provisioningService)
    {
        $this->provisioningService = $provisioningService;
    }

    /**
     * @OA\Post(
     *     path="/api/v1/orders/provisioning/process/{order_id}",
     *     summary="Process order and provision products in Microsoft Partner Center",
     *     description="Processes a pending order and creates the products in Microsoft Partner Center",
     *     operationId="processOrderProvisioning",
     *     tags={"Order Provisioning"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="order_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="ID of the order to process"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Order processed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Products provisioned successfully in Partner Center"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="order_id", type="integer", example=123),
     *                 @OA\Property(property="cart_id", type="string", example="cart-uuid"),
     *                 @OA\Property(property="subscriptions_count", type="integer", example=3)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Order status is not processing",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Order status is 'completed', expected 'processing'")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Order not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Order not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Provisioning failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to create cart in Microsoft Partner Center")
     *         )
     *     )
     * )
     *
     * Process order and provision products in Microsoft Partner Center
     */
    public function processOrder(Request $request, $order_id): JsonResponse
    {
        try {
            // Validate the order_id parameter
            if (!is_numeric($order_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid order ID'
                ], 400);
            }

            $orderId = (int) $order_id;

            Log::info("Processing order provisioning request", ['order_id' => $orderId]);

            // Verify order exists
            $order = Order::find($orderId);

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found'
                ], 404);
            }

            if ($order->status !== 'processing') {
                return response()->json([
                    'success' => false,
                    'message' => "Order status is '{$order->status}', expected 'processing'"
                ], 400);
            }

            // Process the order
            $result = $this->provisioningService->processOrder($orderId);

            if ($result['success']) {
                Log::info("Order provisioning completed successfully", ['order_id' => $orderId]);

                return response()->json([
                    'success' => true,
                    'message' => $result['message'],
                    'data' => [
                        'order_id' => $result['order_id'],
                        'order_status' => $result['order_status'],
                        'fulfillment_status' => $result['fulfillment_status'],
                        'total_products' => $result['total_products'],
                        'successful_products' => $result['successful_products'],
                        'failed_products' => $result['failed_products'],
                        'product_details' => $result['product_details'] ?? [],
                        'cart_id' => $result['cart_id'] ?? null,
                        'subscriptions_count' => $result['subscriptions_count'] ?? 0
                    ]
                ], 200);
            } else {
                Log::error("Order provisioning failed", ['order_id' => $orderId, 'error' => $result['message']]);

                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'data' => [
                        'order_id' => $result['order_id'],
                        'order_status' => $result['order_status'] ?? 'processing',
                        'fulfillment_status' => $result['fulfillment_status'] ?? 'pending',
                        'total_products' => $result['total_products'] ?? 0,
                        'successful_products' => $result['successful_products'] ?? 0,
                        'failed_products' => $result['failed_products'] ?? 0,
                        'product_details' => $result['product_details'] ?? []
                    ]
                ], 422);
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error("Unexpected error in order provisioning", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/orders/provisioning/{order_id}/status",
     *     summary="Get order provisioning status",
     *     description="Retrieves the current status of an order and its provisioning details",
     *     operationId="getOrderProvisioningStatus",
     *     tags={"Order Provisioning"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="order_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer", example=123),
     *         description="ID of the order"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Order status retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="order_id", type="integer", example=123),
     *                 @OA\Property(property="order_number", type="string", example="ORD-20250903-001"),
     *                 @OA\Property(property="fulfillment_status", type="string", example="completed"),
     *                 @OA\Property(property="total_amount", type="number", format="float", example=299.99),
     *                 @OA\Property(property="cart_items_count", type="integer", example=3),
     *                 @OA\Property(property="subscriptions_count", type="integer", example=2),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Order not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Order not found")
     *         )
     *     )
     * )
     *
     * Get order provisioning status
     */
    public function getOrderStatus(Request $request, int $orderId): JsonResponse
    {
        try {
            $order = Order::with(['subscriptions', 'cart.items.product'])
                          ->find($orderId);

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'fulfillment_status' => $order->status,
                    'total_amount' => $order->total_amount,
                    'cart_items_count' => $order->cart->items->count(),
                    'subscriptions_count' => $order->subscriptions->count(),
                    'created_at' => $order->created_at,
                    'updated_at' => $order->updated_at
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error("Error getting order status", [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving order status'
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/orders/provisioning/pending",
     *     summary="Get orders that need provisioning",
     *     description="Retrieves all orders with pending fulfillment status that need to be processed",
     *     operationId="getPendingOrders",
     *     tags={"Order Provisioning"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="integer", example=1),
     *         description="Page number for pagination"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Pending orders retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="pagination", type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="last_page", type="integer", example=5),
     *                 @OA\Property(property="per_page", type="integer", example=20),
     *                 @OA\Property(property="total", type="integer", example=100)
     *             )
     *         )
     *     )
     * )
     *
     * Get orders that need provisioning (pending status)
     */
    public function getPendingOrders(Request $request): JsonResponse
    {
        try {
            $orders = Order::with(['user', 'microsoftAccount', 'cart.items.product'])
                           ->where('status', 'processing')
                           ->orderBy('created_at', 'desc')
                           ->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $orders->items(),
                'pagination' => [
                    'current_page' => $orders->currentPage(),
                    'last_page' => $orders->lastPage(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total()
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error("Error getting pending orders", [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving pending orders'
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/orders/provisioning/batch-process",
     *     summary="Batch process multiple orders",
     *     description="Processes multiple orders at once for Partner Center provisioning",
     *     operationId="batchProcessOrders",
     *     tags={"Order Provisioning"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="order_ids",
     *                 type="array",
     *                 @OA\Items(type="integer"),
     *                 example={123, 124, 125},
     *                 description="Array of order IDs to process (max 10)"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Batch processing completed",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Processed 2 orders successfully, 1 failed"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="results", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="summary", type="object",
     *                     @OA\Property(property="total", type="integer", example=3),
     *                     @OA\Property(property="successful", type="integer", example=2),
     *                     @OA\Property(property="failed", type="integer", example=1)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     *
     * Batch process multiple orders
     */
    public function batchProcessOrders(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'order_ids' => 'required|array|max:10',
                'order_ids.*' => 'integer|exists:orders,id'
            ]);

            $orderIds = $validated['order_ids'];
            $results = [];

            Log::info("Processing batch order provisioning", ['order_ids' => $orderIds]);

            foreach ($orderIds as $orderId) {
                $result = $this->provisioningService->processOrder($orderId);
                $results[] = [
                    'order_id' => $orderId,
                    'success' => $result['success'],
                    'message' => $result['message']
                ];
            }

            $successCount = collect($results)->where('success', true)->count();
            $failCount = collect($results)->where('success', false)->count();

            Log::info("Batch processing completed", [
                'total' => count($orderIds),
                'success' => $successCount,
                'failed' => $failCount
            ]);

            return response()->json([
                'success' => true,
                'message' => "Processed {$successCount} orders successfully, {$failCount} failed",
                'data' => [
                    'results' => $results,
                    'summary' => [
                        'total' => count($orderIds),
                        'successful' => $successCount,
                        'failed' => $failCount
                    ]
                ]
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error("Error in batch order processing", [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred during batch processing'
            ], 500);
        }
    }
}
