<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="Orders",
 *     description="Order management endpoints"
 * )
 */
class OrderController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/orders",
     *     tags={"Orders"},
     *     summary="Get user orders",
     *     description="Returns a list of orders for the authenticated user",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by order status",
     *         @OA\Schema(type="string", enum={"pending", "processing", "completed", "cancelled"})
     *     ),
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         description="Filter by store ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Orders retrieved successfully"
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        $query = Order::where('user_id', $user->id)
                     ->with(['items.product', 'store'])
                     ->orderBy('created_at', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('store_id')) {
            $query->where('store_id', $request->store_id);
        }

        $orders = $query->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $orders->items(),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total()
            ]
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/orders",
     *     tags={"Orders"},
     *     summary="Create order from cart",
     *     description="Creates a new order from the user's cart",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="cart_token", type="string", description="Cart token to convert to order"),
     *             @OA\Property(property="payment_method", type="string", enum={"card", "paypal", "stripe"}, description="Payment method"),
     *             @OA\Property(property="payment_card_id", type="integer", description="Payment card ID (if payment_method is card)"),
     *             @OA\Property(property="billing_information_id", type="integer", description="Billing information ID"),
     *             @OA\Property(property="notes", type="string", description="Order notes")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Order created successfully"
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'cart_token' => 'required|string',
            'payment_method' => 'required|string|in:card,paypal,stripe',
            'payment_card_id' => 'nullable|integer|exists:payment_cards,id',
            'billing_information_id' => 'required|integer|exists:billing_information,id',
            'notes' => 'nullable|string|max:500'
        ]);

        $user = Auth::user();

        // Find the cart
        $cart = Cart::where('cart_token', $request->cart_token)
                   ->where('user_id', $user->id)
                   ->with(['items.product', 'store'])
                   ->first();

        if (!$cart) {
            return response()->json([
                'success' => false,
                'message' => 'Cart not found'
            ], 404);
        }

        if ($cart->items->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Cart is empty'
            ], 400);
        }

        // Validate payment card ownership if provided
        if ($request->payment_card_id) {
            $paymentCard = $user->paymentCards()->find($request->payment_card_id);
            if (!$paymentCard) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment card not found'
                ], 404);
            }
        }

        // Validate billing information ownership
        $billingInfo = $user->billingInformation()->find($request->billing_information_id);
        if (!$billingInfo) {
            return response()->json([
                'success' => false,
                'message' => 'Billing information not found'
            ], 404);
        }

        try {
            DB::beginTransaction();

            // Create the order
            $order = Order::createFromCart($cart, [
                'payment_method' => $request->payment_method,
                'payment_card_id' => $request->payment_card_id,
                'billing_information_id' => $request->billing_information_id,
                'notes' => $request->notes
            ]);

            // Mark cart as converted
            $cart->update(['status' => 'converted']);

            DB::commit();

            // Load the order with relationships
            $order->load(['items.product', 'store', 'user', 'billingInformation']);

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully',
                'data' => $order
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create order: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/orders/{id}",
     *     tags={"Orders"},
     *     summary="Get order details",
     *     description="Returns details of a specific order",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Order ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Order details retrieved successfully"
     *     )
     * )
     */
    public function show($id): JsonResponse
    {
        $user = Auth::user();

        $order = Order::where('id', $id)
                     ->where('user_id', $user->id)
                     ->with(['items.product', 'store', 'billingInformation'])
                     ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $order
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/orders/{id}/cancel",
     *     tags={"Orders"},
     *     summary="Cancel order",
     *     description="Cancels an order if it's still pending",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Order ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="cancellation_reason", type="string", description="Reason for cancellation")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Order cancelled successfully"
     *     )
     * )
     */
    public function cancel(Request $request, $id): JsonResponse
    {
        $request->validate([
            'cancellation_reason' => 'nullable|string|max:500'
        ]);

        $user = Auth::user();

        $order = Order::where('id', $id)
                     ->where('user_id', $user->id)
                     ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        if ($order->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Order cannot be cancelled. Current status: ' . $order->status
            ], 400);
        }

        $order->update([
            'status' => 'cancelled',
            'cancellation_reason' => $request->cancellation_reason,
            'cancelled_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Order cancelled successfully',
            'data' => $order
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/orders/tracking/{order_number}",
     *     tags={"Orders"},
     *     summary="Track order by order number",
     *     description="Returns tracking information for an order",
     *     @OA\Parameter(
     *         name="order_number",
     *         in="path",
     *         required=true,
     *         description="Order number",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Order tracking information retrieved successfully"
     *     )
     * )
     */
    public function trackByOrderNumber($orderNumber): JsonResponse
    {
        $order = Order::where('order_number', $orderNumber)
                     ->with(['items.product', 'store'])
                     ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        $trackingInfo = [
            'order_number' => $order->order_number,
            'status' => $order->status,
            'total_amount' => $order->total_amount,
            'currency' => $order->currency_id,
            'created_at' => $order->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $order->updated_at->format('Y-m-d H:i:s'),
            'tracking_number' => $order->tracking_number,
            'estimated_delivery' => $order->estimated_delivery_date,
            'store' => $order->store->only(['name', 'slug']),
            'items_count' => $order->items->count(),
            'timeline' => [
                [
                    'status' => 'pending',
                    'date' => $order->created_at->format('Y-m-d H:i:s'),
                    'completed' => true
                ],
                [
                    'status' => 'processing',
                    'date' => $order->status === 'processing' ? $order->updated_at->format('Y-m-d H:i:s') : null,
                    'completed' => in_array($order->status, ['processing', 'completed'])
                ],
                [
                    'status' => 'completed',
                    'date' => $order->status === 'completed' ? $order->processed_at?->format('Y-m-d H:i:s') : null,
                    'completed' => $order->status === 'completed'
                ]
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $trackingInfo
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/orders/statistics",
     *     tags={"Orders"},
     *     summary="Get user order statistics",
     *     description="Returns order statistics for the authenticated user",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Order statistics retrieved successfully"
     *     )
     * )
     */
    public function statistics(): JsonResponse
    {
        $user = Auth::user();

        $stats = [
            'total_orders' => Order::where('user_id', $user->id)->count(),
            'pending_orders' => Order::where('user_id', $user->id)->where('status', 'pending')->count(),
            'processing_orders' => Order::where('user_id', $user->id)->where('status', 'processing')->count(),
            'completed_orders' => Order::where('user_id', $user->id)->where('status', 'completed')->count(),
            'cancelled_orders' => Order::where('user_id', $user->id)->where('status', 'cancelled')->count(),
            'total_spent' => Order::where('user_id', $user->id)
                                 ->where('status', '!=', 'cancelled')
                                 ->sum('total_amount'),
            'average_order_value' => Order::where('user_id', $user->id)
                                         ->where('status', '!=', 'cancelled')
                                         ->avg('total_amount'),
            'orders_by_month' => Order::where('user_id', $user->id)
                                     ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, COUNT(*) as count, SUM(total_amount) as total')
                                     ->groupBy('year', 'month')
                                     ->orderBy('year', 'desc')
                                     ->orderBy('month', 'desc')
                                     ->limit(12)
                                     ->get()
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}
