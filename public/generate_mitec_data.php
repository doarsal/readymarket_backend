<?php
/**
 * Generador de cadena encriptada para MITEC
 */

// Incluir la clase AESCrypto del proyecto Laravel
require_once __DIR__ . '/../app/Services/Payment/AESCrypto.php';

// Función para cargar variables del .env
function loadEnv($filePath) {
    if (!file_exists($filePath)) {
        die("Archivo .env no encontrado en: $filePath");
    }
    
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $env = [];
    
    foreach ($lines as $line) {
        // Ignorar comentarios
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Buscar líneas con formato KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $env[trim($key)] = trim($value);
        }
    }
    
    return $env;
}

// Cargar variables del .env del proyecto Laravel
$env = loadEnv(__DIR__ . '/../.env');

// Datos de configuración MITEC (desde .env)
$MITEC_KEY_HEX = $env['MITEC_KEY_HEX'] ?? '';
$MITEC_ID_COMPANY = $env['MITEC_ID_COMPANY'] ?? '';
$MITEC_ID_BRANCH = $env['MITEC_ID_BRANCH'] ?? '';
$MITEC_COUNTRY = $env['MITEC_COUNTRY'] ?? '';
$MITEC_BS_USER = $env['MITEC_BS_USER'] ?? '';
$MITEC_BS_PWD = $env['MITEC_BS_PWD'] ?? '';
$MITEC_DATA0 = $env['MITEC_DATA0'] ?? '';
$MITEC_MERCHANT_AMEX = $env['MITEC_MERCHANT_AMEX'] ?? '';

// Verificar que se cargaron los datos
if (empty($MITEC_KEY_HEX) || empty($MITEC_ID_COMPANY)) {
    die("Error: No se pudieron cargar los datos de MITEC del archivo .env");
}

// Datos de transacción REALES - Usar valores del formulario o valores por defecto
$tx_reference = $_POST['tx_reference'] ?? 'AMEX' . time();
$tx_amount = $_POST['tx_amount'] ?? '2.00';
$tx_currency = $_POST['tx_currency'] ?? 'MXN';
$cc_name = $_POST['cc_name'] ?? 'PAULINO MOTA HERNANDEZ';
$cc_number = $_POST['cc_number'] ?? '379911307544370';
$cc_expMonth = $_POST['cc_expMonth'] ?? '12';
$cc_expYear = $_POST['cc_expYear'] ?? '28';
$cc_cvv = $_POST['cc_cvv'] ?? '6724';
$bl_billingPhone = $_POST['bl_billingPhone'] ?? '5555555555';
$bl_billingEmail = $_POST['bl_billingEmail'] ?? 'test@example.com';
$tx_browserIP = $_POST['tx_browserIP'] ?? '187.184.8.88';
$merchant = $MITEC_MERCHANT_AMEX; // Merchant AMEX desde .env

$isFormSubmitted = !empty($_POST);

echo "<!DOCTYPE html>";
echo "<html lang='es'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
echo "<title>Generador de Cadena Encriptada MITEC</title>";
echo "<style>";
echo "body { font-family: Arial, sans-serif; max-width: 1200px; margin: 20px auto; padding: 0 20px; }";
echo ".form-container { background: #f8f9fa; padding: 25px; border-radius: 8px; margin: 20px 0; }";
echo ".form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px; }";
echo ".form-group { display: flex; flex-direction: column; }";
echo ".form-group label { font-weight: bold; margin-bottom: 5px; color: #333; }";
echo ".form-group input { padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; }";
echo ".btn-generate { background: #0d6efd; color: white; padding: 15px 40px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; font-weight: bold; width: 100%; }";
echo ".btn-generate:hover { background: #0b5ed7; }";
echo ".config-box { background: #e8f5e8; padding: 15px; margin: 20px 0; border-radius: 5px; border: 1px solid #c3e6cb; }";
echo ".full-width { grid-column: 1 / -1; }";
echo "h2 { color: #333; border-bottom: 2px solid #0d6efd; padding-bottom: 10px; }";
echo "h3 { color: #555; margin-top: 25px; }";
echo "textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace; font-size: 12px; }";
echo "</style>";
echo "</head>";
echo "<body>";

echo "<h2>🔐 Generador de Cadena Encriptada MITEC</h2>";

// Mostrar configuración cargada
echo "<div class='config-box'>";
echo "<strong>✅ Configuración MITEC cargada desde .env:</strong><br>";
echo "• ID Company: $MITEC_ID_COMPANY<br>";
echo "• ID Branch: $MITEC_ID_BRANCH<br>";
echo "• Country: $MITEC_COUNTRY<br>";
echo "• User: $MITEC_BS_USER<br>";
echo "• DATA0: $MITEC_DATA0<br>";
echo "• Merchant AMEX: $MITEC_MERCHANT_AMEX<br>";
echo "</div>";

// Formulario para ingresar datos de transacción
if (!$isFormSubmitted) {
    echo "<h3>📝 Ingresa los datos de la transacción:</h3>";
    echo "<form method='POST' class='form-container'>";
    
    echo "<div class='form-row'>";
    echo "<div class='form-group'>";
    echo "<label for='tx_reference'>Referencia de Transacción:</label>";
    echo "<input type='text' id='tx_reference' name='tx_reference' value='AMEX" . time() . "' required>";
    echo "</div>";
    echo "<div class='form-group'>";
    echo "<label for='tx_amount'>Monto:</label>";
    echo "<input type='text' id='tx_amount' name='tx_amount' value='2.00' placeholder='2.00' required pattern='[0-9]+\.?[0-9]*'>";
    echo "</div>";
    echo "</div>";
    
    echo "<div class='form-row'>";
    echo "<div class='form-group'>";
    echo "<label for='tx_currency'>Moneda:</label>";
    echo "<input type='text' id='tx_currency' name='tx_currency' value='MXN' required>";
    echo "</div>";
    echo "<div class='form-group'>";
    echo "<label for='cc_name'>Nombre en la Tarjeta:</label>";
    echo "<input type='text' id='cc_name' name='cc_name' value='' required>";
    echo "</div>";
    echo "</div>";
    
    echo "<div class='form-row'>";
    echo "<div class='form-group'>";
    echo "<label for='cc_number'>Número de Tarjeta:</label>";
    echo "<input type='text' id='cc_number' name='cc_number' value='' required pattern='[0-9]{13,19}'>";
    echo "</div>";
    echo "<div class='form-group'>";
    echo "<label for='cc_cvv'>CVV:</label>";
    echo "<input type='text' id='cc_cvv' name='cc_cvv' value='' required pattern='[0-9]{3,4}'>";
    echo "</div>";
    echo "</div>";
    
    echo "<div class='form-row'>";
    echo "<div class='form-group'>";
    echo "<label for='cc_expMonth'>Mes de Expiración (MM):</label>";
    echo "<input type='text' id='cc_expMonth' name='cc_expMonth' value='' required pattern='(0[1-9]|1[0-2])'>";
    echo "</div>";
    echo "<div class='form-group'>";
    echo "<label for='cc_expYear'>Año de Expiración (YY):</label>";
    echo "<input type='text' id='cc_expYear' name='cc_expYear' value='' required pattern='[0-9]{2}'>";
    echo "</div>";
    echo "</div>";
    
    echo "<div class='form-row'>";
    echo "<div class='form-group'>";
    echo "<label for='bl_billingPhone'>Teléfono de Facturación:</label>";
    echo "<input type='text' id='bl_billingPhone' name='bl_billingPhone' value='5555555555' required>";
    echo "</div>";
    echo "<div class='form-group'>";
    echo "<label for='bl_billingEmail'>Email de Facturación:</label>";
    echo "<input type='email' id='bl_billingEmail' name='bl_billingEmail' value='test@example.com' required>";
    echo "</div>";
    echo "</div>";
    
    echo "<div class='form-row'>";
    echo "<div class='form-group full-width'>";
    echo "<label for='tx_browserIP'>IP del Navegador:</label>";
    echo "<input type='text' id='tx_browserIP' name='tx_browserIP' value='187.184.8.88' required>";
    echo "</div>";
    echo "</div>";
    
    echo "<button type='submit' class='btn-generate'>🔐 Generar Cadena Encriptada</button>";
    echo "</form>";
    
    echo "</body></html>";
    exit; // Detener la ejecución hasta que se envíe el formulario
}

// Si el formulario fue enviado, mostrar resultados
echo "<div class='config-box' style='background: #d1ecf1; border-color: #bee5eb;'>";
echo "<strong>📋 Datos ingresados:</strong><br>";
echo "• Referencia: $tx_reference<br>";
echo "• Monto: $$tx_amount $tx_currency<br>";
echo "• Tarjeta: $cc_name - " . substr($cc_number, 0, 4) . "****" . substr($cc_number, -4) . "<br>";
echo "• Expiración: $cc_expMonth/$cc_expYear<br>";
echo "• Email: $bl_billingEmail<br>";
echo "• Teléfono: $bl_billingPhone<br>";
echo "• IP: $tx_browserIP<br>";
echo "</div>";

// Construir el XML EXACTO como te lo compartieron
$fullXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<TRANSACTION3DS>
<business>
<bs_idCompany>{$MITEC_ID_COMPANY}</bs_idCompany>
<bs_idBranch>{$MITEC_ID_BRANCH}</bs_idBranch>
<bs_country>{$MITEC_COUNTRY}</bs_country>
<bs_user>{$MITEC_BS_USER}</bs_user>
<bs_pwd>{$MITEC_BS_PWD}</bs_pwd>
</business>
<transaction>
<tx_merchant>{$merchant}</tx_merchant>
<tx_reference>{$tx_reference}</tx_reference>
<tx_amount>{$tx_amount}</tx_amount>
<tx_currency>{$tx_currency}</tx_currency>
<creditcard>
<cc_name>{$cc_name}</cc_name>
<cc_number>{$cc_number}</cc_number>
<cc_expMonth>{$cc_expMonth}</cc_expMonth>
<cc_expYear>{$cc_expYear}</cc_expYear>
<cc_cvv>{$cc_cvv}</cc_cvv>
</creditcard>
<billing>
<bl_billingPhone>{$bl_billingPhone}</bl_billingPhone>
<bl_billingEmail>{$bl_billingEmail}</bl_billingEmail>
</billing>
<tx_urlResponse>https://api.myreadymarket.com/response.php?token={$tx_reference}</tx_urlResponse>
<tx_cobro>1</tx_cobro>
<tx_browserIP>{$tx_browserIP}</tx_browserIP>
</transaction>
</TRANSACTION3DS>
XML;

echo "<h3>XML completo que se encriptará (FORMATO EXACTO):</h3>";
echo "<textarea style='width:100%; height:200px;'>$fullXml</textarea>";

$actionUrl = $env['MITEC_3DS_URL'] ?? "https://vip.e-pago.com.mx/ws3dsecure/Auth3dsecure";

// Generar cadena encriptada usando AESCrypto
$encryptedData = AESCrypto::encriptar($fullXml, $MITEC_KEY_HEX);

echo "<h3>Cadena encriptada (para el campo &lt;data&gt;):</h3>";
echo "<textarea style='width:100%; height:100px;'>$encryptedData</textarea>";

echo "<h3>XML completo para el formulario:</h3>";
$xmlData = "<pgs><data0>$MITEC_DATA0</data0><data>$encryptedData</data></pgs>";
echo "<textarea style='width:100%; height:100px;'>$xmlData</textarea>";

echo "<div style='margin-top: 20px; padding: 15px; background-color: #d4edda; border: 1px solid #c3e6cb;'>";
echo "<strong>✅ FORMATO EXACTO COMO LO SOLICITA MITEC:</strong><br>";
echo "- XML sin espaciado extra (como el ejemplo que nos proporcionaron)<br>";
echo "- Incluye tx_browserIP que faltaba<br>";
echo "- Estructura exacta: sin indentación adicional<br>";
echo "- Formato idéntico al que funciona<br>";
echo "</div>";

echo "<hr><h2 style='margin-top: 30px;'>🚀 Formulario de Pago - Clic para probar:</h2>";
?>

<!-- Botón para volver al formulario -->
<form method="GET" style="margin: 20px 0;">
    <button type="submit" style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer;">
        ← Volver a ingresar datos
    </button>
</form>

<!-- Formulario HTML renderizado directamente -->
<form name="cliente" action="<?php echo $actionUrl; ?>" method="post" style="margin: 20px 0;">
    <input type="hidden" name="xml" value="<?php echo htmlspecialchars($xmlData); ?>">
    
    <div style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
        <h4>Datos de la transacción:</h4>
        <ul>
            <li><strong>Referencia:</strong> <?php echo $tx_reference; ?></li>
            <li><strong>Monto:</strong> $<?php echo $tx_amount; ?> <?php echo $tx_currency; ?></li>
            <li><strong>Tarjeta:</strong> <?php echo $cc_name; ?> - <?php echo substr($cc_number, 0, 4); ?>****<?php echo substr($cc_number, -4); ?></li>
            <li><strong>Merchant:</strong> <?php echo $merchant; ?></li>
            <li><strong>URL:</strong> <?php echo $actionUrl; ?></li>
        </ul>
    </div>
    
    <button type="submit" class="btn btn-primary btn-lg" style="padding: 15px 40px; font-size: 18px; background-color: #0d6efd; border: none; border-radius: 5px; cursor: pointer;">
        💳 Solicitar Pago con 3DS v2
    </button>
</form>

<style>
    .btn-primary:hover {
        background-color: #0b5ed7 !important;
    }
</style>
