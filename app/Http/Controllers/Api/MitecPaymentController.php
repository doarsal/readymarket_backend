<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Payment\MitecPaymentService;
use App\Models\PaymentSession;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 *     name="MITEC Payments",
 *     description="API Endpoints for MITEC Payment Gateway - Secure payment processing"
 * )
 */
class MitecPaymentController extends Controller
{
    protected MitecPaymentService $mitecPaymentService;

    public function __construct(MitecPaymentService $mitecPaymentService)
    {
        $this->mitecPaymentService = $mitecPaymentService;
        $this->middleware('auth:sanctum')->except(['webhook', 'getConfig']);
        $this->middleware('throttle:60,1')->only(['processPayment']);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/payments/mitec/process",
     *     tags={"MITEC Payments"},
     *     summary="Process payment with MITEC gateway",
     *     description="Processes a secure payment through MITEC 3DS v2",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"card_number", "card_name", "exp_month", "exp_year", "cvv", "amount"},
     *             @OA\Property(property="card_number", type="string", example="4111111111111111", description="Card number (test card)"),
     *             @OA\Property(property="card_name", type="string", example="JOHN DOE", description="Cardholder name"),
     *             @OA\Property(property="exp_month", type="integer", example=12, description="Expiry month (1-12)"),
     *             @OA\Property(property="exp_year", type="integer", example=25, description="Expiry year (YY format)"),
     *             @OA\Property(property="cvv", type="string", example="123", description="Card CVV"),
     *             @OA\Property(property="amount", type="number", format="float", example=100.00, description="Transaction amount"),
     *             @OA\Property(property="currency", type="string", example="MXN", description="Currency code (optional)"),
     *             @OA\Property(property="billing_phone", type="string", example="5555555555", description="Billing phone (optional)"),
     *             @OA\Property(property="billing_email", type="string", example="test@example.com", description="Billing email (optional)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payment form generated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="transaction_reference", type="string", example="MKT1693234567_A1B2C3D4"),
     *             @OA\Property(property="form_html", type="string", description="HTML form to submit to MITEC"),
     *             @OA\Property(property="mitec_url", type="string", example="https://vip.e-pago.com.mx/ws3dsecure/Auth3dsecure"),
     *             @OA\Property(property="message", type="string", example="Formulario de pago generado correctamente")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Los datos proporcionados no son válidos"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error interno del servidor")
     *         )
     *     )
     * )
     */
    public function processPayment(Request $request): JsonResponse
    {
        try {
            $userId = Auth::id();

            // Obtener cart_token del header y convertirlo a cart_id
            $cartToken = $request->header('X-Cart-Token');
            $cartId = null;

            if ($cartToken) {
                $cart = \App\Models\Cart::where('cart_token', $cartToken)->first();
                if ($cart) {
                    $cartId = $cart->id;
                    Log::info('MITEC: Cart encontrado', [
                        'cart_token_hash' => hash('sha256', $cartToken), // Hash del token para seguridad
                        'cart_id' => $cartId,
                        'user_id' => $userId
                    ]);
                } else {
                    Log::warning('MITEC: Cart no encontrado', [
                        'cart_token_hash' => hash('sha256', $cartToken) // Hash en lugar de token completo
                    ]);
                }
            }

            // Procesar pago con MITEC (ahora con cart_id)
            $result = $this->mitecPaymentService->processPayment(
                $request->all(),
                $userId,
                $cartId
            );

            Log::info('MITEC: Resultado del servicio', [
                'success' => $result['success'],
                'has_reference' => isset($result['transaction_reference']),
                'has_form_html' => isset($result['form_html']),
                'error' => $result['error'] ?? null
            ]);

            if ($result['success']) {
                // Verificar que tenemos todos los datos necesarios
                if (empty($result['transaction_reference'])) {
                    return response()->json(['success' => false, 'message' => 'transaction_reference vacío'], 500);
                }

                if (empty($result['form_html'])) {
                    return response()->json(['success' => false, 'message' => 'form_html vacío'], 500);
                }

                if (empty($result['mitec_url'])) {
                    return response()->json(['success' => false, 'message' => 'mitec_url vacío'], 500);
                }

                // Guardar la transacción en base de datos para mostrar el formulario
                try {
                    $paymentSession = PaymentSession::createForPayment(
                        $result['transaction_reference'],
                        $result['form_html'],
                        $result['mitec_url'],
                        $userId,
                        $cartId
                    );

                } catch (\Exception $dbError) {
                    Log::error('MITEC: Error guardando sesión de pago', [
                        'error' => $dbError->getMessage(),
                        'file' => $dbError->getFile(),
                        'line' => $dbError->getLine(),
                        'reference' => $result['transaction_reference'],
                        'user_id' => $userId,
                        'cart_id' => $cartId
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Error guardando la sesión de pago: ' . $dbError->getMessage(),
                        'error_details' => [
                            'file' => $dbError->getFile(),
                            'line' => $dbError->getLine()
                        ]
                    ], 500);
                }

                return response()->json([
                    'success' => true,
                    'transaction_reference' => $result['transaction_reference'],
                    'redirect_url' => url('/mitec-payment/' . $result['transaction_reference']),
                    'message' => 'Pago iniciado correctamente'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['error'],
                    'details' => $result['message'] ?? null
                ], 500);
            }

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Los datos proporcionados no son válidos',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Error crítico en procesamiento MITEC', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor',
                'error_detail' => $e->getMessage(), // ← AGREGAR DETALLE DEL ERROR
                'error_file' => $e->getFile() . ':' . $e->getLine(), // ← AGREGAR ARCHIVO Y LÍNEA
                'error_id' => uniqid('error_')
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/payments/mitec/webhook",
     *     tags={"MITEC Payments"},
     *     summary="MITEC payment webhook",
     *     description="Receives payment status updates from MITEC",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="transaction_reference", type="string", description="Transaction reference"),
     *             @OA\Property(property="xml_response", type="string", description="Decrypted XML response"),
     *             @OA\Property(property="parsed_data", type="object", description="Parsed response data"),
     *             @OA\Property(property="status", type="string", description="Payment status"),
     *             @OA\Property(property="source", type="string", description="Source of webhook call")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Webhook processed successfully"
     *     )
     * )
     */
    public function webhook(Request $request): JsonResponse
    {
        try {
            Log::info('===== WEBHOOK MITEC RECIBIDO =====', [
                'source' => $request->input('source', 'external'),
                'transaction_reference' => $request->input('transaction_reference'),
                'ip' => $request->ip(),
                'all_request_data' => $request->all()
            ]);

            // Validar datos requeridos
            $transactionReference = $request->input('transaction_reference');
            $xmlResponse = $request->input('xml_response');
            $parsedData = $request->input('parsed_data');

            Log::info('Datos del webhook validados', [
                'has_transaction_ref' => !empty($transactionReference),
                'has_xml_response' => !empty($xmlResponse),
                'has_parsed_data' => !empty($parsedData),
                'parsed_data_keys' => $parsedData ? array_keys($parsedData) : 'NULL'
            ]);

            if (!$transactionReference) {
                return response()->json([
                    'success' => false,
                    'message' => 'transaction_reference es requerido'
                ], 400);
            }

            if (!$xmlResponse) {
                return response()->json([
                    'success' => false,
                    'message' => 'xml_response es requerido'
                ], 400);
            }

            if (!$parsedData) {
                return response()->json([
                    'success' => false,
                    'message' => 'parsed_data es requerido'
                ], 400);
            }

            // PRIMERO: Buscar PaymentSession ANTES de que se borre
            $paymentSession = null;
            if ($transactionReference) {
                $paymentSession = \App\Models\PaymentSession::where('transaction_reference', $transactionReference)->first();

                if (!$paymentSession) {
                    // Intentar sin el sufijo si tiene guión bajo
                    $baseName = explode('_', $transactionReference)[0];
                    $paymentSession = \App\Models\PaymentSession::where('transaction_reference', 'LIKE', $baseName . '%')->first();
                }
            }

            Log::info('PaymentSession buscada en webhook', [
                'transaction_reference' => $transactionReference,
                'payment_session_found' => $paymentSession ? $paymentSession->id : 'NO_ENCONTRADA',
                'cart_id' => $paymentSession ? $paymentSession->cart_id : 'N/A',
                'user_id' => $paymentSession ? $paymentSession->user_id : 'N/A'
            ]);

            // Procesar la respuesta usando el servicio
            $paymentResponseService = app(\App\Services\Payment\PaymentResponseService::class);

            $paymentResponse = $paymentResponseService->processPaymentResponse(
                $parsedData,
                $xmlResponse,
                $request->ip(),
                $request->header('User-Agent'),
                $paymentSession  // PASAR PaymentSession encontrada
            );            Log::info('Webhook MITEC procesado exitosamente', [
                'transaction_reference' => $transactionReference,
                'payment_response_id' => $paymentResponse->id,
                'payment_status' => $paymentResponse->payment_status,
                'order_created' => $paymentResponse->order_id ? 'yes' : 'no'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Webhook procesado correctamente',
                'data' => [
                    'payment_response_id' => $paymentResponse->id,
                    'payment_status' => $paymentResponse->payment_status,
                    'order_id' => $paymentResponse->order_id,
                    'processed_at' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error procesando webhook MITEC', [
                'error' => $e->getMessage(),
                'transaction_reference' => $request->input('transaction_reference'),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error procesando webhook: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/payments/mitec/config",
     *     tags={"MITEC Payments"},
     *     summary="Get MITEC configuration",
     *     description="Returns public MITEC configuration (non-sensitive data)",
     *     @OA\Response(
     *         response=200,
     *         description="Configuration retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="currency", type="string", example="MXN"),
     *             @OA\Property(property="supported_cards", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="min_amount", type="number", example=0.01),
     *             @OA\Property(property="max_amount", type="number", example=999999.99)
     *         )
     *     )
     * )
     */
    public function getConfig(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'config' => [
                'currency' => env('MITEC_DEFAULT_CURRENCY', 'MXN'),
                'supported_cards' => ['amex'], // Solo AMEX
                'min_amount' => (float) env('MITEC_MIN_AMOUNT', 0.01),
                'max_amount' => (float) env('MITEC_MAX_AMOUNT', 999999.99),
                'billing_required' => env('MITEC_BILLING_REQUIRED', false),
                'environment' => env('MITEC_ENVIRONMENT', 'sandbox')
            ]
        ]);
    }
}
