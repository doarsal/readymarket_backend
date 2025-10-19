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

// Datos de transacción REALES
$tx_reference = 'AMEX' . time(); // Referencia única de transacción
$tx_amount = '1.00'; // 1 peso MXN
$tx_currency = 'MXN';
$cc_name = 'PAULINO MOTA HERNANDEZ';
$cc_number = '379911307544370';
$cc_expMonth = '12';
$cc_expYear = '28';
$cc_cvv = '6724';
$merchant = $MITEC_MERCHANT_AMEX; // Merchant AMEX desde .env

echo "<h2>Generador de Cadena Encriptada MITEC</h2>";
echo "<h3>Configuración cargada desde .env:</h3>";
echo "<div style='background-color: #e8f5e8; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
echo "<strong>✅ Datos MITEC cargados correctamente:</strong><br>";
echo "• ID Company: $MITEC_ID_COMPANY<br>";
echo "• ID Branch: $MITEC_ID_BRANCH<br>";
echo "• Country: $MITEC_COUNTRY<br>";
echo "• User: $MITEC_BS_USER<br>";
echo "• DATA0: $MITEC_DATA0<br>";
echo "• Merchant AMEX: $merchant<br>";
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
<bl_billingPhone>5555555555</bl_billingPhone>
<bl_billingEmail>test@example.com</bl_billingEmail>
</billing>
<tx_urlResponse>https://api.myreadymarket.com/response.php?token={$tx_reference}</tx_urlResponse>
<tx_cobro>1</tx_cobro>
<tx_browserIP>187.184.8.88</tx_browserIP>
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
