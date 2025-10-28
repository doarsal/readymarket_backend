<?php
/**
 * TEST COMPLETO DE MITEC CON PAYMENT SESSION
 * Este script simula el flujo COMPLETO: Cart → PaymentSession → MITEC → Response
 */

// Cargar Laravel
require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Cart;
use App\Models\PaymentSession;
use App\Services\Payment\MitecXmlBuilderService;
use App\Services\Payment\MitecEncryptionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Test MITEC COMPLETO con PaymentSession</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
        }
        .section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }
        .section h2 {
            color: #667eea;
            margin-bottom: 15px;
        }
        .code-block {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
            line-height: 1.5;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .success { 
            color: #28a745; 
            font-weight: bold; 
            padding: 10px;
            background: #d4edda;
            border-radius: 5px;
            margin: 10px 0;
        }
        .error { 
            color: #dc3545; 
            font-weight: bold; 
            padding: 10px;
            background: #f8d7da;
            border-radius: 5px;
            margin: 10px 0;
        }
        .info-item {
            background: white;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 10px;
            border: 1px solid #e0e0e0;
        }
        .info-item strong {
            color: #667eea;
            display: inline-block;
            min-width: 200px;
        }
        .btn-submit {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
            padding: 20px 50px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.3em;
            font-weight: 700;
            margin-top: 20px;
            box-shadow: 0 5px 15px rgba(17, 153, 142, 0.3);
            transition: all 0.3s ease;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(17, 153, 142, 0.4);
        }
        .form-section {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            padding: 40px;
            border-radius: 8px;
            text-align: center;
            margin-top: 30px;
        }
        .form-section h2 { color: white; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>🚀 TEST MITEC COMPLETO</h1>
            <p>Con Cart + PaymentSession + XML + Encriptación</p>
        </div>
";

try {
    // ===================================================================
    // PASO 1: BUSCAR CARRITO ESPECÍFICO
    // ===================================================================
    echo "<div class='section'>";
    echo "<h2>📦 PASO 1: Carrito de Prueba</h2>";
    
    // Buscar el carrito específico por cart_token
    $cartToken = 'jRLPuKtoDinlesauNIt7BfmFo8wrIUNo';
    $cart = Cart::where('cart_token', $cartToken)->first();
    
    if (!$cart) {
        throw new Exception("❌ Carrito no encontrado con token: {$cartToken}");
    }
    
    echo "<p class='success'>✅ Carrito encontrado exitosamente</p>";
    echo "<div class='info-item'><strong>Cart ID:</strong> " . $cart->id . "</div>";
    echo "<div class='info-item'><strong>Cart Token:</strong> " . $cart->cart_token . "</div>";
    echo "<div class='info-item'><strong>User ID:</strong> " . ($cart->user_id ?? 'NULL') . "</div>";
    echo "<div class='info-item'><strong>Total Amount:</strong> $" . number_format($cart->total_amount, 2) . " MXN</div>";
    echo "<div class='info-item'><strong>Subtotal:</strong> $" . number_format($cart->subtotal, 2) . " MXN</div>";
    echo "<div class='info-item'><strong>Tax Amount:</strong> $" . number_format($cart->tax_amount, 2) . " MXN</div>";
    echo "<div class='info-item'><strong>Status:</strong> " . $cart->status . "</div>";
    echo "<div class='info-item'><strong>Items en carrito:</strong> " . $cart->items()->count() . "</div>";
    
    // Mostrar items del carrito si existen
    $items = $cart->items;
    if ($items->count() > 0) {
        echo "<div style='margin-top: 15px;'>";
        echo "<strong>Productos en el carrito:</strong>";
        echo "<ul style='margin-left: 20px; margin-top: 10px;'>";
        foreach ($items as $item) {
            echo "<li>Producto ID: {$item->product_id} - Cantidad: {$item->quantity} - Precio: $" . number_format($item->unit_price, 2) . "</li>";
        }
        echo "</ul>";
        echo "</div>";
    }
    
    echo "</div>";
    
    // ===================================================================
    // PASO 2: PREPARAR DATOS DE PAGO
    // ===================================================================
    echo "<div class='section'>";
    echo "<h2>💳 PASO 2: Datos de Pago</h2>";
    
    $transactionReference = 'TEST_' . time() . '_' . strtoupper(substr(uniqid(), -6));
    
    $transactionData = [
        'reference' => $transactionReference,
        'amount' => number_format($cart->total_amount, 2, '.', ''),
        'currency' => 'MXN',
    ];

    $cardData = [
        'name' => 'PAULINO MOTA HERNANDEZ',
        'card_number' => '379911307544370',
        'exp_month' => '12',
        'exp_year' => '28',
        'cvv' => '6724'
    ];

    $billingData = [
        'phone' => '5555555555',  // Exacto como generate_mitec_data.php
        'email' => 'test@example.com',  // Exacto como generate_mitec_data.php
        'ip' => '187.184.8.88'  // Exacto como generate_mitec_data.php
    ];
    
    echo "<div class='info-item'><strong>Reference:</strong> " . htmlspecialchars($transactionReference) . "</div>";
    echo "<div class='info-item'><strong>Amount:</strong> $" . htmlspecialchars($transactionData['amount']) . " MXN</div>";
    echo "<div class='info-item'><strong>Card Name:</strong> " . htmlspecialchars($cardData['name']) . "</div>";
    echo "<div class='info-item'><strong>Card Number:</strong> " . htmlspecialchars($cardData['card_number']) . " (AMEX)</div>";
    echo "<div class='info-item'><strong>Email:</strong> " . htmlspecialchars($billingData['email']) . "</div>";
    echo "</div>";
    
    // ===================================================================
    // PASO 3: GENERAR XML CON EL SERVICIO
    // ===================================================================
    echo "<div class='section'>";
    echo "<h2>🔨 PASO 3: Generar XML</h2>";
    
    $xmlBuilder = new MitecXmlBuilderService();
    $xml = $xmlBuilder->buildTransactionXml($transactionData, $cardData, $billingData);
    
    // Extraer merchant del XML para verificar
    preg_match('/<tx_merchant>(.*?)<\/tx_merchant>/', $xml, $merchantMatch);
    $usedMerchant = $merchantMatch[1] ?? 'NO_ENCONTRADO';
    
    echo "<p class='success'>✅ XML generado correctamente</p>";
    echo "<div class='info-item'><strong>Merchant usado:</strong> " . htmlspecialchars($usedMerchant) . "</div>";
    echo "<div class='info-item'><strong>Longitud XML:</strong> " . strlen($xml) . " bytes</div>";
    echo "<div class='info-item'><strong>Líneas:</strong> " . substr_count($xml, "\n") . "</div>";
    
    // Verificar cc_name en el XML
    preg_match('/<cc_name>(.*?)<\/cc_name>/', $xml, $nameMatch);
    $xmlCcName = $nameMatch[1] ?? 'NO_ENCONTRADO';
    echo "<div class='info-item'><strong>cc_name en XML:</strong> " . htmlspecialchars($xmlCcName) . " (" . strlen($xmlCcName) . " bytes)</div>";
    
    echo "<div class='code-block'>" . htmlspecialchars($xml) . "</div>";
    echo "</div>";
    
    // ===================================================================
    // PASO 4: ENCRIPTAR XML
    // ===================================================================
    echo "<div class='section'>";
    echo "<h2>🔐 PASO 4: Encriptar XML</h2>";
    
    $encryptionService = new MitecEncryptionService();
    $encryptedData = $encryptionService->encrypt($xml, env('MITEC_KEY_HEX'));
    
    echo "<p class='success'>✅ XML encriptado correctamente</p>";
    echo "<div class='info-item'><strong>Longitud Encriptada:</strong> " . strlen($encryptedData) . " bytes</div>";
    echo "<div class='info-item'><strong>Preview:</strong> " . htmlspecialchars(substr($encryptedData, 0, 100)) . "...</div>";
    echo "</div>";
    
    // ===================================================================
    // PASO 5: GENERAR FORMULARIO COMPLETO
    // ===================================================================
    echo "<div class='section'>";
    echo "<h2>📄 PASO 5: Generar Formulario MITEC</h2>";
    
    $formXml = $xmlBuilder->buildFormXml($encryptedData);
    $mitecUrl = env('MITEC_3DS_URL');
    
    // IMPORTANTE: Usar htmlspecialchars() como generate_mitec_data.php que SÍ FUNCIONA
    $xmlForForm = htmlspecialchars($formXml, ENT_QUOTES, 'UTF-8');
    
    $formHtml = '<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Procesando Pago - MITEC 3DS v2</title>
</head>
<body>
    <form id="mitecForm" name="cliente" action="' . $mitecUrl . '" method="post" style="display:none;">
        <input type="hidden" name="xml" value="' . $xmlForForm . '">
    </form>
    <script>
        document.getElementById("mitecForm").submit();
    </script>
</body>
</html>';
    
    echo "<p class='success'>✅ Formulario HTML generado</p>";
    echo "<div class='info-item'><strong>MITEC URL:</strong> " . htmlspecialchars($mitecUrl) . "</div>";
    echo "<div class='info-item'><strong>Longitud FormXML:</strong> " . strlen($formXml) . " bytes</div>";
    echo "</div>";
    
    // ===================================================================
    // PASO 6: CREAR PAYMENT SESSION (¡IMPORTANTE!)
    // ===================================================================
    echo "<div class='section'>";
    echo "<h2>💾 PASO 6: Crear PaymentSession</h2>";
    
    $paymentSession = PaymentSession::create([
        'transaction_reference' => $transactionReference,
        'form_html' => $formHtml,
        'mitec_url' => $mitecUrl,
        'user_id' => $cart->user_id,
        'cart_id' => $cart->id,
        'billing_information_id' => null,
        'microsoft_account_id' => null,
        'payment_method' => 'credit_card',
        'expires_at' => now()->addMinutes(10)
    ]);
    
    echo "<p class='success'>✅ PaymentSession creado exitosamente</p>";
    echo "<div class='info-item'><strong>PaymentSession ID:</strong> " . $paymentSession->id . "</div>";
    echo "<div class='info-item'><strong>Transaction Reference:</strong> " . htmlspecialchars($paymentSession->transaction_reference) . "</div>";
    echo "<div class='info-item'><strong>Cart ID (guardado):</strong> " . $paymentSession->cart_id . "</div>";
    echo "<div class='info-item'><strong>User ID (guardado):</strong> " . $paymentSession->user_id . "</div>";
    echo "<div class='info-item'><strong>Expira en:</strong> 10 minutos</div>";
    echo "</div>";
    
    // ===================================================================
    // PASO 7: VERIFICAR TODO ESTÁ LISTO
    // ===================================================================
    echo "<div class='section'>";
    echo "<h2>✅ PASO 7: Verificación Final</h2>";
    
    echo "<div class='info-item'><strong>⚠️ MERCHANT USADO:</strong> {$usedMerchant}</div>";
    echo "<div class='info-item'><strong>⚠️ AMBIENTE:</strong> PRODUCCIÓN (vip.e-pago.com.mx)</div>";
    echo "<div class='info-item'><strong>⚠️ cc_name que enviamos:</strong> '{$xmlCcName}' ({strlen($xmlCcName)} bytes)</div>";
    
    $checks = [
        'Cart creado' => $cart->id ? '✅' : '❌',
        'XML generado' => !empty($xml) ? '✅' : '❌',
        'XML encriptado' => !empty($encryptedData) ? '✅' : '❌',
        'Formulario HTML' => !empty($formHtml) ? '✅' : '❌',
        'PaymentSession creado' => $paymentSession->id ? '✅' : '❌',
        'Cart_ID asociado' => $paymentSession->cart_id == $cart->id ? '✅' : '❌',
        'cc_name presente en XML' => !empty($xmlCcName) && $xmlCcName !== 'NO_ENCONTRADO' ? '✅' : '❌',
    ];
    
    foreach ($checks as $check => $status) {
        echo "<div class='info-item'><strong>{$check}:</strong> {$status}</div>";
    }
    
    // Log para debugging
    Log::info('🧪 TEST MITEC COMPLETO', [
        'cart_id' => $cart->id,
        'payment_session_id' => $paymentSession->id,
        'transaction_reference' => $transactionReference,
        'amount' => $transactionData['amount'],
        'merchant' => $usedMerchant,
        'cc_name' => $xmlCcName
    ]);
    
    echo "</div>";
    
    // ===================================================================
    // PASO 8: BOTÓN PARA ENVIAR A MITEC
    // ===================================================================
    echo "<div class='form-section'>";
    echo "<h2>🚀 PASO 8: Enviar a MITEC</h2>";
    echo "<p style='color: white; margin-bottom: 20px;'>Ahora que el PaymentSession está creado, response.php podrá encontrar el cart_id cuando MITEC responda</p>";
    // Usar htmlspecialchars() como generate_mitec_data.php
    $xmlForButton = htmlspecialchars($formXml, ENT_QUOTES, 'UTF-8');
    
    echo "<form method='post' action='{$mitecUrl}' target='_blank'>";
    echo "<input type='hidden' name='xml' value='{$xmlForButton}'>";
    echo "<button type='submit' class='btn-submit'>💳 PAGAR CON MITEC</button>";
    echo "</form>";
    echo "<p style='color: white; margin-top: 20px; font-size: 0.9em;'>Se abrirá en una nueva ventana</p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='section'>";
    echo "<p class='error'>❌ ERROR: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<div class='code-block'>" . htmlspecialchars($e->getTraceAsString()) . "</div>";
    echo "</div>";
    
    Log::error('Error en test MITEC completo', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}

echo "    </div>
</body>
</html>";
