<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartCheckOutItem;
use App\Models\PaymentSession;
use App\Services\Payment\MitecPaymentService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
        $this->middleware('auth:sanctum')->except(['webhook', 'getConfig', 'handleCallback']);
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
            $cartId    = null;

            // Obtener billing_information_id y microsoft_account_id del request
            $billingInformationId = $request->input('billing_information_id');
            $microsoftAccountId   = $request->input('microsoft_account_id');
            $paymentMethod        = $request->input('payment_method',
                'credit_card'); // Default a credit_card si no se especifica

            Log::info('MITEC: Datos de entrada del request', [
                'headers'                => $request->headers->all(),
                'cart_token_recibido'    => $cartToken ? 'SÍ' : 'NO',
                'cart_token_hash'        => $cartToken ? hash('sha256', $cartToken) : 'N/A',
                'billing_information_id' => $billingInformationId,
                'microsoft_account_id'   => $microsoftAccountId,
                'payment_method'         => $paymentMethod,
                'user_id'                => $userId,
            ]);

            if ($cartToken) {
                $cart = Cart::where('cart_token', $cartToken)->first();
                if ($cart) {
                    $cartId = $cart->id;

                    Log::info('MITEC: Cart encontrado', [
                        'cart_token_hash' => hash('sha256', $cartToken), // Hash del token para seguridad
                        'cart_id'         => $cartId,
                        'user_id'         => $userId,
                        'cart_status'     => $cart->status,
                        'cart_total'      => $cart->total_amount,
                    ]);
                } else {
                    Log::warning('MITEC: Cart no encontrado con token', [
                        'cart_token_hash'   => hash('sha256', $cartToken), // Hash en lugar de token completo
                        'total_carts_en_db' => Cart::count(),
                        'carts_recientes'   => Cart::latest()->take(3)->pluck('id', 'cart_token'),
                    ]);
                }
            } else {
                Log::warning('MITEC: No se recibió cart_token en headers');
            }

            // Procesar pago con MITEC (ahora con cart_id)
            $result = $this->mitecPaymentService->processPayment($request->all(), $userId, $cartId);

            Log::info('MITEC: Resultado del servicio', [
                'success'       => $result['success'],
                'has_reference' => isset($result['transaction_reference']),
                'has_form_html' => isset($result['form_html']),
                'error'         => $result['error'] ?? null,
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
                    Log::info('MITEC: Creando PaymentSession', [
                        'transaction_reference'  => $result['transaction_reference'],
                        'user_id'                => $userId,
                        'cart_id'                => $cartId,
                        'billing_information_id' => $billingInformationId,
                        'microsoft_account_id'   => $microsoftAccountId,
                        'cart_id_tipo'           => gettype($cartId),
                        'cart_id_es_null'        => $cartId === null ? 'SÍ' : 'NO',
                    ]);

                    $paymentSession = PaymentSession::createForPayment($result['transaction_reference'],
                        $result['form_html'], $result['mitec_url'], $userId, $cartId, $billingInformationId,
                        $microsoftAccountId, $paymentMethod);

                    Log::info('MITEC: PaymentSession creado exitosamente', [
                        'payment_session_id'              => $paymentSession->id,
                        'cart_id_guardado'                => $paymentSession->cart_id,
                        'user_id_guardado'                => $paymentSession->user_id,
                        'billing_information_id_guardado' => $paymentSession->billing_information_id,
                        'microsoft_account_id_guardado'   => $paymentSession->microsoft_account_id,
                        'payment_method_guardado'         => $paymentSession->payment_method,
                    ]);
                } catch (\Exception $dbError) {
                    Log::error('MITEC: Error guardando sesión de pago', [
                        'error'     => $dbError->getMessage(),
                        'file'      => $dbError->getFile(),
                        'line'      => $dbError->getLine(),
                        'reference' => $result['transaction_reference'],
                        'user_id'   => $userId,
                        'cart_id'   => $cartId,
                    ]);

                    return response()->json([
                        'success'       => false,
                        'message'       => 'Error guardando la sesión de pago: ' . $dbError->getMessage(),
                        'error_details' => [
                            'file' => $dbError->getFile(),
                            'line' => $dbError->getLine(),
                        ],
                    ], 500);
                }

                return response()->json([
                    'success'               => true,
                    'transaction_reference' => $result['transaction_reference'],
                    'redirect_url'          => url('/mitec-payment/' . $result['transaction_reference']),
                    'message'               => 'Pago iniciado correctamente',
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['error'],
                    'details' => $result['message'] ?? null,
                ], 500);
            }
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Los datos proporcionados no son válidos',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error crítico en procesamiento MITEC', [
                'error'   => $e->getMessage(),
                'user_id' => Auth::id(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success'      => false,
                'message'      => 'Error interno del servidor',
                'error_detail' => $e->getMessage(), // ← AGREGAR DETALLE DEL ERROR
                'error_file'   => $e->getFile() . ':' . $e->getLine(), // ← AGREGAR ARCHIVO Y LÍNEA
                'error_id'     => uniqid('error_'),
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
                'source'                => $request->input('source', 'external'),
                'transaction_reference' => $request->input('transaction_reference'),
                'ip'                    => $request->ip(),
                'all_request_data'      => $request->all(),
            ]);

            // Validar datos requeridos
            $transactionReference = $request->input('transaction_reference');
            $xmlResponse          = $request->input('xml_response');
            $parsedData           = $request->input('parsed_data');

            Log::info('Datos del webhook validados', [
                'has_transaction_ref' => !empty($transactionReference),
                'has_xml_response'    => !empty($xmlResponse),
                'has_parsed_data'     => !empty($parsedData),
                'parsed_data_keys'    => $parsedData ? array_keys($parsedData) : 'NULL',
            ]);

            if (!$transactionReference) {
                return response()->json([
                    'success' => false,
                    'message' => 'transaction_reference es requerido',
                ], 400);
            }

            if (!$xmlResponse) {
                return response()->json([
                    'success' => false,
                    'message' => 'xml_response es requerido',
                ], 400);
            }

            if (!$parsedData) {
                return response()->json([
                    'success' => false,
                    'message' => 'parsed_data es requerido',
                ], 400);
            }

            // PRIMERO: Buscar PaymentSession ANTES de que se borre
            $paymentSession = null;
            if ($transactionReference) {
                $paymentSession = \App\Models\PaymentSession::where('transaction_reference', $transactionReference)
                    ->first();

                if (!$paymentSession) {
                    // Intentar sin el sufijo si tiene guión bajo
                    $baseName       = explode('_', $transactionReference)[0];
                    $paymentSession = \App\Models\PaymentSession::where('transaction_reference', 'LIKE',
                        $baseName . '%')->first();
                }
            }

            Log::info('PaymentSession buscada en webhook', [
                'transaction_reference' => $transactionReference,
                'payment_session_found' => $paymentSession ? $paymentSession->id : 'NO_ENCONTRADA',
                'cart_id'               => $paymentSession ? $paymentSession->cart_id : 'N/A',
                'user_id'               => $paymentSession ? $paymentSession->user_id : 'N/A',
            ]);

            // Procesar la respuesta usando el servicio
            $paymentResponseService = app(\App\Services\Payment\PaymentResponseService::class);

            $paymentResponse = $paymentResponseService->processPaymentResponse($parsedData, $xmlResponse,
                $request->ip(), $request->header('User-Agent'), $paymentSession  // PASAR PaymentSession encontrada
            );
            Log::info('Webhook MITEC procesado exitosamente', [
                'transaction_reference' => $transactionReference,
                'payment_response_id'   => $paymentResponse->id,
                'payment_status'        => $paymentResponse->payment_status,
                'order_created'         => $paymentResponse->order_id ? 'yes' : 'no',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Webhook procesado correctamente',
                'data'    => [
                    'payment_response_id' => $paymentResponse->id,
                    'payment_status'      => $paymentResponse->payment_status,
                    'order_id'            => $paymentResponse->order_id,
                    'processed_at'        => now()->toISOString(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error procesando webhook MITEC', [
                'error'                 => $e->getMessage(),
                'transaction_reference' => $request->input('transaction_reference'),
                'trace'                 => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error procesando webhook: ' . $e->getMessage(),
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
            'config'  => [
                'currency'         => config('mitec.default_currency', 'MXN'),
                'supported_cards'  => ['amex'], // Solo AMEX
                'min_amount'       => (float) config('mitec.min_amount', 0.01),
                'max_amount'       => (float) config('mitec.max_amount', 999999.99),
                'billing_required' => config('mitec.billing_required', false),
                'environment'      => config('mitec.environment', 'sandbox'),
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/payment-sessions/{reference}",
     *     tags={"MITEC Payments"},
     *     summary="Get payment session by reference",
     *     description="Retrieves payment session data including form HTML for MITEC submission",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="reference",
     *         in="path",
     *         required=true,
     *         description="Transaction reference",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payment session retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="form_html", type="string", description="HTML form for MITEC submission"),
     *             @OA\Property(property="mitec_url", type="string", description="MITEC payment URL"),
     *             @OA\Property(property="transaction_reference", type="string", description="Transaction reference")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Payment session not found"
     *     )
     * )
     */
    public function getPaymentSession(string $reference): JsonResponse
    {
        try {
            $paymentSession = PaymentSession::where('transaction_reference', $reference)
                ->where('user_id', Auth::id())
                ->first();

            if (!$paymentSession) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sesión de pago no encontrada',
                ], 404);
            }

            return response()->json([
                'success'               => true,
                'form_html'             => $paymentSession->form_html,
                'mitec_url'             => $paymentSession->mitec_url,
                'transaction_reference' => $paymentSession->transaction_reference,
                'created_at'            => $paymentSession->created_at,
            ]);
        } catch (\Exception $e) {
            Log::error('Error obteniendo sesión de pago', [
                'reference' => $reference,
                'user_id'   => Auth::id(),
                'error'     => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor',
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/payments/mitec/process-token",
     *     tags={"MITEC Payments"},
     *     summary="Process MITEC response token",
     *     description="Processes the encrypted token returned by MITEC after payment",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="token", type="string", description="Encrypted token from MITEC"),
     *             example={"token": "encrypted_token_here"}
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Token processed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="status", type="string", description="Transaction status"),
     *             @OA\Property(property="data", type="object", description="Transaction details")
     *         )
     *     )
     * )
     */
    public function processToken(Request $request): JsonResponse
    {
        try {
            $token = $request->input('token');

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token requerido',
                ], 400);
            }

            Log::info('Procesando token MITEC', [
                'token'   => substr($token, 0, 20) . '...',
                'user_id' => Auth::id(),
            ]);

            // Procesar token real de MITEC
            $transactionData = $this->processRealMitecToken($token);

            if ($transactionData['success']) {
                return response()->json([
                    'success' => true,
                    'status'  => $transactionData['status'],
                    'data'    => $transactionData['data'],
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $transactionData['message'],
                ], 400);
            }
        } catch (\Exception $e) {
            Log::error('Error procesando token MITEC', [
                'error'   => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error procesando el token',
            ], 500);
        }
    }

    /**
     * Simulate token processing for development/testing
     * In production, this would decrypt and validate the actual MITEC token
     */
    private function simulateTokenProcessing(string $token): bool
    {
        // Simple simulation logic based on token patterns
        // In real implementation, would decrypt token and check actual transaction status

        // If token contains certain patterns, simulate failure
        $failurePatterns = ['DECLINE', 'REJECT', 'ERROR', 'FAIL'];

        foreach ($failurePatterns as $pattern) {
            if (stripos($token, $pattern) !== false) {
                return false;
            }
        }

        // Check if token length suggests an error (very short tokens might be error tokens)
        if (strlen($token) < 20) {
            return false;
        }

        // For testing: simulate 70% success rate
        // This gives us both success and failure scenarios
        return rand(1, 10) <= 7;
    }

    /**
     * Procesa el token real de MITEC desencriptando la respuesta
     */
    private function processRealMitecToken(string $token): array
    {
        try {
            Log::info('MITEC: Procesando token real', [
                'token_length'  => strlen($token),
                'token_preview' => substr($token, 0, 10) . '...' . substr($token, -5),
            ]);

            // Solo manejar tokens de prueba específicos
            if (in_array($token, ['test', 'success', 'fail', 'error'])) {
                Log::info('MITEC: Usando token de prueba simple', ['token' => $token]);

                return $this->handleTestToken($token);
            }

            // Si el token empieza con "MKT", es una referencia de transacción, NO un token encriptado
            if (strpos($token, 'MKT') === 0) {
                Log::error('MITEC: Callback recibió referencia en lugar de token encriptado', [
                    'reference'   => $token,
                    'explanation' => 'MITEC debe enviar token encriptado, no referencia',
                ]);

                return [
                    'success'    => false,
                    'status'     => 'error',
                    'error_code' => 'INVALID_TOKEN_FORMAT',
                    'data'       => [
                        'reference' => $token,
                        'message'   => 'Token inválido: se recibió referencia en lugar de token encriptado',
                    ],
                ];
            }

            // PROCESAMIENTO REAL DE MITEC - desencriptar el token
            Log::info('MITEC: Intentando desencriptar token real');
            $decryptedData = $this->decryptMitecResponse($token);
            Log::info('MITEC: Token desencriptado exitosamente');

            // Parsear la respuesta XML real de MITEC
            $xmlData = $this->parseXmlResponse($decryptedData);
            Log::info('MITEC: XML parseado', ['xml_keys' => array_keys($xmlData)]);

            // Determinar el status basado en los códigos de respuesta reales de MITEC
            $isSuccess = $this->isTransactionSuccessful($xmlData);

            if ($isSuccess) {
                return [
                    'success' => true,
                    'status'  => 'success',
                    'data'    => [
                        'reference'      => $xmlData['r3ds_reference'] ?? 'MKT' . time(),
                        'amount'         => $xmlData['amount'] ?? '0.00',
                        'currency'       => $xmlData['currency'] ?? 'MXN',
                        'auth_code'      => $xmlData['r3ds_authNumber'] ?? '',
                        'message'        => 'Transacción aprobada',
                        'transaction_id' => $xmlData['r3ds_dsTransId'] ?? '',
                        'card_last_four' => $xmlData['card_last_four'] ?? null,
                        'card_type'      => $xmlData['card_type'] ?? 'UNKNOWN',
                        'response_code'  => $xmlData['r3ds_responseCode'] ?? '00',
                        'raw_xml'        => $decryptedData,
                        'processed_at'   => now(),
                    ],
                ];
            } else {
                return [
                    'success' => true,
                    'status'  => 'error',
                    'data'    => [
                        'reference'    => $xmlData['r3ds_reference'] ?? 'MKT' . time(),
                        'amount'       => $xmlData['amount'] ?? '0.00',
                        'currency'     => $xmlData['currency'] ?? 'MXN',
                        'error_code'   => $xmlData['r3ds_responseCode'] ?? 'UNKNOWN',
                        'message'      => $this->getErrorMessage($xmlData['r3ds_responseCode'] ?? ''),
                        'processed_at' => now(),
                    ],
                ];
            }
        } catch (\Exception $e) {
            Log::error('Error procesando token MITEC real', [
                'error' => $e->getMessage(),
                'token' => substr($token, 0, 20) . '...',
            ]);

            return [
                'success' => false,
                'message' => 'Error desencriptando token de respuesta: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Desencripta la respuesta de MITEC usando AES-128-CBC
     */
    private function decryptMitecResponse(string $encryptedData): string
    {
        // Usar la clave existente en .env
        $keyHex = env('MITEC_KEY_HEX', '1BF745522C9903AE583C5E234F3D1CEA');
        $key    = hex2bin($keyHex);

        // Decodificar base64
        $data = base64_decode($encryptedData, true);
        if ($data === false) {
            throw new \Exception('Invalid Base64 encoding');
        }

        // Extraer IV (primeros 16 bytes) y datos encriptados
        $iv              = substr($data, 0, 16);
        $encryptedBinary = substr($data, 16);

        // Desencriptar
        $decrypted = openssl_decrypt($encryptedBinary, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);

        if ($decrypted === false) {
            throw new \Exception('Decryption failed');
        }

        // Buscar el inicio del XML si hay datos extra
        if (false !== $pos = strpos($decrypted, '<?xml')) {
            return substr($decrypted, $pos);
        }

        return $decrypted;
    }

    /**
     * Parsea la respuesta XML de MITEC
     */
    private function parseXmlResponse(string $xmlString): array
    {
        libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_string($xmlString);

            if (!$xml) {
                $errors       = libxml_get_errors();
                $errorMessage = 'XML parsing failed';
                if (!empty($errors)) {
                    $errorMessage .= ': ' . $errors[0]->message;
                }
                throw new \Exception($errorMessage);
            }

            return [
                'r3ds_reference'    => (string) ($xml->r3ds_reference ?? ''),
                'r3ds_dsTransId'    => (string) ($xml->r3ds_dsTransId ?? ''),
                'r3ds_eci'          => (string) ($xml->r3ds_eci ?? ''),
                'r3ds_cavv'         => (string) ($xml->r3ds_cavv ?? ''),
                'r3ds_authNumber'   => (string) ($xml->r3ds_authNumber ?? ''),
                'r3ds_responseCode' => (string) ($xml->r3ds_responseCode ?? ''),
                'r3ds_transStatus'  => (string) ($xml->r3ds_transStatus ?? ''),
                'amount'            => (string) ($xml->amount ?? ''),
                'currency'          => (string) ($xml->currency ?? ''),
            ];
        } catch (\Exception $e) {
            throw new \Exception('Error parsing XML: ' . $e->getMessage());
        }
    }

    /**
     * Determina si la transacción fue exitosa basado en los códigos de respuesta
     */
    private function isTransactionSuccessful(array $xmlData): bool
    {
        $transStatus  = $xmlData['r3ds_transStatus'] ?? '';
        $responseCode = $xmlData['r3ds_responseCode'] ?? '';

        // TransStatus 'Y' significa autenticación exitosa
        // ResponseCode '00' significa transacción aprobada
        return $transStatus === 'Y' && $responseCode === '00';
    }

    /**
     * Obtiene el mensaje de error basado en el código de respuesta
     */
    private function getErrorMessage(string $responseCode): string
    {
        $errorMessages = [
            '01' => 'Referirse al emisor de la tarjeta',
            '03' => 'Comercio inválido',
            '04' => 'Retener tarjeta',
            '05' => 'No autorizar',
            '12' => 'Transacción inválida',
            '13' => 'Importe inválido',
            '14' => 'Número de tarjeta inválido',
            '30' => 'Error en formato',
            '51' => 'Fondos insuficientes',
            '54' => 'Tarjeta vencida',
            '57' => 'Transacción no permitida',
            '61' => 'Excede límite de monto',
            '62' => 'Tarjeta restringida',
            '65' => 'Excede límite de frecuencia',
            '91' => 'Emisor no disponible',
            '96' => 'Error del sistema',
        ];

        return $errorMessages[$responseCode] ?? 'Error desconocido en la transacción';
    }

    /**
     * @OA\Get(
     *     path="/api/v1/payments/mitec/callback",
     *     summary="Callback de MITEC - Procesa respuesta del gateway",
     *     tags={"MITEC Payments"},
     *     @OA\Parameter(
     *         name="token",
     *         in="query",
     *         required=true,
     *         description="Token encriptado de respuesta de MITEC",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=302,
     *         description="Redirección al frontend con referencia de pago"
     *     )
     * )
     */
    public function handleCallback(Request $request)
    {
        try {
            $token = $request->get('token');

            Log::info('MITEC Callback: Inicio del proceso', [
                'token_received' => $token ? 'YES' : 'NO',
                'token_preview'  => $token ? substr($token, 0, 15) . '...' : 'NULL',
                'request_method' => $request->method(),
                'full_url'       => $request->fullUrl(),
            ]);

            if (!$token) {
                Log::error('MITEC Callback: No se recibió token');
                $errorUrl = $this->getFrontendErrorUrl('NO_TOKEN');
                Log::info('MITEC Callback: Redirigiendo a error URL', ['url' => $errorUrl]);

                return redirect($errorUrl);
            }

            Log::info('MITEC Callback: Procesando token', [
                'token_preview' => substr($token, 0, 15) . '...',
            ]);

            // Procesar el token de MITEC y guardar en BD
            $result = $this->processAndStorePayment($token);

            Log::info('MITEC Callback: Resultado del procesamiento', [
                'success'   => $result['success'],
                'reference' => $result['reference'] ?? 'NO_REFERENCE',
            ]);

            if ($result['success']) {
                // Redirigir al frontend con la referencia
                $successUrl = $this->getFrontendSuccessUrl($result['reference']);
                Log::info('MITEC Callback: Redirigiendo a success URL', ['url' => $successUrl]);

                return redirect($successUrl);
            } else {
                // Redirigir al frontend con error
                $errorUrl = $this->getFrontendErrorUrl($result['error_code'] ?? 'UNKNOWN_ERROR');
                Log::info('MITEC Callback: Redirigiendo a error URL', ['url' => $errorUrl]);

                return redirect($errorUrl);
            }
        } catch (\Exception $e) {
            Log::error('MITEC Callback Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $errorUrl = $this->getFrontendErrorUrl('PROCESSING_ERROR');
            Log::info('MITEC Callback: Redirigiendo a error URL por excepción', ['url' => $errorUrl]);

            return redirect($errorUrl);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/payments/status/{reference}",
     *     summary="Consultar estado de pago por referencia",
     *     tags={"MITEC Payments"},
     *     @OA\Parameter(
     *         name="reference",
     *         in="path",
     *         required=true,
     *         description="Referencia del pago",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Estado del pago",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="payment", type="object"),
     *             @OA\Property(property="order", type="object")
     *         )
     *     )
     * )
     */
    public function getPaymentStatus(Request $request, string $reference)
    {
        try {
            // Buscar el pago por referencia en la tabla payment_responses
            $paymentResponse = \App\Models\PaymentResponse::where('transaction_reference', $reference)->with([
                'order',
                'user',
            ])->first();

            if (!$paymentResponse) {
                return response()->json([
                    'success'    => false,
                    'message'    => 'Pago no encontrado',
                    'error_code' => 'PAYMENT_NOT_FOUND',
                ], 404);
            }

            // Si el pago fue aprobado pero NO tiene order_id, esperar y reintentar
            // Esto maneja el race condition cuando el webhook aún está procesando
            if ($paymentResponse->payment_status === 'approved' && !$paymentResponse->order_id) {
                Log::info('Payment status: Pago aprobado sin order_id, esperando creación de orden...', [
                    'reference'           => $reference,
                    'payment_response_id' => $paymentResponse->id,
                ]);

                // Reintentar hasta 3 veces con delays incrementales
                $maxRetries  = 3;
                $retryDelays = [500000, 1000000, 1500000]; // 0.5s, 1s, 1.5s en microsegundos

                for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
                    // Esperar antes de reintentar
                    usleep($retryDelays[$attempt]);

                    // Recargar el PaymentResponse desde la BD
                    $paymentResponse->refresh();
                    $paymentResponse->load(['order', 'user']);

                    // Si ya tiene orden, salir del loop
                    if ($paymentResponse->order_id) {
                        Log::info('Payment status: Orden encontrada después de reintentar', [
                            'reference'           => $reference,
                            'payment_response_id' => $paymentResponse->id,
                            'order_id'            => $paymentResponse->order_id,
                            'attempt'             => $attempt + 1,
                            'wait_time_ms'        => array_sum(array_slice($retryDelays, 0, $attempt + 1)) / 1000,
                        ]);
                        break;
                    }
                }

                // Si aún no tiene order después de todos los reintentos, loguear advertencia
                if (!$paymentResponse->order_id) {
                    Log::warning('Payment status: Pago aprobado SIN orden después de todos los reintentos', [
                        'reference'           => $reference,
                        'payment_response_id' => $paymentResponse->id,
                        'cart_id'             => $paymentResponse->cart_id,
                        'created_at'          => $paymentResponse->created_at,
                        'total_wait_time_ms'  => array_sum($retryDelays) / 1000,
                    ]);
                }
            }

            // Mapear el status al formato esperado por el frontend
            $status = 'error'; // default
            if ($paymentResponse->payment_status === 'approved') {
                $status = 'success';
            } elseif ($paymentResponse->payment_status === 'pending') {
                $status = 'pending';
            } elseif ($paymentResponse->payment_status === 'cancelled') {
                $status = 'declined';
            }

            return response()->json([
                'success' => true,
                'payment' => [
                    'reference'          => $paymentResponse->transaction_reference,
                    'status'             => $status,
                    'amount'             => $paymentResponse->amount,
                    'currency'           => 'MXN', //asumir MXN
                    'authorization_code' => $paymentResponse->auth_code,
                    'processed_at'       => $paymentResponse->created_at,
                    'error_message'      => $paymentResponse->nb_error,
                    'response_code'      => $paymentResponse->response_code,
                    'card_last_four'     => $paymentResponse->card_last_four,
                    'card_type'          => $paymentResponse->card_type,
                    'order_id'           => $paymentResponse->order_id,
                ],
                'order'   => $paymentResponse->order ? [
                    'order_number' => $paymentResponse->order->order_number,
                    'status'       => $paymentResponse->order->status,
                    'total_amount' => $paymentResponse->order->total_amount,
                ] : null,
            ]);
        } catch (\Exception $e) {
            Log::error('Error consultando estado de pago', [
                'reference' => $reference,
                'error'     => $e->getMessage(),
            ]);

            return response()->json([
                'success'    => false,
                'message'    => 'Error interno del servidor',
                'error_code' => 'INTERNAL_ERROR',
            ], 500);
        }
    }

    /**
     * Procesa el token de MITEC y guarda la transacción en base de datos
     */
    private function processAndStorePayment(string $token): array
    {
        \DB::beginTransaction();

        try {
            // Procesar el token
            $tokenResult = $this->processRealMitecToken($token);

            if (!$tokenResult['success']) {
                \DB::rollBack();

                return $tokenResult;
            }

            $data = $tokenResult['data'];

            // Buscar la PaymentSession original para obtener user_id y cart_id
            $paymentSession = \App\Models\PaymentSession::where('transaction_reference', $data['reference'])->first();
            $userId         = null;
            $cartId         = null;

            if ($paymentSession) {
                $userId = $paymentSession->user_id;
                $cartId = $paymentSession->cart_id;
                Log::info('MITEC: PaymentSession encontrada', [
                    'reference' => $data['reference'],
                    'user_id'   => $userId,
                    'cart_id'   => $cartId,
                ]);
            } else {
                Log::warning('MITEC: PaymentSession no encontrada', [
                    'reference' => $data['reference'],
                ]);
            }

            // Crear registro en tu tabla payment_responses
            $paymentResponse = \App\Models\PaymentResponse::create([
                'transaction_reference' => $data['reference'],
                'gateway'               => 'mitec',
                'payment_status'        => $tokenResult['status'] === 'success' ? 'approved' : 'error',
                'amount'                => $data['amount'],
                'auth_code'             => $data['auth_code'] ?? null,
                'response_code'         => $data['response_code'] ?? '00',
                'response_description'  => $data['message'] ?? 'Transacción procesada',
                'card_last_four'        => $data['card_last_four'] ?? null,
                'card_type'             => $data['card_type'] ?? 'UNKNOWN',
                'raw_xml_response'      => $data['raw_xml'] ?? '<xml></xml>',
                'ds_trans_id'           => $data['transaction_id'] ?? null,
                'trans_status'          => $tokenResult['status'] === 'success' ? 'Y' : 'N',
                'mitec_date'            => now(),
                'ip_address'            => request()->ip(),
                'user_agent'            => request()->userAgent(),
                'user_id'               => $userId, // Obtenido de PaymentSession
                'cart_id'               => $cartId, // Obtenido de PaymentSession
                'payment_session_id'    => $paymentSession ? $paymentSession->id : null,
                'metadata'              => [
                    'original_token' => substr($token, 0, 20) . '...',
                    'processed_at'   => now()->toISOString(),
                    'full_response'  => $data,
                ],
            ]);

            // Si el pago es exitoso, generar la orden
            if ($tokenResult['status'] === 'success') {
                $order = $this->createOrderFromPaymentResponse($paymentResponse, $paymentSession);
                $paymentResponse->update(['order_id' => $order->id]);
            }

            \DB::commit();

            return [
                'success'    => true,
                'reference'  => $paymentResponse->transaction_reference,
                'payment_id' => $paymentResponse->id,
            ];
        } catch (\Exception $e) {
            \DB::rollBack();

            Log::error('Error procesando y guardando pago', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success'    => false,
                'error_code' => 'PROCESSING_ERROR',
                'message'    => 'Error procesando el pago',
            ];
        }
    }

    /**
     * Crea una orden a partir de una respuesta de pago exitosa
     */
    private function createOrderFromPaymentResponse(
        \App\Models\PaymentResponse $paymentResponse,
        ?\App\Models\PaymentSession $paymentSession = null
    ): \App\Models\Order {
        $paymentMethod = $paymentSession?->payment_method ?? 'credit_card';

        return \App\Models\Order::create([
            'order_number'    => 'MKT' . substr(time(), -6) . rand(10, 99),
            'user_id'         => $paymentResponse->user_id ?? 1, // ID por defecto si no hay usuario
            'cart_id'         => $paymentResponse->cart_id, // Puede ser null
            'store_id'        => 1, // ID de tienda por defecto
            'status'          => 'completed', // ENUM: pending,processing,completed,cancelled,refunded
            'payment_status'  => 'paid', // ENUM: pending,paid,failed,cancelled,refunded,partial_refund
            'total_amount'    => $paymentResponse->amount,
            'currency_id'     => 1, // Asumiendo MXN
            'payment_method'  => $paymentMethod,
            'payment_gateway' => 'mitec',
            'transaction_id'  => $paymentResponse->ds_trans_id,
            'paid_at'         => $paymentResponse->created_at,
            'processed_at'    => now(),
        ]);
    }

    /**
     * Crea una orden a partir de un pago exitoso (legacy)
     */
    private function createOrderFromPayment(\App\Models\Payment $payment): \App\Models\Order
    {
        // Buscar PaymentSession asociada por cart_id para obtener payment_method
        $paymentSession = \App\Models\PaymentSession::where('cart_id', $payment->cart_id)
            ->where('user_id', $payment->user_id)
            ->first();

        $paymentMethod = $paymentSession?->payment_method ?? 'credit_card';

        // Por ahora crear una orden básica, puedes expandir esto según tu lógica de negocio
        return \App\Models\Order::create([
            'order_number'    => 'ORD-' . time() . '-' . substr(md5($payment->reference), 0, 6),
            'user_id'         => $payment->user_id,
            'cart_id'         => $payment->cart_id,
            'status'          => 'completed', // Status válido del ENUM
            'payment_status'  => 'paid',
            'total_amount'    => $payment->amount,
            'currency_id'     => 1, // Asumiendo MXN
            'payment_method'  => $paymentMethod,
            'payment_gateway' => 'mitec',
            'transaction_id'  => $payment->transaction_id,
            'paid_at'         => $payment->processed_at,
            'processed_at'    => now(),
        ]);
    }

    /**
     * Genera URL de éxito para el frontend
     */
    private function getFrontendSuccessUrl(string $reference): string
    {
        $baseUrl = env('FRONTEND_URL', 'http://localhost:5173');

        return $baseUrl . '/payment-result?reference=' . urlencode($reference) . '&status=success';
    }

    /**
     * Genera URL de error para el frontend
     */
    private function getFrontendErrorUrl(string $errorCode): string
    {
        $baseUrl = env('FRONTEND_URL', 'http://localhost:5173');

        return $baseUrl . '/payment-result?status=error&error=' . urlencode($errorCode);
    }

    /**
     * Maneja tokens de prueba para testing
     */
    private function handleTestToken(string $token): array
    {
        switch ($token) {
            case 'test':
            case 'success':
                return [
                    'success' => true,
                    'status'  => 'success',
                    'data'    => [
                        'reference'          => 'TEST_' . time(),
                        'amount'             => '100.00',
                        'currency'           => 'MXN',
                        'card_last_four'     => '1234',
                        'transaction_date'   => now()->format('Y-m-d H:i:s'),
                        'authorization_code' => 'TEST_AUTH_' . substr(md5(time()), 0, 6),
                    ],
                ];

            case 'fail':
                return [
                    'success'       => false,
                    'status'        => 'declined',
                    'error_code'    => 'CARD_DECLINED',
                    'error_message' => 'La tarjeta fue rechazada por el banco emisor',
                ];

            case 'error':
                return [
                    'success'       => false,
                    'status'        => 'error',
                    'error_code'    => 'PROCESSING_ERROR',
                    'error_message' => 'Error en el procesamiento de la transacción',
                ];

            default:
                return [
                    'success'       => false,
                    'status'        => 'error',
                    'error_code'    => 'INVALID_TEST_TOKEN',
                    'error_message' => 'Token de prueba no reconocido',
                ];
        }
    }
}
