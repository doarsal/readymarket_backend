<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Config;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
        $daysToCancel = (int) Config::get('orders.days_to_cancel');

        $user = Auth::user();

        $query = Order::where('user_id', $user->id)->selectRaw('orders.*, 
        CASE 
            WHEN DATE_ADD(created_at, INTERVAL ? DAY) >= NOW() 
            THEN 1 
            ELSE 0 
        END AS in_cancel_period', [$daysToCancel])->with([
            'items.product',
            'store',
            'billingInformation.taxRegime',
            'billingInformation.cfdiUsage',
            'orderItems',
            'invoice',
            'paymentResponse',
            'currency',
        ])->orderBy('created_at', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('store_id')) {
            $query->where('store_id', $request->store_id);
        }

        $orders = $query->paginate(20);

        return response()->json([
            'success'    => true,
            'data'       => $orders->items(),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page'    => $orders->lastPage(),
                'per_page'     => $orders->perPage(),
                'total'        => $orders->total(),
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path=" / api / v1 / orders",
     *     tags={"Orders"},
     *     summary="Create order from cart",
     *     description="Creates a new order from the user's cart",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="cart_token", type="string", description="Cart token to convert to order"),
     *             @OA\Property(property="payment_method", type="string", enum={"credit_card", "debit_card"}, description="Payment method - Card type"),
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
            'cart_token'             => 'required | string',
            'payment_method'         => 'required | string | in:credit_card,debit_card',
            'payment_card_id'        => 'nullable | integer | exists:payment_cards,id',
            'billing_information_id' => 'required | integer | exists:billing_information,id',
            'notes'                  => 'nullable | string | max:500',
        ]);

        $user = Auth::user();

        // Find the cart
        $cart = Cart::where('cart_token', $request->cart_token)->where('user_id', $user->id)->with([
            'items . product',
            'store . currencies',
        ])->first();

        if (!$cart) {
            return response()->json([
                'success' => false,
                'message' => 'Cart not found',
            ], 404);
        }

        if ($cart->items->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Cart is empty',
            ], 400);
        }

        // Validate payment card ownership if provided
        if ($request->payment_card_id) {
            $paymentCard = $user->paymentCards()->find($request->payment_card_id);
            if (!$paymentCard) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment card not found',
                ], 404);
            }
        }

        // Validate billing information ownership
        $billingInfo = $user->billingInformation()->find($request->billing_information_id);
        if (!$billingInfo) {
            return response()->json([
                'success' => false,
                'message' => 'Billing information not found',
            ], 404);
        }

        try {
            DB::beginTransaction();

            // Create the order
            $order = Order::createFromCart($cart, [
                'payment_method'         => $request->payment_method,
                'payment_card_id'        => $request->payment_card_id,
                'billing_information_id' => $request->billing_information_id,
                'notes'                  => $request->notes,
            ]);

            // Mark cart as converted
            $cart->update(['status' => 'converted']);

            DB::commit();

            // Load the order with relationships
            $order->load(['items . product', 'store', 'user', 'billingInformation', 'currency']);

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully',
                'data'    => $order,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create order: ' . $e->getMessage(),
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

        $order = Order::where('id', $id)->where('user_id', $user->id)->with([
            'items . product',
            'store',
            'billingInformation . taxRegime',
            'billingInformation . cfdiUsage',
            'microsoftAccount',
            'paymentResponse',
            'currency',
        ])->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $order,
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
            'cancellation_reason' => 'nullable|string|max:500',
        ]);

        $user = Auth::user();

        $daysToCancel = (int) Config::get('orders.days_to_cancel');

        $order = Order::where('id', $id)
            ->where('user_id', $user->id)
            ->whereRaw('DATE_ADD(created_at, INTERVAL ? DAY) >= NOW()', [$daysToCancel])
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        if ($order->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Order cannot be cancelled. Current status: ' . $order->status,
            ], 400);
        }

        $order->update([
            'status'              => 'cancelled',
            'cancellation_reason' => $request->cancellation_reason,
            'cancelled_at'        => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Order cancelled successfully',
            'data'    => $order,
        ]);
    }

    /**
     * @OA\Get(
     *     path=" / api / v1 / orders / tracking /{
    order_number}",
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
        $order = Order::where('order_number', $orderNumber)->with([
            'items.product',
            'store',
            'billingInformation',
            'microsoftAccount',
            'paymentResponse',
        ])->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        $trackingInfo = [
            'order_number'       => $order->order_number,
            'status'             => $order->status,
            'total_amount'       => $order->total_amount,
            'currency'           => $order->currency_id,
            'created_at'         => $order->created_at->format('Y-m-d H:i:s'),
            'updated_at'         => $order->updated_at->format('Y-m-d H:i:s'),
            'tracking_number'    => $order->tracking_number,
            'estimated_delivery' => $order->estimated_delivery_date,
            'store'              => $order->store->only(['name', 'slug']),
            'items_count'        => $order->items->count(),
            'timeline'           => [
                [
                    'status'    => 'pending',
                    'date'      => $order->created_at->format('Y-m-d H:i:s'),
                    'completed' => true,
                ],
                [
                    'status'    => 'processing',
                    'date'      => $order->status === 'processing' ? $order->updated_at->format('Y-m-d H:i:s') : null,
                    'completed' => in_array($order->status, ['processing', 'completed']),
                ],
                [
                    'status'    => 'completed',
                    'date'      => $order->status === 'completed' ? $order->processed_at?->format('Y-m-d H:i:s') : null,
                    'completed' => $order->status === 'completed',
                ],
            ],
        ];

        return response()->json([
            'success' => true,
            'data'    => $trackingInfo,
        ]);
    }

    /**
     * @OA\Get(
     *     path=" / api / v1 / orders /{
    orderNumber}/payment - details",
     *     tags={"Orders"},
     *     summary="Get order payment details",
     *     description="Returns detailed payment information for an order by order number",
     *     @OA\Parameter(
     *         name="orderNumber",
     *         in="path",
     *         required=true,
     *         description="Order number",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payment details retrieved successfully"
     *     )
     * )
     */
    public function getPaymentDetails($orderNumber): JsonResponse
    {
        $order = Order::where('order_number', $orderNumber)->with([
            'paymentResponse',
            'billingInformation',
            'microsoftAccount',
            'currency',
        ])->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        $paymentDetails = [
            'order_number'   => $order->order_number,
            'payment_status' => $order->payment_status,
            'payment_method' => $order->payment_method,
            'total_amount'   => $order->total_amount,
            'currency'       => $order->currency->code ?? 'MXN',
            'paid_at'        => $order->paid_at?->format('Y-m-d H:i:s'),
            'transaction_id' => $order->transaction_id,
        ];

        // Agregar información de tarjeta si existe
        if ($order->paymentResponse && $order->paymentResponse->card_last_four) {
            $cardInfo                    = $order->paymentResponse->getCardInfo();
            $paymentDetails['card_info'] = [
                'masked_number' => $cardInfo['masked_number'],
                'last_four'     => $cardInfo['last_four'],
                'card_type'     => $cardInfo['card_type'],
                'card_name'     => $order->paymentResponse->card_name,
                'display_text'  => $cardInfo['display_text'],
            ];
        }

        // Agregar información de transacción si existe
        if ($order->paymentResponse) {
            $paymentDetails['transaction_details'] = [
                'auth_code'    => $order->paymentResponse->auth_code,
                'reference'    => $order->paymentResponse->transaction_reference,
                'gateway'      => $order->paymentResponse->gateway,
                'processed_at' => $order->paymentResponse->created_at?->format('Y-m-d H:i:s'),
            ];
        }

        // Agregar información de facturación si existe
        if ($order->billingInformation) {
            $paymentDetails['billing_info'] = [
                'rfc'          => $order->billingInformation->rfc,
                'company_name' => $order->billingInformation->company_name,
                'email'        => $order->billingInformation->email,
            ];
        }

        // Agregar información de Microsoft si existe
        if ($order->microsoftAccount) {
            $paymentDetails['microsoft_account'] = [
                'domain'       => $order->microsoftAccount->domain,
                'organization' => $order->microsoftAccount->organization,
                'is_active'    => $order->microsoftAccount->is_active,
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => $paymentDetails,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api / v1 / orders / statistics",
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
            'total_orders'        => Order::where('user_id', $user->id)->count(),
            'pending_orders'      => Order::where('user_id', $user->id)->where('status', 'pending')->count(),
            'processing_orders'   => Order::where('user_id', $user->id)->where('status', 'processing')->count(),
            'completed_orders'    => Order::where('user_id', $user->id)->where('status', 'completed')->count(),
            'cancelled_orders'    => Order::where('user_id', $user->id)->where('status', 'cancelled')->count(),
            'total_spent'         => Order::where('user_id', $user->id)
                ->where('status', '!=', 'cancelled')
                ->sum('total_amount'),
            'average_order_value' => Order::where('user_id', $user->id)
                ->where('status', '!=', 'cancelled')
                ->avg('total_amount'),
            'orders_by_month'     => Order::where('user_id', $user->id)
                ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, COUNT(*) as count, SUM(total_amount) as total')
                ->groupBy('year', 'month')
                ->orderBy('year', 'desc')
                ->orderBy('month', 'desc')
                ->limit(12)
                ->get(),
        ];

        return response()->json([
            'success' => true,
            'data'    => $stats,
        ]);
    }

    /**
     * @OA\Post(
     *     path=" / api / v1 / orders /{
    id}/process - microsoft",
     *     tags={"Orders"},
     *     summary="Process order in Microsoft Partner Center",
     *     description="Sends the order to Microsoft Partner Center for provisioning",
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
     *         description="Order processed successfully in Microsoft Partner Center"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Order cannot be processed(invalid status or other error)"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Order not found"
     *     )
     * )
     */
    public function processMicrosoft($id): JsonResponse
    {
        try {
            $user = Auth::user();

            // Buscar la orden del usuario autenticado
            $order = Order::where('id', $id)->where('user_id', $user->id)->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Orden no encontrada',
                ], 404);
            }

            // Verificar que la orden esté en estado 'processing'
            if ($order->status !== 'processing') {
                return response()->json([
                    'success' => false,
                    'message' => 'La orden debe estar en estado "procesando" para ser enviada a Microsoft Partner Center',
                ], 400);
            }

            // Cargar relaciones necesarias
            $order->load(['cartItems.product', 'microsoftAccount', 'user']);

            // Verificar que la orden tenga una cuenta Microsoft asociada
            if (!$order->microsoftAccount) {
                return response()->json([
                    'success' => false,
                    'message' => 'La orden debe tener una cuenta Microsoft asociada',
                ], 400);
            }

            // Usar el servicio de Partner Center para procesar la orden
            $partnerCenterService = app(\App\Services\PartnerCenterProvisioningService::class);

            $result = $partnerCenterService->processOrder($order->id);

            // Verificar si el procesamiento fue exitoso
            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => $result['message'],
                    'data'    => [
                        'order_id'            => $result['order_id'] ?? $order->id,
                        'order_status'        => $result['order_status'] ?? $order->status,
                        'fulfillment_status'  => $result['fulfillment_status'] ?? $order->fulfillment_status,
                        'total_products'      => $result['total_products'] ?? 0,
                        'successful_products' => $result['successful_products'] ?? 0,
                        'failed_products'     => $result['failed_products'] ?? 0,
                        'product_details'     => $result['product_details'] ?? [],
                        'cart_id'             => $result['cart_id'] ?? null,
                        'subscriptions_count' => $result['subscriptions_count'] ?? 0,
                    ],
                ]);
            } else {
                // El servicio devolvió error pero no lanzó excepción
                // Usar 200 para que el frontend pueda procesar los detalles del error
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Error al procesar la orden en Microsoft Partner Center',
                    'data'    => [
                        'order_id'            => $result['order_id'] ?? $order->id,
                        'order_status'        => $result['order_status'] ?? 'processing',
                        'fulfillment_status'  => $result['fulfillment_status'] ?? 'pending',
                        'total_products'      => $result['total_products'] ?? 0,
                        'successful_products' => $result['successful_products'] ?? 0,
                        'failed_products'     => $result['failed_products'] ?? 0,
                        'product_details'     => $result['product_details'] ?? [],
                    ],
                ], 200); // 200 para que frontend pueda procesar los detalles
            }
        } catch (\Exception $e) {
            \Log::error('Error processing order in Microsoft Partner Center: ' . $e->getMessage(), [
                'order_id' => $id,
                'user_id'  => Auth::id(),
                'error'    => $e->getMessage(),
                'trace'    => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la orden en Microsoft Partner Center: ' . $e->getMessage(),
            ], 500);
        }
    }
}
