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
     * @param string|null $cartId ID del carrito (opcional)
     * @return array Resultado del procesamiento
     * @throws ValidationException
     */
    public function processPayment(array $paymentData, ?int $userId = null, ?string $cartId = null): array
    {
        try {
            // Validar datos de entrada
            $validatedData = $this->validatePaymentData($paymentData);

            // Registrar inicio de transacción
            Log::info('Iniciando transacción MITEC', [
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

            // Generar formulario HTML
            $formHtml = $this->generatePaymentForm($formXml);

            Log::info('MITEC: Formulario generado exitosamente', [
                'reference' => $transactionData['reference'],
                'form_html_length' => strlen($formHtml),
                'has_form_xml' => !empty($formXml)
            ]);

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
            'amount' => ['required', 'string', 'regex:/^\d+\.\d{2}$/'], // String con formato 1.00
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
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
        .loading { margin: 20px 0; }
        .form-container { max-width: 400px; margin: 0 auto; }
        .btn { padding: 15px 30px; font-size: 16px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; }
        .btn:hover { background: #0056b3; }
    </style>
</head>
<body>
    <div class="form-container">
        <h2>Procesando su pago</h2>
        <div class="loading">Por favor espere...</div>
        <form id="mitecForm" name="cliente" action="' . $actionUrl . '" method="post">
            <input type="hidden" name="xml" value="' . $xmlForForm . '">
            <input class="btn" type="submit" value="Continuar con el pago seguro">
        </form>
        <p><small>Será redirigido al sistema de pago seguro de MITEC</small></p>
    </div>
    <script>
        // Auto-submit después de 3 segundos
        setTimeout(function() {
            document.getElementById("mitecForm").submit();
        }, 3000);
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
    protected function saveTransactionLog(?int $userId, ?string $cartId, array $transactionData, array $cardData): void
    {
        try {
            // Aquí puedes implementar el guardado del log de transacción
            Log::info('Transacción MITEC iniciada', [
                'user_id' => $userId,
                'cart_id' => $cartId,
                'reference' => $transactionData['reference'],
                'amount' => $transactionData['amount'],
                'card_last_four' => substr($cardData['card_number'], -4),
                'card_type' => $this->detectCardType($cardData['card_number'])
            ]);

        } catch (\Exception $e) {
            Log::error('Error guardando log de transacción', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'cart_id' => $cartId
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
}
