<?php
/**
 * Página de respuesta para transacciones MITEC - VERSIÓN INTEGRADA
 * Recibe callbacks del gateway de pago y desencripta las respuestas automáticamente
 */

// Cargar variables de entorno desde .env
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            [$key, $value] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

// Configurar zona horaria
date_default_timezone_set('America/Mexico_City');

// Configurar headers para evitar cache
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Archivo de log para debugging
$logFile = __DIR__ . '/mitec_responses.log';

/**
 * Función de logging mejorada
 */
function logMitecResponse($message, $data = null) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] {$message}";

    if ($data !== null) {
        $logEntry .= " | Data: " . json_encode($data);
    }

    $logEntry .= PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * Desencripta la respuesta de MITEC usando AES-128-CBC
 * EXACTAMENTE igual que el frontend (con IV aleatorio)
 */
function decryptMitecResponse(string $encryptedData): string {
    // Clave de desencriptación PRODUCCIÓN (actualizada)
    $keyHex = '1BF745522C9903AE583C5E234F3D1CEA';  // PRODUCCIÓN environment

    $key = hex2bin($keyHex);

    // Decodificar base64
    $data = base64_decode($encryptedData, true);
    if ($data === false) {
        throw new Exception('Invalid Base64 encoding');
    }

    // Extraer IV (primeros 16 bytes) y datos encriptados
    $iv = substr($data, 0, 16);
    $encryptedBinary = substr($data, 16);

    // Desencriptar
    $decrypted = openssl_decrypt($encryptedBinary, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);

    if ($decrypted === false) {
        throw new Exception('Decryption failed');
    }

    // Buscar el inicio del XML si hay datos extra
    if (false !== $pos = strpos($decrypted, '<?xml')) {
        return substr($decrypted, $pos);
    }

    return $decrypted;
}/**
 * Parsea la respuesta XML de MITEC
 */
function parseXmlResponse(string $xmlString): array {
    libxml_use_internal_errors(true);

    try {
        $xml = simplexml_load_string($xmlString);

        if (!$xml) {
            $errors = libxml_get_errors();
            $errorMessage = 'XML parsing failed';
            if (!empty($errors)) {
                $errorMessage .= ': ' . $errors[0]->message;
            }
            throw new Exception($errorMessage);
        }

        return [
            'r3ds_reference' => (string)($xml->r3ds_reference ?? ''),
            'r3ds_dsTransId' => (string)($xml->r3ds_dsTransId ?? ''),
            'r3ds_eci' => (string)($xml->r3ds_eci ?? ''),
            'r3ds_cavv' => (string)($xml->r3ds_cavv ?? ''),
            'r3ds_transStatus' => (string)($xml->r3ds_transStatus ?? ''),
            'r3ds_responseCode' => (string)($xml->r3ds_responseCode ?? ''),
            'r3ds_responseDescription' => (string)($xml->r3ds_responseDescription ?? ''),
            'payment_folio' => (string)($xml->CENTEROFPAYMENTS->reference ?? ''),
            'payment_response' => (string)($xml->CENTEROFPAYMENTS->response ?? ''),
            'payment_auth' => (string)($xml->CENTEROFPAYMENTS->auth ?? ''),
            'cd_response' => (string)($xml->CENTEROFPAYMENTS->cd_response ?? ''),
            'cd_error' => (string)($xml->CENTEROFPAYMENTS->cd_error ?? ''),
            'nb_error' => (string)($xml->CENTEROFPAYMENTS->nb_error ?? ''),
            'time' => (string)($xml->CENTEROFPAYMENTS->time ?? ''),
            'date' => (string)($xml->CENTEROFPAYMENTS->date ?? ''),
            'voucher' => (string)($xml->CENTEROFPAYMENTS->voucher ?? ''),
            'amount' => (string)($xml->CENTEROFPAYMENTS->amount ?? $xml->amount ?? ''),
            'cc_name' => (string)($xml->r3ds_cc_name ?? ''),
            'cc_number' => (string)($xml->r3ds_cc_number ?? ''),
            'branch' => (string)($xml->r3ds_idBranch ?? ''),
            'auth_bancaria' => (string)($xml->r3ds_autorizacion_bancaria ?? ''),
            'auth_full' => (string)($xml->r3ds_auth_full ?? ''),
            'protocolo' => (string)($xml->r3ds_protocolo ?? ''),
            'version' => (string)($xml->r3ds_version ?? ''),
        ];

    } finally {
        libxml_use_internal_errors(false);
        libxml_clear_errors();
    }
}

/**
 * Determina el estado de la transacción
 */
function getTransactionStatus(array $data): array {
    $isSuccess = false;
    $message = 'Transacción desconocida';

    // Verificar respuesta del gateway
    if (!empty($data['payment_response'])) {
        $response = strtolower($data['payment_response']);
        if ($response === 'approved' || $response === 'aprobada') {
            $isSuccess = true;
            $message = 'Transacción aprobada exitosamente';
        } elseif ($response === 'error' || $response === 'decline') {
            $isSuccess = false;
            $message = 'Transacción rechazada: ' . ($data['nb_error'] ?: 'Error no especificado');
        }
    }

    // Verificar códigos de error
    if (!empty($data['cd_error'])) {
        $isSuccess = false;
        $message = "Error {$data['cd_error']}: " . ($data['nb_error'] ?: 'Error no especificado');
    }

    // Verificar código de respuesta 3DS
    if (!empty($data['r3ds_responseCode'])) {
        $code = $data['r3ds_responseCode'];
        if (strpos($code, 'E') === 0) { // Códigos que empiezan con E son errores
            $isSuccess = false;
            $message = "Error 3DS {$code}: " . ($data['r3ds_responseDescription'] ?: 'Error no especificado');
        }
    }

    // Obtener monto - intentar del carrito vía payment_session, luego payment_response, luego MITEC
    $amount = 'N/A';
    $transactionRef = $data['r3ds_reference'] ?? $data['payment_folio'] ?? '';

    // Intentar obtener monto del carrito usando payment_session
    try {
        $sqlStartTime = microtime(true);

        $pdo = new PDO('mysql:host=127.0.0.1;port=3309;dbname=doarsal_marketplacev1', 'root', 'mysql');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_TIMEOUT, 3); // TIMEOUT DE 3 SEGUNDOS

        // Buscar payment_session por transaction_reference
        $stmt = $pdo->prepare("
            SELECT ps.cart_id, c.total_amount
            FROM payment_sessions ps
            LEFT JOIN carts c ON ps.cart_id = c.id
            WHERE ps.transaction_reference = ?
            ORDER BY ps.created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$transactionRef]);
        $sessionData = $stmt->fetch(PDO::FETCH_ASSOC);

        $sqlTime = round((microtime(true) - $sqlStartTime) * 1000, 2);

        if ($sessionData && $sessionData['total_amount']) {
            $amount = '$' . number_format($sessionData['total_amount'], 2) . ' MXN';
            logMitecResponse('💰 Monto obtenido del carrito', [
                'amount' => $amount,
                'cart_id' => $sessionData['cart_id'],
                'sql_time_ms' => $sqlTime
            ]);
        } else {
            // Fallback: buscar en payment_responses
            $stmt = $pdo->prepare("SELECT amount FROM payment_responses WHERE transaction_reference = ? ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$transactionRef]);
            $paymentResponse = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($paymentResponse && $paymentResponse['amount']) {
                $amount = '$' . number_format($paymentResponse['amount'], 2) . ' MXN';
                logMitecResponse('💰 Monto obtenido de payment_responses', [
                    'amount' => $amount,
                    'sql_time_ms' => $sqlTime
                ]);
            } else {
                // Fallback final: monto de MITEC si existe
                $mitecAmount = $data['amount'] ?? null;
                if ($mitecAmount && $mitecAmount !== 'N/A' && $mitecAmount !== '') {
                    $amount = '$' . number_format(floatval($mitecAmount), 2) . ' MXN';
                    logMitecResponse('💰 Monto obtenido de MITEC', [
                        'amount' => $amount,
                        'sql_time_ms' => $sqlTime
                    ]);
                }
            }
        }
    } catch (Exception $e) {
        logMitecResponse('❌ ERROR: Falló consulta SQL para monto', [
            'error' => $e->getMessage(),
            'transaction_ref' => $transactionRef
        ]);
        // Si falla la conexión a BD, usar monto de MITEC
        $mitecAmount = $data['amount'] ?? null;
        if ($mitecAmount && $mitecAmount !== 'N/A' && $mitecAmount !== '') {
            $amount = '$' . number_format(floatval($mitecAmount), 2) . ' MXN';
        }
    }    return [
        'success' => $isSuccess,
        'message' => $message,
        'reference' => $data['r3ds_reference'] ?: $data['payment_folio'],
        'auth_code' => $data['payment_auth'],
        'amount' => $amount,
        'date' => $data['date'],
        'time' => $data['time']
    ];
}

// Inicializar variables
$status = 'unknown';
$decryptedData = [];
$transactionStatus = [];
$errorMessage = '';
$decryptedXml = '';

try {
    logMitecResponse('=== NUEVA RESPUESTA DE MITEC ===');
    logMitecResponse('Método HTTP', $_SERVER['REQUEST_METHOD']);
    logMitecResponse('Query String', $_SERVER['QUERY_STRING'] ?? '');
    logMitecResponse('GET params', $_GET);
    logMitecResponse('POST params', $_POST);
    logMitecResponse('RAW input', file_get_contents('php://input'));
    logMitecResponse('Headers', $_SERVER['HTTP_HOST'] ? 'HTTP headers available' : 'No HTTP headers');

    // Verificar si es una respuesta fake (puede venir por POST['fake_mode'] o si detectamos FAKE en el XML)
    $isFakeMode = isset($_POST['fake_mode']) && $_POST['fake_mode'] === '1';

    if ($isFakeMode && isset($_POST['xml'])) {
        // Modo fake con datos en base64 (formato antiguo)
        logMitecResponse('MODO FAKE DETECTADO - Procesando respuesta simulada (formato base64)');

        // Decodificar datos fake
        $fakeData = json_decode(base64_decode($_POST['xml']), true);

        $decryptedData = [
            'r3ds_reference' => $fakeData['reference'],
            'payment_folio' => $fakeData['reference'],
            'payment_response' => $fakeData['response'],
            'payment_auth' => $fakeData['auth'],
            'cd_response' => $fakeData['cd_response'],
            'cd_error' => $fakeData['cd_error'],
            'nb_error' => $fakeData['nb_error'],
            'time' => $fakeData['time'],
            'date' => $fakeData['date'],
            'voucher' => $fakeData['voucher'],
            'amount' => $fakeData['amount']
        ];

        $transactionStatus = [
            'success' => true,
            'message' => 'Pago fake procesado exitosamente',
            'response_code' => '00',
            'auth_code' => $fakeData['auth']
        ];

        $status = 'success';
        $decryptedXml = '<!-- FAKE MODE - XML no disponible -->';

        logMitecResponse('Respuesta fake procesada', $decryptedData);

        // *** LLAMAR AL WEBHOOK DE LARAVEL PARA MODO FAKE ***
        if (!empty($decryptedData)) {
            $webhookReference = $decryptedData['r3ds_reference'] ?? $decryptedData['payment_folio'] ?? 'unknown';
            callInternalWebhook($webhookReference, $decryptedXml, $decryptedData, $status);

            // *** REDIRECCIONAR AL FRONTEND DESPUÉS DE PROCESAR FAKE ***
            $frontendUrl = $_ENV['FRONTEND_URL'] ?? 'http://localhost:5173';
            $redirectUrl = $frontendUrl . '/payment-result?reference=' . urlencode($webhookReference) . '&status=' . $status;

            logMitecResponse('Redirigiendo al frontend (MODO FAKE)', [
                'reference' => $webhookReference,
                'status' => $status,
                'redirect_url' => $redirectUrl
            ]);

            header('Location: ' . $redirectUrl);
            exit;
        }
    }
    // Verificar que tenemos una respuesta encriptada (real o fake encriptada)
    else if (isset($_POST['strResponse']) && !empty($_POST['strResponse'])) {

        $encryptedResponse = $_POST['strResponse'];
        $company = $_POST['strIdCompany'] ?? '';
        $merchant = $_POST['strIdMerchant'] ?? '';
        $token = $_GET['token'] ?? '';

        logMitecResponse('Intentando desencriptar respuesta de MITEC');

        // Desencriptar la respuesta
        $decryptedXml = decryptMitecResponse($encryptedResponse);
        logMitecResponse('XML desencriptado exitosamente', substr($decryptedXml, 0, 200) . '...');

        // Verificar si es una respuesta fake encriptada
        if (strpos($decryptedXml, 'FAKE_') !== false || $isFakeMode) {
            logMitecResponse('MODO FAKE DETECTADO - Respuesta encriptada fake');
            $isFakeMode = true;
        }

        // Parsear el XML
        $decryptedData = parseXmlResponse($decryptedXml);
        logMitecResponse('XML parseado exitosamente', $decryptedData);

        // Determinar estado de la transacción
        $transactionStatus = getTransactionStatus($decryptedData);
        logMitecResponse('Estado de transacción determinado', $transactionStatus);

        $status = $transactionStatus['success'] ? 'success' : 'error';

        // *** LLAMAR AL WEBHOOK DE LARAVEL VIA ENDPOINT ***
        if (!empty($decryptedData)) {
            $webhookReference = $decryptedData['r3ds_reference'] ?? $decryptedData['payment_folio'] ?? 'unknown';
            callInternalWebhook($webhookReference, $decryptedXml, $decryptedData, $status);

            // *** REDIRECCIONAR AL FRONTEND DESPUÉS DE PROCESAR ***
            $frontendUrl = $_ENV['FRONTEND_URL'] ?? 'http://localhost:5173';
            $redirectUrl = $frontendUrl . '/payment-result?reference=' . urlencode($webhookReference) . '&status=' . $status;

            logMitecResponse('Redirigiendo al frontend', [
                'reference' => $webhookReference,
                'status' => $status,
                'redirect_url' => $redirectUrl
            ]);

            header('Location: ' . $redirectUrl);
            exit;
        }

    } else {
        $status = 'waiting';
        logMitecResponse('Esperando respuesta de MITEC - no hay datos POST');

        // Si no hay datos POST, mostrar página de espera/debug
        // (continúa con la página HTML de debugging)
    }

} catch (Exception $e) {
    $status = 'error';
    $errorMessage = $e->getMessage();
    logMitecResponse('ERROR al procesar respuesta', $errorMessage);

    // Redireccionar al frontend con error
    $frontendUrl = $_ENV['FRONTEND_URL'] ?? 'http://localhost:5173';
    $redirectUrl = $frontendUrl . '/payment-result?status=error&error=' . urlencode('PROCESSING_ERROR');

    logMitecResponse('Redirigiendo al frontend por error', [
        'error' => $errorMessage,
        'redirect_url' => $redirectUrl
    ]);

    header('Location: ' . $redirectUrl);
    exit;
}

/**
 * Procesa directamente con Laravel sin HTTP - MUCHO MÁS RÁPIDO
 */
function processWithLaravelDirectly($transactionReference, $xmlResponse, $parsedData, $status) {
    $startTime = microtime(true);

    try {
        logMitecResponse('⚡ PROCESANDO DIRECTAMENTE CON LARAVEL', [
            'transaction_reference' => $transactionReference,
            'status' => $status
        ]);

        // Cargar Laravel directamente
        require_once __DIR__ . '/../vendor/autoload.php';
        $app = require_once __DIR__ . '/../bootstrap/app.php';
        $kernel = $app->make('Illuminate\Contracts\Console\Kernel');
        $kernel->bootstrap();

        // Buscar PaymentSession
        $paymentSession = \App\Models\PaymentSession::where('transaction_reference', $transactionReference)->first();
        if (!$paymentSession) {
            $baseName = explode('_', $transactionReference)[0];
            $paymentSession = \App\Models\PaymentSession::where('transaction_reference', 'LIKE', $baseName . '%')->first();
        }

        // Crear PaymentResponse usando el servicio
        $paymentResponseService = $app->make(\App\Services\Payment\PaymentResponseService::class);
        $paymentResponse = $paymentResponseService->processPaymentResponse(
            $parsedData,
            $xmlResponse,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            $paymentSession
        );

        $totalTime = round((microtime(true) - $startTime) * 1000, 2);

        logMitecResponse('✅ SUCCESS: Laravel procesado DIRECTAMENTE', [
            'payment_response_id' => $paymentResponse->id,
            'payment_status' => $paymentResponse->payment_status,
            'order_id' => $paymentResponse->order_id,
            'total_time_ms' => $totalTime
        ]);

        return true;

    } catch (Exception $e) {
        $totalTime = round((microtime(true) - $startTime) * 1000, 2);

        logMitecResponse('❌ ERROR procesando directamente con Laravel', [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'total_time_ms' => $totalTime
        ]);

        return false;
    }
}
function callInternalWebhook($transactionReference, $xmlResponse, $parsedData, $status) {
    $startTime = microtime(true); // TIEMPO INICIO

    try {
        logMitecResponse('🚀 INICIANDO webhook interno de Laravel', [
            'transaction_reference' => $transactionReference,
            'status' => $status,
            'start_time' => date('H:i:s.') . substr(microtime(), 2, 3)
        ]);

        $webhookData = [
            'transaction_reference' => $transactionReference,
            'xml_response' => $xmlResponse,
            'parsed_data' => $parsedData,
            'status' => $status,
            'source' => 'response_page'
        ];

        // URL del webhook desde .env
        $webhookUrl = $_ENV['MITEC_WEBHOOK_URL'] ?? 'http://127.0.0.1:8000/api/v1/payments/mitec/webhook';

        logMitecResponse('Llamando webhook de Laravel', [
            'url' => $webhookUrl,
            'transaction_reference' => $transactionReference
        ]);

        // Configurar cURL para llamada a Laravel - TIMEOUTS OPTIMIZADOS
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $webhookUrl,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($webhookData),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'User-Agent: MITEC-Response-Page/1.0'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,        // REDUCIDO DE 30 A 5 SEGUNDOS
            CURLOPT_CONNECTTIMEOUT => 2, // REDUCIDO DE 10 A 2 SEGUNDOS
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);

        $curlStartTime = microtime(true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlTime = round((microtime(true) - $curlStartTime) * 1000, 2); // milisegundos
        curl_close($ch);

        $totalTime = round((microtime(true) - $startTime) * 1000, 2); // milisegundos

        if ($curlError) {
            logMitecResponse('❌ ERROR: cURL falló al llamar Laravel', [
                'error' => $curlError,
                'url' => $webhookUrl,
                'curl_time_ms' => $curlTime,
                'total_time_ms' => $totalTime
            ]);
        } elseif ($httpCode !== 200) {
            logMitecResponse('⚠️ ERROR: Laravel retornó código HTTP no exitoso', [
                'http_code' => $httpCode,
                'response' => substr($response, 0, 200) . '...',
                'url' => $webhookUrl,
                'curl_time_ms' => $curlTime,
                'total_time_ms' => $totalTime
            ]);
        } else {
            $responseData = json_decode($response, true);
            logMitecResponse('✅ SUCCESS: Laravel webhook procesado', [
                'http_code' => $httpCode,
                'success' => $responseData['success'] ?? false,
                'message' => $responseData['message'] ?? 'No message',
                'curl_time_ms' => $curlTime,
                'total_time_ms' => $totalTime
            ]);
        }

    } catch (Exception $e) {
        logMitecResponse('Excepción llamando webhook Laravel', [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
    }
}

// Contar visitas para debugging
$getCount = isset($_COOKIE['mitec_get_count']) ? (int)$_COOKIE['mitec_get_count'] + 1 : 1;
$postCount = isset($_COOKIE['mitec_post_count']) ? (int)$_COOKIE['mitec_post_count'] + 1 : 1;

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    setcookie('mitec_get_count', $getCount, time() + 3600);
} else {
    setcookie('mitec_post_count', $postCount, time() + 3600);
}

logMitecResponse('Página de respuesta mostrada', [
    'status' => $status,
    'get_count' => $getCount,
    'post_count' => $postCount
]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🏦 Respuesta de MITEC - Página de Debug</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0; padding: 20px; min-height: 100vh;
        }
        .container {
            max-width: 900px; margin: 0 auto; background: white;
            border-radius: 15px; box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; padding: 30px; text-align: center;
        }
        .header h1 { margin: 0; font-size: 28px; font-weight: 300; }
        .timestamp { opacity: 0.9; margin-top: 10px; }
        .content { padding: 30px; }
        .status-card {
            padding: 20px; border-radius: 10px; margin-bottom: 20px;
            border-left: 5px solid;
        }
        .status-success { background: #d4edda; border-color: #28a745; color: #155724; }
        .status-error { background: #f8d7da; border-color: #dc3545; color: #721c24; }
        .status-waiting { background: #fff3cd; border-color: #ffc107; color: #856404; }
        .status-unknown { background: #e2e3e5; border-color: #6c757d; color: #383d41; }
        .data-section {
            background: #f8f9fa; padding: 20px; border-radius: 8px;
            margin: 15px 0; border: 1px solid #dee2e6;
        }
        .data-title { font-weight: bold; color: #495057; margin-bottom: 10px; font-size: 16px; }
        .data-content {
            background: white; padding: 15px; border-radius: 5px;
            font-family: 'Courier New', monospace; font-size: 14px;
            max-height: 300px; overflow-y: auto;
            border: 1px solid #dee2e6;
        }
        .transaction-details {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px; margin-top: 20px;
        }
        .detail-item { background: #f8f9fa; padding: 15px; border-radius: 8px; }
        .detail-label { font-size: 12px; color: #6c757d; text-transform: uppercase; font-weight: bold; }
        .detail-value { font-size: 16px; color: #212529; margin-top: 5px; }
        .icon { font-size: 20px; margin-right: 8px; }
        pre { margin: 0; white-space: pre-wrap; word-break: break-word; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏦 Respuesta de MITEC - Página de Debug</h1>
            <div class="timestamp">Timestamp: <?php echo date('Y-m-d H:i:s'); ?></div>
        </div>

        <div class="content">
            <!-- Estado de la transacción -->
            <div class="status-card status-<?php echo $status; ?>">
                <div style="font-size: 18px; font-weight: bold; margin-bottom: 10px;">
                    <?php
                    switch($status) {
                        case 'success':
                            echo '✅ Transacción Exitosa';
                            break;
                        case 'error':
                            echo '❌ Error en Transacción';
                            break;
                        case 'waiting':
                            echo '⏳ Esperando Respuesta de MITEC';
                            break;
                        default:
                            echo '❓ Estado Desconocido';
                    }
                    ?>
                </div>
                <?php if ($status === 'success' && !empty($transactionStatus)): ?>
                    <div><?php echo htmlspecialchars($transactionStatus['message']); ?></div>
                <?php elseif ($status === 'error' && !empty($errorMessage)): ?>
                    <div>Error: <?php echo htmlspecialchars($errorMessage); ?></div>
                <?php elseif ($status === 'error' && !empty($transactionStatus)): ?>
                    <div><?php echo htmlspecialchars($transactionStatus['message']); ?></div>
                <?php else: ?>
                    <div>Esperando respuesta de MITEC</div>
                <?php endif; ?>
            </div>

            <!-- Detalles de la transacción (si está disponible) -->
            <?php if (!empty($transactionStatus) && $status !== 'waiting'): ?>
            <div class="data-section">
                <div class="data-title">📊 Detalles de la Transacción</div>
                <div class="transaction-details">
                    <div class="detail-item">
                        <div class="detail-label">Referencia</div>
                        <div class="detail-value"><?php echo htmlspecialchars($transactionStatus['reference'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Código de Autorización</div>
                        <div class="detail-value"><?php echo htmlspecialchars($transactionStatus['auth_code'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Monto</div>
                        <div class="detail-value"><?php echo htmlspecialchars($transactionStatus['amount'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Fecha y Hora</div>
                        <div class="detail-value"><?php echo htmlspecialchars(($transactionStatus['date'] ?? '') . ' ' . ($transactionStatus['time'] ?? '')); ?></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- XML Desencriptado (si está disponible) -->
            <?php if (!empty($decryptedXml)): ?>
            <div class="data-section">
                <div class="data-title">🔓 XML Desencriptado de MITEC</div>
                <div class="data-content">
                    <pre><?php echo htmlspecialchars($decryptedXml); ?></pre>
                </div>
            </div>
            <?php endif; ?>

            <!-- Datos Parseados (si están disponibles) -->
            <?php if (!empty($decryptedData)): ?>
            <div class="data-section">
                <div class="data-title">📋 Datos Parseados</div>
                <div class="data-content">
                    <pre><?php echo json_encode($decryptedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?></pre>
                </div>
            </div>
            <?php endif; ?>

            <!-- Parámetros GET recibidos -->
            <?php if (!empty($_GET)): ?>
            <div class="data-section">
                <div class="data-title">📨 Parámetros GET Recibidos</div>
                <div class="data-content">
                    <pre><?php print_r($_GET); ?></pre>
                </div>
            </div>
            <?php endif; ?>

            <!-- Parámetros POST recibidos -->
            <?php if (!empty($_POST)): ?>
            <div class="data-section">
                <div class="data-title">📨 Parámetros POST Recibidos</div>
                <div class="data-content">
                    <pre><?php print_r($_POST); ?></pre>
                </div>
            </div>
            <?php endif; ?>

            <!-- Raw Input -->
            <?php
            $rawInput = file_get_contents('php://input');
            if (!empty($rawInput)):
            ?>
            <div class="data-section">
                <div class="data-title">📨 Raw Input</div>
                <div class="data-content">
                    <pre><?php echo htmlspecialchars($rawInput); ?></pre>
                </div>
            </div>
            <?php endif; ?>

            <!-- Headers recibidos -->
            <div class="data-section">
                <div class="data-title">🌐 Headers Recibidos</div>
                <div class="data-content">
                    <pre><?php
                    if (function_exists('getallheaders')) {
                        print_r(getallheaders());
                    } else {
                        echo "User-Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'N/A') . "\n";
                        echo "Host: " . ($_SERVER['HTTP_HOST'] ?? 'N/A') . "\n";
                        echo "Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'N/A') . "\n";
                    }
                    ?></pre>
                </div>
            </div>

            <!-- Información del servidor -->
            <div class="data-section">
                <div class="data-title">🔧 Información del Servidor</div>
                <div class="data-content">
                    REQUEST_METHOD: <?php echo $_SERVER['REQUEST_METHOD']; ?><br>
                    REQUEST_URI: <?php echo $_SERVER['REQUEST_URI']; ?><br>
                    QUERY_STRING: <?php echo $_SERVER['QUERY_STRING']; ?><br>
                    HTTP_USER_AGENT: <?php echo $_SERVER['HTTP_USER_AGENT'] ?? 'N/A'; ?><br>
                    REMOTE_ADDR: <?php echo $_SERVER['REMOTE_ADDR']; ?><br>
                </div>
            </div>

            <!-- Próximos pasos -->
            <div class="data-section">
                <div class="data-title">📋 Próximos pasos</div>
                <div class="data-content">
                    Los datos se han guardado en el log: mitec_responses.log<br>
                    <?php if ($status === 'success'): ?>
                    ✅ Transacción procesada exitosamente<br>
                    <?php elseif ($status === 'error'): ?>
                    ❌ Revisa el error arriba y verifica la configuración<br>
                    <?php else: ?>
                    ⏳ Esperando respuesta de MITEC<br>
                    <?php endif; ?>
                    Si hay errores, verifica la configuración de MITEC<br>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
