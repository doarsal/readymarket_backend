<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\PurchaseConfirmationEmailService;
use App\Services\WhatsAppNotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class TestPurchaseConfirmationController extends Controller
{
    protected PurchaseConfirmationEmailService $purchaseEmailService;
    protected WhatsAppNotificationService $whatsAppService;

    public function __construct(
        PurchaseConfirmationEmailService $purchaseEmailService,
        WhatsAppNotificationService $whatsAppService
    ) {
        $this->purchaseEmailService = $purchaseEmailService;
        $this->whatsAppService = $whatsAppService;
    }

    /**
     * Test purchase confirmation emails and WhatsApp for a specific order
     */
    public function testConfirmations(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'order_id' => 'required|integer|exists:orders,id'
            ]);

            $orderId = $request->input('order_id');

            // Buscar la orden con todas las relaciones
            $order = Order::with([
                'user',
                'currency',
                'items.product',
                'billing_information.tax_regime',
                'billing_information.cfdi_usage',
                'microsoft_account'
            ])->find($orderId);

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Orden no encontrada'
                ], 404);
            }

            // Datos de transacción de prueba (simulando datos reales del frontend)
            $paymentData = [
                'reference' => $order->transaction_id ?? 'TEST-REF-' . time(),
                'auth_code' => 'TEST-AUTH-' . rand(100000, 999999),
                'amount' => $order->total_amount,
                'currency' => $order->currency->code ?? 'MXN',
                'processed_at' => $order->paid_at?->toISOString() ?? now()->toISOString()
            ];

            $microsoftAccount = $order->microsoft_account;

            Log::info('Test: Enviando confirmaciones de compra', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'payment_data' => $paymentData,
                'customer_email' => $order->user->email,
                'customer_phone' => $order->user->phone,
                'has_microsoft_account' => $microsoftAccount ? true : false
            ]);

            $results = [
                'order' => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'total_amount' => $order->total_amount,
                    'customer_email' => $order->user->email,
                    'customer_phone' => $order->user->phone
                ],
                'payment_data' => $paymentData,
                'email_results' => [],
                'whatsapp_results' => []
            ];

            // Enviar confirmaciones por email
            try {
                $customerEmailSent = $this->purchaseEmailService->sendCustomerConfirmation($order, $microsoftAccount, $paymentData);
                $adminEmailSent = $this->purchaseEmailService->sendAdminConfirmation($order, $microsoftAccount, $paymentData);

                $results['email_results'] = [
                    'customer_sent' => $customerEmailSent,
                    'admin_sent' => $adminEmailSent,
                    'status' => ($customerEmailSent && $adminEmailSent) ? 'success' : 'partial_success'
                ];
            } catch (\Exception $e) {
                $results['email_results'] = [
                    'error' => $e->getMessage(),
                    'status' => 'error'
                ];
                Log::error('Test: Error enviando emails', ['error' => $e->getMessage()]);
            }

            // Enviar confirmaciones por WhatsApp
            try {
                // Al cliente (si tiene teléfono)
                $customerWhatsAppSent = false;
                if ($order->user->phone) {
                    $this->whatsAppService->sendPurchaseConfirmationToCustomer($order, $microsoftAccount, $paymentData);
                    $customerWhatsAppSent = true;
                }

                // A los administradores
                $this->whatsAppService->sendPurchaseConfirmationToAdmins($order, $microsoftAccount, $paymentData);

                $results['whatsapp_results'] = [
                    'customer_sent' => $customerWhatsAppSent,
                    'customer_phone' => $order->user->phone,
                    'admin_sent' => true,
                    'status' => 'success'
                ];
            } catch (\Exception $e) {
                $results['whatsapp_results'] = [
                    'error' => $e->getMessage(),
                    'status' => 'error'
                ];
                Log::error('Test: Error enviando WhatsApp', ['error' => $e->getMessage()]);
            }

            // Determinar el estado general del test
            $overallSuccess = (
                ($results['email_results']['status'] ?? 'error') === 'success' ||
                ($results['whatsapp_results']['status'] ?? 'error') === 'success'
            );

            return response()->json([
                'success' => $overallSuccess,
                'message' => $overallSuccess ? 'Confirmaciones enviadas correctamente' : 'Hubo errores enviando las confirmaciones',
                'data' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('Test: Error general en confirmaciones', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error general: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get test order data to help with testing
     */
    public function getTestOrder(): JsonResponse
    {
        try {
            // Buscar una orden con status 'paid' o 'completed' para testing
            $order = Order::with([
                'user',
                'currency',
                'items.product',
                'billing_information',
                'microsoft_account'
            ])
            ->whereIn('payment_status', ['paid'])
            ->whereNotNull('paid_at')
            ->orderBy('created_at', 'desc')
            ->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró ninguna orden pagada para testing'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Orden encontrada para testing',
                'data' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'total_amount' => $order->total_amount,
                    'payment_status' => $order->payment_status,
                    'customer' => [
                        'name' => $order->user->name,
                        'email' => $order->user->email,
                        'phone' => $order->user->phone
                    ],
                    'microsoft_account' => $order->microsoft_account ? [
                        'domain' => $order->microsoft_account->domain,
                        'id' => $order->microsoft_account->id
                    ] : null,
                    'billing_info' => $order->billing_information ? [
                        'rfc' => $order->billing_information->rfc,
                        'company' => $order->billing_information->organization
                    ] : null,
                    'items_count' => $order->items->count(),
                    'created_at' => $order->created_at->format('Y-m-d H:i:s')
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Test: Error obteniendo orden de prueba', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
}
