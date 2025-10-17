<?php

namespace App\Services\Payment;

use App\Models\PaymentCard;
use App\Models\Order;
use App\Models\PaymentSession;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Servicio principal para procesar pagos con MITEC
 * Maneja la lógica completa del proceso de pago
 */
class MitecPaymentService
{
    protected MitecXmlBuilderService $xmlBuilder;
    protected MitecEncryptionService $encryptionService;

    public function __construct(
        MitecXmlBuilderService $xmlBuilder,
        MitecEncryptionService $encryptionService
    ) {
        $this->xmlBuilder = $xmlBuilder;
        $this->encryptionService = $encryptionService;
    }

    /**
     * Procesa una transacción de pago con MITEC
     *
     * @param array $paymentData Datos del pago
     * @param int|null $userId ID del usuario (opcional)
     * @param int|null $cartId ID del carrito (opcional)
     * @return array Resultado del procesamiento
     * @throws ValidationException
     */
    public function processPayment(array $paymentData, ?int $userId = null, ?int $cartId = null): array
    {
        try {
            // Si no viene amount en los datos, calcularlo desde el carrito
            if (empty($paymentData['amount']) && $cartId) {
                $cart = \App\Models\Cart::find($cartId);
                if ($cart) {
                    $paymentData['amount'] = number_format($cart->total_amount, 2, '.', '');
                    Log::info('MITEC: Amount calculado desde carrito', [
                        'cart_id' => $cartId,
                        'amount' => $paymentData['amount']
                    ]);
                } else {
                    Log::error('MITEC: Cart no encontrado para calcular amount', [
                        'cart_id' => $cartId
                    ]);
                    throw new \Exception('No se pudo determinar el monto de la transacción');
                }
            }

            // Validar datos de entrada
            $validatedData = $this->validatePaymentData($paymentData);

            // Verificar si está en modo fake
            if (env('MICROSOFT_FAKE_MODE', false)) {
                Log::info('MITEC: Modo FAKE activado - procesando como transacción normal pero con respuesta simulada', [
                    'user_id' => $userId,
                    'cart_id' => $cartId,
                    'amount' => $validatedData['amount'],
                    'card_last_four' => substr($validatedData['card_number'], -4)
                ]);

                // Continuar con el proceso normal para generar el XML y todo
                // Solo cambiaremos el comportamiento al final
                $fakeMode = true;
            } else {
                $fakeMode = false;
            }

            // Registrar inicio de transacción real
            Log::info('Iniciando transacción MITEC REAL', [
                'user_id' => $userId,
                'cart_id' => $cartId,
                'amount' => $validatedData['amount'],
                'card_last_four' => substr($validatedData['card_number'], -4)
            ]);

            // Preparar datos para el XML
            $transactionData = [
                'reference' => $this->generateTransactionReference(),
                'amount' => $validatedData['amount'],
                'currency' => $validatedData['currency'] ?? 'MXN',
                'cobro' => '1'
            ];

            $cardData = [
                'name' => $validatedData['card_name'],
                'card_number' => $validatedData['card_number'],
                'exp_month' => $validatedData['exp_month'],
                'exp_year' => $validatedData['exp_year'],
                'cvv' => $validatedData['cvv']
            ];

            $billingData = [
                'phone' => $validatedData['billing_phone'] ?? null,
                'email' => $validatedData['billing_email'] ?? null,
                'ip' => '187.184.10.88' // IP fija para pruebas con MITEC
            ];

            // Construir XML de transacción
            $transactionXml = $this->xmlBuilder->buildTransactionXml(
                $transactionData,
                $cardData,
                $billingData
            );

            // Encriptar XML
            $encryptionService = new MitecEncryptionService();
            $encryptedData = $encryptionService->encrypt(
                $transactionXml,
                env('MITEC_KEY_HEX')
            );

            // Construir XML final para formulario
            $formXml = $this->xmlBuilder->buildFormXml($encryptedData);

            // En modo fake, modificar el formulario para que vaya directamente a response.php con respuesta exitosa
            if ($fakeMode) {
                $formHtml = $this->generateFakeSuccessForm($transactionData['reference'], $validatedData);
                Log::info('MITEC: Formulario FAKE generado exitosamente', [
                    'reference' => $transactionData['reference'],
                    'fake_mode' => true
                ]);
            } else {
                // Generar formulario HTML normal
                $formHtml = $this->generatePaymentForm($formXml);
                Log::info('MITEC: Formulario generado exitosamente', [
                    'reference' => $transactionData['reference'],
                    'form_html_length' => strlen($formHtml),
                    'has_form_xml' => !empty($formXml)
                ]);
            }

            // Guardar registro de transacción (opcional)
            if ($userId || $cartId) {
                $this->saveTransactionLog($userId, $cartId, $transactionData, $cardData);
            }

            return [
                'success' => true,
                'transaction_reference' => $transactionData['reference'],
                'form_html' => $formHtml,
                'form_xml' => $formXml,
                'mitec_url' => env('MITEC_3DS_URL'),
                'encrypted_data' => $encryptedData,
                'cart_id' => $cartId, // Incluir cart_id en la respuesta
                'raw_xml' => $transactionXml // Solo para debug
            ];

        } catch (ValidationException $e) {
            Log::warning('Datos inválidos en transacción MITEC', [
                'errors' => $e->errors(),
                'user_id' => $userId
            ]);
            throw $e;

        } catch (\Exception $e) {
            Log::error('Error procesando pago MITEC', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'Error interno procesando el pago',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Valida los datos de pago
     *
     * @param array $data Datos a validar
     * @return array Datos validados
     * @throws ValidationException
     */
    protected function validatePaymentData(array $data): array
    {
        $validator = Validator::make($data, [
            'card_number' => ['required', 'string', 'min:13', 'max:19', 'regex:/^[0-9]+$/'],
            'card_name' => ['required', 'string', 'max:255'],
            'exp_month' => ['required', 'string', 'size:2', 'regex:/^(0[1-9]|1[0-2])$/'], // String 01-12
            'exp_year' => ['required', 'string', 'size:2', 'regex:/^[0-9]{2}$/'], // String de 2 dígitos
            'cvv' => ['required', 'string', 'min:3', 'max:4', 'regex:/^[0-9]+$/'],
            'amount' => ['sometimes', 'string', 'regex:/^\d+\.\d{2}$/'], // Opcional - se calcula desde el carrito si no viene
            'currency' => ['sometimes', 'string', 'in:MXN,USD'],
            'billing_phone' => ['sometimes', 'string', 'max:20'],
            'billing_email' => ['sometimes', 'email', 'max:255']
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * Genera el formulario HTML para envío a MITEC
     *
     * @param string $formXml XML del formulario
     * @return string HTML del formulario
     */
    protected function generatePaymentForm(string $formXml): string
    {
        $actionUrl = env('MITEC_3DS_URL');
        // NO escapar el XML - MITEC necesita el XML sin escapar
        // Solo escapar las comillas dentro del XML si las hay
        $xmlForForm = str_replace('"', '&quot;', $formXml);

        return '<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Procesando Pago - MITEC 3DS v2</title>
</head>
<body>
    <form id="mitecForm" name="cliente" action="' . $actionUrl . '" method="post" style="display:none;">
        <input type="hidden" name="xml" value="' . $xmlForForm . '">
    </form>
    <script>
        // Auto-submit inmediatamente
        document.getElementById("mitecForm").submit();
    </script>
</body>
</html>';
    }

    /**
     * Genera una referencia única de transacción
     *
     * @return string
     */
    protected function generateTransactionReference(): string
    {
        // Generar un ID único verdaderamente único combinando timestamp con microsegundos y un UUID parcial
        $microtime = microtime(true);
        $microseconds = str_replace('.', '', $microtime);
        $uuid = strtoupper(substr(str_replace('-', '', uniqid('', true)), 0, 8));

        return 'MKT' . $microseconds . '_' . $uuid;
    }

    /**
     * Guarda el log de la transacción
     *
     * @param int|null $userId ID del usuario
     * @param string|null $cartId ID del carrito
     * @param array $transactionData Datos de transacción
     * @param array $cardData Datos de tarjeta (sin datos sensibles)
     */
    /**
     * Guarda el log de transacción
     *
     * @param int|null $userId ID del usuario
     * @param int|null $cartId ID del carrito
     * @param array $transactionData Datos de transacción
     * @param array $cardData Datos de tarjeta (sin datos sensibles)
     */
    protected function saveTransactionLog(?int $userId, ?int $cartId, array $transactionData, array $cardData): void
    {
        try {
            // Solo logear la información de la transacción
            // El PaymentSession se crea en el controlador para evitar duplicados
            Log::info('Transacción MITEC iniciada', [
                'user_id' => $userId,
                'cart_id' => $cartId,
                'reference' => $transactionData['reference'],
                'amount' => $transactionData['amount'],
                'card_last_four' => substr($cardData['card_number'], -4),
                'card_type' => $this->detectCardType($cardData['card_number'])
            ]);

        } catch (\Exception $e) {
            Log::error('Error logueando transacción', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'cart_id' => $cartId,
                'reference' => $transactionData['reference'] ?? 'N/A'
            ]);
        }
    }

    /**
     * Detecta el tipo de tarjeta por el número
     *
     * @param string $cardNumber
     * @return string
     */
    protected function detectCardType(string $cardNumber): string
    {
        if (str_starts_with($cardNumber, '4')) {
            return 'visa';
        } elseif (str_starts_with($cardNumber, '5') || str_starts_with($cardNumber, '2')) {
            return 'mastercard';
        } elseif (str_starts_with($cardNumber, '3')) {
            return 'amex';
        }

        return 'unknown';
    }

    /**
     * Simula el flujo completo de pago cuando está en modo fake
     * Mantiene la misma estructura que el flujo real para no romper el frontend
     *
     * @param array $validatedData
     * @param int|null $userId
     * @param int|null $cartId
     * @return array
     */
    protected function simulateFakePaymentFlow(array $validatedData, ?int $userId = null, ?int $cartId = null): array
    {
        // Generar referencia de transacción fake
        $fakeReference = 'FAKE_' . strtoupper(uniqid());

        // Crear URL fake que redirigirá automáticamente con éxito
        $fakeUrl = url('/fake-payment-success/' . $fakeReference);

        // Generar HTML de formulario fake que se auto-submite
        $formHtml = $this->generateFakePaymentForm($fakeReference, $validatedData);

        // Retornar la misma estructura que el flujo real
        return [
            'success' => true,
            'transaction_reference' => $fakeReference,
            'form_html' => $formHtml,
            'mitec_url' => $fakeUrl,
            'message' => 'Pago fake iniciado correctamente'
        ];
    }

    /**
     * Genera un formulario HTML fake que simula una respuesta exitosa de MITEC
     * Se auto-envía directamente a response.php con datos de éxito SIN ESTILOS
     */
    protected function generateFakeSuccessForm(string $reference, array $validatedData): string
    {
        $responseUrl = url('/response.php');
        $amount = $validatedData['amount'];
        $cardLast4 = substr($validatedData['card_number'], -4);

        // Generar datos de respuesta fake (usando el formato simple que ya funciona)
        $fakeResponseData = [
            'reference' => $reference,
            'response' => 'approved',
            'auth' => 'FAKE' . rand(100000, 999999),
            'cd_response' => '00',
            'cd_error' => '00',
            'nb_error' => 'Transaccion Aprobada',
            'amount' => $amount,
            'date' => date('Y-m-d'),
            'time' => date('H:i:s'),
            'voucher' => 'FAKE_VOUCHER_' . time(),
            'card_last_four' => $cardLast4
        ];

        // Crear data encriptada fake (base64 simple para simular)
        $fakeEncryptedData = base64_encode(json_encode($fakeResponseData));

        // Generar formulario INVISIBLE que se auto-envía inmediatamente
        return '<!DOCTYPE html>
<html>
<head>
    <title>Procesando...</title>
</head>
<body>
    <form id="fakeForm" method="POST" action="' . $responseUrl . '" style="display:none;">
        <input type="hidden" name="xml" value="' . $fakeEncryptedData . '">
        <input type="hidden" name="reference" value="' . $reference . '">
        <input type="hidden" name="fake_mode" value="1">
    </form>
    <script>
        // Auto-envío inmediato sin mostrar nada
        document.getElementById("fakeForm").submit();
    </script>
</body>
</html>';
    }

    /**
     * Genera un formulario HTML fake que simula el comportamiento de MITEC
     * Redirige inmediatamente sin mostrar nada al usuario
     */
    protected function generateFakePaymentForm(string $reference, array $validatedData): string
    {
        $responseUrl = url('/response.php');
        $amount = $validatedData['amount'];
        $cardLast4 = substr($validatedData['card_number'], -4);

        // Generar datos de respuesta fake encriptados (simulando MITEC)
        $fakeResponseData = [
            'reference' => $reference,
            'response' => 'approved',
            'auth' => 'FAKE' . rand(100000, 999999),
            'cd_response' => '00',
            'cd_error' => '00',
            'nb_error' => 'Transaccion Aprobada',
            'amount' => $amount,
            'date' => date('Y-m-d'),
            'time' => date('H:i:s'),
            'voucher' => 'FAKE_VOUCHER_' . time(),
            'card_last_four' => $cardLast4
        ];

        // Crear data encriptada fake (base64 simple para simular)
        $fakeEncryptedData = base64_encode(json_encode($fakeResponseData));

        return "<!DOCTYPE html><html><head><title>Redirigiendo...</title></head><body><form id='f' method='POST' action='{$responseUrl}'><input name='xml' value='{$fakeEncryptedData}'><input name='reference' value='{$reference}'><input name='fake_mode' value='1'></form><script>document.getElementById('f').submit();</script></body></html>";
    }

    /**
     * Simula un pago exitoso cuando está en modo fake (MÉTODO LEGACY - MANTENIDO PARA COMPATIBILIDAD)
     *
     * @param array $validatedData
     * @param int|null $userId
     * @param int|null $cartId
     * @return array
     */
    protected function simulateFakeSuccessfulPayment(array $validatedData, ?int $userId = null, ?int $cartId = null): array
    {
        // Generar referencia de transacción fake
        $fakeReference = 'FAKE_' . strtoupper(uniqid());

        // Simular datos de respuesta exitosa
        $fakeResponse = [
            'success' => true,
            'message' => 'Pago simulado exitosamente (MODO FAKE)',
            'transaction_reference' => $fakeReference,
            'payment_status' => 'success',
            'payment_method' => 'fake_payment',
            'amount' => $validatedData['amount'],
            'currency' => $validatedData['currency'] ?? 'MXN',
            'auth_code' => 'FAKE' . rand(100000, 999999),
            'transaction_id' => 'TXN_FAKE_' . time(),
            'card_last_four' => substr($validatedData['card_number'], -4),
            'card_type' => $this->detectCardType($validatedData['card_number']),
            'processed_at' => now()->toDateTimeString(),
            'fake_mode' => true
        ];

        // Crear PaymentSession simulada
        try {
            $paymentSession = PaymentSession::create([
                'user_id' => $userId,
                'cart_id' => $cartId,
                'transaction_reference' => $fakeReference,
                'amount' => $validatedData['amount'],
                'currency' => $validatedData['currency'] ?? 'MXN',
                'status' => 'completed',
                'payment_method' => 'fake_payment',
                'metadata' => json_encode([
                    'fake_mode' => true,
                    'card_last_four' => substr($validatedData['card_number'], -4),
                    'card_type' => $this->detectCardType($validatedData['card_number']),
                    'auth_code' => $fakeResponse['auth_code'],
                    'transaction_id' => $fakeResponse['transaction_id']
                ])
            ]);

            Log::info('PaymentSession fake creada', [
                'payment_session_id' => $paymentSession->id,
                'transaction_reference' => $fakeReference,
                'user_id' => $userId,
                'cart_id' => $cartId,
                'amount' => $validatedData['amount']
            ]);

        } catch (\Exception $e) {
            Log::error('Error creando PaymentSession fake', [
                'error' => $e->getMessage(),
                'reference' => $fakeReference
            ]);
        }

        return $fakeResponse;
    }
}
